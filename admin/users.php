<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();

$user  = getCurrentUser();
$flash = getFlash();

// ── Handle POST Actions ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action    = $_POST['action']    ?? '';
    $targetId  = (int)($_POST['user_id'] ?? 0);

    // Prevent acting on self
    if ($targetId === (int)$_SESSION['user_id']) {
        setFlash('error', 'Aksi Ditolak', 'Anda tidak dapat mengubah akun Anda sendiri melalui panel ini.');
        redirect(BASE_URL . '/admin/users.php');
    }

    if ($action === 'change_status' && $targetId > 0) {
        $newStatus = $_POST['new_status'] ?? '';
        if (in_array($newStatus, ['active', 'inactive', 'suspended'])) {
            dbExecute('UPDATE users SET status=? WHERE id=?', [$newStatus, $targetId]);
            auditLog((int)$_SESSION['user_id'], 'admin_change_status', "User #$targetId → $newStatus");
            setFlash('success', 'Status Diperbarui', "Status pengguna #$targetId berubah menjadi $newStatus.");
        }
    }

    if ($action === 'change_role' && $targetId > 0) {
        if (!isSuperAdmin()) {
            setFlash('error', 'Akses Ditolak', 'Hanya Super Admin yang dapat mengubah role pengguna.');
            redirect(BASE_URL . '/admin/users.php');
        }
        $newRole = $_POST['new_role'] ?? '';
        if (in_array($newRole, ['superadmin', 'admin', 'merchant', 'customer'])) {
            dbExecute('UPDATE users SET role=? WHERE id=?', [$newRole, $targetId]);
            auditLog((int)$_SESSION['user_id'], 'admin_change_role', "User #$targetId role → $newRole");
            setFlash('success', 'Role Diperbarui', "Role pengguna #$targetId berubah menjadi $newRole.");
        }
    }

    if ($action === 'change_plan' && $targetId > 0) {
        $newPlan = $_POST['new_plan'] ?? '';
        if (in_array($newPlan, ['starter', 'business', 'enterprise'])) {
            dbExecute('UPDATE users SET plan=? WHERE id=?', [$newPlan, $targetId]);
            auditLog((int)$_SESSION['user_id'], 'admin_change_plan', "User #$targetId plan → $newPlan");
            setFlash('success', 'Paket Diperbarui', "Paket pengguna #$targetId berubah menjadi $newPlan.");
        }
    }

    if ($action === 'delete_user' && $targetId > 0) {
        dbExecute('DELETE FROM users WHERE id=?', [$targetId]);
        auditLog((int)$_SESSION['user_id'], 'admin_delete_user', "User #$targetId dihapus");
        setFlash('success', 'Pengguna Dihapus', "Pengguna #$targetId berhasil dihapus.");
    }

    redirect(BASE_URL . '/admin/users.php');
}

// ── Filters ──────────────────────────────────────────────────
$search    = trim($_GET['q']      ?? '');
$roleFilter  = $_GET['role']     ?? '';
$statusFilter = $_GET['status']  ?? '';
$planFilter  = $_GET['plan']     ?? '';
$page        = max(1, (int)($_GET['page'] ?? 1));
$perPage     = 15;
$offset      = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];
if ($search) {
    $where[]  = '(u.name LIKE ? OR u.email LIKE ? OR u.member_code LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($roleFilter)   { $where[] = 'u.role=?';   $params[] = $roleFilter; }
if ($statusFilter) { $where[] = 'u.status=?'; $params[] = $statusFilter; }
if ($planFilter)   { $where[] = 'u.plan=?';   $params[] = $planFilter; }

$whereStr = implode(' AND ', $where);
$total    = (int)(dbFetchOne("SELECT COUNT(*) AS cnt FROM users u WHERE $whereStr", $params)['cnt'] ?? 0);
$pages    = max(1, (int)ceil($total / $perPage));

$users = dbFetchAll(
    "SELECT u.*, w.balance,
            rp.status AS reg_status, rp.payment_method AS reg_pay_method, rp.paid_at AS reg_paid_at
     FROM users u
     LEFT JOIN wallets w ON w.user_id = u.id
     LEFT JOIN (
         SELECT email, status, payment_method, paid_at
         FROM registration_payments
         WHERE status='paid'
         GROUP BY email
     ) rp ON rp.email = u.email
     WHERE $whereStr ORDER BY u.created_at DESC LIMIT $perPage OFFSET $offset",
    $params
);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Manajemen Pengguna – Admin EgiPay</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link href="../css/style.css" rel="stylesheet"/>
  <style>
    .admin-badge { background:linear-gradient(135deg,#f72585,#b5179e);color:#fff;font-size:0.65rem;font-weight:700;padding:2px 8px;border-radius:6px;text-transform:uppercase;letter-spacing:0.06em;vertical-align:middle; }
    .sidebar-link.admin-active { background:linear-gradient(135deg,rgba(247,37,133,0.15),rgba(108,99,255,0.1)) !important; border-left-color:#f72585 !important; color:#f72585 !important; }
    .filter-bar { background:var(--bg-card);border:1px solid var(--border-glass);border-radius:14px;padding:1rem 1.25rem; }
    .role-badge-admin    { background:rgba(247,37,133,0.15);color:#f72585;border:1px solid rgba(247,37,133,0.3);border-radius:6px;padding:2px 8px;font-size:0.7rem;font-weight:700; }
    .role-badge-merchant { background:rgba(108,99,255,0.15);color:#a78bfa;border:1px solid rgba(108,99,255,0.3);border-radius:6px;padding:2px 8px;font-size:0.7rem;font-weight:700; }
    .role-badge-customer { background:rgba(16,185,129,0.12);color:#10b981;border:1px solid rgba(16,185,129,0.25);border-radius:6px;padding:2px 8px;font-size:0.7rem;font-weight:700; }
  </style>
</head>
<body>
<div class="page-loader" id="pageLoader"><div class="loader-logo"><svg width="60" height="60" viewBox="0 0 60 60" fill="none"><defs><linearGradient id="lg1" x1="0" y1="0" x2="60" y2="60"><stop stop-color="#f72585"/><stop offset="1" stop-color="#6c63ff"/></linearGradient></defs><rect width="60" height="60" rx="16" fill="url(#lg1)"/><path d="M18 20h14a8 8 0 010 16H18V20zm0 8h12a4 4 0 000-8" fill="white" opacity="0.9"/><circle cx="42" cy="40" r="4" fill="white" opacity="0.7"/></svg></div><div class="loader-bar"><div class="loader-bar-fill"></div></div></div>
<div class="toast-container" id="toastContainer"></div>
<?php if ($flash): ?><div id="flashMessage" data-type="<?= $flash['type'] ?>" data-title="<?= htmlspecialchars($flash['title']) ?>" data-message="<?= htmlspecialchars($flash['message']) ?>" style="display:none"></div><?php endif; ?>

<div id="sidebarOverlay" style="position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:999;display:none;backdrop-filter:blur(4px);" class="d-lg-none"></div>

<aside class="sidebar" id="mainSidebar">
  <div class="sidebar-logo">
    <svg width="36" height="36" viewBox="0 0 42 42" fill="none">
      <defs><linearGradient id="sLg" x1="0" y1="0" x2="42" y2="42"><stop stop-color="#f72585"/><stop offset="1" stop-color="#6c63ff"/></linearGradient></defs>
      <rect width="42" height="42" rx="12" fill="url(#sLg)"/>
      <path d="M12 14h10a6 6 0 010 12H12V14zm0 6h8a2 2 0 000-6" fill="white" opacity="0.95"/>
      <circle cx="30" cy="28" r="3" fill="white" opacity="0.8"/>
    </svg>
    <span class="brand-text" style="font-size:1.1rem;">EgiPay <span class="admin-badge">Admin</span></span>
  </div>
  <ul class="sidebar-menu">
    <li class="sidebar-section-title">Dashboard</li>
    <li><a href="index.php" class="sidebar-link"><span class="icon"><i class="bi bi-speedometer2"></i></span>Overview</a></li>
    <li class="sidebar-section-title">Manajemen</li>
    <li><a href="users.php" class="sidebar-link admin-active"><span class="icon"><i class="bi bi-people-fill"></i></span>Pengguna</a></li>
    <li><a href="transactions.php" class="sidebar-link"><span class="icon"><i class="bi bi-arrow-left-right"></i></span>Semua Transaksi</a></li>
    <li><a href="registrations.php" class="sidebar-link"><span class="icon"><i class="bi bi-person-check-fill"></i></span>Registrasi Member</a></li>
    <li><a href="withdrawals.php" class="sidebar-link"><span class="icon"><i class="bi bi-cash-coin"></i></span>Penarikan Dana<?php $__wdrPending=(int)(dbFetchOne('SELECT COUNT(*) AS c FROM withdrawals WHERE status="pending"')['c']??0);if($__wdrPending>0):?><span style="margin-left:auto;background:#f72585;color:#fff;font-size:.6rem;font-weight:700;border-radius:20px;padding:2px 7px;"><?=$__wdrPending?></span><?php endif;?></a></li>
    <li class="sidebar-section-title">Lainnya</li>
    <?php if (isSuperAdmin()): ?>
    <li><a href="../superadmin/index.php" class="sidebar-link" style="color:#c084fc;"><span class="icon"><i class="bi bi-shield-shaded"></i></span>Super Admin Panel</a></li>
    <?php endif; ?>
    <li><a href="../dashboard.php" class="sidebar-link"><span class="icon"><i class="bi bi-house-fill"></i></span>Kembali ke Beranda</a></li>
    <li><a href="../logout.php" class="sidebar-link" style="color:#ef4444;"><span class="icon"><i class="bi bi-box-arrow-left"></i></span>Keluar</a></li>
  </ul>
  <div class="sidebar-bottom">
    <div class="sidebar-profile">
      <div class="profile-avatar-sm" style="background:linear-gradient(135deg,#f72585,#6c63ff);"><?= htmlspecialchars($user['avatar'] ?? '?') ?></div>
      <div><div class="profile-name"><?= htmlspecialchars($user['name']) ?></div><div class="profile-role" style="color:#f72585;">Administrator</div></div>
    </div>
  </div>
</aside>

<main class="main-content">
  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div class="d-flex align-items-center gap-3">
      <button class="btn d-lg-none" id="sidebarToggle" style="background:var(--bg-card);border:1px solid var(--border-glass);border-radius:10px;color:var(--text-primary);padding:8px 12px;"><i class="bi bi-list fs-5"></i></button>
      <div>
        <h1 class="dash-title">Manajemen Pengguna</h1>
        <p class="dash-subtitle">Total <?= number_format($total) ?> pengguna terdaftar</p>
      </div>
    </div>
  </div>

  <!-- Filter Bar -->
  <div class="filter-bar mb-4">
    <form method="GET" action="users.php" class="row g-2 align-items-end">
      <div class="col-sm-4 col-lg-3">
        <label style="font-size:0.72rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;">Cari</label>
        <div style="position:relative;">
          <i class="bi bi-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:0.8rem;"></i>
          <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Nama atau email..."
            style="background:var(--bg-dark);border:1px solid var(--border-glass);border-radius:10px;color:var(--text-primary);padding:7px 12px 7px 32px;font-size:0.83rem;outline:none;width:100%;"/>
        </div>
      </div>
      <div class="col-sm-2">
        <label style="font-size:0.72rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;">Role</label>
        <select name="role" class="form-select form-select-sm" style="background:var(--bg-dark);border:1px solid var(--border-glass);color:var(--text-primary);border-radius:10px;">
          <option value="">Semua</option>
          <option value="admin"    <?= $roleFilter==='admin'   ? 'selected' : '' ?>>Admin</option>
          <option value="merchant" <?= $roleFilter==='merchant'? 'selected' : '' ?>>Merchant</option>
          <option value="customer" <?= $roleFilter==='customer'? 'selected' : '' ?>>Customer</option>
        </select>
      </div>
      <div class="col-sm-2">
        <label style="font-size:0.72rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;">Status</label>
        <select name="status" class="form-select form-select-sm" style="background:var(--bg-dark);border:1px solid var(--border-glass);color:var(--text-primary);border-radius:10px;">
          <option value="">Semua</option>
          <option value="active"    <?= $statusFilter==='active'   ? 'selected' : '' ?>>Aktif</option>
          <option value="inactive"  <?= $statusFilter==='inactive' ? 'selected' : '' ?>>Nonaktif</option>
          <option value="suspended" <?= $statusFilter==='suspended'? 'selected' : '' ?>>Suspended</option>
        </select>
      </div>
      <div class="col-sm-2">
        <label style="font-size:0.72rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;">Paket</label>
        <select name="plan" class="form-select form-select-sm" style="background:var(--bg-dark);border:1px solid var(--border-glass);color:var(--text-primary);border-radius:10px;">
          <option value="">Semua</option>
          <option value="starter"    <?= $planFilter==='starter'   ? 'selected' : '' ?>>Starter</option>
          <option value="business"   <?= $planFilter==='business'  ? 'selected' : '' ?>>Business</option>
          <option value="enterprise" <?= $planFilter==='enterprise'? 'selected' : '' ?>>Enterprise</option>
        </select>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-sm px-4" style="background:linear-gradient(135deg,#6c63ff,#00d4ff);color:#fff;border:none;border-radius:10px;padding:7px 16px;">
          <i class="bi bi-search me-1"></i>Filter
        </button>
        <a href="users.php" class="btn btn-sm px-3 ms-1" style="background:var(--bg-card);border:1px solid var(--border-glass);color:var(--text-muted);border-radius:10px;padding:7px 14px;">Reset</a>
      </div>
    </form>
  </div>

  <!-- Users Table -->
  <div class="glass-table-wrapper animate-on-scroll">
    <?php if ($users): ?>
    <div class="table-responsive">
      <table class="glass-table" id="usersTable">
        <thead>
          <tr>
            <th>#ID</th>
            <th>Kode Member</th>
            <th>Pengguna</th>
            <th>Role</th>
            <th>Paket</th>
            <th>Saldo</th>
            <th>Status Member</th>
            <th>Registrasi</th>
            <th>Daftar</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u):
            $isSelf = ($u['id'] == $_SESSION['user_id']);
          ?>
          <tr>
            <td style="color:var(--text-muted);font-size:0.75rem;">#<?= $u['id'] ?></td>
            <td>
              <?php if ($u['member_code']): ?>
              <span style="font-family:'Space Grotesk';font-size:0.78rem;font-weight:700;background:linear-gradient(135deg,rgba(247,37,133,.12),rgba(108,99,255,.1));color:#f72585;border:1px solid rgba(247,37,133,.25);border-radius:8px;padding:3px 9px;letter-spacing:.05em;white-space:nowrap;">
                <?= htmlspecialchars($u['member_code']) ?>
              </span>
              <?php else: ?>
              <span style="font-size:0.72rem;color:var(--text-muted);">—</span>
              <?php endif; ?>
            </td>
            <td>
              <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,#6c63ff,#00d4ff);display:flex;align-items:center;justify-content:center;font-size:0.8rem;font-weight:700;color:#fff;flex-shrink:0;">
                  <?= htmlspecialchars($u['avatar'] ?? '?') ?>
                </div>
                <div>
                  <div style="font-weight:600;font-size:0.83rem;"><?= htmlspecialchars($u['name']) ?> <?= $isSelf ? '<span style="font-size:0.65rem;color:#10b981;">(Anda)</span>' : '' ?></div>
                  <div style="font-size:0.72rem;color:var(--text-muted);"><?= htmlspecialchars($u['email']) ?></div>
                </div>
              </div>
            </td>
            <td>
              <span class="role-badge-<?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span>
            </td>
            <td><span style="font-size:0.75rem;color:var(--text-secondary);"><?= ucfirst($u['plan']) ?></span></td>
            <td style="font-family:'Space Grotesk';font-weight:700;font-size:0.83rem;"><?= formatRupiah($u['balance'] ?? 0) ?></td>
            <td>
              <span class="tx-badge tx-<?= $u['status'] === 'active' ? 'success' : ($u['status'] === 'suspended' ? 'failed' : 'pending') ?>">
                <?= ucfirst($u['status']) ?>
              </span>
            </td>
            <td>
              <?php if ($u['reg_status'] === 'paid'): ?>
              <span style="background:rgba(16,185,129,.12);color:#10b981;border:1px solid rgba(16,185,129,.25);border-radius:6px;padding:2px 8px;font-size:0.7rem;font-weight:700;">
                <i class="bi bi-check-circle-fill me-1"></i>Lunas
              </span>
              <div style="font-size:0.68rem;color:var(--text-muted);margin-top:2px;"><?= $u['reg_pay_method'] ?></div>
              <?php else: ?>
              <span style="background:rgba(100,116,139,.1);color:#94a3b8;border:1px solid rgba(100,116,139,.2);border-radius:6px;padding:2px 8px;font-size:0.7rem;font-weight:700;">Admin</span>
              <?php endif; ?>
            </td>
            <td style="font-size:0.75rem;color:var(--text-muted);white-space:nowrap;"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
            <td>
              <?php if (!$isSelf): ?>
              <div class="dropdown">
                <button class="btn btn-sm dropdown-toggle"
                  data-bs-toggle="dropdown"
                  style="background:transparent;border:1px solid var(--border-glass);color:var(--text-muted);border-radius:8px;font-size:0.7rem;padding:4px 10px;">
                  Aksi <i class="bi bi-chevron-down ms-1"></i>
                </button>
                <ul class="dropdown-menu" style="background:rgba(15,15,30,0.97);border:1px solid var(--border-glass);border-radius:12px;padding:0.5rem;min-width:200px;">
                  <!-- Change Status -->
                  <li style="padding:0.25rem 0.5rem;font-size:0.7rem;color:var(--text-muted);text-transform:uppercase;font-weight:700;">Ubah Status</li>
                  <?php foreach (['active'=>'Aktifkan','inactive'=>'Nonaktifkan','suspended'=>'Suspend'] as $s=>$label): ?>
                  <?php if ($s !== $u['status']): ?>
                  <li>
                    <form method="POST" style="margin:0;">
                      <?= csrfField() ?>
                      <input type="hidden" name="action" value="change_status">
                      <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                      <input type="hidden" name="new_status" value="<?= $s ?>">
                      <button type="submit" class="dropdown-item"
                        style="background:none;border:none;color:var(--text-secondary);font-size:0.78rem;padding:0.35rem 0.75rem;width:100%;text-align:left;border-radius:8px;cursor:pointer;">
                        <i class="bi bi-circle-fill me-2" style="font-size:0.5rem;color:<?= $s==='active'?'#10b981':($s==='suspended'?'#ef4444':'#f59e0b') ?>;"></i><?= $label ?>
                      </button>
                    </form>
                  </li>
                  <?php endif; ?>
                  <?php endforeach; ?>

                  <li><hr class="dropdown-divider" style="border-color:var(--border-glass);margin:0.25rem 0;"></li>

                  <!-- Change Role -->
                  <li style="padding:0.25rem 0.5rem;font-size:0.7rem;color:var(--text-muted);text-transform:uppercase;font-weight:700;">Ubah Role</li>
                  <?php foreach (['admin','merchant','customer'] as $r): ?>
                  <?php if ($r !== $u['role']): ?>
                  <li>
                    <form method="POST" style="margin:0;">
                      <?= csrfField() ?>
                      <input type="hidden" name="action" value="change_role">
                      <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                      <input type="hidden" name="new_role" value="<?= $r ?>">
                      <button type="submit" class="dropdown-item"
                        style="background:none;border:none;color:var(--text-secondary);font-size:0.78rem;padding:0.35rem 0.75rem;width:100%;text-align:left;border-radius:8px;cursor:pointer;">
                        <i class="bi bi-shield me-2"></i>Jadikan <?= ucfirst($r) ?>
                      </button>
                    </form>
                  </li>
                  <?php endif; ?>
                  <?php endforeach; ?>

                  <li><hr class="dropdown-divider" style="border-color:var(--border-glass);margin:0.25rem 0;"></li>

                  <!-- Change Plan -->
                  <li style="padding:0.25rem 0.5rem;font-size:0.7rem;color:var(--text-muted);text-transform:uppercase;font-weight:700;">Ubah Paket</li>
                  <?php foreach (['starter','business','enterprise'] as $p): ?>
                  <?php if ($p !== $u['plan']): ?>
                  <li>
                    <form method="POST" style="margin:0;">
                      <?= csrfField() ?>
                      <input type="hidden" name="action" value="change_plan">
                      <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                      <input type="hidden" name="new_plan" value="<?= $p ?>">
                      <button type="submit" class="dropdown-item"
                        style="background:none;border:none;color:var(--text-secondary);font-size:0.78rem;padding:0.35rem 0.75rem;width:100%;text-align:left;border-radius:8px;cursor:pointer;">
                        <i class="bi bi-diamond me-2"></i>Ubah ke <?= ucfirst($p) ?>
                      </button>
                    </form>
                  </li>
                  <?php endif; ?>
                  <?php endforeach; ?>

                  <li><hr class="dropdown-divider" style="border-color:var(--border-glass);margin:0.25rem 0;"></li>

                  <!-- Delete -->
                  <li>
                    <form method="POST" style="margin:0;" onsubmit="return confirm('Yakin menghapus pengguna ini? Data akan hilang permanen.');">
                      <?= csrfField() ?>
                      <input type="hidden" name="action" value="delete_user">
                      <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                      <button type="submit" class="dropdown-item"
                        style="background:none;border:none;color:#ef4444;font-size:0.78rem;padding:0.35rem 0.75rem;width:100%;text-align:left;border-radius:8px;cursor:pointer;">
                        <i class="bi bi-trash me-2"></i>Hapus Pengguna
                      </button>
                    </form>
                  </li>
                </ul>
              </div>
              <?php else: ?>
              <span style="font-size:0.72rem;color:var(--text-muted);">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
    <div class="d-flex align-items-center justify-content-between p-4 pt-2 flex-wrap gap-2">
      <span style="font-size:0.78rem;color:var(--text-muted);">
        Menampilkan <?= (($page-1)*$perPage) + 1 ?>–<?= min($page*$perPage, $total) ?> dari <?= $total ?> pengguna
      </span>
      <div style="display:flex;gap:4px;">
        <?php
        $baseUrl = 'users.php?' . http_build_query(array_filter(['q'=>$search,'role'=>$roleFilter,'status'=>$statusFilter,'plan'=>$planFilter]));
        for ($p = 1; $p <= $pages; $p++):
        ?>
        <a href="<?= $baseUrl ?>&page=<?= $p ?>"
          style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:8px;font-size:0.8rem;font-weight:600;text-decoration:none;
            background:<?= $p===$page ? 'linear-gradient(135deg,#6c63ff,#00d4ff)' : 'var(--bg-card)' ?>;
            color:<?= $p===$page ? '#fff' : 'var(--text-muted)' ?>;
            border:1px solid <?= $p===$page ? 'transparent' : 'var(--border-glass)' ?>;"><?= $p ?></a>
        <?php endfor; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div style="text-align:center;padding:4rem 2rem;color:var(--text-muted);">
      <i class="bi bi-people" style="font-size:3rem;display:block;margin-bottom:0.75rem;"></i>
      Tidak ada pengguna yang ditemukan.
    </div>
    <?php endif; ?>
  </div>

  <div style="margin-top:2rem;padding-top:1rem;border-top:1px solid var(--border-glass);text-align:center;color:var(--text-muted);font-size:0.75rem;">
    EgiPay Admin Panel v<?= SITE_VERSION ?> &nbsp;·&nbsp; <?= date('d M Y H:i') ?> WIB
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/main.js"></script>
<script>
document.getElementById('sidebarToggle')?.addEventListener('click', () => {
  const s = document.getElementById('mainSidebar');
  const o = document.getElementById('sidebarOverlay');
  s.classList.toggle('open');
  o.style.display = s.classList.contains('open') ? 'block' : 'none';
});
document.getElementById('sidebarOverlay')?.addEventListener('click', () => {
  document.getElementById('mainSidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').style.display = 'none';
});
function toggleSidebarSubmenu(el) {
  const li = el.closest('.sidebar-has-submenu');
  const submenu = li.querySelector('.sidebar-submenu');
  const isOpen = submenu.classList.contains('open');
  document.querySelectorAll('.sidebar-submenu.open').forEach(m => { m.classList.remove('open'); m.closest('.sidebar-has-submenu').querySelector('.sidebar-link-toggle').classList.remove('open'); });
  if (!isOpen) { submenu.classList.add('open'); el.classList.add('open'); }
}
</script>
</body>
</html>
