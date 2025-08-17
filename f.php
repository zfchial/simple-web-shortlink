<?php
$dbFile = __DIR__ . '/data/db.sqlite';
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$code = preg_replace('/[^0-9A-Za-z]/', '', $_GET['c'] ?? '');
if (!$code) {
    http_response_code(400);
    echo "Kode tidak valid.";
    exit;
}
$stmt = $pdo->prepare("SELECT f.filename, f.stored_name, f.size, f.mime FROM files f JOIN links l ON f.code = l.code WHERE f.code = :c AND l.type='file'");
$stmt->execute([':c' => $code]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    http_response_code(404);
    echo "File tidak ditemukan.";
    exit;
}
$stored = __DIR__ . '/uploads/' . $row['stored_name'];
if (!file_exists($stored)) {
    http_response_code(410);
    echo "File sudah hilang.";
    exit;
}
$update = $pdo->prepare("UPDATE links SET hits = hits + 1 WHERE code = :c");
$update->execute([':c' => $code]);
$filename = $row['filename'];
$mime = $row['mime'] ?: 'application/octet-stream';
header('Content-Description: File Transfer');
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
header('Content-Length: ' . filesize($stored));
header('Cache-Control: public, must-revalidate');
readfile($stored);
exit;
