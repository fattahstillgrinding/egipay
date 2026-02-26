<?php
// ============================================================
// EgiPay - Database Connection (PDO)
// ============================================================

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'egipay');
define('DB_USER', 'root');
define('DB_PASS', '');        // XAMPP default: kosong
define('DB_CHARSET', 'utf8mb4');

/**
 * Singleton PDO connection
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // Return a error page instead of showing raw DB error
            http_response_code(503);
            die(renderDbError($e->getMessage()));
        }
    }
    return $pdo;
}

/**
 * Execute a query and return all rows
 */
function dbFetchAll(string $sql, array $params = []): array {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Execute a query and return single row
 */
function dbFetchOne(string $sql, array $params = []): ?array {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Execute INSERT/UPDATE/DELETE, return affected rows
 */
function dbExecute(string $sql, array $params = []): int {
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

/**
 * Get last inserted ID
 */
function dbLastId(): string {
    return getDB()->lastInsertId();
}

/**
 * Generate unique transaction ID: TXN-XXXXXX (uppercase hex)
 */
function generateTxId(): string {
    do {
        $txId = 'TXN-' . strtoupper(bin2hex(random_bytes(4)));
        $exists = dbFetchOne('SELECT id FROM transactions WHERE tx_id = ?', [$txId]);
    } while ($exists);
    return $txId;
}

/**
 * Generate API keys
 */
function generateApiKey(string $type = 'sandbox'): string {
    $prefix = $type === 'live' ? 'EGI-Live-' : 'EGI-SB-';
    return $prefix . bin2hex(random_bytes(16));
}

/**
 * Log an action to audit_logs
 */
function auditLog(?int $userId, string $action, string $description = ''): void {
    $ip        = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 300);
    try {
        dbExecute(
            'INSERT INTO audit_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)',
            [$userId, $action, $description, $ip, $userAgent]
        );
    } catch (PDOException) {
        // Non-critical — don't break app on audit failure
    }
}

/**
 * Render a styled DB error (only shown if DB is unreachable)
 */
function renderDbError(string $msg): string {
    return <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Database Error – EgiPay</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Segoe UI',sans-serif;background:#0a0a1a;color:#f1f5f9;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:2rem}
    .box{background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);border-radius:20px;padding:3rem;max-width:500px;text-align:center}
    .icon{font-size:3rem;margin-bottom:1rem}
    h1{font-size:1.5rem;margin-bottom:0.5rem;color:#ef4444}
    p{color:#94a3b8;line-height:1.7;margin-bottom:1rem;font-size:.9rem}
    code{background:rgba(239,68,68,.1);color:#fca5a5;padding:4px 10px;border-radius:6px;font-size:.8rem;display:block;margin-top:1rem;word-break:break-all}
    a{color:#a78bfa;text-decoration:none}
  </style>
</head>
<body>
  <div class="box">
    <div class="icon">🔌</div>
    <h1>Koneksi Database Gagal</h1>
    <p>EgiPay tidak dapat terhubung ke database MySQL. Pastikan:</p>
    <p>✓ <strong>XAMPP MySQL</strong> sudah berjalan<br>
       ✓ Database <strong>egipay</strong> sudah dibuat<br>
       ✓ Konfigurasi di <code>includes/db.php</code> sudah benar</p>
    <code>$msg</code>
    <p style="margin-top:1.5rem"><a href="install.php">→ Jalankan Installer Otomatis</a></p>
  </div>
</body>
</html>
HTML;
}
?>
