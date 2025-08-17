<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Inisialisasi DB
$dbFile = __DIR__ . '/data/db.sqlite';
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Ambil data JSON yang dikirim
$data = json_decode(file_get_contents('php://input'), true);
$code = $data['code'] ?? '';
$type = $data['type'] ?? '';

try {
    // Mulai transaksi
    $pdo->beginTransaction();
    
    // Jika tipe file, hapus file fisiknya
    if ($type === 'file') {
        // Ambil nama file yang tersimpan
        $stmt = $pdo->prepare("SELECT stored_name FROM files WHERE code = ?");
        $stmt->execute([$code]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($file) {
            $filepath = __DIR__ . '/uploads/' . $file['stored_name'];
            if (file_exists($filepath)) {
                unlink($filepath);
            }
        }
        
        // Hapus dari tabel files
        $stmt = $pdo->prepare("DELETE FROM files WHERE code = ?");
        $stmt->execute([$code]);
    }
    
    // Hapus dari tabel links
    $stmt = $pdo->prepare("DELETE FROM links WHERE code = ?");
    $stmt->execute([$code]);
    
    // Commit transaksi
    $pdo->commit();
    
    http_response_code(200);
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    // Rollback jika ada error
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}