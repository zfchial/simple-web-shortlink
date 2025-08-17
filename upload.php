<?php
session_start(); // Add session start at the very top

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Set proper headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Error handling
set_error_handler(function($errno, $errstr) {
    http_response_code(500);
    echo json_encode(['error' => $errstr]);
    exit;
});

try {
    // direktori
    $uploadDir = __DIR__ . '/uploads';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $dbFile = __DIR__ . '/data/db.sqlite';
    if (!is_dir(__DIR__.'/data')) mkdir(__DIR__.'/data', 0755, true);
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // buat tabel kalau belum
    $pdo->exec("CREATE TABLE IF NOT EXISTS links (
        code TEXT PRIMARY KEY,
        target TEXT,
        created_at INTEGER NOT NULL,
        hits INTEGER NOT NULL DEFAULT 0,
        type TEXT NOT NULL,
        user_id INTEGER NOT NULL DEFAULT 1
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS files (
        code TEXT PRIMARY KEY,
        filename TEXT NOT NULL,
        stored_name TEXT NOT NULL,
        size INTEGER NOT NULL,
        mime TEXT,
        uploaded_at INTEGER NOT NULL,
        FOREIGN KEY(code) REFERENCES links(code) ON DELETE CASCADE
    )");

    // ambil data chunk
    $code = preg_replace('/[^0-9A-Za-z]/', '', $_POST['code'] ?? '');
    $filename = $_POST['filename'] ?? '';
    $index = isset($_POST['index']) ? (int)$_POST['index'] : 0;
    $total = isset($_POST['total']) ? (int)$_POST['total'] : 1;
    $size = isset($_POST['size']) ? (int)$_POST['size'] : 0;
    $mime = $_POST['mime'] ?? '';
    $chunk = $_FILES['chunk'] ?? null;

    if (!$code || !$filename || !$chunk) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Parameter hilang'
        ]);
        exit;
    }

    // buat temp folder per code
    $tmpDir = __DIR__ . "/uploads/{$code}_tmp";
    if (!is_dir($tmpDir)) mkdir($tmpDir, 0755, true);

    // simpan chunk sementara
    $partPath = "$tmpDir/part_{$index}";
    if (!move_uploaded_file($chunk['tmp_name'], $partPath)) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Gagal menyimpan chunk'
        ]);
        exit;
    }

    // jika semua chunk sudah ada, gabungkan
    $allPresent = true;
    for ($i = 0; $i < $total; $i++) {
        if (!file_exists("$tmpDir/part_{$i}")) {
            $allPresent = false;
            break;
        }
    }

    if ($allPresent) {
        // buat code unik (cek conflict)
        $stmt = $pdo->prepare("SELECT 1 FROM links WHERE code = :c");
        $stmt->execute([':c' => $code]);
        if ($stmt->fetchColumn()) {
            $code = $code . substr(bin2hex(random_bytes(2)),0,4);
        }

        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $stored = bin2hex(random_bytes(8)) . ($ext ? ".{$ext}" : '');
        $finalPath = __DIR__ . "/uploads/{$stored}";
        $out = fopen($finalPath, 'wb');
        for ($i = 0; $i < $total; $i++) {
            $part = fopen("$tmpDir/part_{$i}", 'rb');
            stream_copy_to_stream($part, $out);
            fclose($part);
        }
        fclose($out);

        // bersihkan temp
        array_map('unlink', glob("$tmpDir/part_*"));
        rmdir($tmpDir);

        // insert ke links + files
        $now = time();
        $insert = $pdo->prepare("INSERT INTO links (code, target, created_at, type, user_id) VALUES (:c, NULL, :ca, 'file', :uid)");
        $insert->execute([
            ':c' => $code,
            ':ca' => $now,
            ':uid' => $_SESSION['user_id']
        ]);
        $insertF = $pdo->prepare("INSERT INTO files (code, filename, stored_name, size, mime, uploaded_at) VALUES (:c, :fn, :sn, :sz, :m, :ua)");
        $insertF->execute([
            ':c' => $code,
            ':fn' => $filename,
            ':sn' => $stored,
            ':sz' => $size,
            ':m' => $mime,
            ':ua' => $now
        ]);

        echo json_encode([
            'status' => 'done',
            'code' => $code
        ]);
        exit;
    } else {
        echo json_encode([
            'status' => 'progress',
            'part' => $index
        ]);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
    exit;
}
