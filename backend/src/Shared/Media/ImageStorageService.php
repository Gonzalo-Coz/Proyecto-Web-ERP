<?php

declare(strict_types=1);

namespace App\Shared\Media;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Servicio transversal de almacenamiento y optimización de imágenes
 * (§Almacenamiento y §Gestión de imágenes del Documento Maestro).
 *
 * Reglas del Maestro que implementa:
 *   - Las imágenes NUNCA se guardan en la base de datos, solo su ruta.
 *   - Se optimizan automáticamente (redimensión/recorte según preset) cuando
 *     la extensión GD está disponible; si no, se conserva el original validado.
 *   - Cada tipo de imagen tiene dimensiones recomendadas (ver ImagePreset),
 *     por lo que nunca se deforman.
 *
 * Portabilidad (§Infraestructura futura): la ruta base se deriva de
 * kernel.project_dir; los ficheros viven en public/uploads/<categoría>, que
 * el servidor web sirve directamente y que los respaldos pueden copiar.
 */
final class ImageStorageService
{
    /** Tamaño máximo aceptado del fichero subido. */
    private const MAX_BYTES = 5 * 1024 * 1024;

    /** Tipos MIME admitidos. */
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];

    private string $uploadsDir;

    public function __construct(
        #[Autowire('%kernel.project_dir%')] string $projectDir,
    ) {
        $this->uploadsDir = rtrim($projectDir, '/\\').'/public/uploads';
    }

    /**
     * Valida, optimiza y almacena la imagen. Devuelve la ruta RELATIVA
     * (p. ej. "uploads/avatars/ab/cd/<hash>.webp") que debe persistirse en BD.
     */
    public function store(UploadedFile $file, ImagePreset $preset): string
    {
        $this->assertValid($file);

        [$maxW, $maxH] = $preset->dimensions();
        $category = $preset->category();
        $token = bin2hex(random_bytes(16));
        $shardA = substr($token, 0, 2);
        $shardB = substr($token, 2, 2);
        $absDir = sprintf('%s/%s/%s/%s', $this->uploadsDir, $category, $shardA, $shardB);

        if (!is_dir($absDir) && !mkdir($absDir, 0775, true) && !is_dir($absDir)) {
            throw new UnprocessableEntityHttpException('No se pudo preparar el almacenamiento de imágenes.');
        }

        // Optimización con GD si está disponible; si no, se guarda el original.
        if (\function_exists('imagecreatetruecolor')) {
            $ext = \function_exists('imagewebp') ? 'webp' : 'jpg';
            $filename = $token.'.'.$ext;
            $this->optimize($file->getPathname(), $absDir.'/'.$filename, $maxW, $maxH, $preset->fit(), $ext);
        } else {
            $ext = $this->extensionForMime($file->getMimeType() ?? '');
            $filename = $token.'.'.$ext;
            $file->move($absDir, $filename);
        }

        return sprintf('uploads/%s/%s/%s/%s', $category, $shardA, $shardB, $filename);
    }

    /** Elimina una imagen previa (solo dentro de public/uploads; ignora null/externas). */
    public function delete(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '' || !str_starts_with($relativePath, 'uploads/')) {
            return;
        }
        if (str_contains($relativePath, '..')) {
            return; // defensa ante path traversal
        }
        $abs = $this->uploadsDir.'/'.substr($relativePath, \strlen('uploads/'));
        if (is_file($abs)) {
            @unlink($abs);
        }
    }

    /** Convierte la ruta relativa almacenada en URL pública (o null). */
    public function publicUrl(?string $relativePath): ?string
    {
        return ($relativePath === null || $relativePath === '') ? null : '/'.ltrim($relativePath, '/');
    }

    private function assertValid(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw new UnprocessableEntityHttpException('El archivo no se subió correctamente.');
        }
        if ($file->getSize() > self::MAX_BYTES) {
            throw new UnprocessableEntityHttpException('La imagen supera el tamaño máximo de 5 MB.');
        }
        $mime = $file->getMimeType() ?? '';
        if (!\in_array($mime, self::ALLOWED_MIME, true)) {
            throw new UnprocessableEntityHttpException('Formato no admitido. Use JPG, PNG o WEBP.');
        }
        if (@getimagesize($file->getPathname()) === false) {
            throw new UnprocessableEntityHttpException('El archivo no es una imagen válida.');
        }
    }

    private function extensionForMime(string $mime): string
    {
        return match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }

    /**
     * Redimensiona (y recorta si el preset es COVER) con GD, exportando al
     * formato indicado. Nunca amplía por encima del tamaño original.
     */
    private function optimize(string $src, string $dest, int $maxW, int $maxH, ImageFit $fit, string $ext): void
    {
        [$sw, $sh, $type] = getimagesize($src);
        $source = match ($type) {
            \IMAGETYPE_JPEG => imagecreatefromjpeg($src),
            \IMAGETYPE_PNG => imagecreatefrompng($src),
            \IMAGETYPE_WEBP => \function_exists('imagecreatefromwebp') ? imagecreatefromwebp($src) : null,
            default => null,
        };
        if ($source === false || $source === null) {
            throw new UnprocessableEntityHttpException('No se pudo procesar la imagen.');
        }

        if ($fit === ImageFit::COVER) {
            // Marco de relación fija maxW:maxH: escala para cubrir y recorta al centro.
            // Nunca amplía: si cubrir exigiría agrandar, el marco se reduce de forma
            // proporcional (conservando la relación de aspecto, p. ej. avatar cuadrado).
            $coverScale = max($maxW / $sw, $maxH / $sh);
            $frameScale = min(1.0, 1.0 / $coverScale);
            $tw = max(1, (int) round($maxW * $frameScale));
            $th = max(1, (int) round($maxH * $frameScale));
            $srcRatio = $sw / $sh;
            $dstRatio = $tw / $th;
            if ($srcRatio > $dstRatio) {
                $cropH = $sh;
                $cropW = (int) round($sh * $dstRatio);
                $cropX = (int) round(($sw - $cropW) / 2);
                $cropY = 0;
            } else {
                $cropW = $sw;
                $cropH = (int) round($sw / $dstRatio);
                $cropX = 0;
                $cropY = (int) round(($sh - $cropH) / 2);
            }
            $canvas = imagecreatetruecolor($tw, $th);
            $this->preserveTransparency($canvas, $ext);
            imagecopyresampled($canvas, $source, 0, 0, $cropX, $cropY, $tw, $th, $cropW, $cropH);
        } else {
            // CONTAIN: escala para caber dentro del marco, sin ampliar.
            $scale = min($maxW / $sw, $maxH / $sh, 1.0);
            $tw = max(1, (int) round($sw * $scale));
            $th = max(1, (int) round($sh * $scale));
            $canvas = imagecreatetruecolor($tw, $th);
            $this->preserveTransparency($canvas, $ext);
            imagecopyresampled($canvas, $source, 0, 0, 0, 0, $tw, $th, $sw, $sh);
        }

        if ($ext === 'webp') {
            imagewebp($canvas, $dest, 82);
        } else {
            imagejpeg($canvas, $dest, 85);
        }
        imagedestroy($canvas);
        imagedestroy($source);
    }

    /** Conserva el canal alfa (webp) o rellena en blanco (jpeg). */
    private function preserveTransparency(\GdImage $canvas, string $ext): void
    {
        if ($ext === 'webp') {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefilledrectangle($canvas, 0, 0, imagesx($canvas), imagesy($canvas), $transparent);
            imagealphablending($canvas, true);
        } else {
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefilledrectangle($canvas, 0, 0, imagesx($canvas), imagesy($canvas), $white);
        }
    }
}
