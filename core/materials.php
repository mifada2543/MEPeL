<?php

const MATERIALS_UPLOAD_DIR_REL = 'uploads/materi';
const MATERIALS_MAX_SIZE_BYTES = 10485760; // 10 MB

function ensure_materials_table(PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS level_materials (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            category VARCHAR(50) NOT NULL,
            level INT NOT NULL,
            pdf_filename VARCHAR(255) NOT NULL,
            pdf_path VARCHAR(255) NOT NULL,
            uploaded_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_category_level (category, level),
            INDEX idx_uploaded_by (uploaded_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function materials_storage_dir(): string {
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . MATERIALS_UPLOAD_DIR_REL;
}

function ensure_materials_storage_dir(): string {
    $dir = materials_storage_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function materials_relative_path(string $storedName): string {
    return MATERIALS_UPLOAD_DIR_REL . '/' . $storedName;
}

function materials_full_path(string $relativePath): string {
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . ltrim($relativePath, '/');
}

function sanitize_uploaded_pdf_name(string $name): string {
    return trim(basename($name));
}

function materials_is_valid_pdf_upload(array $file, ?string &$error = null): bool {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $error = 'Upload file gagal. Pastikan PDF dipilih dengan benar.';
        return false;
    }

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > MATERIALS_MAX_SIZE_BYTES) {
        $error = 'Ukuran PDF harus antara 1 byte sampai 10 MB.';
        return false;
    }

    $originalName = (string)($file['name'] ?? '');
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        $error = 'File harus berformat .pdf';
        return false;
    }

    $tmpPath = (string)($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        $error = 'File upload tidak valid.';
        return false;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($tmpPath) ?: '';
    $allowedMimes = ['application/pdf', 'application/x-pdf'];

    if (!in_array($mime, $allowedMimes, true)) {
        $error = 'MIME file tidak valid. Hanya PDF yang diizinkan.';
        return false;
    }

    return true;
}

function generate_stored_pdf_name(string $category, int $level): string {
    $slug = preg_replace('/[^a-z0-9_\-]/i', '_', $category) ?: 'cat';
    $rand = bin2hex(random_bytes(8));
    return sprintf('%s_lvl_%d_%s.pdf', $slug, $level, $rand);
}

function safe_unlink_material_file(?string $relativePath): void {
    if (!$relativePath) return;

    $fullPath = materials_full_path($relativePath);
    $baseDir  = realpath(materials_storage_dir());
    $realFile = realpath($fullPath);

    if (!$baseDir || !$realFile) return;
    if (!str_starts_with($realFile, $baseDir . DIRECTORY_SEPARATOR)) return;
    if (is_file($realFile)) {
        @unlink($realFile);
    }
}
