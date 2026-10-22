<?php

declare(strict_types=1);

namespace App\Module\Dashboard\Service;

use Doctrine\DBAL\Connection;

/**
 * Indicadores del Dashboard Gerencial (§6), calculados en vivo.
 * Nota de rendimiento (informe técnico): cuando el volumen crezca se
 * migrará a tablas de resumen alimentadas por eventos, sin cambiar la API.
 */
final class DashboardService
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function summary(): array
    {
        return [
            'sales' => $this->sales(),
            'cash' => $this->cash(),
            'inventory' => $this->inventory(),
            'workshop' => $this->workshop(),
            'customers' => $this->customers(),
            'purchases' => $this->purchases(),
            'alerts' => $this->alerts(),
            'recentActivity' => $this->recentActivity(),
            'statistics' => $this->statistics(),
        ];
    }

    /**
     * Capa de análisis estadístico para la toma de decisiones:
     * ventas/rentabilidad (crecimiento, ticket promedio, margen, distribución)
     * e inventario (rotación, stock muerto, ABC). Alias de ventas: s.
     *
     * @return array<string, mixed>
     */
    private function statistics(): array
    {
        $approved = self::APPROVED_SALE;
        // Costo de ventas (COGS) por venta, reutilizado para margen y rentabilidad.
        $costJoin = "JOIN LATERAL (
            SELECT COALESCE(SUM(CASE i.item_type
                WHEN 'SPARE_PART' THEN COALESCE(sp.purchase_price, 0) * i.quantity
                WHEN 'MOTORCYCLE_UNIT' THEN COALESCE(mu.purchase_price, 0)
                ELSE 0 END), 0) AS total_cost
            FROM sale_items i
            LEFT JOIN spare_parts sp ON sp.id = i.spare_part_id
            LEFT JOIN motorcycle_units mu ON mu.id = i.motorcycle_unit_id
            WHERE i.sale_id = s.id
        ) cost ON true";

        // --- Crecimiento mes vs mes (%) ---
        $thisMonth = (float) $this->scalar("SELECT COALESCE(SUM(s.total),0) FROM sales s WHERE $approved AND s.sale_date >= date_trunc('month', CURRENT_DATE)::date");
        $lastMonth = (float) $this->scalar("SELECT COALESCE(SUM(s.total),0) FROM sales s WHERE $approved AND s.sale_date >= (date_trunc('month', CURRENT_DATE) - interval '1 month')::date AND s.sale_date < date_trunc('month', CURRENT_DATE)::date");
        $growthPct = $lastMonth > 0 ? round(($thisMonth - $lastMonth) / $lastMonth * 100, 1) : null;

        // --- Ticket promedio (mes) ---
        $ticket = $this->db->fetchAssociative("SELECT COALESCE(AVG(s.total),0) AS avg, COUNT(*) AS n FROM sales s WHERE $approved AND s.sale_date >= date_trunc('month', CURRENT_DATE)::date");

        // --- Margen de ganancia (mes) ---
        $margin = $this->db->fetchAssociative(
            "SELECT COALESCE(SUM(s.subtotal),0) AS revenue, COALESCE(SUM(cost.total_cost),0) AS cost
             FROM sales s $costJoin WHERE $approved AND s.sale_date >= date_trunc('month', CURRENT_DATE)::date",
        );
        $rev = (float) ($margin['revenue'] ?? 0);
        $cost = (float) ($margin['cost'] ?? 0);

        // --- Top repuestos por ingreso (últimos 90 días) ---
        $topProducts = $this->db->fetchAllAssociative(
            "SELECT sp.description AS label, ROUND(SUM(si.line_total),2) AS revenue, SUM(si.quantity) AS qty
             FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN spare_parts sp ON sp.id = si.spare_part_id
             WHERE si.item_type = 'SPARE_PART' AND $approved AND s.sale_date >= CURRENT_DATE - 90
             GROUP BY sp.description ORDER BY revenue DESC LIMIT 8",
        );

        // --- Ventas por canal (mes) ---
        $byChannel = $this->db->fetchAllAssociative(
            "SELECT s.channel AS label, ROUND(SUM(s.total),2) AS total, COUNT(*) AS n
             FROM sales s WHERE $approved AND s.sale_date >= date_trunc('month', CURRENT_DATE)::date
             GROUP BY s.channel ORDER BY total DESC",
        );

        // --- Ventas por método de pago (mes) ---
        $byPayment = $this->db->fetchAllAssociative(
            "SELECT COALESCE(ci.name, 'Efectivo') AS label, ROUND(SUM(sp.amount),2) AS total
             FROM sale_payments sp
             JOIN sales s ON s.id = sp.sale_id
             LEFT JOIN catalog_items ci ON ci.id = sp.payment_method_id
             WHERE $approved AND s.sale_date >= date_trunc('month', CURRENT_DATE)::date
             GROUP BY ci.name ORDER BY total DESC",
        );

        return [
            'salesGrowth' => ['thisMonth' => round($thisMonth, 2), 'lastMonth' => round($lastMonth, 2), 'pct' => $growthPct],
            'avgTicket' => ['value' => round((float) ($ticket['avg'] ?? 0), 2), 'count' => (int) ($ticket['n'] ?? 0)],
            'grossMargin' => [
                'revenue' => round($rev, 2),
                'cost' => round($cost, 2),
                'profit' => round($rev - $cost, 2),
                'pct' => $rev > 0 ? round(($rev - $cost) / $rev * 100, 1) : null,
            ],
            'marginTrend' => $this->marginTrend($costJoin),
            'topProducts' => $topProducts,
            'byChannel' => $byChannel,
            'byPayment' => $byPayment,
            'inventoryStats' => $this->inventoryStats(),
            'abc' => $this->abcAnalysis(),
        ];
    }

    /**
     * Margen bruto (%) por mes, últimos 6 meses (para la línea de tendencia).
     *
     * @return list<array{label: string, pct: float}>
     */
    private function marginTrend(string $costJoin): array
    {
        $approved = self::APPROVED_SALE;
        $rows = $this->db->fetchAllAssociative(
            "SELECT to_char(date_trunc('month', s.sale_date), 'YYYY-MM') AS ym,
                    COALESCE(SUM(s.subtotal),0) AS rev, COALESCE(SUM(cost.total_cost),0) AS cost
             FROM sales s $costJoin
             WHERE $approved AND s.sale_date >= (date_trunc('month', CURRENT_DATE) - interval '5 months')::date
             GROUP BY 1",
        );
        $map = [];
        foreach ($rows as $r) {
            $rev = (float) $r['rev'];
            $map[$r['ym']] = $rev > 0 ? round(($rev - (float) $r['cost']) / $rev * 100, 1) : 0.0;
        }

        $months = ['1' => 'Ene', '2' => 'Feb', '3' => 'Mar', '4' => 'Abr', '5' => 'May', '6' => 'Jun', '7' => 'Jul', '8' => 'Ago', '9' => 'Set', '10' => 'Oct', '11' => 'Nov', '12' => 'Dic'];
        $series = [];
        $cursor = (new \DateTimeImmutable('first day of this month'))->modify('-5 months');
        for ($i = 0; $i < 6; ++$i) {
            $series[] = ['label' => $months[(string) (int) $cursor->format('n')], 'pct' => $map[$cursor->format('Y-m')] ?? 0.0];
            $cursor = $cursor->modify('+1 month');
        }

        return $series;
    }

    /**
     * Estadísticas de inventario: valor inmovilizado, stock muerto y rotación.
     *
     * @return array<string, mixed>
     */
    private function inventoryStats(): array
    {
        $value = (float) $this->scalar('SELECT COALESCE(SUM(stock * COALESCE(purchase_price,0)),0) FROM spare_parts WHERE deleted_at IS NULL AND is_active = true');

        // Stock muerto: con existencia pero sin ningún movimiento de salida (VENTA/TALLER) en 90 días.
        $dead = $this->db->fetchAssociative(
            "SELECT COUNT(*) AS n, COALESCE(SUM(sp.stock * COALESCE(sp.purchase_price,0)),0) AS value
             FROM spare_parts sp
             WHERE sp.deleted_at IS NULL AND sp.is_active = true AND sp.stock > 0
               AND NOT EXISTS (
                 SELECT 1 FROM kardex_movements k
                 WHERE k.spare_part_id = sp.id AND k.movement_type IN ('VENTA','TALLER')
                   AND k.created_at >= CURRENT_DATE - 90)",
        );

        // Costo de salidas (COGS) últimos 90 días → rotación anualizada y días de inventario.
        $cogs90 = (float) $this->scalar(
            "SELECT COALESCE(SUM(-k.quantity * COALESCE(sp.purchase_price,0)),0)
             FROM kardex_movements k JOIN spare_parts sp ON sp.id = k.spare_part_id
             WHERE k.movement_type IN ('VENTA','TALLER') AND k.created_at >= CURRENT_DATE - 90",
        );
        $turnover = $value > 0 ? round($cogs90 * (365.0 / 90.0) / $value, 2) : null;
        $daysInv = $cogs90 > 0 ? round($value / ($cogs90 / 90.0), 0) : null;

        return [
            'inventoryValue' => round($value, 2),
            'deadStockCount' => (int) ($dead['n'] ?? 0),
            'deadStockValue' => round((float) ($dead['value'] ?? 0), 2),
            'turnover' => $turnover,
            'daysOfInventory' => $daysInv,
        ];
    }

    /**
     * Análisis ABC (Pareto) de repuestos por ingreso de los últimos 180 días:
     * A = 80% del ingreso (los críticos), B = siguiente 15%, C = último 5%.
     *
     * @return list<array{cls: string, n: int, revenue: float}>
     */
    private function abcAnalysis(): array
    {
        $approved = self::APPROVED_SALE;
        $rows = $this->db->fetchAllAssociative(
            "WITH sold AS (
                SELECT sp.id, SUM(si.line_total) AS rev
                FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN spare_parts sp ON sp.id = si.spare_part_id
                WHERE si.item_type = 'SPARE_PART' AND $approved AND s.sale_date >= CURRENT_DATE - 180
                GROUP BY sp.id
             ), ranked AS (
                SELECT rev,
                       SUM(rev) OVER () AS total,
                       SUM(rev) OVER (ORDER BY rev DESC, id) AS cum
                FROM sold
             )
             SELECT CASE WHEN total > 0 AND cum <= 0.8 * total THEN 'A'
                         WHEN total > 0 AND cum <= 0.95 * total THEN 'B'
                         ELSE 'C' END AS cls,
                    COUNT(*) AS n, ROUND(SUM(rev),2) AS revenue
             FROM ranked GROUP BY 1 ORDER BY 1",
        );

        return array_map(static fn (array $r): array => [
            'cls' => (string) $r['cls'],
            'n' => (int) $r['n'],
            'revenue' => (float) $r['revenue'],
        ], $rows);
    }

    /**
     * Condición contable de "venta aprobada": completada Y con comprobante
     * ACEPTADO por SUNAT. Excluye anuladas, rechazadas y las que aún no tienen
     * comprobante aceptado. (Alias de la tabla de ventas: s.)
     */
    private const APPROVED_SALE = "s.status = 'COMPLETADA' AND EXISTS (SELECT 1 FROM electronic_documents d WHERE d.sale_id = s.id AND d.status = 'ACEPTADO')";

    private function sales(): array
    {
        $approved = self::APPROVED_SALE;
        $base = "SELECT COALESCE(SUM(s.total), 0) FROM sales s WHERE $approved";

        return [
            'today' => $this->scalar($base." AND s.sale_date = CURRENT_DATE"),
            'week' => $this->scalar($base." AND s.sale_date >= date_trunc('week', CURRENT_DATE)::date"),
            'month' => $this->scalar($base." AND s.sale_date >= date_trunc('month', CURRENT_DATE)::date"),
            'year' => $this->scalar($base." AND s.sale_date >= date_trunc('year', CURRENT_DATE)::date"),
            'totalBilled' => $this->scalar($base),
            'totalCollected' => $this->scalar("SELECT COALESCE(SUM(s.paid_amount), 0) FROM sales s WHERE $approved"),
            'receivables' => $this->scalar("SELECT COALESCE(SUM(s.total - s.paid_amount), 0) FROM sales s WHERE $approved"),
            'bySeller' => $this->db->fetchAllAssociative(
                "SELECT s.seller, COALESCE(SUM(s.total), 0) AS total, COUNT(*) AS count
                 FROM sales s WHERE $approved AND s.sale_date >= date_trunc('month', CURRENT_DATE)::date
                 GROUP BY s.seller ORDER BY total DESC LIMIT 5",
            ),
            'trend' => $this->salesTrend(),
        ];
    }

    /**
     * Serie de ventas de los últimos 6 meses (para el gráfico de tendencia).
     * Rellena los meses sin ventas con 0 para no dejar huecos en el gráfico.
     *
     * @return list<array{label: string, total: string}>
     */
    private function salesTrend(): array
    {
        $approved = self::APPROVED_SALE;
        $rows = $this->db->fetchAllKeyValue(
            "SELECT to_char(date_trunc('month', s.sale_date), 'YYYY-MM') AS ym, COALESCE(SUM(s.total), 0) AS total
             FROM sales s
             WHERE $approved
               AND s.sale_date >= (date_trunc('month', CURRENT_DATE) - interval '5 months')::date
             GROUP BY 1",
        );

        $months = ['1' => 'Ene', '2' => 'Feb', '3' => 'Mar', '4' => 'Abr', '5' => 'May', '6' => 'Jun', '7' => 'Jul', '8' => 'Ago', '9' => 'Set', '10' => 'Oct', '11' => 'Nov', '12' => 'Dic'];
        $series = [];
        $cursor = new \DateTimeImmutable('first day of this month');
        $cursor = $cursor->modify('-5 months');
        for ($i = 0; $i < 6; ++$i) {
            $key = $cursor->format('Y-m');
            $series[] = [
                'label' => $months[(string) (int) $cursor->format('n')],
                'total' => (string) ($rows[$key] ?? '0'),
            ];
            $cursor = $cursor->modify('+1 month');
        }

        return $series;
    }

    private function cash(): array
    {
        $open = $this->db->fetchAssociative(
            "SELECT id, session_number, opening_amount FROM cash_sessions WHERE status = 'ABIERTA' LIMIT 1",
        );

        $todayIncome = $this->scalar(
            "SELECT COALESCE(SUM(amount), 0) FROM cash_movements WHERE movement_type = 'INGRESO' AND created_at::date = CURRENT_DATE",
        );
        $todayExpense = $this->scalar(
            "SELECT COALESCE(SUM(amount), 0) FROM cash_movements WHERE movement_type = 'EGRESO' AND created_at::date = CURRENT_DATE",
        );

        return [
            'isOpen' => $open !== false,
            'sessionNumber' => $open !== false ? $open['session_number'] : null,
            'todayIncome' => $todayIncome,
            'todayExpense' => $todayExpense,
            'todayNet' => number_format((float) $todayIncome - (float) $todayExpense, 2, '.', ''),
        ];
    }

    private function inventory(): array
    {
        return [
            'motorcyclesAvailable' => (int) $this->scalar("SELECT COUNT(*) FROM motorcycle_units WHERE status = 'DISPONIBLE' AND deleted_at IS NULL"),
            'sparePartsActive' => (int) $this->scalar('SELECT COUNT(*) FROM spare_parts WHERE is_active = true AND deleted_at IS NULL'),
            'lowStock' => (int) $this->scalar('SELECT COUNT(*) FROM spare_parts WHERE deleted_at IS NULL AND stock > 0 AND stock <= min_stock'),
            'outOfStock' => (int) $this->scalar('SELECT COUNT(*) FROM spare_parts WHERE deleted_at IS NULL AND is_active = true AND stock <= 0'),
            'topSold' => $this->db->fetchAllAssociative(
                "SELECT sp.description, SUM(-k.quantity) AS sold
                 FROM kardex_movements k JOIN spare_parts sp ON sp.id = k.spare_part_id
                 WHERE k.movement_type = 'VENTA'
                 GROUP BY sp.description ORDER BY sold DESC LIMIT 5",
            ),
        ];
    }

    private function workshop(): array
    {
        $counts = $this->db->fetchAllKeyValue('SELECT status, COUNT(*) FROM service_orders GROUP BY status');

        return [
            // Flujo de 3 etapas: Recibida (cola) → En reparación (trabajo) → Entregada.
            'pending' => (int) ($counts['RECIBIDA'] ?? 0),
            'inProgress' => (int) ($counts['EN_REPARACION'] ?? 0),
            'ready' => (int) ($counts['EN_REPARACION'] ?? 0),
            'delivered' => (int) ($counts['ENTREGADA'] ?? 0),
            'delayed' => (int) $this->scalar(
                "SELECT COUNT(*) FROM service_orders WHERE status NOT IN ('ENTREGADA') AND estimated_date IS NOT NULL AND estimated_date < CURRENT_DATE",
            ),
        ];
    }

    private function customers(): array
    {
        return [
            'total' => (int) $this->scalar('SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL AND is_active = true'),
            'newThisMonth' => (int) $this->scalar(
                "SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL AND created_at >= date_trunc('month', CURRENT_DATE)",
            ),
            'topBuyers' => $this->db->fetchAllAssociative(
                "SELECT c.name, COALESCE(SUM(s.total), 0) AS total
                 FROM sales s JOIN customers c ON c.id = s.customer_id
                 WHERE s.status = 'COMPLETADA'
                 GROUP BY c.name ORDER BY total DESC LIMIT 5",
            ),
        ];
    }

    private function purchases(): array
    {
        return [
            'month' => $this->scalar(
                "SELECT COALESCE(SUM(total), 0) FROM purchases WHERE status = 'REGISTRADA' AND purchase_date >= date_trunc('month', CURRENT_DATE)::date",
            ),
            'topSuppliers' => $this->db->fetchAllAssociative(
                "SELECT sp.business_name AS name, COALESCE(SUM(p.total), 0) AS total
                 FROM purchases p JOIN suppliers sp ON sp.id = p.supplier_id
                 WHERE p.status = 'REGISTRADA'
                 GROUP BY sp.business_name ORDER BY total DESC LIMIT 3",
            ),
        ];
    }

    /** Alertas automáticas (§6). */
    private function alerts(): array
    {
        $alerts = [];

        $out = (int) $this->scalar('SELECT COUNT(*) FROM spare_parts WHERE deleted_at IS NULL AND is_active = true AND stock <= 0');
        if ($out > 0) {
            $alerts[] = ['level' => 'danger', 'message' => sprintf('%d repuesto(s) sin stock.', $out), 'link' => ['name' => 'spare-parts', 'query' => ['stock' => 'out']]];
        }

        $low = (int) $this->scalar('SELECT COUNT(*) FROM spare_parts WHERE deleted_at IS NULL AND stock > 0 AND stock <= min_stock');
        if ($low > 0) {
            $alerts[] = ['level' => 'warning', 'message' => sprintf('%d repuesto(s) con stock bajo.', $low), 'link' => ['name' => 'spare-parts', 'query' => ['stock' => 'low']]];
        }

        // Caja mensual: solo avisa si la caja abierta es de un MES anterior (nuevo mes → cerrar y abrir otra).
        $oldSession = $this->db->fetchAssociative(
            "SELECT session_number FROM cash_sessions WHERE status = 'ABIERTA' AND date_trunc('month', opened_at) < date_trunc('month', CURRENT_DATE) LIMIT 1",
        );
        if ($oldSession !== false) {
            $alerts[] = ['level' => 'warning', 'message' => sprintf('La caja %s es del mes anterior. Ciérrala y abre una nueva para el mes en curso.', $oldSession['session_number']), 'link' => ['name' => 'cash']];
        }

        $rejected = (int) $this->scalar("SELECT COUNT(*) FROM electronic_documents WHERE status = 'RECHAZADO'");
        if ($rejected > 0) {
            $alerts[] = ['level' => 'danger', 'message' => sprintf('%d comprobante(s) rechazado(s) por SUNAT.', $rejected), 'link' => ['name' => 'invoicing', 'query' => ['status' => 'RECHAZADO']]];
        }

        $delayed = (int) $this->scalar(
            "SELECT COUNT(*) FROM service_orders WHERE status NOT IN ('ENTREGADA') AND estimated_date IS NOT NULL AND estimated_date < CURRENT_DATE",
        );
        if ($delayed > 0) {
            $alerts[] = ['level' => 'warning', 'message' => sprintf('%d orden(es) de taller retrasada(s).', $delayed), 'link' => ['name' => 'workshop']];
        }

        $receivables = (float) $this->scalar('SELECT COALESCE(SUM(s.total - s.paid_amount), 0) FROM sales s WHERE '.self::APPROVED_SALE);
        if ($receivables > 0.009) {
            $alerts[] = ['level' => 'info', 'message' => sprintf('Cuentas por cobrar pendientes: S/ %.2f.', $receivables), 'link' => ['name' => 'sales', 'query' => ['pending' => '1']]];
        }

        return $alerts;
    }

    /** Últimos movimientos del sistema (§6: Actividad Reciente), desde auditoría. */
    private function recentActivity(): array
    {
        return $this->db->fetchAllAssociative(
            "SELECT username, module, action, entity_class, created_at
             FROM audit_logs
             WHERE module != 'shared'
             ORDER BY id DESC LIMIT 10",
        );
    }

    private function scalar(string $sql): string
    {
        return (string) $this->db->fetchOne($sql);
    }
}
