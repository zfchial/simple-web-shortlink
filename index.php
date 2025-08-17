<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// inisialisasi DB (reuse dari sebelumnya)
$dbFile = __DIR__ . '/data/db.sqlite';
if (!is_dir(__DIR__.'/data')) mkdir(__DIR__.'/data', 0755, true);
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// buat tabel jika belum ada
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
$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    is_admin INTEGER DEFAULT 0,
    created_at INTEGER NOT NULL
)");

// Add user_id column to links table if it doesn't exist
try {
    $pdo->exec("ALTER TABLE links ADD COLUMN user_id INTEGER NOT NULL DEFAULT 1");
} catch (PDOException $e) {
    // Column might already exist, ignore the error
}

// Add index for user_id column in links table
try {
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_links_user_id ON links(user_id)");
} catch (PDOException $e) {
    // Index might already exist, ignore the error
}

// Add admin user if not exists
try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'admin'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, is_admin, created_at) VALUES (?, ?, 1, ?)");
        $stmt->execute(['admin', $hash, time()]);
    }
} catch (PDOException $e) {
    // Handle error
}

// helper base url
function base_url(){
    $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    return $scheme . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/';
}

// Modify query to only show user's links
$stmt = $pdo->prepare("SELECT l.code, l.target, l.created_at, l.hits, l.type, f.filename 
                       FROM links l 
                       LEFT JOIN files f ON l.code = f.code 
                       WHERE l.user_id = ? 
                       ORDER BY l.created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user data
$stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Shortlink Dashboard</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4CAF50;
            --secondary-color: #45a049;
        }
        
        body { 
            background: #f8f9fa; 
            padding-top: 60px;
        }
        
        .navbar {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.05);
            border: none;
            margin-bottom: 25px;
            padding: 20px;
        }
        
        .card-title {
            color: #333;
            font-size: 1.25rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }
        
        .form-control {
            border-radius: 8px;
            border: 1px solid #ddd;
            padding: 10px 15px;
        }
        
        .btn-primary {
            background: var(--primary-color);
            border: none;
            border-radius: 8px;
            padding: 10px 20px;
        }
        
        .btn-primary:hover {
            background: var(--secondary-color);
        }
        
        .table {
            margin-top: 15px;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .progress {
            height: 10px;
            border-radius: 5px;
            margin: 15px 0;
        }
        
        .progress-bar {
            background: var(--primary-color);
        }
        
        .stats-card {
            text-align: center;
            padding: 15px;
        }
        
        .stats-number {
            font-size: 24px;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .stats-label {
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-white fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-link me-2"></i>Shortlink
            </a>
            <div class="d-flex align-items-center">
                <span class="navbar-text me-3">
                    <i class="fas fa-user me-1"></i>
                    <?= htmlspecialchars($user['username']) ?>
                </span>
                <a href="logout.php" class="btn btn-outline-danger btn-sm">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Stats Overview -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="stats-number"><?= count(array_filter($list, fn($item) => $item['type'] === 'url')) ?></div>
                    <div class="stats-label">Total URLs</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="stats-number"><?= count(array_filter($list, fn($item) => $item['type'] === 'file')) ?></div>
                    <div class="stats-label">Total Files</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="stats-number"><?= array_sum(array_column($list, 'hits')) ?></div>
                    <div class="stats-label">Total Hits</div>
                </div>
            </div>
        </div>

        <!-- Shortlink form -->
        <div class="card">
            <h5 class="card-title"><i class="fas fa-link me-2"></i>Buat Shortlink URL</h5>
            <form method="post" action="shorten.php" class="row g-3">
                <div class="col-md-9">
                    <input type="url" name="url" class="form-control" placeholder="https://example.com" required>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-compress-alt me-1"></i>Perpendek
                    </button>
                </div>
            </form>
        </div>

        <!-- Upload file -->
        <div class="card">
            <h5 class="card-title"><i class="fas fa-file-upload me-2"></i>Upload File</h5>
            <div class="mb-3">
                <input type="file" id="fileInput" class="form-control">
            </div>
            <button id="uploadBtn" class="btn btn-primary">
                <i class="fas fa-upload me-1"></i>Upload
            </button>
            <div id="status" class="mt-2 text-muted"></div>
            <div class="progress">
                <div class="progress-bar" id="bar" role="progressbar" style="width: 0%"></div>
            </div>
            <div id="result" class="mt-2"></div>
        </div>

        <!-- Recent links/files -->
        <div class="card">
            <h5 class="card-title"><i class="fas fa-history me-2"></i>Link Terbaru</h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Short URL</th>
                            <th>Tipe</th>
                            <th>Target</th>
                            <th>Dibuat</th>
                            <th>Hits</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($list as $row): 
                        $code = htmlspecialchars($row['code'], ENT_QUOTES);
                        $type = $row['type'];
                        $created = date('Y-m-d H:i', $row['created_at']);
                        $hits = (int)$row['hits'];
                        $display = $type === 'url' ? htmlspecialchars($row['target'], ENT_QUOTES) : htmlspecialchars($row['filename'], ENT_QUOTES);
                        $link = $type === 'url' ? base_url() . $code : base_url() . 'f.php?c=' . $code;
                    ?>
                        <tr>
                            <td>
                                <a href="<?= $link ?>" target="_blank" class="text-decoration-none">
                                    <i class="fas fa-<?= $type === 'url' ? 'link' : 'file' ?> me-1"></i>
                                    <?= $link ?>
                                </a>
                            </td>
                            <td><span class="badge bg-<?= $type === 'url' ? 'primary' : 'success' ?>"><?= $type ?></span></td>
                            <td class="text-truncate" style="max-width: 200px;"><?= $display ?></td>
                            <td><?= $created ?></td>
                            <td><?= $hits ?></td>
                            <td>
                                <button onclick="deleteLink('<?= $code ?>', '<?= $type ?>')" class="btn btn-sm btn-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    const uploadBtn = document.getElementById('uploadBtn');
    const fileInput = document.getElementById('fileInput');
    const bar = document.getElementById('bar');
    const statusEl = document.getElementById('status');
    const resultEl = document.getElementById('result');

    uploadBtn.addEventListener('click', async () => {
        const file = fileInput.files[0];
        if (!file) {
            alert('Pilih file dulu');
            return;
        }

        try {
            statusEl.textContent = 'Mempersiapkan upload...';
            const chunkSize = 1024 * 1024; // 1MB chunks
            const totalChunks = Math.ceil(file.size / chunkSize);
            const code = Math.random().toString(36).substring(2, 8);

            // Upload chunks
            for (let i = 0; i < totalChunks; i++) {
                const start = i * chunkSize;
                const end = Math.min(file.size, start + chunkSize);
                const chunk = file.slice(start, end);

                const formData = new FormData();
                formData.append('chunk', chunk);
                formData.append('index', i);
                formData.append('total', totalChunks);
                formData.append('code', code);
                formData.append('filename', file.name);
                formData.append('size', file.size);
                formData.append('mime', file.type);

                const response = await fetch('upload.php', {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error('Upload failed');
                }

                // Update progress
                const percent = Math.round(((i + 1) / totalChunks) * 100);
                statusEl.textContent = `Mengupload... ${percent}%`;
                bar.style.width = `${percent}%`;
            }

            // Save to database
            statusEl.textContent = 'Menyimpan ke database...';
            const dbResponse = await fetch('save_file.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    code: code,
                    filename: file.name,
                    size: file.size,
                    mime: file.type
                })
            });

            const dbResult = await dbResponse.json();

            if (dbResult.success) {
                statusEl.textContent = 'Upload berhasil!';
                // Reset form
                fileInput.value = '';
                bar.style.width = '0%';
                
                // Refresh page after short delay
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            } else {
                throw new Error(dbResult.error || 'Gagal menyimpan ke database');
            }

        } catch (error) {
            console.error('Upload error:', error);
            statusEl.textContent = 'Error: ' + error.message;
            bar.style.width = '0%';
        }
    });

    async function deleteLink(code, type) {
        if (!confirm('Yakin ingin menghapus?')) return;

        try {
            const response = await fetch('delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ code, type })
            });

            // First check if response is OK
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            let result;
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                result = await response.json();
            } else {
                throw new Error('Response was not JSON');
            }
            
            if (result.success) {
                alert('Berhasil dihapus');
                location.reload();
            } else {
                alert('Gagal dihapus: ' + (result.error || 'Unknown error'));
            }
        } catch (err) {
            console.error('Delete error:', err);
            alert('Error saat menghapus: ' + err.message);
        }
    }
    </script>
</body>
</html>
