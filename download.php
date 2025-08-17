<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$code = $_GET['code'] ?? '';
$isAdmin = isset($_GET['admin']) && $_SESSION['is_admin'];

if (!$code) {
    die('No file specified');
}

// Initialize DB
$dbFile = __DIR__ . '/data/db.sqlite';
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Get file info
$stmt = $pdo->prepare("
    SELECT f.*, l.user_id 
    FROM files f 
    JOIN links l ON f.code = l.code 
    WHERE f.code = ?
");
$stmt->execute([$code]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if file exists and user has permission
if (!$file || (!$isAdmin && $file['user_id'] != $_SESSION['user_id'])) {
    die('File not found or access denied');
}

$filepath = __DIR__ . '/uploads/' . $file['stored_name'];

if (!file_exists($filepath)) {
    die('File not found on server');
}

// Prevent XSS in filename
$filename = htmlspecialchars_decode($file['filename']);
$filename = str_replace(['"', "'", '\\', '/'], '', $filename);

// Set headers for download
header('Content-Type: ' . ($file['mime'] ?: 'application/octet-stream'));
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Output file in chunks to handle large files
$handle = fopen($filepath, 'rb');
while (!feof($handle)) {
    echo fread($handle, 8192);
    flush();
}
fclose($handle);

// Update download count
$stmt = $pdo->prepare("UPDATE links SET hits = hits + 1 WHERE code = ?");
$stmt->execute([$code]);