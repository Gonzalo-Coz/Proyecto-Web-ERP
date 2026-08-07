<?php

declare(strict_types=1);

namespace App\Module\Purchasing\Service;

use App\Module\Catalog\Service\CatalogService;
use App\Module\Inventory\Dto\SparePartPayload;
use App\Module\Inventory\Repository\SparePartRepository;
use App\Module\Inventory\Service\SparePartService;
use App\Module\Motorcycle\Dto\UnitPayload;
use App\Module\Motorcycle\Entity\MotorcycleModel;
use App\Module\Motorcycle\Repository\MotorcycleModelRepository;
use App\Module\Motorcycle\Repository\MotorcycleUnitRepository;
use App\Module\Motorcycle\Service\UnitService;
use App\Module\Purchasing\Dto\PurchasePayload;
use App\Module\Supplier\Entity\Supplier;
use App\Module\Supplier\Repository\SupplierRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Importación de facturas electrónicas de Yamaha (UBL 2.1):
 *  - preview(): lee el XML y devuelve una vista previa editable (NO escribe).
 *  - confirm(): crea repuestos/unidades faltantes y registra la Compra.
 */
final class InvoiceImportService
{
    public function __construct(
        private readonly YamahaInvoiceParser $parser,
        private readonly ExchangeRateClient $exchangeRate,
        private readonly PdfDuaExtractor $duaExtractor,
        private readonly EntityManagerInterface $entityManager,
        private readonly SupplierRepository $supplierRepository,
        private readonly SparePartRepository $sparePartRepository,
        private readonly SparePartService $sparePartService,
        private readonly MotorcycleUnitRepository $unitRepository,
        private readonly MotorcycleModelRepository $modelRepository,
        private readonly UnitService $unitService,
        private readonly CatalogService $catalogService,
        private readonly PurchaseService $purchaseService,
    ) {
    }

    /** @return array<string, mixed> */
    public function preview(string $xml, ?string $pdf = null): array
    {
        $data = $this->parser->parse($xml);
        $doc = $data['document'];
        $currency = $doc['currency'];

        // DUA por VIN desde el PDF (si se adjuntó); el XML no lo incluye.
        $duaByVin = ($pdf !== null && $pdf !== '') ? $this->duaExtractor->extractByVin($pdf) : [];

        $rate = $currency === 'USD' ? $this->exchangeRate->saleRate($doc['issueDate']) : 1.0;
        $factor = $rate ?? 0.0; // si no hay T.C., el frontend lo pedirá

        $supplier = $this->supplierRepository->findOneByRuc($data['supplier']['ruc']);

        $spareParts = [];
        $motorcycles = [];
        foreach ($data['lines'] as $line) {
            $costPen = $currency === 'USD' ? round((float) $line['netUnit'] * $factor, 2) : (float) $line['netUnit'];
            if ($line['kind'] === 'MOTORCYCLE') {
                $m = $line['moto'];
                $existing = $m['vin'] !== '' ? $this->unitRepository->findOneBy(['vin' => $m['vin']]) : null;
                $dua = $duaByVin[strtoupper($m['vin'])] ?? null;
                $motorcycles[] = [
                    'code' => $line['code'],
                    'description' => $line['description'],
                    'brand' => $m['brand'],
                    'model' => $m['model'],
                    'color' => $m['color'],
                    'engine' => $m['engine'],
                    'vin' => $m['vin'],
                    'chassis' => $m['chassis'],
                    'year' => $m['year'],
                    'netUnit' => (float) $line['netUnit'],
                    'costPen' => $costPen,
                    'salePrice' => null,
                    'duaNumber' => $dua['dua'] ?? null,
                    'duaItem' => $dua['item'] ?? null,
                    'alreadyExists' => $existing !== null,
                ];
            } else {
                $part = $line['code'] !== '' ? $this->sparePartRepository->findOneByPartCode($line['code']) : null;
                $spareParts[] = [
                    'code' => $line['code'],
                    'description' => $line['description'],
                    'quantity' => (int) round((float) $line['quantity']),
                    'netUnit' => (float) $line['netUnit'],
                    'costPen' => $costPen,
                    'salePrice' => null,
                    'existingId' => $part?->getId(),
                    'existingStock' => $part?->getStock(),
                ];
            }
        }

        return [
            'document' => $doc,
            'supplier' => [
                'ruc' => $data['supplier']['ruc'],
                'name' => $data['supplier']['name'],
                'existingId' => $supplier?->getId(),
            ],
            'exchangeRate' => $rate,          // null si no se pudo obtener (USD)
            'exchangeRateAuto' => $rate !== null,
            'spareParts' => $spareParts,
            'motorcycles' => $motorcycles,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function confirm(array $payload): array
    {
        $sup = $payload['supplier'] ?? [];
        $doc = $payload['document'] ?? [];
        $ruc = (string) ($sup['ruc'] ?? '');
        if ($ruc === '') {
            throw new UnprocessableEntityHttpException('Falta el RUC del proveedor.');
        }

        $supplier = $this->supplierRepository->findOneByRuc($ruc);
        if ($supplier === null) {
            $supplier = new Supplier($ruc, (string) ($sup['name'] ?? 'PROVEEDOR'));
            $this->entityManager->persist($supplier);
            $this->entityManager->flush();
        }

        $items = [];

        foreach (($payload['spareParts'] ?? []) as $sp) {
            $code = trim((string) ($sp['code'] ?? ''));
            $qty = max(1, (int) ($sp['quantity'] ?? 1));
            $cost = round((float) ($sp['costPen'] ?? 0), 2);
            if ($code === '') {
                continue;
            }
            $sale = isset($sp['salePrice']) && $sp['salePrice'] !== null && $sp['salePrice'] !== ''
                ? round((float) $sp['salePrice'], 2) : null;
            $part = $this->sparePartRepository->findOneByPartCode($code);
            if ($part === null) {
                $created = $this->sparePartService->create(new SparePartPayload(
                    partCode: substr($code, 0, 40),
                    description: (string) ($sp['description'] ?? $code),
                    purchasePrice: $cost,
                    salePrice: $sale,
                ));
                $partId = (int) $created['id'];
            } else {
                $partId = (int) $part->getId();
            }
            $items[] = ['itemType' => 'SPARE_PART', 'sparePartId' => $partId, 'quantity' => $qty, 'unitPrice' => $cost, 'discount' => 0];
        }

        foreach (($payload['motorcycles'] ?? []) as $mt) {
            $vin = trim((string) ($mt['vin'] ?? ''));
            $cost = round((float) ($mt['costPen'] ?? 0), 2);
            if ($vin === '') {
                throw new UnprocessableEntityHttpException('Una motocicleta no tiene VIN; corrígelo antes de confirmar.');
            }
            $model = $this->resolveModel((string) ($mt['brand'] ?? 'YAMAHA'), (string) ($mt['model'] ?? ''), (string) ($mt['year'] ?? ''));

            $sale = isset($mt['salePrice']) && $mt['salePrice'] !== null && $mt['salePrice'] !== ''
                ? round((float) $mt['salePrice'], 2) : null;
            $created = $this->unitService->create(new UnitPayload(
                vin: $vin,
                modelId: (int) $model->getId(),
                color: (string) ($mt['color'] ?? '') ?: 'N/D',
                engineNumber: $this->nullify((string) ($mt['engine'] ?? '')),
                chassisNumber: $this->nullify((string) ($mt['chassis'] ?? '')),
                manufactureYear: ctype_digit((string) ($mt['year'] ?? '')) ? (int) $mt['year'] : null,
                purchasePrice: $cost,
                salePrice: $sale,
                duaNumber: $this->nullify((string) ($mt['duaNumber'] ?? '')),
                duaItem: $this->nullify((string) ($mt['duaItem'] ?? '')),
            ));
            $items[] = ['itemType' => 'MOTORCYCLE_UNIT', 'motorcycleUnitId' => (int) $created['id'], 'quantity' => 1, 'unitPrice' => $cost, 'discount' => 0];
        }

        if ($items === []) {
            throw new UnprocessableEntityHttpException('No hay líneas para importar.');
        }

        return $this->purchaseService->create(new PurchasePayload(
            supplierId: (int) $supplier->getId(),
            purchaseDate: (string) ($doc['issueDate'] ?? date('Y-m-d')),
            documentType: 'FACTURA',
            items: $items,
            series: $this->nullify((string) ($doc['series'] ?? '')),
            documentNumber: $this->nullify((string) ($doc['number'] ?? '')),
            notes: 'Importado del XML '.((string) ($doc['fullNumber'] ?? '')),
        ));
    }

    private function resolveModel(string $brand, string $model, string $year): MotorcycleModel
    {
        // Normaliza espacios para no crear modelos "duplicados" por espaciado.
        $model = trim((string) preg_replace('/\s+/', ' ', $model));
        $model = $model !== '' ? $model : 'MODELO';

        // Reutiliza un modelo existente (comparación sin distinguir mayúsculas).
        $existing = $this->modelRepository->createQueryBuilder('m')
            ->where('LOWER(m.model) = LOWER(:name)')
            ->setParameter('name', $model)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        if ($existing !== null) {
            return $existing;
        }
        $brandItem = $this->catalogService->findOrCreateByName('brands', trim($brand) !== '' ? trim($brand) : 'YAMAHA');
        $m = new MotorcycleModel($brandItem, $model, ctype_digit($year) ? (int) $year : (int) date('Y'));
        $this->entityManager->persist($m);
        $this->entityManager->flush();

        return $m;
    }

    private function nullify(string $s): ?string
    {
        $s = trim($s);

        return $s === '' ? null : $s;
    }
}
