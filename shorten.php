<?php
session_start(); // Add this at the very top

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// inisialisasi SQLite dan helper dari awal
function generate_code($length = 6) {
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $max = strlen($chars) - 1;
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, $max)];
    }
    return $code;
}

function normalize_url($url) {
    $url = trim($url);
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }
    return filter_var($url, FILTER_VALIDATE_URL) ? $url : false;
}

function base_url() {
    $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    return $scheme . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/';
}

// Database initialization
$dbFile = __DIR__ . '/data/db.sqlite';
if (!is_dir(__DIR__.'/data')) mkdir(__DIR__.'/data', 0755, true);
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create tables if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS links (
    code TEXT PRIMARY KEY,
    target TEXT,
    created_at INTEGER NOT NULL,
    hits INTEGER NOT NULL DEFAULT 0,
    type TEXT NOT NULL
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS files (
    code TEXT PRIMARY KEY,
    filename TEXT NOT NULL,
    stored_name TEXT NOT NULL,
    size INTEGER NOT NULL,
    mime TEXT,
    FOREIGN KEY(code) REFERENCES links(code) ON DELETE CASCADE
)");

// Process URL shortening
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = $_POST['url'] ?? '';
    $target = normalize_url($raw);
    
    if (!$target) {
        die("URL tidak valid. Kembali dan periksa kembali.");
    }

    // Check if URL already exists
    $stmt = $pdo->prepare("SELECT code FROM links WHERE target = :t AND type='url' LIMIT 1");
    $stmt->execute([':t' => $target]);
    $existing = $stmt->fetchColumn();

    if ($existing) {
        header("Location: index.php?created=" . urlencode($existing));
        exit;
    }

    // Generate unique code
    do {
        $code = generate_code(6);
        $check = $pdo->prepare("SELECT 1 FROM links WHERE code = :c");
        $check->execute([':c' => $code]);
        $exists = $check->fetchColumn();
    } while ($exists);

    // Insert new short URL - modify the existing insert statement
    $stmt = $pdo->prepare("INSERT INTO links (code, target, created_at, type, user_id) VALUES (:c, :t, :ca, 'url', :uid)");
    $stmt->execute([
        ':c' => $code,
        ':t' => $target,
        ':ca' => time(),
        ':uid' => $_SESSION['user_id'] // Add the user_id from session
    ]);

    header("Location: index.php?created=" . urlencode($code));
    exit;
}

// If not POST request, redirect to index
header("Location: index.php");
exit;
