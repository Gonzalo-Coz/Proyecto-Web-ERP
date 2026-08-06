<?php

declare(strict_types=1);

namespace App\Shared\Import;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Utilidades compartidas para la carga masiva por CSV/Excel.
 *
 * Genera plantillas (UTF-8 con BOM, delimitador ';' para Excel en español) y lee
 * archivos subidos autodetectando el delimitador y normalizando cabeceras/valores.
 * Reutilizado por los importadores de Repuestos, Clientes y Unidades de moto.
 */
final class CsvImportReader
{
    /**
     * Construye el contenido de una plantilla CSV.
     *
     * @param list<string>      $headers    etiquetas de las columnas
     * @param list<string>|null $exampleRow fila de ejemplo opcional
     */
    public function buildTemplate(array $headers, ?array $exampleRow = null): string
    {
        $bom = "\xEF\xBB\xBF";
        $out = implode(';', $headers)."\r\n";
        if ($exampleRow !== null) {
            $out .= implode(';', $exampleRow)."\r\n";
        }

        return $bom.$out;
    }

    /**
     * Lee el archivo como matriz de celdas (incluida la cabecera como primera fila).
     *
     * @return list<list<string>>
     */
    public function readRows(UploadedFile $file): array
    {
        $content = (string) file_get_contents($file->getPathname());
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content; // quitar BOM
        $content = str_replace(["\r\n", "\r"], "\n", $content);               // normalizar saltos

        $firstLine = strtok($content, "\n") ?: '';
        $delimiter = $this->detectDelimiter($firstLine);

        $rows = [];
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        while (($cells = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            if ($cells === [null]) {
                continue; // línea vacía
            }
            $rows[] = array_map(static fn ($c) => (string) ($c ?? ''), $cells);
        }
        fclose($handle);

        return $rows;
    }

    public function detectDelimiter(string $line): string
    {
        $candidates = [';' => substr_count($line, ';'), ',' => substr_count($line, ','), "\t" => substr_count($line, "\t")];
        arsort($candidates);
        $best = array_key_first($candidates);

        return ($candidates[$best] ?? 0) > 0 ? (string) $best : ';';
    }

    /**
     * Mapea las cabeceras del archivo a claves internas de columna vía aliases.
     *
     * @param list<string>          $headerCells
     * @param array<string, string> $aliases     cabecera normalizada → clave de columna
     *
     * @return array<string, int> clave de columna → índice
     */
    public function mapColumns(array $headerCells, array $aliases): array
    {
        $map = [];
        foreach ($headerCells as $i => $label) {
            $key = $aliases[$this->normalize($label)] ?? null;
            if ($key !== null && !isset($map[$key])) {
                $map[$key] = $i;
            }
        }

        return $map;
    }

    public function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u']);

        return (string) preg_replace('/[^a-z0-9]/', '', $s);
    }

    public function parseInt(string $v): ?int
    {
        $v = preg_replace('/[^0-9\-]/', '', $v) ?? '';

        return $v === '' || $v === '-' ? null : (int) $v;
    }

    /** Tolera "1234.50", "1234,50", "1.234,50" y "1,234.50". */
    public function parseDecimal(string $v): ?float
    {
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        $v = (string) preg_replace('/[^0-9.,\-]/', '', $v);
        $hasDot = str_contains($v, '.');
        $hasComma = str_contains($v, ',');
        if ($hasDot && $hasComma) {
            if (strrpos($v, ',') > strrpos($v, '.')) {
                $v = str_replace(['.', ','], ['', '.'], $v);
            } else {
                $v = str_replace(',', '', $v);
            }
        } elseif ($hasComma) {
            $v = str_replace(',', '.', $v);
        }

        return is_numeric($v) ? (float) $v : null;
    }

    /** Vacío = true (activo por defecto). NO/0/FALSE/INACTIVO/N = false. */
    public function parseBool(string $v): bool
    {
        $v = $this->normalize($v);
        if ($v === '') {
            return true;
        }

        return !in_array($v, ['no', '0', 'false', 'inactivo', 'n'], true);
    }
}
