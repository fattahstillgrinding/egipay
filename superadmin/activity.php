<?php
require_once __DIR__ . '/../includes/config.php';
requireSuperAdmin();

$user  = getCurrentUser();
$flash = getFlash();

// ── Filters ───────────────────────────────────────────────────
$search      = trim($_GET['q'] ?? '');
$filterAction = trim($_GET['action'] ?? '');
$filterUser   = (int)($_GET['uid'] ?? 0);
$filterDate   = trim($_GET['date'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 20;

$where  = [];
$params = [];
if ($search) {
    $where[]  = '(a.action LIKE ? OR a.description LIKE ? OR u.name LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($filterAction) {
    $where[]  = 'a.action = ?';
    $params[] = $filterAction;
}
if ($filterUser > 0) {
    $where[]  = 'a.user_id = ?';
    $params[] = $filterUser;
}
if ($filterDate) {
    $where[]  = 'DATE(a.created_at) = ?';
    $params[] = $filterDate;
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total      = dbFetchOne("SELECT COUNT(*) AS c FROM audit_logs a LEFT JOIN users u ON a.user_id=u.id $whereSQL", $params)['c'] ?? 0;
$totalPages = max(1, (int)ceil($total / $perPage));
$offset     = ($page - 1) * $perPage;

$logs = dbFetchAll(
    "SELECT a.*, u.name AS user_name, u.email AS user_email, u.role AS user_role, u.avatar AS user_avatar
     FROM audit_logs a
     LEFT JOIN users u ON a.user_id = u.id
     $whereSQL
     ORDER BY a.created_at DESC
     LIMIT $perPage OFFSET $offset",
    $params
);

// Distinct actions for filter dropdown
$actionList = dbFetchAll('SELECT DISTINCT action FROM audit_logs ORDER BY action ASC');

// Summary stats
$totalLogs   = dbFetchOne('SELECT COUNT(*) AS c FROM audit_logs')['c'] ?? 0;
$todayLogs   = dbFetchOne('SELECT COUNT(*) AS c FROM audit_logs WHERE DATE(created_at) = CURDATE()')['c'] ?? 0;
$failedLogins= dbFetchOne('SELECT COUNT(*) AS c FROM audit_logs WHERE action = "login_failed"')['c'] ?? 0;
$adminActions= dbFetchOne('SELECT COUNT(*) AS c FROM audit_logs WHERE action LIKE "admin_%"')['c'] ?? 0;

// Action color map
$actionColors = [
    'login'                 => '#10b981',
    'login_failed'          => '#ef4444',
    'logout'                => '#94a3b8',
    'register'              => '#6c63ff',
    'register_paid'         => '#a78bfa',
    'admin_change_role'     => '#f59e0b',
    'admin_change_status'   => '#f59e0b',
    'admin_change_plan'     => '#06b6d4',
    'admin_edit_user'       => '#06b6d4',
    'admin_wdr_approve'     => '#10b981',
    'admin_wdr_reject'      => '#ef4444',
    'admin_wdr_processing'  => '#f59e0b',
    'admin_approve_reg'     => '#10b981',
    'admin_reject_reg'      => '#ef4444',
    'admin_approve_tx'      => '#10b981',
    'admin_reject_tx'       => '#ef4444',
    'wallet_topup'          => '#34d399',
    'create_invoice'        => '#a78bfa',
    'pay_invoice'           => '#10b981',
];

function qstr(array $override = []): string {
    $p = array_merge($_GET, $override);
    unset($p['page']);
    return '?' . http_build_query(array_filter($p, fn($v) => $v !== ''));
}

function actionLabel(string $action): string {
    return ucfirst(str_replace('_', ' ', $action));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Log Aktivitas – Super Admin SolusiMu</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link href="../css/style.css" rel="stylesheet"/>
  <style>
    :root{--sa-primary:#a855f7;--sa-secondary:#f59e0b;}
    .sa-badge{background:linear-gradient(135deg,#a855f7,#6366f1);color:#fff;font-size:.6rem;font-weight:800;padding:2px 9px;border-radius:6px;text-transform:uppercase;letter-spacing:.08em;}
    .sa-active{background:linear-gradient(135deg,rgba(168,85,247,.18),rgba(99,102,241,.1))!important;border-left-color:#a855f7!important;color:#c084fc!important;}
    .stat-card{background:var(--bg-card);border:1px solid var(--border-glass);border-radius:16px;padding:1.1rem 1.4rem;}
    .pill{display:inline-block;font-size:.67rem;font-weight:700;padding:2px 10px;border-radius:20px;}
    .tbl th{font-size:.69rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);border:none;padding:.6rem .9rem;white-space:nowrap;}
    .tbl td{padding:.65rem .9rem;vertical-align:middle;border-top:1px solid rgba(255,255,255,.04);font-size:.8rem;}
    .tbl tr:hover>td{background:rgba(255,255,255,.02);}
    .action-dot{width:8px;height:8px;border-radius:50%;display:inline-block;flex-shrink:0;}
    .action-btn{padding:.3rem .7rem;border-radius:8px;font-size:.75rem;font-weight:700;cursor:pointer;text-decoration:none;display:inline-block;}
  </style>
</head>
<body>
<div class="page-loader" id="pageLoader"><div class="loader-logo"><img src="../media/logo/Screenshot_2026-02-28_133755-removebg-preview.png" alt="SolusiMu" style="width: 80px; height: auto;"></div><div class="loader-bar"><div class="loader-bar-fill"></div></div></div>
<div class="toast-container" id="toastContainer"></div>
<?php if ($flash): ?><div id="flashMessage" data-type="<?= $flash['type'] ?>" data-title="<?= htmlspecialchars($flash['title']) ?>" data-message="<?= htmlspecialchars($flash['message']) ?>" style="display:none"></div><?php endif; ?>
<div id="sidebarOverlay" style="position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:999;display:none;backdrop-filter:blur(4px);" class="d-lg-none"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="mainSidebar">
  <div class="sidebar-logo">
    <svg width="36" height="36" viewBox="0 0 42 42" fill="none"><defs><linearGradient id="sLg" x1="0" y1="0" x2="42" y2="42"><stop stop-color="#a855f7"/><stop offset="1" stop-color="#6366f1"/></linearGradient></defs><rect width="42" height="42" rx="12" fill="url(#sLg)"/><path d="M12 14h10a6 6 0 010 12H12V14zm0 6h8a2 2 0 000-6" fill="white" opacity=".95"/><circle cx="30" cy="28" r="3" fill="white" opacity=".8"/></svg>
    <span class="brand-text" style="font-size:1.1rem;">SolusiMu <span class="sa-badge">SU</span></span>
  </div>
  <ul class="sidebar-menu">
    <li class="sidebar-section-title">Super Admin</li>
    <li><a href="index.php"    class="sidebar-link"><span class="icon"><i class="bi bi-shield-shaded"></i></span>Platform Overview</a></li>
    <li><a href="users.php"    class="sidebar-link"><span class="icon"><i class="bi bi-people-fill"></i></span>Kelola Pengguna & Role</a></li>
    <li><a href="activity.php" class="sidebar-link sa-active"><span class="icon"><i class="bi bi-activity"></i></span>Log Aktivitas</a></li>
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
        <h1 style="font-size:1.4rem;font-weight:800;margin:0;">Log Aktivitas <span class="sa-badge ms-2">SUPER ADMIN</span></h1>
        <p style="font-size:.78rem;color:var(--text-muted);margin:0;">Rekam jejak seluruh aktivitas platform SolusiMu secara lengkap</p>
      </div>
    </div>
    <span style="font-size:.78rem;color:var(--text-muted);"><?= number_format($total) ?> entri ditemukan</span>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="stat-card" style="border-color:rgba(168,85,247,.2);text-align:center;">
        <div style="font-family:'Space Grotesk';font-size:1.6rem;font-weight:800;color:#c084fc;"><?= number_format($totalLogs) ?></div>
        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin-top:.25rem;">Total Log</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card" style="border-color:rgba(16,185,129,.18);text-align:center;">
        <div style="font-family:'Space Grotesk';font-size:1.6rem;font-weight:800;color:#10b981;"><?= number_format($todayLogs) ?></div>
        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin-top:.25rem;">Hari Ini</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card" style="border-color:rgba(239,68,68,.18);text-align:center;">
        <div style="font-family:'Space Grotesk';font-size:1.6rem;font-weight:800;color:#f87171;"><?= number_format($failedLogins) ?></div>
        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin-top:.25rem;">Login Gagal</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="stat-card" style="border-color:rgba(245,158,11,.18);text-align:center;">
        <div style="font-family:'Space Grotesk';font-size:1.6rem;font-weight:800;color:#f59e0b;"><?= number_format($adminActions) ?></div>
        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin-top:.25rem;">Aksi Admin</div>
      </div>
    </div>
  </div>

  <!-- Filter -->
  <div style="background:var(--bg-card);border:1px solid var(--border-glass);border-radius:14px;padding:1rem 1.25rem;margin-bottom:1.5rem;">
    <form method="GET" class="d-flex flex-wrap gap-2 align-items-center">
      <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Cari action / deskripsi / nama…"
        style="flex:1;min-width:180px;max-width:280px;background:rgba(255,255,255,.04);border:1px solid var(--border-glass);border-radius:10px;padding:.42rem .85rem;color:var(--text-primary);font-size:.82rem;outline:none;" />

      <select name="action" style="background:rgba(255,255,255,.04);border:1px solid var(--border-glass);border-radius:10px;padding:.42rem .75rem;color:var(--text-primary);font-size:.82rem;outline:none;">
        <option value="">Semua Aksi</option>
        <?php foreach ($actionList as $a): ?>
        <option value="<?= htmlspecialchars($a['action']) ?>" <?= $filterAction===$a['action']?'selected':'' ?>>
          <?= htmlspecialchars(actionLabel($a['action'])) ?>
        </option>
        <?php endforeach; ?>
      </select>

      <input type="date" name="date" value="<?= htmlspecialchars($filterDate) ?>"
        style="background:rgba(255,255,255,.04);border:1px solid var(--border-glass);border-radius:10px;padding:.42rem .75rem;color:var(--text-primary);font-size:.82rem;outline:none;color-scheme:dark;" />

      <button type="submit" style="background:linear-gradient(135deg,#a855f7,#6366f1);color:#fff;border:none;border-radius:10px;padding:.42rem 1rem;font-size:.82rem;font-weight:700;cursor:pointer;">
        <i class="bi bi-search me-1"></i>Filter
      </button>
      <?php if ($search||$filterAction||$filterUser||$filterDate): ?>
      <a href="activity.php" style="color:#ef4444;font-size:.78rem;text-decoration:none;padding:.42rem .5rem;"><i class="bi bi-x-circle me-1"></i>Reset</a>
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
            <th>Waktu</th>
            <th>Aksi</th>
            <th>Pengguna</th>
            <th>Deskripsi</th>
            <th>IP Address</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($logs)): ?>
          <tr><td colspan="6" style="text-align:center;padding:2.5rem;color:var(--text-muted);">Tidak ada log yang cocok dengan filter</td></tr>
          <?php endif; ?>
          <?php foreach ($logs as $log):
            $dot = $actionColors[$log['action']] ?? '#94a3b8';
          ?>
          <tr>
            <td style="color:var(--text-muted);font-size:.72rem;">#<?= $log['id'] ?></td>
            <td style="white-space:nowrap;font-size:.74rem;color:var(--text-muted);">
              <div><?= date('d M Y', strtotime($log['created_at'])) ?></div>
              <div style="font-size:.67rem;"><?= date('H:i:s', strtotime($log['created_at'])) ?></div>
            </td>
            <td style="white-space:nowrap;">
              <span style="display:inline-flex;align-items:center;gap:6px;">
                <span class="action-dot" style="background:<?= $dot ?>;"></span>
                <span class="pill" style="background:<?= $dot ?>1a;color:<?= $dot ?>;font-size:.68rem;"><?= htmlspecialchars(actionLabel($log['action'])) ?></span>
              </span>
            </td>
            <td>
              <?php if ($log['user_name']): ?>
              <div style="display:flex;align-items:center;gap:.5rem;">
                <div style="width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,#a855f7,#6366f1);display:flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:800;color:#fff;flex-shrink:0;">
                  <?= htmlspecialchars($log['user_avatar'] ?? mb_substr($log['user_name'],0,1)) ?>
                </div>
                <div>
                  <div style="font-size:.78rem;font-weight:600;"><?= htmlspecialchars($log['user_name']) ?></div>
                  <a href="<?= qstr(['uid'=>$log['user_id'],'action'=>'']) ?>" style="font-size:.68rem;color:#a78bfa;text-decoration:none;"><?= htmlspecialchars($log['user_email'] ?? '') ?></a>
                </div>
              </div>
              <?php else: ?>
              <span style="font-size:.75rem;color:var(--text-muted);">
                <?= $log['user_id'] ? 'User #'.$log['user_id'] : '<em>Guest</em>' ?>
              </span>
              <?php endif; ?>
            </td>
            <td style="max-width:320px;">
              <?php if ($log['description']): ?>
              <div style="font-size:.78rem;color:var(--text-secondary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($log['description']) ?>">
                <?= htmlspecialchars(mb_substr($log['description'], 0, 80)) ?>
                <?php if (mb_strlen($log['description']) > 80): ?>&hellip;<?php endif; ?>
              </div>
              <?php else: ?>
              <span style="font-size:.72rem;color:var(--text-muted);">—</span>
              <?php endif; ?>
            </td>
            <td style="font-size:.74rem;color:var(--text-muted);font-family:monospace;white-space:nowrap;">
              <?= htmlspecialchars($log['ip_address'] ?? '—') ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div style="padding:1rem 1.5rem;border-top:1px solid var(--border-glass);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;">
      <span style="font-size:.75rem;color:var(--text-muted);">Hal <?= $page ?> / <?= $totalPages ?> &nbsp;·&nbsp; <?= number_format($total) ?> entri</span>
      <div style="display:flex;gap:.35rem;">
        <?php if ($page > 1): ?>
        <a href="<?= qstr() ?>&page=<?= $page-1 ?>" class="action-btn" style="background:var(--bg-card);border:1px solid var(--border-glass);color:var(--text-primary);"><i class="bi bi-chevron-left"></i></a>
        <?php endif; ?>
        <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
        <a href="<?= qstr() ?>&page=<?= $p ?>" class="action-btn"
           style="background:<?= $p==$page?'linear-gradient(135deg,#a855f7,#6366f1)':'var(--bg-card)' ?>;border:1px solid <?= $p==$page?'transparent':'var(--border-glass)' ?>;color:<?= $p==$page?'#fff':'var(--text-primary)' ?>;"><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($page < $totalPages): ?>
        <a href="<?= qstr() ?>&page=<?= $page+1 ?>" class="action-btn" style="background:var(--bg-card);border:1px solid var(--border-glass);color:var(--text-primary);"><i class="bi bi-chevron-right"></i></a>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/main.js"></script>
<script>
document.getElementById('sidebarOverlay')?.addEventListener('click', function(){
  document.getElementById('mainSidebar').classList.remove('open');
  this.style.display='none';
});
</script>
</body>
</html>
