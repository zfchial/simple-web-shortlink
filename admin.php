<?php
session_start();

// Check if user is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: login.php');
    exit;
}

// Anti XSS function
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

$dbFile = __DIR__ . '/data/db.sqlite';
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Get all users and their links
$stmt = $pdo->query("SELECT u.*, 
    (SELECT COUNT(*) FROM links WHERE user_id = u.id) as total_links,
    (SELECT SUM(hits) FROM links WHERE user_id = u.id) as total_hits
    FROM users u WHERE u.is_admin = 0");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all links
$stmt = $pdo->query("SELECT l.*, u.username, f.filename, f.stored_name, f.size, f.mime 
                     FROM links l 
                     LEFT JOIN users u ON l.user_id = u.id
                     LEFT JOIN files f ON l.code = f.code
                     ORDER BY l.created_at DESC");
$links = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to format file size
function formatSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' bytes';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Shortlink</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { padding-top: 60px; background: #f8f9fa; }
        .card { margin-bottom: 20px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
        .nav-tabs { margin-bottom: 20px; }
        .table td { vertical-align: middle; }
        .truncate { 
            max-width: 200px; 
            white-space: nowrap; 
            overflow: hidden; 
            text-overflow: ellipsis; 
        }
        .btn-group-sm > .btn { margin: 0 2px; }
        .download-btn { background-color: #28a745; color: white; }
        .download-btn:hover { background-color: #218838; color: white; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="fas fa-shield-alt me-2"></i>Admin Dashboard</a>
            <div class="d-flex">
                <a href="index.php" class="btn btn-outline-light me-2">Home</a>
                <a href="logout.php" class="btn btn-outline-light">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Navigation tabs -->
        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" data-bs-toggle="tab" href="#users">
                    <i class="fas fa-users me-2"></i>Users
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#links">
                    <i class="fas fa-link me-2"></i>Links & Files
                </a>
            </li>
        </ul>

        <!-- Tab content -->
        <div class="tab-content">
            <!-- Users tab -->
            <div class="tab-pane fade show active" id="users">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-4">User Management</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Total Links</th>
                                        <th>Total Hits</th>
                                        <th>Registered</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach($users as $user): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($user['username']) ?></td>
                                        <td><?= (int)$user['total_links'] ?></td>
                                        <td><?= (int)$user['total_hits'] ?></td>
                                        <td><?= date('Y-m-d H:i', $user['created_at']) ?></td>
                                        <td>
                                            <button onclick="deleteUser(<?= $user['id'] ?>)" class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash me-1"></i>Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Links tab -->
            <div class="tab-pane fade" id="links">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-4">Links & Files Management</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Owner</th>
                                        <th>Short Code</th>
                                        <th>Target/Filename</th>
                                        <th>Hits</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach($links as $link): 
                                    $isFile = $link['type'] === 'file';
                                    $display = $isFile ? $link['filename'] : $link['target'];
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?= $isFile ? 'success' : 'primary' ?>">
                                                <?= $isFile ? 'File' : 'URL' ?>
                                            </span>
                                        </td>
                                        <td><?= e($link['username']) ?></td>
                                        <td><?= e($link['code']) ?></td>
                                        <td class="truncate" title="<?= e($display) ?>">
                                            <?php if ($isFile): ?>
                                                <i class="fas fa-file me-1"></i><?= e($display) ?>
                                                <small class="text-muted">(<?= formatSize($link['size']) ?>)</small>
                                            <?php else: ?>
                                                <i class="fas fa-link me-1"></i>
                                                <a href="<?= e($link['target']) ?>" target="_blank" class="text-decoration-none">
                                                    <?= e($display) ?>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= (int)$link['hits'] ?></td>
                                        <td><?= date('Y-m-d H:i', $link['created_at']) ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <?php if ($isFile): ?>
                                                    <a href="download.php?code=<?= e($link['code']) ?>&admin=1" 
                                                       class="btn btn-sm download-btn" 
                                                       title="Download File">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <button onclick="deleteLink('<?= e($link['code']) ?>', '<?= e($link['type']) ?>')" 
                                                        class="btn btn-sm btn-danger"
                                                        title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    async function deleteUser(userId) {
        if (!confirm('Are you sure you want to delete this user and all their data?')) return;

        try {
            const response = await fetch('admin_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    action: 'delete_user',
                    user_id: userId 
                })
            });

            const result = await response.json();
            if (result.success) {
                alert('User deleted successfully');
                location.reload();
            } else {
                alert(result.error || 'Failed to delete user');
            }
        } catch (err) {
            alert('Error: ' + err.message);
        }
    }

    async function deleteLink(code, type) {
        if (!confirm('Are you sure you want to delete this link?')) return;

        try {
            const response = await fetch('admin_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    action: 'delete_link',
                    code: code,
                    type: type
                })
            });

            const result = await response.json();
            if (result.success) {
                alert('Link deleted successfully');
                location.reload();
            } else {
                alert(result.error || 'Failed to delete link');
            }
        } catch (err) {
            alert('Error: ' + err.message);
        }
    }
    </script>
</body>
</html>