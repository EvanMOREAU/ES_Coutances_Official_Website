<?php

namespace App\Service\Upload;

/**
 * Réencode une image envoyée par un utilisateur : les métadonnées (position GPS, appareil, miniature EXIF) et tout
 * contenu greffé après les données de l'image disparaissent. L'orientation des photos de téléphone est appliquée
 * avant de retirer l'EXIF. Sans l'extension PHP « exif », un JPEG est laissé tel quel (le réencoder le ferait pivoter).
 * Les GIF (animations) ne sont pas touchés. Toute erreur laisse le fichier d'origine en place.
 */
final class ImageCleaner
{
    private const MAX_PIXELS = 40_000_000;

    public function clean(string $path): bool
    {
        if (!is_file($path) || !\extension_loaded('gd')) {
            return false;
        }

        $info = @getimagesize($path);
        if (false === $info || $info[0] * $info[1] > self::MAX_PIXELS) {
            return false;
        }

        try {
            return match ($info[2]) {
                \IMAGETYPE_JPEG => $this->cleanJpeg($path),
                \IMAGETYPE_PNG  => $this->rewrite($path, @imagecreatefrompng($path), static fn ($im, $p) => imagepng($im, $p, 6), true),
                \IMAGETYPE_WEBP => $this->rewrite($path, @imagecreatefromwebp($path), static fn ($im, $p) => imagewebp($im, $p, 88), true),
                default         => false,
            };
        } catch (\Throwable) {
            return false;
        }
    }

    private function cleanJpeg(string $path): bool
    {
        if (!\function_exists('exif_read_data')) {
            return false;
        }

        $image = @imagecreatefromjpeg($path);
        if (false === $image) {
            return false;
        }

        $exif        = @exif_read_data($path);
        $orientation = \is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
        if (0 !== $angle) {
            $rotated = imagerotate($image, $angle, 0);
            if (false !== $rotated) {
                $image = $rotated;
            }
        }

        return $this->rewrite($path, $image, static fn ($im, $p) => imagejpeg($im, $p, 88), false);
    }

    /** @param \GdImage|false $image */
    private function rewrite(string $path, mixed $image, callable $encode, bool $alpha): bool
    {
        if (!$image instanceof \GdImage) {
            return false;
        }
        if ($alpha) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        // Écriture dans un fichier voisin puis remplacement : jamais d'image tronquée en cas d'erreur.
        $temporary = $path.'.clean';
        if (!$encode($image, $temporary) || !is_file($temporary) || 0 === filesize($temporary)) {
            @unlink($temporary);

            return false;
        }

        return rename($temporary, $path);
    }
}
