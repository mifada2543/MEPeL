<?php
session_name('mepel');
session_start();
require_once '../core/db.php';
require_once '../core/materials.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo 'Akses ditolak.';
    exit;
}

$category = $_GET['cat'] ?? '';
$level    = max(1, (int)($_GET['lvl'] ?? 0));
$valid_categories = ['dasar_php', 'menengah_php', 'project_php'];

if (!in_array($category, $valid_categories, true) || $level < 1) {
    http_response_code(400);
    echo 'Parameter tidak valid.';
    exit;
}

ensure_materials_table($pdo);

$stmt = $pdo->prepare(
    "SELECT pdf_filename, pdf_path
     FROM level_materials
     WHERE category = ? AND level = ?
     LIMIT 1"
);
$stmt->execute([$category, $level]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(404);
    echo 'Materi tidak ditemukan.';
    exit;
}

$baseDir = realpath(materials_storage_dir());
$filePath = realpath(materials_full_path((string)$row['pdf_path']));

if (!$baseDir || !$filePath || !str_starts_with($filePath, $baseDir . DIRECTORY_SEPARATOR) || !is_file($filePath)) {
    http_response_code(404);
    echo 'File materi tidak tersedia.';
    exit;
}

$downloadName = sanitize_uploaded_pdf_name((string)$row['pdf_filename']);
if ($downloadName === '') {
    $downloadName = 'materi.pdf';
}

header('Content-Type: application/pdf');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: inline; filename="' . rawurlencode($downloadName) . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
