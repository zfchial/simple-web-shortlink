<?php
$dbFile = __DIR__ . '/data/db.sqlite';
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$code = $_GET['c'] ?? '';
$code = preg_replace('/[^0-9A-Za-z]/', '', $code);
if (!$code) {
    die("Kode tidak valid.");
}
$stmt = $pdo->prepare("SELECT code, target, created_at, hits FROM links WHERE code = :c");
$stmt->execute([':c' => $code]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    die("Shortlink tidak ditemukan.");
}
$created = date('Y-m-d H:i:s', $row['created_at']);
$short = htmlspecialchars($row['code'], ENT_QUOTES);
$target = htmlspecialchars($row['target'], ENT_QUOTES);
$hits = (int)$row['hits'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <title>Stats <?= $short ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <style>
    body { font-family: system-ui,-apple-system,BlinkMacSystemFont,sans-serif; padding:30px; max-width:700px; margin:auto; }
    .box { border:1px solid #ccc; padding:16px; border-radius:8px; }
    a { color:#007bff; text-decoration:none; }
  </style>
</head>
<body>
  <h1>Statistik Shortlink</h1>
  <div class="box">
    <p><strong>Short:</strong> <a href="<?= $short ?>" target="_blank"><?= htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/' . $short) ?></a></p>
    <p><strong>Target:</strong> <a href="<?= $target ?>" target="_blank"><?= $target ?></a></p>
    <p><strong>Dibuat:</strong> <?= $created ?></p>
    <p><strong>Hits:</strong> <?= $hits ?></p>
  </div>
  <p><a href="index.php">&larr; Kembali</a></p>
</body>
</html>
