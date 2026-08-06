<?php

declare(strict_types=1);

namespace App\Module\Inventory\Service;

use App\Module\Catalog\Service\CatalogService;
use App\Module\Inventory\Entity\SparePart;
use App\Module\Inventory\Repository\SparePartRepository;
use App\Shared\Pricing\Service\PriceHistoryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Carga masiva de repuestos/productos desde una plantilla CSV (compatible con Excel).
 *
 * - Upsert por código: si el código interno o el código de repuesto ya existe, ACTUALIZA;
 *   si no existe, CREA.
 * - Stock inicial sólo para productos NUEVOS (genera un movimiento AJUSTE en el Kardex).
 *   En productos existentes el stock NO se toca (se gestiona por compras/ajustes).
 * - Marcas y categorías se resuelven por nombre y se crean al vuelo si no existen.
 * - Con dryRun=true entrega una vista previa (nada se guarda).
 */
final class SparePartImportService
{
    /** Cabeceras de la plantilla, en orden. La clave es el identificador interno de la columna. */
    private const COLUMNS = [
        'internalCode' => 'Codigo Interno',
        'partCode' => 'Codigo de Repuesto',
        'description' => 'Descripcion',
        'brand' => 'Marca',
        'category' => 'Categoria',
        'unit' => 'Unidad de Medida',
        'minStock' => 'Stock Minimo',
        'purchasePrice' => 'Precio de Compra',
        'salePrice' => 'Precio de Venta (IGV incl.)',
        'location' => 'Ubicacion',
        'initialStock' => 'Stock Inicial (solo nuevos)',
        'active' => 'Activo (SI/NO)',
    ];

    /** Aliases de cabecera normalizada → clave de columna. Tolera acentos, espacios y variantes. */
    private const HEADER_ALIASES = [
        'codigointerno' => 'internalCode',
        'codigoderepuesto' => 'partCode',
        'codigorepuesto' => 'partCode',
        'codigodebarras' => 'partCode',
        'descripcion' => 'description',
        'marca' => 'brand',
        'categoria' => 'category',
        'unidaddemedida' => 'unit',
        'unidad' => 'unit',
        'stockminimo' => 'minStock',
        'stockmin' => 'minStock',
        'preciodecompra' => 'purchasePrice',
        'preciocompra' => 'purchasePrice',
        'preciodeventaigvincl' => 'salePrice',
        'preciodeventa' => 'salePrice',
        'precioventa' => 'salePrice',
        'ubicacion' => 'location',
        'stockinicialsolonuevos' => 'initialStock',
        'stockinicial' => 'initialStock',
        'activosino' => 'active',
        'activo' => 'active',
    ];

    /** @var array<string, true> códigos internos ya vistos en este archivo */
    private array $seenInternal = [];

    /** @var array<string, true> códigos de repuesto ya vistos en este archivo */
    private array $seenPart = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SparePartRepository $partRepository,
        private readonly CatalogService $catalogService,
        private readonly StockService $stockService,
        private readonly PriceHistoryService $priceHistory,
    ) {
    }

    /** Genera el contenido de la plantilla CSV (UTF-8 con BOM, delimitador ';' para Excel en español). */
    public function template(): string
    {
        $bom = "\xEF\xBB\xBF";
        $header = implode(';', array_values(self::COLUMNS));
        $example = implode(';', [
            'REP-001', '7501234567890', 'Aceite 4T 1L', 'Yamaha', 'Lubricantes',
            'UNIDAD', '5', '18.00', '25.00', 'Estante A1', '10', 'SI',
        ]);

        // El ejemplo se ignora al importar (código que empieza con "EJEMPLO" o fila de muestra REP-001 se muestra en preview).
        return $bom.$header."\r\n".$example."\r\n";
    }

    /**
     * Procesa el archivo subido.
     *
     * @return array{summary: array{total:int,create:int,update:int,error:int}, rows: list<array<string,mixed>>, committed: bool}
     */
    public function process(UploadedFile $file, bool $dryRun): array
    {
        $rows = $this->readRows($file);
        if (\count($rows) < 1) {
            throw new UnprocessableEntityHttpException('El archivo está vacío o no tiene datos.');
        }

        $headerCells = array_shift($rows);
        $colIndex = $this->mapColumns($headerCells);
        if (!isset($colIndex['internalCode'], $colIndex['partCode'], $colIndex['description'])) {
            throw new UnprocessableEntityHttpException(
                'La plantilla no tiene las columnas obligatorias (Código Interno, Código de Repuesto, Descripción). Descargue la plantilla y respete las cabeceras.',
            );
        }

        $this->seenInternal = [];
        $this->seenPart = [];
        $results = [];
        $counts = ['total' => 0, 'create' => 0, 'update' => 0, 'error' => 0];
        $line = 1; // la cabecera es la línea 1

        foreach ($rows as $cells) {
            ++$line;
            $get = static fn (string $key): string => isset($colIndex[$key]) ? trim((string) ($cells[$colIndex[$key]] ?? '')) : '';

            $internalCode = $get('internalCode');
            $partCode = $get('partCode');
            $description = $get('description');

            // Fila totalmente vacía → se ignora en silencio.
            if ($internalCode === '' && $partCode === '' && $description === '') {
                continue;
            }

            ++$counts['total'];

            $row = $this->evaluateRow($get, $internalCode, $partCode, $description, $line, $dryRun);
            ++$counts[$row['status']];
            $results[] = $row;
        }

        return [
            'summary' => $counts,
            'rows' => $results,
            'committed' => !$dryRun,
        ];
    }

    /**
     * @param callable(string):string $get
     *
     * @return array<string,mixed>
     */
    private function evaluateRow(callable $get, string $internalCode, string $partCode, string $description, int $line, bool $dryRun): array
    {
        $base = ['line' => $line, 'internalCode' => $internalCode, 'partCode' => $partCode, 'description' => $description];

        // Validaciones obligatorias.
        $errors = [];
        if ($internalCode === '') {
            $errors[] = 'falta el código interno';
        }
        if ($partCode === '') {
            $errors[] = 'falta el código de repuesto';
        }
        if ($description === '') {
            $errors[] = 'falta la descripción';
        }
        if ($errors !== []) {
            return $base + ['status' => 'error', 'message' => ucfirst(implode('; ', $errors)).'.'];
        }

        // Determinar destino (upsert) y detectar conflicto de códigos cruzados.
        $byInternal = $this->partRepository->findOneByInternalCode($internalCode);
        $byPart = $this->partRepository->findOneByPartCode($partCode);
        if ($byInternal !== null && $byPart !== null && $byInternal->getId() !== $byPart->getId()) {
            return $base + [
                'status' => 'error',
                'message' => 'El código interno y el código de repuesto pertenecen a productos distintos.',
            ];
        }
        $target = $byInternal ?? $byPart;

        // Duplicado dentro del MISMO archivo → actualización de la fila previa (preview coincide con commit).
        $codeUpper = strtoupper($internalCode);
        $partUpper = strtoupper($partCode);
        $fileDup = isset($this->seenInternal[$codeUpper]) || isset($this->seenPart[$partUpper]);
        $this->seenInternal[$codeUpper] = true;
        $this->seenPart[$partUpper] = true;

        $status = ($target !== null || $fileDup) ? 'update' : 'create';

        // Datos parseados.
        $brand = $get('brand');
        $category = $get('category');
        $unit = strtoupper($get('unit')) ?: 'UNIDAD';
        $minStock = $this->parseInt($get('minStock')) ?? 0;
        $purchasePrice = $this->parseDecimal($get('purchasePrice'));
        $salePrice = $this->parseDecimal($get('salePrice'));
        $location = $get('location') ?: null;
        $initialStock = $this->parseInt($get('initialStock')) ?? 0;
        $active = $this->parseBool($get('active'));

        $message = $status === 'create' ? 'Se creará' : 'Se actualizará';
        if ($status === 'create' && $initialStock > 0) {
            $message .= sprintf(' (stock inicial %d)', $initialStock);
        }
        if ($status === 'update' && $initialStock > 0) {
            $message .= ' — el stock inicial se ignora en productos existentes';
        }

        if ($dryRun) {
            return $base + ['status' => $status, 'message' => $message.'.'];
        }

        // ---- Commit ----
        try {
            $this->persistRow(
                $target,
                $internalCode,
                $partCode,
                $description,
                $brand,
                $category,
                $unit,
                $minStock,
                $purchasePrice,
                $salePrice,
                $location,
                $active,
                $status === 'create' ? $initialStock : 0,
            );
        } catch (\Throwable $e) {
            return $base + ['status' => 'error', 'message' => 'No se pudo guardar: '.$e->getMessage()];
        }

        return $base + ['status' => $status, 'message' => $status === 'create' ? 'Creado.' : 'Actualizado.'];
    }

    private function persistRow(
        ?SparePart $target,
        string $internalCode,
        string $partCode,
        string $description,
        string $brand,
        string $category,
        string $unit,
        int $minStock,
        ?float $purchasePrice,
        ?float $salePrice,
        ?string $location,
        bool $active,
        int $initialStock,
    ): void {
        $isNew = $target === null;
        $part = $target ?? new SparePart($internalCode, $partCode, $description);
        $oldSalePrice = $isNew ? null : $part->getSalePrice();

        $part->setInternalCode($internalCode);
        $part->setPartCode($partCode);
        $part->setDescription($description);
        $part->setBarcode($partCode); // el código de repuesto hace de código de barras
        $part->setUnitOfMeasure($unit);
        $part->setBrand($brand !== '' ? $this->catalogService->findOrCreateByName('brands', $brand) : null);
        $part->setCategory($category !== '' ? $this->catalogService->findOrCreateByName('categories', $category) : null);
        $part->setMinStock($minStock);
        $part->setPurchasePrice($purchasePrice !== null ? number_format($purchasePrice, 2, '.', '') : ($isNew ? null : $part->getPurchasePrice()));
        $part->setSalePrice($salePrice !== null ? number_format($salePrice, 2, '.', '') : ($isNew ? null : $part->getSalePrice()));
        $part->setLocation($location);
        $part->setActive($active);

        if ($isNew) {
            $this->entityManager->persist($part);
        }
        $this->entityManager->flush();

        // Historial de precios (A3).
        if ($salePrice !== null && (string) $part->getSalePrice() !== (string) $oldSalePrice) {
            $this->priceHistory->record(
                PriceHistoryService::SUBJECT_SPARE_PART,
                (int) $part->getId(),
                mb_substr(sprintf('%s · %s', $part->getInternalCode(), $part->getDescription()), 0, 200),
                $oldSalePrice,
                $part->getSalePrice(),
                'Importación masiva',
            );
        }

        // Stock inicial sólo en nuevos.
        if ($isNew && $initialStock > 0) {
            $this->stockService->registerMovement(
                $part,
                'AJUSTE',
                $initialStock,
                $purchasePrice,
                'Carga inicial',
                'Importación masiva de productos',
            );
        }
    }

    /**
     * Lee el archivo como matriz de celdas, autodetectando el delimitador (; , o tab).
     *
     * @return list<list<string>>
     */
    private function readRows(UploadedFile $file): array
    {
        $content = (string) file_get_contents($file->getPathname());
        // Quitar BOM.
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        // Normalizar saltos de línea.
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        $firstLine = strtok($content, "\n") ?: '';
        $delimiter = $this->detectDelimiter($firstLine);

        $rows = [];
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        while (($cells = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            // fgetcsv devuelve [null] en líneas totalmente vacías.
            if ($cells === [null]) {
                continue;
            }
            $rows[] = array_map(static fn ($c) => (string) ($c ?? ''), $cells);
        }
        fclose($handle);

        return $rows;
    }

    private function detectDelimiter(string $line): string
    {
        $candidates = [';' => substr_count($line, ';'), ',' => substr_count($line, ','), "\t" => substr_count($line, "\t")];
        arsort($candidates);
        $best = array_key_first($candidates);

        return ($candidates[$best] ?? 0) > 0 ? (string) $best : ';';
    }

    /**
     * @param list<string> $headerCells
     *
     * @return array<string,int> clave de columna → índice
     */
    private function mapColumns(array $headerCells): array
    {
        $map = [];
        foreach ($headerCells as $i => $label) {
            $key = self::HEADER_ALIASES[$this->normalize($label)] ?? null;
            if ($key !== null && !isset($map[$key])) {
                $map[$key] = $i;
            }
        }

        return $map;
    }

    private function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);

        return (string) preg_replace('/[^a-z0-9]/', '', $s);
    }

    private function parseInt(string $v): ?int
    {
        $v = preg_replace('/[^0-9\-]/', '', $v) ?? '';

        return $v === '' || $v === '-' ? null : (int) $v;
    }

    /** Tolera "1234.50", "1234,50", "1.234,50" y "1,234.50". */
    private function parseDecimal(string $v): ?float
    {
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        $v = (string) preg_replace('/[^0-9.,\-]/', '', $v);
        $hasDot = str_contains($v, '.');
        $hasComma = str_contains($v, ',');
        if ($hasDot && $hasComma) {
            // El último separador es el decimal; el otro son miles.
            if (strrpos($v, ',') > strrpos($v, '.')) {
                $v = str_replace('.', '', $v);
                $v = str_replace(',', '.', $v);
            } else {
                $v = str_replace(',', '', $v);
            }
        } elseif ($hasComma) {
            $v = str_replace(',', '.', $v);
        }

        return is_numeric($v) ? (float) $v : null;
    }

    private function parseBool(string $v): bool
    {
        $v = $this->normalize($v);
        if ($v === '') {
            return true; // por defecto activo
        }

        return in_array($v, ['no', '0', 'false', 'inactivo', 'n'], true) ? false : true;
    }
}
