<?php

declare(strict_types=1);

namespace App\Module\Purchasing\Service;

use Psr\Log\LoggerInterface;

/**
 * Extrae del PDF de la factura de Yamaha el DUA e Ítem DUA de cada unidad,
 * indexados por VIN. El XML no trae el DUA, pero el PDF sí lo imprime por
 * línea (Serie/VIN | Motor | … | DUA | Item DUA). Usa pdftotext (poppler).
 */
final class PdfDuaExtractor
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    /**
     * @return array<string, array{dua: string, item: string}> mapa VIN => DUA/Ítem
     */
    public function extractByVin(string $pdfBytes): array
    {
        if ($pdfBytes === '' || !\function_exists('exec')) {
            return [];
        }

        $pdf = tempnam(sys_get_temp_dir(), 'dua_').'.pdf';
        $out = $pdf.'.txt';
        file_put_contents($pdf, $pdfBytes);

        $text = '';
        try {
            @exec('pdftotext -layout '.escapeshellarg($pdf).' '.escapeshellarg($out).' 2>/dev/null', $o, $code);
            if (is_file($out)) {
                $text = (string) file_get_contents($out);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('PDF DUA: fallo al ejecutar pdftotext', ['error' => $e->getMessage()]);
        } finally {
            @unlink($pdf);
            @unlink($out);
        }

        if (trim($text) === '') {
            return [];
        }

        // Aplana saltos de línea (el DUA se parte por el ajuste de línea del PDF).
        $flat = (string) preg_replace('/\s+/', ' ', $text);

        $map = [];
        $pattern = '/Serie:\s*(.+?)\s*\|\s*Motor.*?DUA\s*:\s*(.+?)\s*\|\s*Item\s*DUA\s*:\s*(\d+)/i';
        if (preg_match_all($pattern, $flat, $matches, \PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $vin = strtoupper((string) preg_replace('/\s/', '', $m[1]));
                if ($vin === '') {
                    continue;
                }
                $map[$vin] = [
                    'dua' => (string) preg_replace('/\s/', '', $m[2]),
                    'item' => trim($m[3]),
                ];
            }
        }

        return $map;
    }
}
