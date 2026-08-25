<?php

declare(strict_types=1);

namespace App\Module\Maintenance\Service;

use App\Module\Inventory\Repository\SparePartRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Planes de mantenimiento por kilometraje (taller). Fuente de solo lectura:
 * los datos vienen del Excel maestro normalizado (Resources/plans_2025.json).
 *
 * NO define su propia tabla: es material de referencia. La "venta de servicio"
 * que se arma a partir de un plan usa el motor de Ventas existente (líneas tipo
 * SERVICE para la mano de obra y SPARE_PART para los repuestos del kit).
 *
 * Cada repuesto del kit se cruza con el inventario por su código de parte
 * (SparePart.partCode) para traer precio de venta y stock reales; si el código
 * aún no está cargado, se marca como "pendiente" (visible, sin precio/stock).
 */
final class MaintenancePlanService
{
    /** @var array<string, mixed>|null */
    private ?array $data = null;

    public function __construct(private readonly SparePartRepository $spareParts)
    {
    }

    /**
     * Lista de modelos para el selector: id, modelo y kilometrajes disponibles.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listModels(): array
    {
        $out = [];
        foreach ($this->all()['models'] as $m) {
            $out[] = [
                'id' => $m['id'],
                'model' => $m['model'],
                'kmIntervals' => $m['kmIntervals'],
            ];
        }

        return $out;
    }

    /**
     * Resuelve el servicio de un modelo a un kilometraje: mano de obra,
     * actividades marcadas y kit de repuestos (enriquecido con inventario).
     *
     * @return array<string, mixed>
     */
    public function getService(int $id, int $km): array
    {
        $model = null;
        foreach ($this->all()['models'] as $m) {
            if ((int) $m['id'] === $id) {
                $model = $m;
                break;
            }
        }
        if ($model === null) {
            throw new NotFoundHttpException('Plan de mantenimiento no encontrado.');
        }

        $kmKey = (string) $km;

        // Mano de obra del plan para ese km (puede no existir → se define manual).
        $labor = $model['labor'][$kmKey] ?? null;

        // Actividades que aplican en ese km (checklist con su acción I/R/A/E/L).
        $activities = [];
        foreach ($model['activities'] as $a) {
            if (isset($a['actions'][$kmKey])) {
                $activities[] = [
                    'system' => $a['system'],
                    'activity' => $a['activity'],
                    'action' => $a['actions'][$kmKey],
                ];
            }
        }

        // Kit de repuestos que se cambian en ese km, enriquecido con inventario.
        $parts = [];
        foreach ($model['parts'] as $p) {
            if (!in_array($km, $p['replaceAt'], true)) {
                continue;
            }
            $sp = $this->spareParts->findOneByPartCode((string) $p['code']);
            $parts[] = [
                'category' => $p['category'],
                'code' => $p['code'],
                'description' => $p['description'],
                'unit' => $p['unit'],
                'quantity' => $p['qty'],
                // Enlace al inventario (por código de parte).
                'sparePartId' => $sp?->getId(),
                'internalCode' => $sp?->getInternalCode(),
                'salePrice' => $sp?->getSalePrice(),
                'stock' => $sp?->getStock(),
                'inInventory' => $sp !== null,
            ];
        }

        return [
            'id' => $model['id'],
            'model' => $model['model'],
            'km' => $km,
            'labor' => $labor,           // {hours, cost} o null
            'activities' => $activities, // [{system, activity, action}]
            'parts' => $parts,           // kit con precio/stock del inventario
            'legend' => $this->all()['legend'],
        ];
    }

    /**
     * Carga (y cachea en memoria) el JSON de planes normalizado.
     *
     * @return array<string, mixed>
     */
    private function all(): array
    {
        if ($this->data === null) {
            $path = __DIR__.'/../Resources/plans_2025.json';
            $raw = is_file($path) ? file_get_contents($path) : false;
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            $this->data = is_array($decoded) ? $decoded : ['legend' => [], 'models' => []];
        }

        return $this->data;
    }
}
