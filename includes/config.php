<?php
// ============================================================
// SolusiMu - Core Config & Helpers
// ============================================================

define('SITE_NAME',    'SolusiMu');
define('SITE_TAGLINE', 'Gateway Pembayaran Terpercaya');
define('SITE_VERSION', '2.0.0');
define('CURRENCY',     'IDR');
define('BASE_URL',     'http://localhost/egipay');

// ── Session ──────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,      // 1 day
        'path'     => '/',
        'secure'   => false,      // set true in production (HTTPS)
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ── Database ─────────────────────────────────────────────────
require_once __DIR__ . '/db.php';

// ── Flash Messages ───────────────────────────────────────────
function setFlash(string $type, string $title, string $message): void {
    $_SESSION['flash'] = compact('type', 'title', 'message');
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ── Auth helpers ─────────────────────────────────────────────
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    static $user = null;
    if ($user === null) {
        $user = dbFetchOne(
            'SELECT id, name, email, phone, role, plan, status, avatar, member_code, referral_code FROM users WHERE id = ? AND status = "active"',
            [$_SESSION['user_id']]
        );
        if (!$user) {
            session_destroy();
            return null;
        }
    }
    return $user;
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        setFlash('error', 'Akses Ditolak', 'Silakan masuk terlebih dahulu.');
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

// ── Role checkers ─────────────────────────────────────────────
/**
 * Returns true for admin OR superadmin (both can access admin panel)
 */
function isAdmin(): bool {
    $user = getCurrentUser();
    return $user && in_array($user['role'], ['admin', 'superadmin']);
}

/**
 * Only superadmin passes this check
 */
function isSuperAdmin(): bool {
    $user = getCurrentUser();
    return $user && $user['role'] === 'superadmin';
}

/**
 * Returns the role slug (superadmin, admin, merchant, customer)
 */
function currentRole(): string {
    $user = getCurrentUser();
    return $user['role'] ?? 'guest';
}

/**
 * Roles that may access the admin panel
 */
function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        setFlash('error', 'Akses Ditolak', 'Halaman ini hanya dapat diakses oleh Admin.');
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    }
}

/**
 * Only superadmin may access; redirect others appropriately
 */
function requireSuperAdmin(): void {
    requireLogin();
    if (!isSuperAdmin()) {
        if (isAdmin()) {
            setFlash('error', 'Akses Ditolak', 'Halaman ini hanya dapat diakses oleh Super Admin.');
            header('Location: ' . BASE_URL . '/admin/index.php');
        } else {
            setFlash('error', 'Akses Ditolak', 'Halaman ini hanya dapat diakses oleh Super Admin.');
            header('Location: ' . BASE_URL . '/dashboard.php');
        }
        exit;
    }
}

function requireGuest(): void {
    if (isLoggedIn()) {
        $role = currentRole();
        if ($role === 'superadmin') {
            header('Location: ' . BASE_URL . '/superadmin/index.php');
        } elseif ($role === 'admin') {
            header('Location: ' . BASE_URL . '/admin/index.php');
        } else {
            header('Location: ' . BASE_URL . '/dashboard.php');
        }
        exit;
    }
}

// ── Wallet helper ─────────────────────────────────────────────
function getUserWallet(int $userId): ?array {
    return dbFetchOne('SELECT * FROM wallets WHERE user_id = ?', [$userId]);
}

// ── Formatting ───────────────────────────────────────────────
function formatRupiah(float $amount): string {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function formatDate(string $datetime, string $format = 'd M Y'): string {
    return date($format, strtotime($datetime));
}

// ── CSRF Token ────────────────────────────────────────────────
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

function verifyCsrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrfToken(), $token)) {
        http_response_code(403);
        die('Request tidak valid (CSRF check failed).');
    }
}

// ── Redirect helper ───────────────────────────────────────────
function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

// ── Referral code generator ──────────────────────────────────
function generateReferralCode(string $name): string {
    $rand = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    $code = 'SMU-' . $rand;
    // ensure uniqueness
    $dup  = dbFetchOne('SELECT id FROM users WHERE referral_code = ?', [$code]);
    if ($dup) {
        $rand = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $code = 'SMU-' . $rand;
    }
    return $code;
}

// ── Get unread notification count ─────────────────────────────
function getUnreadNotifCount(int $userId): int {
    $row = dbFetchOne(
        'SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0',
        [$userId]
    );
    return (int)($row['cnt'] ?? 0);
}
?>
