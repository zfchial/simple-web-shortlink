<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Set JSON response headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    // Get JSON data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['code']) || !isset($data['filename'])) {
        throw new Exception('Missing required data');
    }

    // Initialize database
    $dbFile = __DIR__ . '/data/db.sqlite';
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if code already exists
    $stmt = $pdo->prepare("SELECT code FROM links WHERE code = ?");
    $stmt->execute([$data['code']]);
    if ($stmt->fetch()) {
        // Generate new code if exists
        do {
            $data['code'] = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 6);
            $stmt->execute([$data['code']]);
        } while ($stmt->fetch());
    }

    // Start transaction
    $pdo->beginTransaction();

    // Insert into links table
    $stmt = $pdo->prepare("INSERT INTO links (code, target, created_at, type, user_id) VALUES (?, NULL, ?, 'file', ?)");
    $stmt->execute([$data['code'], time(), $_SESSION['user_id']]);

    // Insert into files table with updated code
    $stmt = $pdo->prepare("INSERT INTO files (code, filename, stored_name, size, mime, uploaded_at) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['code'],
        $data['filename'],
        $data['code'] . '_' . $data['filename'],
        $data['size'],
        $data['mime'],
        time() // Add current timestamp for uploaded_at
    ]);

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'code' => $data['code']
    ]);

} catch (Exception $e) {
    // Rollback on error
    if (isset($pdo)) {
        $pdo->rollBack();
    }

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}