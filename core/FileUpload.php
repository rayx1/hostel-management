<?php

class FileUpload
{
    public static function save(array $file, string $targetDir, array $allowedMime, int $maxSize = 2097152): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed.');
        }

        if (($file['size'] ?? 0) > $maxSize) {
            throw new RuntimeException('File must be less than 2MB.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowedMime, true)) {
            throw new RuntimeException('Invalid file type uploaded.');
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $name = uniqid('file_', true) . '.' . $ext;
        $fullPath = BASE_PATH . '/' . trim($targetDir, '/') . '/' . $name;

        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            throw new RuntimeException('Unable to save upload.');
        }

        return trim($targetDir, '/') . '/' . $name;
    }
}

