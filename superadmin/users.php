<?php
require_once __DIR__ . '/../includes/config.php';
requireSuperAdmin();

$user  = getCurrentUser();
$flash = getFlash();
$db    = getDB();

// ── Roles supported ──────────────────────────────────────────
$ROLES     = ['superadmin', 'admin', 'merchant', 'customer'];
$STATUSES  = ['active', 'inactive', 'suspended'];
$PLANS     = ['free', 'basic', 'premium', 'enterprise'];

// ── POST actions ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $action   = $_POST['action'] ?? '';
    $targetId = (int)($_POST['user_id'] ?? 0);

    if ($targetId <= 0) {
        setFlash('error', 'Pengguna Tidak Ditemukan', 'ID pengguna tidak valid.');
        redirect(BASE_URL . '/superadmin/users.php');
    }

    // Prevent actions on self (except safe ones)
    $targetUser = dbFetchOne('SELECT * FROM users WHERE id = ?', [$targetId]);
    if (!$targetUser) {
        setFlash('error', 'Tidak Ditemukan', 'Pengguna tidak ditemukan.');
        redirect(BASE_URL . '/superadmin/users.php');
    }

    if ($action === 'change_role') {
        $newRole = $_POST['new_role'] ?? '';
        if (!in_array($newRole, $ROLES)) {
            setFlash('error', 'Role Tidak Valid', 'Role yang dipilih tidak valid.');
            redirect(BASE_URL . '/superadmin/users.php');
        }
        if ($targetId == $user['id'] && $newRole !== 'superadmin') {
            setFlash('error', 'Aksi Ditolak', 'Anda tidak bisa mengubah role akun sendiri ke role lebih rendah.');
            redirect(BASE_URL . '/superadmin/users.php');
        }
        $oldRole = $targetUser['role'];
        dbExecute('UPDATE users SET role = ?, updated_at = NOW() WHERE id = ?', [$newRole, $targetId]);
        auditLog('admin_change_role', "Role {$targetUser['name']} (ID:{$targetId}) diubah dari {$oldRole} ke {$newRole} oleh superadmin ID:{$user['id']}");
        setFlash('success', 'Role Diubah', "{$targetUser['name']}: {$oldRole} → {$newRole}");

    } elseif ($action === 'change_status') {
        $newStatus = $_POST['new_status'] ?? '';
        if (!in_array($newStatus, $STATUSES)) {
            setFlash('error', 'Status Tidak Valid', '');
            redirect(BASE_URL . '/superadmin/users.php');
        }
        if ($targetId == $user['id']) {
            setFlash('error', 'Aksi Ditolak', 'Tidak dapat mengubah status akun sendiri.');
            redirect(BASE_URL . '/superadmin/users.php');
        }
        $old = $targetUser['status'];
        dbExecute('UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?', [$newStatus, $targetId]);
        auditLog('admin_change_status', "Status {$targetUser['name']} (ID:{$targetId}) diubah dari {$old} ke {$newStatus} oleh superadmin ID:{$user['id']}");
        setFlash('success', 'Status Diperbarui', "Status {$targetUser['name']}: {$old} → {$newStatus}");

    } elseif ($action === 'change_plan') {
        $newPlan = $_POST['new_plan'] ?? '';
        if (!in_array($newPlan, $PLANS)) {
            setFlash('error', 'Plan Tidak Valid','');
            redirect(BASE_URL . '/superadmin/users.php');
        }
        dbExecute('UPDATE users SET plan = ?, updated_at = NOW() WHERE id = ?', [$newPlan, $targetId]);
        auditLog('admin_change_plan', "Plan {$targetUser['name']} (ID:{$targetId}) diubah ke {$newPlan} oleh superadmin ID:{$user['id']}");
        setFlash('success', 'Plan Diperbarui', "Plan {$targetUser['name']} → {$newPlan}");

    } elseif ($action === 'reset_password') {
        if ($targetId == $user['id']) {
            setFlash('error', 'Aksi Ditolak', 'Tidak dapat mereset password akun sendiri via panel ini.');
            redirect(BASE_URL . '/superadmin/users.php');
        }
        $newPass   = trim($_POST['new_password'] ?? '');
        $confPass  = trim($_POST['confirm_password'] ?? '');
        if (strlen($newPass) < 8) {
            setFlash('error', 'Password Terlalu Pendek', 'Password minimal 8 karakter.');
            redirect(BASE_URL . '/superadmin/users.php');
        }
        if ($newPass !== $confPass) {
            setFlash('error', 'Password Tidak Cocok', 'Konfirmasi password tidak sesuai.');
            redirect(BASE_URL . '/superadmin/users.php');
        }
        $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost'=>10]);
        dbExecute('UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?', [$hash, $targetId]);
        auditLog('admin_reset_password', "Password {$targetUser['name']} (ID:{$targetId}) direset oleh superadmin ID:{$user['id']}");
        setFlash('success', 'Password Direset', "Password {$targetUser['name']} berhasil diubah.");

    } elseif ($action === 'reset_info') {
        $newName  = trim($_POST['new_name'] ?? '');
        $newEmail = trim($_POST['new_email'] ?? '');
        if (!$newName || !$newEmail || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            setFlash('error', 'Input Tidak Valid', 'Nama atau email tidak valid.');
            redirect(BASE_URL . '/superadmin/users.php');
        }
        // Check email uniqueness
        $dup = dbFetchOne('SELECT id FROM users WHERE email = ? AND id != ?', [$newEmail, $targetId]);
        if ($dup) {
            setFlash('error', 'Email Sudah Digunakan', 'Pilih email lain.');
            redirect(BASE_URL . '/superadmin/users.php');
        }
        dbExecute('UPDATE users SET name = ?, email = ?, updated_at = NOW() WHERE id = ?', [$newName, $newEmail, $targetId]);
        auditLog('admin_edit_user', "Info {$targetUser['name']} (ID:{$targetId}) diubah oleh superadmin ID:{$user['id']}");
        setFlash('success', 'Info Diperbarui', "{$targetUser['name']} berhasil diperbarui.");
    }

    redirect(BASE_URL . '/superadmin/users.php');
}

// ── Query / Filter / Pagination ───────────────────────────────
$search    = trim($_GET['q'] ?? '');
$filterRole = $_GET['role'] ?? '';
$filterStat = $_GET['status'] ?? '';
$filterPlan = $_GET['plan'] ?? '';
$page      = max(1, (int)($_GET['page'] ?? 1));
$perPage   = 15;

$where  = [];
$params = [];
if ($search) {
    $where[]  = '(u.name LIKE ? OR u.email LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filterRole && in_array($filterRole, $ROLES)) {
    $where[]  = 'u.role = ?';
    $params[] = $filterRole;
}
if ($filterStat && in_array($filterStat, $STATUSES)) {
    $where[]  = 'u.status = ?';
    $params[] = $filterStat;
}
if ($filterPlan && in_array($filterPlan, $PLANS)) {
    $where[]  = 'u.plan = ?';
    $params[] = $filterPlan;
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total      = dbFetchOne("SELECT COUNT(*) AS c FROM users u $whereSQL", $params)['c'] ?? 0;
$totalPages = max(1, (int)ceil($total / $perPage));
$offset     = ($page - 1) * $perPage;

$users = dbFetchAll(
    "SELECT u.*, 
            COALESCE(w.balance, 0) AS wallet_bal,
            (SELECT COUNT(*) FROM transactions t WHERE t.user_id = u.id AND t.status='success') AS tx_ok
     FROM users u
     LEFT JOIN wallets w ON w.user_id = u.id
     $whereSQL
     ORDER BY u.created_at DESC LIMIT $perPage OFFSET $offset",
    $params
);

// Per-role counts for filter badges
$roleCounts = [];
$rows = dbFetchAll('SELECT role, COUNT(*) AS c FROM users GROUP BY role');
foreach ($rows as $r) $roleCounts[$r['role']] = (int)$r['c'];

$roleColors   = ['superadmin'=>['bg'=>'rgba(168,85,247,.15)','txt'=>'#c084fc'],'admin'=>['bg'=>'rgba(247,37,133,.12)','txt'=>'#f472b6'],'merchant'=>['bg'=>'rgba(108,99,255,.12)','txt'=>'#a78bfa'],'customer'=>['bg'=>'rgba(16,185,129,.1)','txt'=>'#34d399']];
$statusColors = ['active'=>['bg'=>'rgba(16,185,129,.1)','txt'=>'#10b981'],'inactive'=>['bg'=>'rgba(100,116,139,.1)','txt'=>'#94a3b8'],'suspended'=>['bg'=>'rgba(239,68,68,.12)','txt'=>'#f87171']];

function qstr(array $override = []): string {
    $p = array_merge($_GET, $override);
    unset($p['page']);
    return '?' . http_build_query(array_filter($p, fn($v) => $v !== ''));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Kelola Pengguna – Super Admin EgiPay</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link href="../css/style.css" rel="stylesheet"/>
  <style>
    :root{--sa-primary:#a855f7;--sa-secondary:#f59e0b;}
    .sa-badge{background:linear-gradient(135deg,#a855f7,#6366f1);color:#fff;font-size:.6rem;font-weight:800;padding:2px 9px;border-radius:6px;text-transform:uppercase;letter-spacing:.08em;}
    .sa-active{background:linear-gradient(135deg,rgba(168,85,247,.18),rgba(99,102,241,.1))!important;border-left-color:#a855f7!important;color:#c084fc!important;}
    .pill{display:inline-block;font-size:.68rem;font-weight:700;padding:2px 10px;border-radius:20px;}
    .tbl th{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);border:none;padding:.6rem .9rem;white-space:nowrap;}
    .tbl td{padding:.7rem .9rem;vertical-align:middle;border-top:1px solid rgba(255,255,255,.04);font-size:.82rem;}
    .tbl tr:hover>td{background:rgba(255,255,255,.025);}
    .action-btn{padding:.3rem .7rem;border-radius:8px;font-size:.75rem;font-weight:700;cursor:pointer;}
    .filter-tag{display:inline-flex;align-items:center;gap:4px;padding:.28rem .75rem;border-radius:20px;font-size:.73rem;font-weight:700;border:1px solid transparent;cursor:pointer;text-decoration:none;color:var(--text-secondary);background:var(--bg-card);border-color:var(--border-glass);}
    .filter-tag.active{background:rgba(168,85,247,.15);border-color:#a855f7;color:#c084fc;}
  </style>
</head>
<body>
<div class="page-loader" id="pageLoader"><div class="loader-logo"><svg width="60" height="60" viewBox="0 0 60 60" fill="none"><defs><linearGradient id="lg1" x1="0" y1="0" x2="60" y2="60"><stop stop-color="#a855f7"/><stop offset="1" stop-color="#6366f1"/></linearGradient></defs><rect width="60" height="60" rx="16" fill="url(#lg1)"/><path d="M18 20h14a8 8 0 010 16H18V20zm0 8h12a4 4 0 000-8" fill="white" opacity=".9"/><circle cx="42" cy="40" r="4" fill="white" opacity=".7"/></svg></div><div class="loader-bar"><div class="loader-bar-fill"></div></div></div>
<div class="toast-container" id="toastContainer"></div>
<?php if ($flash): ?><div id="flashMessage" data-type="<?= $flash['type'] ?>" data-title="<?= htmlspecialchars($flash['title']) ?>" data-message="<?= htmlspecialchars($flash['message']) ?>" style="display:none"></div><?php endif; ?>
<div id="sidebarOverlay" style="position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:999;display:none;backdrop-filter:blur(4px);" class="d-lg-none"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="mainSidebar">
  <div class="sidebar-logo">
    <svg width="36" height="36" viewBox="0 0 42 42" fill="none"><defs><linearGradient id="sLg" x1="0" y1="0" x2="42" y2="42"><stop stop-color="#a855f7"/><stop offset="1" stop-color="#6366f1"/></linearGradient></defs><rect width="42" height="42" rx="12" fill="url(#sLg)"/><path d="M12 14h10a6 6 0 010 12H12V14zm0 6h8a2 2 0 000-6" fill="white" opacity=".95"/><circle cx="30" cy="28" r="3" fill="white" opacity=".8"/></svg>
    <span class="brand-text" style="font-size:1.1rem;">EgiPay <span class="sa-badge">SU</span></span>
  </div>
  <ul class="sidebar-menu">
    <li class="sidebar-section-title">Super Admin</li>
    <li><a href="index.php"    class="sidebar-link"><span class="icon"><i class="bi bi-shield-shaded"></i></span>Platform Overview</a></li>
    <li><a href="users.php"    class="sidebar-link sa-active"><span class="icon"><i class="bi bi-people-fill"></i></span>Kelola Pengguna & Role</a></li>
    <li><a href="activity.php" class="sidebar-link"><span class="icon"><i class="bi bi-activity"></i></span>Log Aktivitas</a></li>
    <li class="sidebar-section-title">Admin Panel</li>
    <li><a href="../admin/index.php"         class="sidebar-link"><span class="icon"><i class="bi bi-speedometer2"></i></span>Admin Overview</a></li>
    <li><a href="../admin/transactions.php"  class="sidebar-link"><span class="icon"><i class="bi bi-arrow-left-right"></i></span>Transaksi</a></li>
    <li><a href="../admin/registrations.php" class="sidebar-link"><span class="icon"><i class="bi bi-person-check-fill"></i></span>Registrasi</a></li>
    <li><a href="../admin/withdrawals.php"   class="sidebar-link"><span class="icon"><i class="bi bi-cash-coin"></i></span>Penarikan Dana</a></li>
    <li class="sidebar-section-title">Navigasi</li>
    <li><a href="../dashboard.php" class="sidebar-link"><span class="icon"><i class="bi bi-house-fill"></i></span>Beranda</a></li>
    <li><a href="../logout.php"    class="sidebar-link" style="color:#ef4444;"><span class="icon"><i class="bi bi-box-arrow-left"></i></span>Keluar</a></li>
  </ul>
  <div class="sidebar-bottom">
    <div class="sidebar-profile">
      <div class="profile-avatar-sm" style="background:linear-gradient(135deg,#a855f7,#6366f1);"><?= htmlspecialchars($user['avatar'] ?? '?') ?></div>
      <div>
        <div class="profile-name"><?= htmlspecialchars($user['name']) ?></div>
        <div class="profile-role" style="color:#c084fc;">Super Administrator</div>
      </div>
    </div>
  </div>
</aside>

<main class="main-content">
  <!-- Header -->
  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div class="d-flex align-items-center gap-3">
      <button class="btn d-lg-none p-2" style="background:var(--bg-card);border:1px solid var(--border-glass);border-radius:10px;color:var(--text-primary);" onclick="document.getElementById('mainSidebar').classList.add('open');document.getElementById('sidebarOverlay').style.display='block';">
        <i class="bi bi-list" style="font-size:1.2rem;"></i>
      </button>
      <div>
        <h1 style="font-size:1.4rem;font-weight:800;margin:0;">Kelola Pengguna <span class="sa-badge ms-2">SUPER ADMIN</span></h1>
        <p style="font-size:.78rem;color:var(--text-muted);margin:0;">Ubah role, status, plan, dan informasi pengguna platform</p>
      </div>
    </div>
    <div style="font-size:.78rem;color:var(--text-muted);"><?= number_format($total) ?> pengguna ditemukan</div>
  </div>

  <!-- Filter bar -->
  <div style="background:var(--bg-card);border:1px solid var(--border-glass);border-radius:14px;padding:1rem 1.25rem;margin-bottom:1.5rem;">
    <form method="GET" action="" class="d-flex flex-wrap gap-2 align-items-center">
      <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Cari nama / email…" style="flex:1;min-width:160px;max-width:280px;background:rgba(255,255,255,.04);border:1px solid var(--border-glass);border-radius:10px;padding:.42rem .85rem;color:var(--text-primary);font-size:.82rem;outline:none;" />

      <select name="role" style="background:rgba(255,255,255,.04);border:1px solid var(--border-glass);border-radius:10px;padding:.42rem .75rem;color:var(--text-primary);font-size:.82rem;outline:none;">
        <option value="">Semua Role</option>
        <?php foreach ($ROLES as $r): ?>
        <option value="<?= $r ?>" <?= $filterRole==$r?'selected':'' ?>><?= ucfirst($r) ?> (<?= $roleCounts[$r] ?? 0 ?>)</option>
        <?php endforeach; ?>
      </select>

      <select name="status" style="background:rgba(255,255,255,.04);border:1px solid var(--border-glass);border-radius:10px;padding:.42rem .75rem;color:var(--text-primary);font-size:.82rem;outline:none;">
        <option value="">Semua Status</option>
        <?php foreach ($STATUSES as $s): ?><option value="<?= $s ?>" <?= $filterStat==$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
      </select>

      <select name="plan" style="background:rgba(255,255,255,.04);border:1px solid var(--border-glass);border-radius:10px;padding:.42rem .75rem;color:var(--text-primary);font-size:.82rem;outline:none;">
        <option value="">Semua Plan</option>
        <?php foreach ($PLANS as $pl): ?><option value="<?= $pl ?>" <?= $filterPlan==$pl?'selected':'' ?>><?= ucfirst($pl) ?></option><?php endforeach; ?>
      </select>

      <button type="submit" style="background:linear-gradient(135deg,#a855f7,#6366f1);color:#fff;border:none;border-radius:10px;padding:.42rem 1rem;font-size:.82rem;font-weight:700;cursor:pointer;">
        <i class="bi bi-search me-1"></i>Cari
      </button>
      <?php if ($search||$filterRole||$filterStat||$filterPlan): ?>
      <a href="users.php" style="color:#ef4444;font-size:.78rem;text-decoration:none;padding:.42rem .5rem;"><i class="bi bi-x-circle me-1"></i>Reset</a>
      <?php endif; ?>
    </form>
  </div>

  <!-- Table -->
  <div style="background:var(--bg-card);border:1px solid var(--border-glass);border-radius:18px;overflow:hidden;">
    <div class="table-responsive">
      <table class="table tbl mb-0" style="color:var(--text-primary);">
        <thead style="background:rgba(255,255,255,.025);">
          <tr>
            <th>#ID</th>
            <th>Pengguna</th>
            <th>Role</th>
            <th>Status</th>
            <th>Plan</th>
            <th>Saldo Wallet</th>
            <th>Tx OK</th>
            <th>Bergabung</th>
            <th style="text-align:right;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($users)): ?>
          <tr><td colspan="9" style="text-align:center;padding:2.5rem;color:var(--text-muted);">Tidak ada pengguna yang sesuai filter</td></tr>
          <?php endif; ?>
          <?php foreach ($users as $u):
            $rc = $roleColors[$u['role']] ?? ['bg'=>'rgba(255,255,255,.05)','txt'=>'#94a3b8'];
            $sc = $statusColors[$u['status']] ?? ['bg'=>'rgba(255,255,255,.05)','txt'=>'#94a3b8'];
            $isSelf = ($u['id'] == $user['id']);
          ?>
          <tr>
            <td style="color:var(--text-muted);font-size:.75rem;">#<?= $u['id'] ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:.6rem;">
                <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#a855f7,#6366f1);display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:800;color:#fff;flex-shrink:0;">
                  <?= htmlspecialchars($u['avatar'] ?? mb_substr($u['name'],0,1)) ?>
                </div>
                <div>
                  <div style="font-size:.83rem;font-weight:600;"><?= htmlspecialchars($u['name']) ?><?= $isSelf ? ' <span style="font-size:.65rem;color:#c084fc;">(Saya)</span>' : '' ?></div>
                  <div style="font-size:.7rem;color:var(--text-muted);"><?= htmlspecialchars($u['email']) ?></div>
                </div>
              </div>
            </td>
            <td>
              <span class="pill" style="background:<?= $rc['bg'] ?>;color:<?= $rc['txt'] ?>;">
                <?= ucfirst($u['role']) ?>
              </span>
            </td>
            <td>
              <span class="pill" style="background:<?= $sc['bg'] ?>;color:<?= $sc['txt'] ?>;">
                <?= ucfirst($u['status']) ?>
              </span>
            </td>
            <td style="font-size:.78rem;color:var(--text-muted);"><?= ucfirst($u['plan'] ?? 'free') ?></td>
            <td style="font-weight:700;color:#34d399;font-size:.8rem;"><?= formatRupiah((float)$u['wallet_bal']) ?></td>
            <td style="text-align:center;font-weight:700;font-size:.8rem;"><?= (int)$u['tx_ok'] ?></td>
            <td style="font-size:.73rem;color:var(--text-muted);white-space:nowrap;"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
            <td style="text-align:right;white-space:nowrap;">
              <button class="action-btn" style="background:rgba(168,85,247,.12);color:#c084fc;border:1px solid rgba(168,85,247,.25);"
                onclick="openEditModal(<?= htmlspecialchars(json_encode($u)) ?>)">
                <i class="bi bi-pencil-square me-1"></i>Edit
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div style="padding:1rem 1.5rem;border-top:1px solid var(--border-glass);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;">
      <span style="font-size:.75rem;color:var(--text-muted);">Hal <?= $page ?> / <?= $totalPages ?> &nbsp;·&nbsp; <?= $total ?> pengguna</span>
      <div style="display:flex;gap:.35rem;">
        <?php if ($page > 1): ?>
        <a href="<?= qstr() ?>&page=<?= $page-1 ?>" class="action-btn" style="background:var(--bg-card);border:1px solid var(--border-glass);color:var(--text-primary);text-decoration:none;"><i class="bi bi-chevron-left"></i></a>
        <?php endif; ?>
        <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
        <a href="<?= qstr() ?>&page=<?= $p ?>" class="action-btn <?= $p==$page?'text-white':'' ?>"
           style="background:<?= $p==$page?'linear-gradient(135deg,#a855f7,#6366f1)':'var(--bg-card)' ?>;border:1px solid <?= $p==$page?'transparent':'var(--border-glass)' ?>;color:<?= $p==$page?'#fff':'var(--text-primary)' ?>;text-decoration:none;"><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
        <a href="<?= qstr() ?>&page=<?= $page+1 ?>" class="action-btn" style="background:var(--bg-card);border:1px solid var(--border-glass);color:var(--text-primary);text-decoration:none;"><i class="bi bi-chevron-right"></i></a>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</main>

<!-- ====== EDIT MODAL ====== -->
<div class="modal fade" id="editModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content" style="background:var(--bg-card);border:1px solid var(--border-glass);border-radius:18px;color:var(--text-primary);">
      <div class="modal-header" style="border-color:var(--border-glass);padding:1.25rem 1.5rem;">
        <h5 class="modal-title" style="font-weight:800;font-size:1rem;">
          <i class="bi bi-person-gear me-2" style="color:#a855f7;"></i>Edit Pengguna
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="padding:1.5rem;">

        <!-- User info header -->
        <div id="editUserInfo" style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;padding:1rem;background:rgba(255,255,255,.04);border-radius:12px;">
          <div id="editAvatar" style="width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,#a855f7,#6366f1);display:flex;align-items:center;justify-content:center;font-size:1rem;font-weight:800;color:#fff;"></div>
          <div>
            <div id="editName" style="font-weight:700;font-size:.95rem;"></div>
            <div id="editEmail" style="font-size:.78rem;color:var(--text-muted);"></div>
          </div>
        </div>
        
        <!-- Tab buttons -->
        <div style="display:flex;gap:.5rem;margin-bottom:1.25rem;flex-wrap:wrap;">
          <button type="button" class="action-btn edit-tab active" data-tab="role" style="background:rgba(168,85,247,.15);border:1px solid #a855f7;color:#c084fc;" onclick="switchTab('role')"><i class="bi bi-person-badge me-1"></i>Role</button>
          <button type="button" class="action-btn edit-tab" data-tab="status" style="background:var(--bg-card);border:1px solid var(--border-glass);color:var(--text-secondary);" onclick="switchTab('status')"><i class="bi bi-toggle-on me-1"></i>Status</button>
          <button type="button" class="action-btn edit-tab" data-tab="plan" style="background:var(--bg-card);border:1px solid var(--border-glass);color:var(--text-secondary);" onclick="switchTab('plan')"><i class="bi bi-stars me-1"></i>Plan</button>
          <button type="button" class="action-btn edit-tab" data-tab="info" style="background:var(--bg-card);border:1px solid var(--border-glass);color:var(--text-secondary);" onclick="switchTab('info')"><i class="bi bi-pencil me-1"></i>Info</button>
          <button type="button" class="action-btn edit-tab" data-tab="password" style="background:var(--bg-card);border:1px solid var(--border-glass);color:var(--text-secondary);" onclick="switchTab('password')"><i class="bi bi-key me-1"></i>Password</button>
        </div>

        <!-- Change Role -->
        <div class="edit-pane" id="pane-role">
          <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="change_role"/>
            <input type="hidden" name="user_id" id="roleUserId"/>
            <label style="font-size:.78rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.5rem;display:block;">Role Baru</label>
            <select name="new_role" id="roleSelect" style="width:100%;background:rgba(255,255,255,.04);border:1px solid var(--border-glass);border-radius:10px;padding:.5rem .85rem;color:var(--text-primary);font-size:.9rem;outline:none;margin-bottom:1rem;">
              <?php foreach ($ROLES as $r): ?><option value="<?= $r ?>"><?= ucfirst($r) ?></option><?php endforeach; ?>
            </select>
            <div id="roleSelfWarn" style="display:none;padding:.6rem .85rem;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.25);border-radius:8px;font-size:.76rem;color:#f87171;margin-bottom:.75rem;">
              <i class="bi bi-exclamation-triangle me-1"></i> Mengubah role akun sendiri ke level lebih rendah akan menghapus akses Super Admin Anda.
            </div>
            <button type="submit" style="background:linear-gradient(135deg,#a855f7,#6366f1);color:#fff;border:none;border-radius:10px;padding:.5rem 1.25rem;font-size:.85rem;font-weight:700;cursor:pointer;">
              <i class="bi bi-check2-circle me-1"></i>Simpan Role
            </button>
          </form>
        </div>

        <!-- Change Status -->
        <div class="edit-pane" id="pane-status" style="display:none;">
          <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="change_status"/>
            <input type="hidden" name="user_id" id="statusUserId"/>
            <label style="font-size:.78rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.5rem;display:block;">Status Baru</label>
            <div id="statusOptions" style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem;">
              <?php foreach ($STATUSES as $s): ?>
              <label style="cursor:pointer;">
                <input type="radio" name="new_status" value="<?= $s ?>" style="display:none;" class="status-radio">
                <span class="pill status-pill" data-val="<?= $s ?>" style="background:var(--bg-card);border:2px solid var(--border-glass);color:var(--text-secondary);cursor:pointer;padding:6px 16px;font-size:.82rem;">
                  <?= ucfirst($s) ?>
                </span>
              </label>
              <?php endforeach; ?>
            </div>
            <button type="submit" style="background:linear-gradient(135deg,#a855f7,#6366f1);color:#fff;border:none;border-radius:10px;padding:.5rem 1.25rem;font-size:.85rem;font-weight:700;cursor:pointer;">
              <i class="bi bi-check2-circle me-1"></i>Simpan Status
            </button>
          </form>
        </div>

        <!-- Change Plan -->
        <div class="edit-pane" id="pane-plan" style="display:none;">
          <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="change_plan"/>
            <input type="hidden" name="user_id" id="planUserId"/>
            <label style="font-size:.78rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.5rem;display:block;">Plan Baru</label>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem;">
              <?php foreach ($PLANS as $pl): ?>
              <label style="cursor:pointer;">
                <input type="radio" name="new_plan" value="<?= $pl ?>" style="display:none;" class="plan-radio">
                <span class="pill plan-pill" data-val="<?= $pl ?>" style="background:var(--bg-card);border:2px solid var(--border-glass);color:var(--text-secondary);cursor:pointer;padding:6px 16px;font-size:.82rem;"><?= ucfirst($pl) ?></span>
              </label>
              <?php endforeach; ?>
            </div>
            <button type="submit" style="background:linear-gradient(135deg,#a855f7,#6366f1);color:#fff;border:none;border-radius:10px;padding:.5rem 1.25rem;font-size:.85rem;font-weight:700;cursor:pointer;">
              <i class="bi bi-check2-circle me-1"></i>Simpan Plan
            </button>
          </form>
        </div>

        <!-- Reset Password -->
        <div class="edit-pane" id="pane-password" style="display:none;">
          <form method="POST" onsubmit="return confirmResetPass()">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="reset_password"/>
            <input type="hidden" name="user_id" id="passUserId"/>
            <div style="padding:.65rem .9rem;background:rgba(239,68,68,.07);border:1px solid rgba(239,68,68,.22);border-radius:10px;font-size:.78rem;color:#f87171;margin-bottom:1.1rem;">
              <i class="bi bi-shield-exclamation me-1"></i>Password baru akan langsung aktif. Beritahu pengguna setelah reset.
            </div>
            <div class="row g-3">
              <div class="col-sm-6">
                <label style="font-size:.78rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.4rem;display:block;">Password Baru</label>
                <input type="password" name="new_password" id="passNew" required minlength="8"
                  style="width:100%;background:rgba(255,255,255,.04);border:1px solid var(--border-glass);border-radius:10px;padding:.5rem .85rem;color:var(--text-primary);font-size:.88rem;outline:none;"
                  placeholder="Min. 8 karakter" />
              </div>
              <div class="col-sm-6">
                <label style="font-size:.78rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.4rem;display:block;">Konfirmasi Password</label>
                <input type="password" name="confirm_password" id="passConf" required minlength="8"
                  style="width:100%;background:rgba(255,255,255,.04);border:1px solid var(--border-glass);border-radius:10px;padding:.5rem .85rem;color:var(--text-primary);font-size:.88rem;outline:none;"
                  placeholder="Ulangi password" />
              </div>
            </div>
            <div id="passMismatch" style="display:none;margin-top:.5rem;font-size:.76rem;color:#f87171;"><i class="bi bi-x-circle me-1"></i>Password tidak cocok.</div>
            <button type="submit" style="margin-top:1rem;background:linear-gradient(135deg,#ef4444,#f97316);color:#fff;border:none;border-radius:10px;padding:.5rem 1.25rem;font-size:.85rem;font-weight:700;cursor:pointer;">
              <i class="bi bi-key-fill me-1"></i>Reset Password
            </button>
          </form>
        </div>

        <!-- Edit Info -->
        <div class="edit-pane" id="pane-info" style="display:none;">
          <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="reset_info"/>
            <input type="hidden" name="user_id" id="infoUserId"/>
            <div class="row g-3">
              <div class="col-sm-6">
                <label style="font-size:.78rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.4rem;display:block;">Nama Lengkap</label>
                <input type="text" name="new_name" id="infoName" required style="width:100%;background:rgba(255,255,255,.04);border:1px solid var(--border-glass);border-radius:10px;padding:.5rem .85rem;color:var(--text-primary);font-size:.88rem;outline:none;" />
              </div>
              <div class="col-sm-6">
                <label style="font-size:.78rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.4rem;display:block;">Email</label>
                <input type="email" name="new_email" id="infoEmailInput" required style="width:100%;background:rgba(255,255,255,.04);border:1px solid var(--border-glass);border-radius:10px;padding:.5rem .85rem;color:var(--text-primary);font-size:.88rem;outline:none;" />
              </div>
            </div>
            <button type="submit" style="margin-top:1rem;background:linear-gradient(135deg,#a855f7,#6366f1);color:#fff;border:none;border-radius:10px;padding:.5rem 1.25rem;font-size:.85rem;font-weight:700;cursor:pointer;">
              <i class="bi bi-save me-1"></i>Simpan Info
            </button>
          </form>
        </div>

      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/main.js"></script>
<script>
const SELF_ID = <?= (int)$user['id'] ?>;
let editModal;
document.addEventListener('DOMContentLoaded', function() {
  editModal = new bootstrap.Modal(document.getElementById('editModal'));
  document.getElementById('sidebarOverlay')?.addEventListener('click', function(){
    document.getElementById('mainSidebar').classList.remove('open');
    this.style.display='none';
  });
  // Status & plan radio styling
  document.querySelectorAll('.status-radio').forEach(r => {
    r.addEventListener('change', function(){
      document.querySelectorAll('.status-pill').forEach(p => { p.style.background='var(--bg-card)'; p.style.borderColor='var(--border-glass)'; p.style.color='var(--text-secondary)'; });
      this.nextElementSibling.style.background='rgba(168,85,247,.15)';
      this.nextElementSibling.style.borderColor='#a855f7';
      this.nextElementSibling.style.color='#c084fc';
    });
  });
  document.querySelectorAll('.plan-radio').forEach(r => {
    r.addEventListener('change', function(){
      document.querySelectorAll('.plan-pill').forEach(p => { p.style.background='var(--bg-card)'; p.style.borderColor='var(--border-glass)'; p.style.color='var(--text-secondary)'; });
      this.nextElementSibling.style.background='rgba(168,85,247,.15)';
      this.nextElementSibling.style.borderColor='#a855f7';
      this.nextElementSibling.style.color='#c084fc';
    });
  });
});

function switchTab(tab) {
  document.querySelectorAll('.edit-pane').forEach(p => p.style.display='none');
  document.querySelectorAll('.edit-tab').forEach(b => {
    b.style.background='var(--bg-card)'; b.style.borderColor='var(--border-glass)'; b.style.color='var(--text-secondary)';
  });
  document.getElementById('pane-'+tab).style.display='block';
  document.querySelector('[data-tab="'+tab+'"]').style.background='rgba(168,85,247,.15)';
  document.querySelector('[data-tab="'+tab+'"]').style.borderColor='#a855f7';
  document.querySelector('[data-tab="'+tab+'"]').style.color='#c084fc';
}

function confirmResetPass() {
  const p = document.getElementById('passNew').value;
  const c = document.getElementById('passConf').value;
  if (p !== c) {
    document.getElementById('passMismatch').style.display = 'block';
    return false;
  }
  return confirm('Reset password ' + document.getElementById('editName').textContent.trim() + '?');
}
document.addEventListener('input', function(e) {
  if (e.target.id === 'passNew' || e.target.id === 'passConf') {
    const match = document.getElementById('passNew').value === document.getElementById('passConf').value;
    document.getElementById('passMismatch').style.display = (!match && document.getElementById('passConf').value) ? 'block' : 'none';
  }
});

function openEditModal(u) {
  // Populate header
  document.getElementById('editAvatar').textContent = u.avatar || u.name.charAt(0).toUpperCase();
  document.getElementById('editName').innerHTML  = u.name + (u.id == SELF_ID ? ' <span style="font-size:.7rem;color:#c084fc;">(Akun Saya)</span>' : '');
  document.getElementById('editEmail').textContent = u.email;

  // Role tab
  document.getElementById('roleUserId').value = u.id;
  document.getElementById('roleSelect').value = u.role;
  document.getElementById('roleSelfWarn').style.display = (u.id == SELF_ID) ? 'block' : 'none';

  // Status tab
  document.getElementById('statusUserId').value = u.id;
  document.querySelectorAll('.status-radio').forEach(r => {
    r.checked = (r.value === u.status);
    if (r.checked) { r.nextElementSibling.style.background='rgba(168,85,247,.15)'; r.nextElementSibling.style.borderColor='#a855f7'; r.nextElementSibling.style.color='#c084fc'; }
    else { r.nextElementSibling.style.background='var(--bg-card)'; r.nextElementSibling.style.borderColor='var(--border-glass)'; r.nextElementSibling.style.color='var(--text-secondary)'; }
  });

  // Plan tab
  document.getElementById('planUserId').value = u.id;
  document.querySelectorAll('.plan-radio').forEach(r => {
    r.checked = (r.value === (u.plan||'free'));
    if (r.checked) { r.nextElementSibling.style.background='rgba(168,85,247,.15)'; r.nextElementSibling.style.borderColor='#a855f7'; r.nextElementSibling.style.color='#c084fc'; }
    else { r.nextElementSibling.style.background='var(--bg-card)'; r.nextElementSibling.style.borderColor='var(--border-glass)'; r.nextElementSibling.style.color='var(--text-secondary)'; }
  });

  // Info tab
  document.getElementById('infoUserId').value     = u.id;
  document.getElementById('infoName').value        = u.name;
  document.getElementById('infoEmailInput').value  = u.email;

  // Password tab
  document.getElementById('passUserId').value = u.id;
  document.getElementById('passNew').value    = '';
  document.getElementById('passConf').value   = '';
  document.getElementById('passMismatch').style.display = 'none';

  switchTab('role');
  editModal.show();
}
</script>
</body>
</html>
