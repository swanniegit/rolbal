<?php
/**
 * File Upload Helper
 */

class Upload {
    const CLUB_ICONS_DIR = __DIR__ . '/../assets/club-icons/';
    const MAX_SIZE = 2 * 1024 * 1024; // 2MB
    const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public static function clubIcon(array $file): ?string {
        if (!self::validateImage($file)) {
            return null;
        }

        if (!is_dir(self::CLUB_ICONS_DIR)) {
            mkdir(self::CLUB_ICONS_DIR, 0755, true);
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = self::generateFilename($extension);
        $destination = self::CLUB_ICONS_DIR . $filename;

        if (move_uploaded_file($file['tmp_name'], $destination)) {
            return $filename;
        }

        return null;
    }

    public static function deleteClubIcon(string $filename): bool {
        if (empty($filename)) {
            return true;
        }

        // Security: Prevent path traversal attacks
        if (basename($filename) !== $filename) {
            return false;
        }

        $path = self::CLUB_ICONS_DIR . $filename;

        // Security: Verify the resolved path is within allowed directory
        $realPath = realpath($path);
        $realDir = realpath(self::CLUB_ICONS_DIR);
        if ($realPath === false || $realDir === false || strpos($realPath, $realDir) !== 0) {
            return false;
        }

        if (file_exists($path)) {
            return unlink($path);
        }

        return true;
    }

    public static function validateImage(array $file): bool {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }

        if ($file['size'] > self::MAX_SIZE) {
            return false;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!in_array($mimeType, self::ALLOWED_TYPES)) {
            return false;
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
            return false;
        }

        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            return false;
        }

        return true;
    }

    private static function generateFilename(string $extension): string {
        return time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    }
}
