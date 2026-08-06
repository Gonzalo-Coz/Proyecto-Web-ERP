<?php

declare(strict_types=1);

namespace App\Module\Motorcycle\Service;

use App\Module\Motorcycle\Entity\MotorcycleModel;
use App\Module\Motorcycle\Entity\MotorcycleUnit;
use App\Module\Motorcycle\Repository\MotorcycleModelRepository;
use App\Module\Motorcycle\Repository\MotorcycleUnitRepository;
use App\Module\Supplier\Repository\SupplierRepository;
use App\Shared\Import\CsvImportReader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Carga masiva de unidades de moto desde CSV/Excel (mismo patrón que Repuestos).
 *
 * Cada fila es UNA moto física. Upsert por VIN: si el VIN existe → actualiza; si no → crea.
 * El modelo se referencia por su nombre completo (igual que aparece en Modelos).
 * Las motos VENDIDAS no se modifican (expediente histórico).
 */
final class UnitImportService
{
    private const COLUMNS = [
        'internalCode' => 'Codigo Interno',
        'vin' => 'VIN',
        'model' => 'Modelo',
        'color' => 'Color',
        'engineNumber' => 'Nro de Motor',
        'chassisNumber' => 'Nro de Chasis',
        'series' => 'Serie',
        'manufactureYear' => 'Ano de Fabricacion',
        'entryDate' => 'Fecha de Ingreso (AAAA-MM-DD)',
        'supplierRuc' => 'Proveedor (RUC)',
        'purchasePrice' => 'Precio de Compra',
        'salePrice' => 'Precio de Venta',
        'location' => 'Ubicacion',
        'duaNumber' => 'DUA',
        'duaItem' => 'Item DUA',
    ];

    private const HEADER_ALIASES = [
        'codigointerno' => 'internalCode',
        'codigo' => 'internalCode',
        'vin' => 'vin',
        'modelo' => 'model',
        'color' => 'color',
        'nrodemotor' => 'engineNumber',
        'nromotor' => 'engineNumber',
        'numerodemotor' => 'engineNumber',
        'motor' => 'engineNumber',
        'nrodechasis' => 'chassisNumber',
        'nrochasis' => 'chassisNumber',
        'numerodechasis' => 'chassisNumber',
        'chasis' => 'chassisNumber',
        'serie' => 'series',
        'anodefabricacion' => 'manufactureYear',
        'anofabricacion' => 'manufactureYear',
        'ano' => 'manufactureYear',
        'fechadeingresoaaaammdd' => 'entryDate',
        'fechadeingreso' => 'entryDate',
        'fechaingreso' => 'entryDate',
        'proveedorruc' => 'supplierRuc',
        'proveedor' => 'supplierRuc',
        'ruc' => 'supplierRuc',
        'preciodecompra' => 'purchasePrice',
        'preciocompra' => 'purchasePrice',
        'preciodeventa' => 'salePrice',
        'precioventa' => 'salePrice',
        'ubicacion' => 'location',
        'dua' => 'duaNumber',
        'nrodua' => 'duaNumber',
        'numerodedua' => 'duaNumber',
        'itemdua' => 'duaItem',
        'itemdedua' => 'duaItem',
    ];

    private const VIN_PATTERN = '/^[A-HJ-NPR-Za-hj-npr-z0-9]{17}$/';

    /** @var array<string, MotorcycleModel> nombre normalizado → modelo */
    private array $modelMap = [];

    /** @var array<string, true> VIN ya visto en este archivo */
    private array $seenVins = [];

    /** @var array<string, string> nº de motor (mayúsculas) → VIN que lo usa en este archivo */
    private array $seenEngines = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MotorcycleUnitRepository $unitRepository,
        private readonly MotorcycleModelRepository $modelRepository,
        private readonly SupplierRepository $supplierRepository,
        private readonly CsvImportReader $csv,
    ) {
    }

    public function template(): string
    {
        return $this->csv->buildTemplate(
            array_values(self::COLUMNS),
            ['MOT-001', '9C6KE1500N0000001', 'Yamaha YBR125 2024', 'Negro', 'E1500N01', 'CH1500N01', '', '2024', '2026-08-01', '20123456789', '9500.00', '11900.00', 'Tienda', '118-2026-10-177946-01-4-00', '34'],
        );
    }

    /**
     * @return array{summary: array{total:int,create:int,update:int,error:int}, rows: list<array<string,mixed>>, committed: bool}
     */
    public function process(UploadedFile $file, bool $dryRun): array
    {
        $rows = $this->csv->readRows($file);
        if ($rows === []) {
            throw new UnprocessableEntityHttpException('El archivo está vacío o no tiene datos.');
        }

        $colIndex = $this->csv->mapColumns(array_shift($rows), self::HEADER_ALIASES);
        if (!isset($colIndex['vin'], $colIndex['internalCode'], $colIndex['model'], $colIndex['color'])) {
            throw new UnprocessableEntityHttpException(
                'La plantilla no tiene las columnas obligatorias (Código Interno, VIN, Modelo, Color). Descargue la plantilla y respete las cabeceras.',
            );
        }

        $this->loadModels();
        $this->seenVins = [];
        $this->seenEngines = [];

        $results = [];
        $counts = ['total' => 0, 'create' => 0, 'update' => 0, 'error' => 0];
        $line = 1;

        foreach ($rows as $cells) {
            ++$line;
            $get = static fn (string $key): string => isset($colIndex[$key]) ? trim((string) ($cells[$colIndex[$key]] ?? '')) : '';

            $vin = strtoupper($get('vin'));
            $internalCode = $get('internalCode');
            if ($vin === '' && $internalCode === '' && $get('model') === '') {
                continue; // fila vacía
            }

            ++$counts['total'];
            $row = $this->evaluateRow($get, $vin, $internalCode, $line, $dryRun);
            ++$counts[$row['status']];
            $results[] = $row;
        }

        return ['summary' => $counts, 'rows' => $results, 'committed' => !$dryRun];
    }

    /**
     * @param callable(string):string $get
     *
     * @return array<string,mixed>
     */
    private function evaluateRow(callable $get, string $vin, string $internalCode, int $line, bool $dryRun): array
    {
        $base = ['line' => $line, 'code' => $vin, 'label' => trim($internalCode.' · '.$get('model'))];

        $errors = [];
        if ($internalCode === '') {
            $errors[] = 'falta el código interno';
        }
        if ($vin === '') {
            $errors[] = 'falta el VIN';
        } elseif (preg_match(self::VIN_PATTERN, $vin) !== 1) {
            $errors[] = 'el VIN debe tener 17 caracteres alfanuméricos (sin I, O, Q)';
        }

        $modelName = $get('model');
        $model = $this->modelMap[$this->csv->normalize($modelName)] ?? null;
        if ($modelName === '') {
            $errors[] = 'falta el modelo';
        } elseif ($model === null) {
            $errors[] = sprintf('modelo "%s" no encontrado (escríbelo igual que en Modelos, p. ej. "Yamaha YBR125 2024")', $modelName);
        }

        $color = $get('color');
        if ($color === '') {
            $errors[] = 'falta el color';
        }

        $engineNumber = $get('engineNumber') ?: null;
        $engineUpper = $engineNumber !== null ? strtoupper($engineNumber) : null;
        $existing = $vin !== '' ? $this->unitRepository->findOneByVin($vin) : null;

        // Duplicado dentro del MISMO archivo (para que la vista previa refleje el resultado real).
        $fileVinDup = $vin !== '' && isset($this->seenVins[$vin]);
        if ($engineUpper !== null && isset($this->seenEngines[$engineUpper]) && $this->seenEngines[$engineUpper] !== $vin) {
            $errors[] = sprintf('el número de motor %s está repetido en el archivo (en otra unidad)', $engineUpper);
        }

        // Nº de motor ya registrado en la base (en otra unidad).
        if ($engineNumber !== null) {
            $byEngine = $this->unitRepository->findOneByEngineNumber($engineNumber);
            if ($byEngine !== null && ($existing === null || $byEngine->getId() !== $existing->getId())) {
                $errors[] = sprintf('el número de motor %s ya está registrado en otra unidad', $engineUpper);
            }
        }

        $entryDate = $get('entryDate');
        $parsedEntry = $entryDate !== '' ? $this->parseDate($entryDate) : null;
        if ($entryDate !== '' && $parsedEntry === null) {
            $errors[] = sprintf('fecha de ingreso inválida (%s); use AAAA-MM-DD', $entryDate);
        }

        if ($existing !== null && $existing->isSold()) {
            $errors[] = 'la moto está VENDIDA y no puede modificarse';
        }

        if ($errors !== []) {
            return $base + ['status' => 'error', 'message' => ucfirst(implode('; ', $errors)).'.'];
        }

        // Fila válida: registrar VIN/motor vistos para detectar duplicados en filas siguientes
        // (se hace en preview y en commit por igual, así ambas pasadas dan el mismo resultado).
        $this->seenVins[$vin] = true;
        if ($engineUpper !== null) {
            $this->seenEngines[$engineUpper] = $vin;
        }

        $status = ($existing !== null || $fileVinDup) ? 'update' : 'create';

        if ($dryRun) {
            return $base + ['status' => $status, 'message' => ($status === 'create' ? 'Se creará' : 'Se actualizará').'.'];
        }

        try {
            /** @var MotorcycleModel $model (validado arriba) */
            $unit = $existing ?? new MotorcycleUnit($internalCode, $vin, $model, $color);
            $unit->setInternalCode($internalCode);
            $unit->setModel($model);
            $unit->setColor($color);
            $unit->setEngineNumber($engineNumber);
            $unit->setChassisNumber($get('chassisNumber') ?: null);
            $unit->setSeries($get('series') ?: null);
            $unit->setManufactureYear($this->csv->parseInt($get('manufactureYear')));
            if ($parsedEntry !== null) {
                $unit->setEntryDate($parsedEntry);
            }
            $ruc = $get('supplierRuc');
            $unit->setSupplier($ruc !== '' ? $this->supplierRepository->findOneByRuc($ruc) : null);
            $purchase = $this->csv->parseDecimal($get('purchasePrice'));
            $sale = $this->csv->parseDecimal($get('salePrice'));
            $unit->setPurchasePrice($purchase !== null ? number_format($purchase, 2, '.', '') : null);
            $unit->setSalePrice($sale !== null ? number_format($sale, 2, '.', '') : null);
            $unit->setLocation($get('location') ?: null);
            $unit->setDuaNumber($get('duaNumber') ?: null);
            $unit->setDuaItem($get('duaItem') ?: null);

            if ($existing === null) {
                $this->entityManager->persist($unit);
            }
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            return $base + ['status' => 'error', 'message' => 'No se pudo guardar: '.$e->getMessage()];
        }

        return $base + ['status' => $status, 'message' => $status === 'create' ? 'Creada.' : 'Actualizada.'];
    }

    private function loadModels(): void
    {
        $this->modelMap = [];
        foreach ($this->modelRepository->findAll() as $model) {
            if ($model->isActive()) {
                $this->modelMap[$this->csv->normalize($model->getFullName())] = $model;
            }
        }
    }

    private function parseDate(string $v): ?\DateTimeImmutable
    {
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $fmt) {
            $d = \DateTimeImmutable::createFromFormat('!'.$fmt, $v);
            if ($d !== false) {
                return $d;
            }
        }

        return null;
    }
}
