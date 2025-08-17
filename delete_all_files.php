<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];

$dbFile = __DIR__ . '/data/db.sqlite';
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $pdo->beginTransaction();

    // Pilih file yang akan dihapus
    if ($isAdmin) {
        $stmt = $pdo->query("SELECT stored_name FROM files");
    } else {
        $stmt = $pdo->prepare("SELECT f.stored_name FROM files f JOIN links l ON f.code = l.code WHERE l.user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    }

    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Hapus file fisik
    foreach ($files as $file) {
        $filepath = __DIR__ . '/uploads/' . $file['stored_name'];
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }

    // Hapus dari database
    if ($isAdmin) {
        $pdo->exec("DELETE FROM files");
        $pdo->exec("DELETE FROM links WHERE type = 'file'");
    } else {
        $stmt = $pdo->prepare("DELETE FROM files WHERE code IN (SELECT code FROM links WHERE user_id = ? AND type = 'file')");
        $stmt->execute([$_SESSION['user_id']]);
        $stmt = $pdo->prepare("DELETE FROM links WHERE user_id = ? AND type = 'file'");
        $stmt->execute([$_SESSION['user_id']]);
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}