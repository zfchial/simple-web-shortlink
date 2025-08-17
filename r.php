<?php
$dbFile = __DIR__ . '/data/db.sqlite';
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$code = $_GET['c'] ?? '';
$code = preg_replace('/[^0-9A-Za-z]/', '', $code);
if (!$code) {
    http_response_code(400);
    echo "Kode tidak valid.";
    exit;
}
$stmt = $pdo->prepare("SELECT target FROM links WHERE code = :c");
$stmt->execute([':c' => $code]);
$target = $stmt->fetchColumn();
if (!$target) {
    http_response_code(404);
    echo "Link tidak ditemukan.";
    exit;
}
$update = $pdo->prepare("UPDATE links SET hits = hits + 1 WHERE code = :c");
$update->execute([':c' => $code]);
header("Location: " . $target, true, 302);
exit;
