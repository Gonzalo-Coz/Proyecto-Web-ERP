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
        ];
    }

    private function sales(): array
    {
        $base = "SELECT COALESCE(SUM(total), 0) FROM sales WHERE status = 'COMPLETADA'";

        return [
            'today' => $this->scalar($base." AND sale_date = CURRENT_DATE"),
            'week' => $this->scalar($base." AND sale_date >= date_trunc('week', CURRENT_DATE)::date"),
            'month' => $this->scalar($base." AND sale_date >= date_trunc('month', CURRENT_DATE)::date"),
            'year' => $this->scalar($base." AND sale_date >= date_trunc('year', CURRENT_DATE)::date"),
            'totalBilled' => $this->scalar($base),
            'totalCollected' => $this->scalar("SELECT COALESCE(SUM(paid_amount), 0) FROM sales WHERE status != 'ANULADA'"),
            'receivables' => $this->scalar("SELECT COALESCE(SUM(total - paid_amount), 0) FROM sales WHERE status = 'COMPLETADA'"),
            'bySeller' => $this->db->fetchAllAssociative(
                "SELECT seller, COALESCE(SUM(total), 0) AS total, COUNT(*) AS count
                 FROM sales WHERE status = 'COMPLETADA' AND sale_date >= date_trunc('month', CURRENT_DATE)::date
                 GROUP BY seller ORDER BY total DESC LIMIT 5",
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
        $rows = $this->db->fetchAllKeyValue(
            "SELECT to_char(date_trunc('month', sale_date), 'YYYY-MM') AS ym, COALESCE(SUM(total), 0) AS total
             FROM sales
             WHERE status = 'COMPLETADA'
               AND sale_date >= (date_trunc('month', CURRENT_DATE) - interval '5 months')::date
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
            'pending' => (int) ($counts['RECIBIDA'] ?? 0) + (int) ($counts['EN_DIAGNOSTICO'] ?? 0),
            'inProgress' => (int) ($counts['ESPERANDO_REPUESTOS'] ?? 0) + (int) ($counts['EN_REPARACION'] ?? 0),
            'ready' => (int) ($counts['LISTA_PARA_ENTREGA'] ?? 0),
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
            $alerts[] = ['level' => 'danger', 'message' => sprintf('%d repuesto(s) sin stock.', $out)];
        }

        $low = (int) $this->scalar('SELECT COUNT(*) FROM spare_parts WHERE deleted_at IS NULL AND stock > 0 AND stock <= min_stock');
        if ($low > 0) {
            $alerts[] = ['level' => 'warning', 'message' => sprintf('%d repuesto(s) con stock bajo.', $low)];
        }

        // Caja mensual: solo avisa si la caja abierta es de un MES anterior (nuevo mes → cerrar y abrir otra).
        $oldSession = $this->db->fetchAssociative(
            "SELECT session_number FROM cash_sessions WHERE status = 'ABIERTA' AND date_trunc('month', opened_at) < date_trunc('month', CURRENT_DATE) LIMIT 1",
        );
        if ($oldSession !== false) {
            $alerts[] = ['level' => 'warning', 'message' => sprintf('La caja %s es del mes anterior. Ciérrala y abre una nueva para el mes en curso.', $oldSession['session_number'])];
        }

        $rejected = (int) $this->scalar("SELECT COUNT(*) FROM electronic_documents WHERE status = 'RECHAZADO'");
        if ($rejected > 0) {
            $alerts[] = ['level' => 'danger', 'message' => sprintf('%d comprobante(s) rechazado(s) por SUNAT.', $rejected)];
        }

        $delayed = (int) $this->scalar(
            "SELECT COUNT(*) FROM service_orders WHERE status NOT IN ('ENTREGADA') AND estimated_date IS NOT NULL AND estimated_date < CURRENT_DATE",
        );
        if ($delayed > 0) {
            $alerts[] = ['level' => 'warning', 'message' => sprintf('%d orden(es) de taller retrasada(s).', $delayed)];
        }

        $receivables = (float) $this->scalar("SELECT COALESCE(SUM(total - paid_amount), 0) FROM sales WHERE status = 'COMPLETADA'");
        if ($receivables > 0.009) {
            $alerts[] = ['level' => 'info', 'message' => sprintf('Cuentas por cobrar pendientes: S/ %.2f.', $receivables)];
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
