<?php
/**
 * EgiPay – Database Installer
 * Visit: http://localhost/egipay/install.php
 * DELETE this file after installation!
 */

$status  = [];
$success = true;

// DB connection without dbname first
try {
    $pdo = new PDO(
        'mysql:host=localhost;port=3306;charset=utf8mb4',
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `egipay` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $status[] = ['ok', 'Database `egipay` dibuat / sudah ada'];

    $pdo->exec("USE `egipay`");

    // Read SQL file
    $sqlFile = __DIR__ . '/database/egipay.sql';
    if (!file_exists($sqlFile)) {
        throw new RuntimeException("File database/egipay.sql tidak ditemukan!");
    }
    $sql = file_get_contents($sqlFile);

    // Remove CREATE DATABASE / USE statements (already done)
    $sql = preg_replace('/CREATE DATABASE.*?;/is', '', $sql);
    $sql = preg_replace('/USE `[^`]+`;/i', '', $sql);

    // Split and execute
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        fn($s) => strlen($s) > 5
    );
    $count = 0;
    foreach ($statements as $stmt) {
        $pdo->exec($stmt);
        $count++;
    }
    $status[] = ['ok', "Berhasil menjalankan {$count} SQL statement"];

    // Verify tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $status[] = ['ok', 'Tabel ditemukan: ' . implode(', ', $tables)];

    // Check demo user
    $users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $txs   = $pdo->query("SELECT COUNT(*) FROM transactions")->fetchColumn();
    $status[] = ['ok', "Data seed: {$users} user, {$txs} transaksi"];

} catch (Throwable $e) {
    $status[] = ['error', $e->getMessage()];
    $success = false;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>EgiPay Installer</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Segoe UI',sans-serif;background:#0a0a1a;color:#f1f5f9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:2rem}
    .card{background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:24px;padding:2.5rem;max-width:600px;width:100%}
    .logo{display:flex;align-items:center;gap:12px;margin-bottom:2rem}
    h1{font-size:1.5rem;margin-bottom:0.25rem}
    .subtitle{color:#64748b;font-size:0.875rem}
    .step{display:flex;align-items:flex-start;gap:12px;padding:0.75rem 1rem;border-radius:12px;margin-bottom:0.5rem;font-size:0.875rem}
    .step.ok{background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.2);color:#10b981}
    .step.error{background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);color:#ef4444}
    .btn{display:inline-flex;align-items:center;gap:8px;padding:0.75rem 2rem;border-radius:12px;border:none;font-weight:700;font-size:0.9rem;cursor:pointer;text-decoration:none;margin-top:1rem}
    .btn-primary{background:linear-gradient(135deg,#6c63ff,#00d4ff);color:#fff}
    .btn-outline{background:transparent;border:1px solid rgba(255,255,255,0.15);color:#94a3b8}
    .warn{background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.2);border-radius:12px;padding:1rem;margin-top:1.5rem;font-size:0.8rem;color:#f59e0b;display:flex;align-items:flex-start;gap:10px;}
  </style>
</head>
<body>
<div class="card">
  <div class="logo">
    <svg width="44" height="44" viewBox="0 0 42 42" fill="none">
      <defs><linearGradient id="iLg" x1="0" y1="0" x2="42" y2="42"><stop stop-color="#6c63ff"/><stop offset="1" stop-color="#00d4ff"/></linearGradient></defs>
      <rect width="42" height="42" rx="12" fill="url(#iLg)"/>
      <path d="M12 14h10a6 6 0 010 12H12V14zm0 6h8a2 2 0 000-6" fill="white" opacity="0.95"/>
      <circle cx="30" cy="28" r="3" fill="white" opacity="0.8"/>
    </svg>
    <div>
      <h1>EgiPay Installer</h1>
      <div class="subtitle">Setup Database Otomatis</div>
    </div>
  </div>

  <div>
    <?php foreach ($status as [$type, $msg]): ?>
    <div class="step <?= $type ?>">
      <i class="bi bi-<?= $type === 'ok' ? 'check-circle-fill' : 'x-circle-fill' ?>"></i>
      <span><?= htmlspecialchars($msg) ?></span>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if ($success): ?>
  <div style="padding:1.5rem;background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.2);border-radius:16px;margin-top:1.5rem;text-align:center;">
    <div style="font-size:2rem;margin-bottom:0.5rem;">🎉</div>
    <div style="font-size:1.1rem;font-weight:700;color:#10b981;margin-bottom:0.25rem;">Instalasi Berhasil!</div>
    <div style="font-size:0.8rem;color:#64748b;margin-bottom:1rem;">
      Login demo: <strong>merchant@demo.com</strong> / <strong>password</strong>
    </div>
    <a href="login.php" class="btn btn-primary"><i class="bi bi-box-arrow-in-right"></i>Buka EgiPay</a>
  </div>

  <div class="warn">
    <i class="bi bi-exclamation-triangle-fill" style="flex-shrink:0;margin-top:2px;"></i>
    <div><strong>PENTING:</strong> Segera hapus file <code>install.php</code> setelah instalasi untuk keamanan.</div>
  </div>
  <?php else: ?>
  <div style="text-align:center;margin-top:1.5rem;">
    <p style="color:#64748b;font-size:0.875rem;margin-bottom:1rem;">Pastikan XAMPP MySQL sudah berjalan dan coba lagi.</p>
    <a href="install.php" class="btn btn-primary"><i class="bi bi-arrow-clockwise"></i>Coba Lagi</a>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
