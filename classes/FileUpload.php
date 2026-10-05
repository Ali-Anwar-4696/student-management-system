<?php

declare(strict_types=1);

class FileUpload
{
    private const ALLOWED = [
        'pdf' => ['application/pdf'],
        'txt' => ['text/plain'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'zip' => ['application/zip', 'application/x-zip-compressed', 'multipart/x-zip'],
    ];

    public static function storeAssignment(?array $file): array
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => true, 'path' => null];
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'message' => 'File upload failed.'];
        }

        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            return ['ok' => false, 'message' => 'File must be 5MB or smaller.'];
        }

        $original = (string) ($file['name'] ?? '');
        if (preg_match('/\.(php|phtml|phar|cgi|pl|exe|js|html|htm)\.?/i', $original)) {
            return ['ok' => false, 'message' => 'File type is not allowed.'];
        }

        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED[$ext])) {
            return ['ok' => false, 'message' => 'File type is not allowed.'];
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'message' => 'Invalid uploaded file.'];
        }

        $detected = '';
        if (class_exists('finfo')) {
            $detected = (string) (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        }

        if ($detected === '' || !in_array($detected, self::ALLOWED[$ext], true)) {
            return ['ok' => false, 'message' => 'File content does not match the allowed type.'];
        }

        $dir = dirname(__DIR__) . '/uploads/assignments';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return ['ok' => false, 'message' => 'Upload directory is not available.'];
        }

        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = $dir . '/' . $name;
        if (!move_uploaded_file($tmp, $dest)) {
            return ['ok' => false, 'message' => 'Could not save the uploaded file.'];
        }

        @chmod($dest, 0644);

        return ['ok' => true, 'path' => 'uploads/assignments/' . $name];
    }
}
