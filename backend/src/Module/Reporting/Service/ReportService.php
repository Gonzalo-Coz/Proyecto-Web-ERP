<?php

declare(strict_types=1);

namespace App\Module\Reporting\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Reportes dinámicos con filtros de fecha (§16).
 * Devuelven columnas + filas tabulares; el frontend exporta a CSV (Excel).
 * XLSX/PDF nativos: pendiente con la infraestructura de exportación.
 */
final class ReportService
{
    public const TYPES = [
        'sales', 'purchases', 'cash', 'kardex', 'workshop', 'documents',
        'customers', 'suppliers', 'motorcycles', 'inventory', 'utilities', 'audit',
        'repuestosyamaha', 'motosyamaha',
    ];

    public function __construct(
        private readonly Connection $db,
        private readonly \App\Shared\Settings\Service\SettingsService $settings,
    ) {
    }

    public function generate(string $type, string $from, string $to): array
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new NotFoundHttpException('Tipo de reporte no disponible.');
        }

        $params = ['from' => $from, 'to' => $to];

        return match ($type) {
            'sales' => [
                'title' => 'Reporte de Ventas',
                'columns' => $this->cols(['Número', 'Fecha', 'Cliente', 'Productos', 'Vendedor', 'Estado', 'Subtotal', 'IGV', 'Total', 'Pagado', 'Saldo']),
                'rows' => $this->db->fetchAllNumeric(
                    "SELECT s.sale_number, s.sale_date, c.name, {$this->saleProductsSql('s.id')}, s.seller, s.status, s.subtotal, s.igv, s.total, s.paid_amount, (s.total - s.paid_amount)
                     FROM sales s JOIN customers c ON c.id = s.customer_id
                     WHERE s.sale_date BETWEEN :from AND :to
                     ORDER BY s.sale_date, s.id", $params,
                ),
            ],
            // Reporte retail que Yamaha exige al dealer: venta de repuestos (por línea).
            'repuestosyamaha' => [
                'title' => 'Venta de Repuestos (Formato Yamaha)',
                'columns' => $this->cols(['Bloque', 'CL/RUC', 'Local', 'Código', 'Descripción', 'Cantidad Vendida', '% Descto', 'Descuento', 'Precio Venta (con IGV)', 'Monto total', 'Origen', 'Fecha Ope']),
                'rows' => $this->db->fetchAllNumeric(
                    "SELECT 'Venta', :ruc, :local, sp.part_code, si.description, si.quantity,
                            COALESCE(si.discount_percent, 0), si.discount, si.unit_price, si.line_total,
                            CASE WHEN EXISTS (SELECT 1 FROM service_orders so WHERE so.invoice_sale_id = s.id) THEN 'Taller' ELSE 'Mostrador' END,
                            s.sale_date
                     FROM sale_items si
                     JOIN sales s ON s.id = si.sale_id
                     JOIN spare_parts sp ON sp.id = si.spare_part_id
                     WHERE si.item_type = 'SPARE_PART' AND s.status = 'COMPLETADA'
                       AND s.sale_date BETWEEN :from AND :to
                     ORDER BY s.sale_date, s.id",
                    array_merge($params, [
                        'ruc' => $this->settings->get('company.ruc') ?? '',
                        'local' => $this->settings->get('company.trade_name') ?: ($this->settings->get('company.name') ?? 'PRINCIPAL'),
                    ]),
                ),
            ],
            // Reporte retail Yamaha: venta de motocicletas (formato oficial DATA).
            'motosyamaha' => [
                'title' => 'Venta de Motos (Formato Yamaha)',
                'columns' => $this->cols(['DEALER', 'VIN', 'FECHA DE VENTA RETAIL', 'N° DE COMPROBANTE DE PAGO', 'TIPO DE PAGO', 'ENTIDAD FINANCIERA', 'TCEA', 'BONO YMDP', 'BONO DEALER', 'CAMPAÑA', 'MODELO', 'COLOR', 'FECHA DE COMPRA']),
                'rows' => $this->db->fetchAllNumeric(
                    "SELECT :dealer, u.vin, s.sale_date,
                            (SELECT CONCAT(ed.series, ed.correlative) FROM electronic_documents ed WHERE ed.sale_id = s.id ORDER BY ed.id DESC LIMIT 1),
                            '', '', '', '', '', '',
                            TRIM(CONCAT('MOTOCICLETA ', m.model, ' ', COALESCE(m.version, ''))),
                            u.color, COALESCE(u.purchase_date, u.entry_date)
                     FROM sale_items si
                     JOIN sales s ON s.id = si.sale_id
                     JOIN motorcycle_units u ON u.id = si.motorcycle_unit_id
                     JOIN motorcycle_models m ON m.id = u.model_id
                     WHERE si.item_type = 'MOTORCYCLE_UNIT' AND s.status = 'COMPLETADA'
                       AND s.sale_date BETWEEN :from AND :to
                     ORDER BY s.sale_date, s.id",
                    array_merge($params, ['dealer' => $this->settings->get('company.name') ?? '']),
                ),
            ],
            'purchases' => [
                'title' => 'Reporte de Compras',
                'columns' => $this->cols(['Número', 'Fecha', 'Proveedor', 'Documento', 'Estado', 'Subtotal', 'IGV', 'Total']),
                'rows' => $this->db->fetchAllNumeric(
                    "SELECT p.purchase_number, p.purchase_date, sp.business_name,
                            CONCAT(p.document_type, ' ', COALESCE(p.series, ''), '-', COALESCE(p.document_number, '')),
                            p.status, p.subtotal, p.igv, p.total
                     FROM purchases p JOIN suppliers sp ON sp.id = p.supplier_id
                     WHERE p.purchase_date BETWEEN :from AND :to
                     ORDER BY p.purchase_date, p.id", $params,
                ),
            ],
            'cash' => [
                'title' => 'Reporte de Caja',
                'columns' => $this->cols(['Fecha/Hora', 'Sesión', 'Tipo', 'Concepto', 'Referencia', 'Monto', 'Usuario']),
                'rows' => $this->db->fetchAllNumeric(
                    "SELECT m.created_at, s.session_number, m.movement_type, m.concept, m.reference, m.amount, m.username
                     FROM cash_movements m JOIN cash_sessions s ON s.id = m.session_id
                     WHERE m.created_at::date BETWEEN :from AND :to
                     ORDER BY m.created_at", $params,
                ),
            ],
            'kardex' => [
                'title' => 'Reporte de Kardex',
                'columns' => $this->cols(['Fecha/Hora', 'Repuesto', 'Tipo', 'Cantidad', 'Saldo', 'Referencia', 'Usuario']),
                'rows' => $this->db->fetchAllNumeric(
                    "SELECT k.created_at, sp.description, k.movement_type, k.quantity, k.balance_after, k.reference, k.username
                     FROM kardex_movements k JOIN spare_parts sp ON sp.id = k.spare_part_id
                     WHERE k.created_at::date BETWEEN :from AND :to
                     ORDER BY k.created_at", $params,
                ),
            ],
            'workshop' => [
                'title' => 'Reporte de Taller',
                'columns' => $this->cols(['Orden', 'Ingreso', 'Cliente', 'Motocicleta', 'Mecánico', 'Estado', 'Entregada', 'Total']),
                'rows' => $this->db->fetchAllNumeric(
                    "SELECT o.order_number, o.entry_date, c.name,
                            COALESCE(o.motorcycle_description, 'Unidad propia #' || o.motorcycle_unit_id),
                            o.mechanic_name, o.status, o.delivered_at,
                            COALESCE((SELECT SUM(i.line_total) FROM service_order_items i WHERE i.service_order_id = o.id), 0)
                     FROM service_orders o JOIN customers c ON c.id = o.customer_id
                     WHERE o.entry_date BETWEEN :from AND :to
                     ORDER BY o.entry_date, o.id", $params,
                ),
            ],
            'documents' => [
                'title' => 'Reporte de Comprobantes SUNAT',
                'columns' => $this->cols(['Comprobante', 'Tipo', 'Emisión', 'Cliente', 'Documento', 'Productos', 'Total', 'Estado SUNAT']),
                'rows' => $this->db->fetchAllNumeric(
                    "SELECT CONCAT(d.series, '-', LPAD(d.correlative::text, 8, '0')),
                            CASE d.doc_type WHEN '01' THEN 'FACTURA' WHEN '03' THEN 'BOLETA' ELSE d.doc_type END,
                            d.issue_date, d.customer_name, CONCAT(d.customer_doc_type, ' ', d.customer_doc_number),
                            {$this->saleProductsSql('d.sale_id')},
                            d.total, d.status
                     FROM electronic_documents d
                     WHERE d.issue_date BETWEEN :from AND :to
                     ORDER BY d.issue_date, d.id", $params,
                ),
            ],
            'customers' => [
                'title' => 'Reporte de Clientes',
                'columns' => $this->cols(['Documento', 'Nombre/Razón Social', 'Celular', 'Correo', 'Registrado', 'Compras (S/)', 'Nº Ventas']),
                'rows' => $this->db->fetchAllNumeric(
                    "SELECT CONCAT(c.document_type, ' ', c.document_number), c.name, c.mobile, c.email, c.created_at::date,
                            COALESCE((SELECT SUM(s.total) FROM sales s WHERE s.customer_id = c.id AND s.status = 'COMPLETADA'), 0),
                            (SELECT COUNT(*) FROM sales s WHERE s.customer_id = c.id AND s.status = 'COMPLETADA')
                     FROM customers c WHERE c.deleted_at IS NULL AND c.created_at::date BETWEEN :from AND :to
                     ORDER BY c.name", $params,
                ),
            ],
            'suppliers' => [
                'title' => 'Reporte de Proveedores',
                'columns' => $this->cols(['RUC', 'Razón Social', 'Contacto', 'Teléfono', 'Compras (S/)', 'Nº Compras']),
                'rows' => $this->db->fetchAllNumeric(
                    "SELECT sp.ruc, sp.business_name, sp.contact_person, sp.phone,
                            COALESCE((SELECT SUM(p.total) FROM purchases p WHERE p.supplier_id = sp.id AND p.status = 'REGISTRADA' AND p.purchase_date BETWEEN :from AND :to), 0),
                            (SELECT COUNT(*) FROM purchases p WHERE p.supplier_id = sp.id AND p.status = 'REGISTRADA' AND p.purchase_date BETWEEN :from AND :to)
                     FROM suppliers sp WHERE sp.deleted_at IS NULL
                     ORDER BY sp.business_name", $params,
                ),
            ],
            'motorcycles' => [
                'title' => 'Reporte de Motocicletas (unidades)',
                'columns' => $this->cols(['Código', 'VIN', 'Modelo', 'Color', 'Ingreso', 'P. Compra', 'P. Venta', 'Estado']),
                'rows' => $this->db->fetchAllNumeric(
                    "SELECT u.internal_code, u.vin, CONCAT(b.name, ' ', m.model, ' ', m.model_year), u.color,
                            u.entry_date, u.purchase_price, u.sale_price, u.status
                     FROM motorcycle_units u
                     JOIN motorcycle_models m ON m.id = u.model_id
                     JOIN catalog_items b ON b.id = m.brand_id
                     WHERE u.deleted_at IS NULL AND u.entry_date BETWEEN :from AND :to
                     ORDER BY u.entry_date, u.internal_code", $params,
                ),
            ],
            'inventory' => [
                'title' => 'Inventario Valorizado de Repuestos (a la fecha)',
                'columns' => $this->cols(['Código', 'Descripción', 'Categoría', 'Stock', 'Mínimo', 'Costo Unit.', 'Valorizado (S/)', 'Ubicación']),
                'rows' => $this->db->fetchAllNumeric(
                    "SELECT sp.internal_code, sp.description, c.name, sp.stock, sp.min_stock,
                            sp.purchase_price, ROUND(sp.stock * COALESCE(sp.purchase_price, 0), 2), sp.location
                     FROM spare_parts sp LEFT JOIN catalog_items c ON c.id = sp.category_id
                     WHERE sp.deleted_at IS NULL
                     ORDER BY sp.description",
                ),
            ],
            'utilities' => [
                'title' => 'Reporte de Utilidades (aprox. sobre costo actual)',
                'columns' => $this->cols(['Venta', 'Fecha', 'Cliente', 'Productos', 'Subtotal', 'Costo estimado', 'Utilidad', 'Margen %']),
                'rows' => $this->db->fetchAllNumeric(
                    "SELECT s.sale_number, s.sale_date, c.name, {$this->saleProductsSql('s.id')}, s.subtotal,
                            ROUND(cost.total_cost, 2),
                            ROUND(s.subtotal - cost.total_cost, 2),
                            CASE WHEN s.subtotal > 0 THEN ROUND((s.subtotal - cost.total_cost) / s.subtotal * 100, 1) ELSE 0 END
                     FROM sales s
                     JOIN customers c ON c.id = s.customer_id
                     JOIN LATERAL (
                        SELECT COALESCE(SUM(
                            CASE i.item_type
                                WHEN 'SPARE_PART' THEN COALESCE(sp.purchase_price, 0) * i.quantity
                                WHEN 'MOTORCYCLE_UNIT' THEN COALESCE(mu.purchase_price, 0)
                                ELSE 0
                            END), 0) AS total_cost
                        FROM sale_items i
                        LEFT JOIN spare_parts sp ON sp.id = i.spare_part_id
                        LEFT JOIN motorcycle_units mu ON mu.id = i.motorcycle_unit_id
                        WHERE i.sale_id = s.id
                     ) cost ON true
                     WHERE s.status = 'COMPLETADA' AND s.sale_date BETWEEN :from AND :to
                     ORDER BY s.sale_date, s.id", $params,
                ),
            ],
            'audit' => [
                'title' => 'Reporte de Auditoría',
                'columns' => $this->cols(['Fecha/Hora', 'Usuario', 'IP', 'Módulo', 'Acción', 'Entidad', 'Registro']),
                'rows' => $this->db->fetchAllNumeric(
                    "SELECT a.created_at, a.username, a.ip_address, a.module, a.action,
                            REVERSE(SPLIT_PART(REVERSE(a.entity_class), '\\', 1)), a.entity_id
                     FROM audit_logs a
                     WHERE a.created_at::date BETWEEN :from AND :to
                     ORDER BY a.created_at DESC LIMIT 2000", $params,
                ),
            ],
        };
    }

    /** @param list<string> $labels */
    private function cols(array $labels): array
    {
        return array_map(static fn (string $label) => ['label' => $label], $labels);
    }

    /**
     * Subconsulta que lista los productos/motos vendidos en una venta, concatenados.
     * Ej.: "2x ESPEJO COMPLETO | 1x Yamaha XTZ150 (VIN MH3...)". Para motos toma la
     * primera línea de la descripción (el modelo) y agrega el VIN.
     *
     * @param string $saleIdExpr expresión SQL con el id de la venta (ej. 's.id', 'd.sale_id')
     */
    private function saleProductsSql(string $saleIdExpr): string
    {
        return "COALESCE((SELECT STRING_AGG(
                    CASE WHEN i.item_type = 'MOTORCYCLE_UNIT'
                         THEN CONCAT(i.quantity, 'x ', SPLIT_PART(i.description, CHR(10), 1), ' (VIN ', COALESCE(mu.vin, ''), ')')
                         ELSE CONCAT(i.quantity, 'x ', SPLIT_PART(i.description, CHR(10), 1)) END, ' | ' ORDER BY i.id)
                 FROM sale_items i
                 LEFT JOIN motorcycle_units mu ON mu.id = i.motorcycle_unit_id
                 WHERE i.sale_id = {$saleIdExpr}), '')";
    }
}
