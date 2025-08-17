<?php
$code = $_GET['code'] ?? '';
if (!$code) {
    header('Location: index.php');
    exit;
}

// Initialize DB
$dbFile = __DIR__ . '/data/db.sqlite';
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Get link target
$stmt = $pdo->prepare("SELECT target, type FROM links WHERE code = ?");
$stmt->execute([$code]);
$link = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$link) {
    die('Link tidak ditemukan');
}

// Update hits counter
$pdo->prepare("UPDATE links SET hits = hits + 1 WHERE code = ?")->execute([$code]);

// Redirect based on type
if ($link['type'] === 'url') {
    header('Location: ' . $link['target']);
} else {
    header('Location: f.php?c=' . $code);
}
exit;