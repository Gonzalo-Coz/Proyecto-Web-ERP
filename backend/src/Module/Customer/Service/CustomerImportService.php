<?php

declare(strict_types=1);

namespace App\Module\Customer\Service;

use App\Module\Customer\Entity\Customer;
use App\Module\Customer\Repository\CustomerRepository;
use App\Shared\Import\CsvImportReader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Carga masiva de clientes desde CSV/Excel (mismo patrón que Repuestos).
 *
 * Upsert por (tipo + número de documento): si existe → actualiza; si no → crea.
 * El tipo de documento se infiere del número cuando la columna va vacía (8 díg. → DNI, 11 → RUC).
 */
final class CustomerImportService
{
    private const COLUMNS = [
        'documentType' => 'Tipo de Documento',
        'documentNumber' => 'Numero de Documento',
        'name' => 'Nombre o Razon Social',
        'tradeName' => 'Nombre Comercial',
        'address' => 'Direccion',
        'district' => 'Distrito',
        'province' => 'Provincia',
        'department' => 'Departamento',
        'phone' => 'Telefono',
        'email' => 'Email',
        'active' => 'Activo (SI/NO)',
    ];

    private const HEADER_ALIASES = [
        'tipodedocumentodnirucsepasaporte' => 'documentType',
        'tipodedocumento' => 'documentType',
        'tipodocumento' => 'documentType',
        'tipodoc' => 'documentType',
        'tipo' => 'documentType',
        'numerodedocumento' => 'documentNumber',
        'numerodocumento' => 'documentNumber',
        'nrodocumento' => 'documentNumber',
        'ndocumento' => 'documentNumber',
        'documento' => 'documentNumber',
        'nombreorazonsocial' => 'name',
        'nombrerazonsocial' => 'name',
        'razonsocial' => 'name',
        'nombre' => 'name',
        'nombrecomercial' => 'tradeName',
        'direccion' => 'address',
        'distrito' => 'district',
        'provincia' => 'province',
        'departamento' => 'department',
        'telefono' => 'phone',
        'celular' => 'phone',
        'email' => 'email',
        'correo' => 'email',
        'correoelectronico' => 'email',
        'activosino' => 'active',
        'activo' => 'active',
    ];

    /** @var array<string, true> claves "tipo|numero" ya vistas en este archivo */
    private array $seenDocs = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CustomerRepository $customerRepository,
        private readonly CsvImportReader $csv,
    ) {
    }

    public function template(): string
    {
        return $this->csv->buildTemplate(
            array_values(self::COLUMNS),
            ['DNI', '44556677', 'Juan Pérez Ramírez', '', 'Jr. Lima 123', 'Huánuco', 'Huánuco', 'Huánuco', '962111222', 'juan@correo.com', 'SI'],
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
        if (!isset($colIndex['documentNumber'], $colIndex['name'])) {
            throw new UnprocessableEntityHttpException(
                'La plantilla no tiene las columnas obligatorias (Número de Documento, Nombre o Razón Social). Descargue la plantilla y respete las cabeceras.',
            );
        }

        $this->seenDocs = [];
        $results = [];
        $counts = ['total' => 0, 'create' => 0, 'update' => 0, 'error' => 0];
        $line = 1;

        foreach ($rows as $cells) {
            ++$line;
            $get = static fn (string $key): string => isset($colIndex[$key]) ? trim((string) ($cells[$colIndex[$key]] ?? '')) : '';

            $number = $get('documentNumber');
            $name = $get('name');
            if ($number === '' && $name === '' && $get('documentType') === '') {
                continue; // fila vacía
            }

            ++$counts['total'];
            $row = $this->evaluateRow($get, $number, $name, $line, $dryRun);
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
    private function evaluateRow(callable $get, string $number, string $name, int $line, bool $dryRun): array
    {
        $base = ['line' => $line, 'code' => $number, 'label' => $name];

        $errors = [];
        if ($number === '') {
            $errors[] = 'falta el número de documento';
        }
        if ($name === '') {
            $errors[] = 'falta el nombre o razón social';
        }

        $type = strtoupper($get('documentType')) ?: $this->inferType($number);
        if ($type === '') {
            $errors[] = 'falta el tipo de documento y no se pudo inferir';
        } elseif (!in_array($type, Customer::DOCUMENT_TYPES, true)) {
            $errors[] = sprintf('tipo de documento inválido (%s)', $type);
        } elseif ($number !== '' && !$this->isValidFormat($type, $number)) {
            $errors[] = sprintf('el número no tiene formato válido para %s', $type);
        }

        if ($errors !== []) {
            return $base + ['status' => 'error', 'message' => ucfirst(implode('; ', $errors)).'.'];
        }

        $existing = $this->customerRepository->findOneByDocument($type, $number);
        // Documento repetido dentro del mismo archivo → se tratará como actualización de la fila previa
        // (así la vista previa coincide con lo que hará el guardado).
        $fileDup = isset($this->seenDocs[$type.'|'.$number]);
        $this->seenDocs[$type.'|'.$number] = true;
        $status = ($existing !== null || $fileDup) ? 'update' : 'create';

        if ($dryRun) {
            return $base + ['status' => $status, 'message' => ($status === 'create' ? 'Se creará' : 'Se actualizará').'.'];
        }

        try {
            $customer = $existing ?? new Customer($type, $number, $name);
            $customer->setDocumentType($type);
            $customer->setDocumentNumber($number);
            $customer->setName($name);
            $customer->setTradeName($get('tradeName') ?: null);
            $customer->setAddress($get('address') ?: null);
            $customer->setDistrict($get('district') ?: null);
            $customer->setProvince($get('province') ?: null);
            $customer->setDepartment($get('department') ?: null);
            $customer->setPhone($get('phone') ?: null);
            $customer->setEmail($get('email') ?: null);
            $customer->setActive($this->csv->parseBool($get('active')));

            if ($existing === null) {
                $this->entityManager->persist($customer);
            }
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            return $base + ['status' => 'error', 'message' => 'No se pudo guardar: '.$e->getMessage()];
        }

        return $base + ['status' => $status, 'message' => $status === 'create' ? 'Creado.' : 'Actualizado.'];
    }

    private function inferType(string $number): string
    {
        if (preg_match('/^\d{8}$/', $number) === 1) {
            return 'DNI';
        }
        if (preg_match('/^(10|15|17|20)\d{9}$/', $number) === 1) {
            return 'RUC';
        }

        return '';
    }

    /** Réplica de la validación de formato de CustomerService (§7). */
    private function isValidFormat(string $type, string $number): bool
    {
        return match ($type) {
            'DNI' => preg_match('/^\d{8}$/', $number) === 1,
            'RUC' => preg_match('/^(10|15|17|20)\d{9}$/', $number) === 1,
            'CE' => preg_match('/^[A-Za-z0-9]{6,12}$/', $number) === 1,
            'PASAPORTE' => preg_match('/^[A-Za-z0-9]{6,15}$/', $number) === 1,
            default => preg_match('/^[A-Za-z0-9-]{3,20}$/', $number) === 1,
        };
    }
}
