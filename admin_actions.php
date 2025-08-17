<?php
session_start();
header('Content-Type: application/json');

// Check if user is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$dbFile = __DIR__ . '/data/db.sqlite';
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

try {
    switch ($action) {
        case 'delete_user':
            $userId = (int)$data['user_id'];
            
            // Start transaction
            $pdo->beginTransaction();
            
            // Get and delete user's files
            $stmt = $pdo->prepare("SELECT f.stored_name 
                                 FROM files f 
                                 JOIN links l ON f.code = l.code 
                                 WHERE l.user_id = ?");
            $stmt->execute([$userId]);
            
            while ($file = $stmt->fetch()) {
                $filepath = __DIR__ . '/uploads/' . $file['stored_name'];
                if (file_exists($filepath)) {
                    unlink($filepath);
                }
            }
            
            // Delete user's links
            $stmt = $pdo->prepare("DELETE FROM links WHERE user_id = ?");
            $stmt->execute([$userId]);
            
            // Delete user
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND is_admin = 0");
            $stmt->execute([$userId]);
            
            $pdo->commit();
            echo json_encode(['success' => true]);
            break;

        case 'delete_link':
            $code = $data['code'];
            
            // Start transaction
            $pdo->beginTransaction();
            
            // Check if it's a file and delete physical file
            $stmt = $pdo->prepare("SELECT stored_name FROM files WHERE code = ?");
            $stmt->execute([$code]);
            $file = $stmt->fetch();
            
            if ($file) {
                $filepath = __DIR__ . '/uploads/' . $file['stored_name'];
                if (file_exists($filepath)) {
                    unlink($filepath);
                }
                
                // Delete file record
                $stmt = $pdo->prepare("DELETE FROM files WHERE code = ?");
                $stmt->execute([$code]);
            }
            
            // Delete link record
            $stmt = $pdo->prepare("DELETE FROM links WHERE code = ?");
            $stmt->execute([$code]);
            
            $pdo->commit();
            echo json_encode(['success' => true]);
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    if (isset($pdo)) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}