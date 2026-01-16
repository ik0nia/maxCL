<?php
declare(strict_types=1);

namespace App\Core;

final class Upload
{
    /**
     * Salvează o imagine și generează thumbnail 256px (JPEG).
     *
     * @return array{image_url:string, thumb_url:string, image_fs:string, thumb_fs:string, ext:string}
     */
    public static function saveFinishImage(array $file): array
    {
        if (!isset($file['error']) || (int)$file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Upload eșuat. Încearcă din nou.');
        }

        $tmp = (string)$file['tmp_name'];
        if (!is_file($tmp)) {
            throw new \RuntimeException('Fișier invalid.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($tmp);
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if (!isset($allowed[$mime])) {
            throw new \RuntimeException('Format invalid. Acceptat: JPG/PNG/WEBP.');
        }
        $ext = $allowed[$mime];

        $dir = dirname(__DIR__, 2) . '/storage/uploads/finishes';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Nu pot crea folderul de upload.');
        }

        $basename = bin2hex(random_bytes(16));
        $imageName = $basename . '.' . $ext;
        $thumbName = 'thumb_' . $basename . '.jpg';

        $imageFs = $dir . '/' . $imageName;
        if (!move_uploaded_file($tmp, $imageFs)) {
            throw new \RuntimeException('Nu pot salva fișierul încărcat.');
        }

        $thumbFs = $dir . '/' . $thumbName;
        self::makeThumb256($imageFs, $mime, $thumbFs);

        // URL-urile sunt servite prin endpoint intern (/uploads/finishes/{name})
        return [
            'image_url' => Url::to('/uploads/finishes/' . $imageName),
            'thumb_url' => Url::to('/uploads/finishes/' . $thumbName),
            'image_fs' => $imageFs,
            'thumb_fs' => $thumbFs,
            'ext' => $ext,
        ];
    }

    private static function makeThumb256(string $srcFs, string $mime, string $dstFs): void
    {
        $img = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($srcFs),
            'image/png' => @imagecreatefrompng($srcFs),
            'image/webp' => @imagecreatefromwebp($srcFs),
            default => false,
        };
        if (!$img) {
            throw new \RuntimeException('Nu pot procesa imaginea.');
        }

        $w = imagesx($img);
        $h = imagesy($img);
        if ($w <= 0 || $h <= 0) {
            imagedestroy($img);
            throw new \RuntimeException('Dimensiuni imagine invalide.');
        }

        $max = 256;
        $scale = min($max / $w, $max / $h, 1.0);
        $nw = (int)max(1, floor($w * $scale));
        $nh = (int)max(1, floor($h * $scale));

        $dst = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);

        if (!imagejpeg($dst, $dstFs, 85)) {
            imagedestroy($dst);
            imagedestroy($img);
            throw new \RuntimeException('Nu pot salva thumbnail-ul.');
        }

        imagedestroy($dst);
        imagedestroy($img);
    }

    /**
     * Salvează un fișier generic (DXF/GCODE/PDF/imagini etc.) în storage/uploads/files.
     *
     * @return array{stored_name:string, original_name:string, mime:string|null, size_bytes:int|null, fs_path:string, url:string}
     */
    public static function saveEntityFile(array $file): array
    {
        if (!isset($file['error']) || (int)$file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Upload eșuat. Încearcă din nou.');
        }
        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_file($tmp)) {
            throw new \RuntimeException('Fișier invalid.');
        }

        $orig = trim((string)($file['name'] ?? ''));
        if ($orig === '') $orig = 'fisier';
        $orig = preg_replace('/[^\w\.\-\(\)\[\]\s]+/u', '_', $orig) ?? $orig;
        $orig = mb_substr($orig, 0, 180);

        $size = isset($file['size']) && is_numeric($file['size']) ? (int)$file['size'] : null;
        if ($size !== null && $size > 100 * 1024 * 1024) {
            throw new \RuntimeException('Fișier prea mare (max 100MB).');
        }

        $mime = null;
        try {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = (string)$finfo->file($tmp);
        } catch (\Throwable $e) {
            $mime = null;
        }

        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if ($ext === '') $ext = 'bin';
        if (!preg_match('/^[a-z0-9]{1,8}$/', $ext)) $ext = 'bin';

        $dir = dirname(__DIR__, 2) . '/storage/uploads/files';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Nu pot crea folderul de upload.');
        }

        $basename = bin2hex(random_bytes(16));
        $stored = $basename . '.' . $ext;
        $fs = $dir . '/' . $stored;
        if (!move_uploaded_file($tmp, $fs)) {
            throw new \RuntimeException('Nu pot salva fișierul încărcat.');
        }

        return [
            'stored_name' => $stored,
            'original_name' => $orig,
            'mime' => $mime ?: null,
            'size_bytes' => $size,
            'fs_path' => $fs,
            'url' => Url::to('/uploads/files/' . $stored),
        ];
    }
}

