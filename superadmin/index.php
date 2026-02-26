<?php
require_once __DIR__ . '/../includes/config.php';
requireSuperAdmin();

$user  = getCurrentUser();
$flash = getFlash();

// ── Platform-wide Stats ────────────────────────────────────────
$usersTotal     = dbFetchOne('SELECT COUNT(*) AS c FROM users')['c'] ?? 0;
$usersBySuperAdmin = dbFetchOne('SELECT COUNT(*) AS c FROM users WHERE role="superadmin"')['c'] ?? 0;
$usersByAdmin   = dbFetchOne('SELECT COUNT(*) AS c FROM users WHERE role="admin"')['c'] ?? 0;
$usersByMerch   = dbFetchOne('SELECT COUNT(*) AS c FROM users WHERE role="merchant"')['c'] ?? 0;
$usersActive    = dbFetchOne('SELECT COUNT(*) AS c FROM users WHERE status="active"')['c'] ?? 0;
$usersSuspended = dbFetchOne('SELECT COUNT(*) AS c FROM users WHERE status="suspended"')['c'] ?? 0;
$newThisMonth   = dbFetchOne('SELECT COUNT(*) AS c FROM users WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())')['c'] ?? 0;

$txAll     = dbFetchOne('SELECT COUNT(*) AS c, COALESCE(SUM(amount),0) AS vol, COALESCE(SUM(fee),0) AS fees FROM transactions WHERE status="success"');
$txPending = dbFetchOne('SELECT COUNT(*) AS c FROM transactions WHERE status="pending"')['c'] ?? 0;
$txFailed  = dbFetchOne('SELECT COUNT(*) AS c FROM transactions WHERE status IN("failed","cancelled")')['c'] ?? 0;
$txToday   = dbFetchOne('SELECT COUNT(*) AS c, COALESCE(SUM(amount),0) AS vol FROM transactions WHERE status="success" AND DATE(created_at)=CURDATE()');

$wdrStats = dbFetchOne('SELECT COUNT(*) AS total, COUNT(CASE WHEN status="pending" THEN 1 END) AS pending, COALESCE(SUM(CASE WHEN status="approved" THEN net_amount END),0) AS vol_paid FROM withdrawals');

$totalWalletBalance = dbFetchOne('SELECT COALESCE(SUM(balance),0) AS total FROM wallets')['total'] ?? 0;
$totalWalletLocked  = dbFetchOne('SELECT COALESCE(SUM(locked),0) AS total FROM wallets')['total'] ?? 0;

$regStats = dbFetchOne('SELECT COUNT(CASE WHEN status="paid" THEN 1 END) AS paid, COUNT(CASE WHEN status="pending" THEN 1 END) AS pending, COALESCE(SUM(CASE WHEN status="paid" THEN amount END),0) AS revenue FROM registration_payments');

// ── Recent Audit Activity ──────────────────────────────────────
$recentActivity = dbFetchAll(
    'SELECT a.*, u.name AS user_name, u.role AS user_role
     FROM audit_logs a
     LEFT JOIN users u ON a.user_id = u.id
     ORDER BY a.created_at DESC LIMIT 12'
);

// ── Monthly transaction chart ──────────────────────────────────
$chartData = dbFetchAll(
    'SELECT DATE_FORMAT(created_at, "%Y-%m") AS mon,
            COALESCE(SUM(amount),0) AS vol,
            COUNT(*) AS cnt
     FROM transactions WHERE status="success"
       AND created_at >= NOW() - INTERVAL 6 MONTH
     GROUP BY DATE_FORMAT(created_at, "%Y-%m")
     ORDER BY mon ASC'
);
$chartLabels = [];
$chartVol    = [];
$chartCnt    = [];
foreach ($chartData as $r) {
    $chartLabels[] = date('M Y', strtotime($r['mon'] . '-01'));
    $chartVol[]    = (float)$r['vol'];
    $chartCnt[]    = (int)$r['cnt'];
}

// ── Role distribution for donut ───────────────────────────────
$roleData = dbFetchAll('SELECT role, COUNT(*) AS cnt FROM users GROUP BY role ORDER BY cnt DESC');

// ── Top active users (by tx count) ────────────────────────────
$topUsers = dbFetchAll(
    'SELECT u.name, u.email, u.role, u.avatar, COUNT(t.id) AS tx_count, COALESCE(SUM(t.amount),0) AS tx_vol
     FROM users u
     LEFT JOIN transactions t ON t.user_id = u.id AND t.status="success"
     GROUP BY u.id
     ORDER BY tx_count DESC LIMIT 5'
);

// ── Systems Health ─────────────────────────────────────────────
$dbCheck = dbFetchOne('SELECT 1 AS ok')['ok'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Super Admin – SOLUSIMU</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link href="../css/style.css" rel="stylesheet"/>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <style>
    :root { --sa-primary: #a855f7; --sa-secondary: #f59e0b; --sa-accent: #06b6d4; }
    .sa-badge { background: linear-gradient(135deg,#a855f7,#6366f1); color:#fff; font-size:.6rem; font-weight:800; padding:2px 9px; border-radius:6px; text-transform:uppercase; letter-spacing:.08em; }
    .sa-active { background:linear-gradient(135deg,rgba(168,85,247,.18),rgba(99,102,241,.1)) !important; border-left-color:#a855f7 !important; color:#c084fc !important; }
    .stat-card  { background:var(--bg-card); border:1px solid var(--border-glass); border-radius:16px; padding:1.25rem 1.5rem; }
    .stat-val   { font-family:'Space Grotesk',sans-serif; font-size:1.7rem; font-weight:800; line-height:1.1; }
    .stat-lbl   { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.07em; color:var(--text-muted); margin-top:.3rem; }
    .activity-item { display:flex; gap:.75rem; padding:.65rem 0; border-bottom:1px solid rgba(255,255,255,.04); align-items:flex-start; }
    .activity-item:last-child { border-bottom:none; }
    .activity-dot { width:8px; height:8px; border-radius:50%; margin-top:.35rem; flex-shrink:0; }
    .health-chip  { display:inline-flex; align-items:center; gap:6px; padding:.3rem .85rem; border-radius:20px; font-size:.75rem; font-weight:700; }
  </style>
</head>
<body>
<div class="page-loader" id="pageLoader">
  <div class="loader-logo">
    <svg width="60" height="60" viewBox="0 0 60 60" fill="none"><defs><linearGradient id="lg1" x1="0" y1="0" x2="60" y2="60"><stop stop-color="#a855f7"/><stop offset="1" stop-color="#6366f1"/></linearGradient></defs><rect width="60" height="60" rx="16" fill="url(#lg1)"/><path d="M18 20h14a8 8 0 010 16H18V20zm0 8h12a4 4 0 000-8" fill="white" opacity=".9"/><circle cx="42" cy="40" r="4" fill="white" opacity=".7"/></svg>
  </div>
  <div class="loader-bar"><div class="loader-bar-fill"></div></div>
</div>
<div class="toast-container" id="toastContainer"></div>
<?php if ($flash): ?><div id="flashMessage" data-type="<?= $flash['type'] ?>" data-title="<?= htmlspecialchars($flash['title']) ?>" data-message="<?= htmlspecialchars($flash['message']) ?>" style="display:none"></div><?php endif; ?>
<div id="sidebarOverlay" style="position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:999;display:none;backdrop-filter:blur(4px);" class="d-lg-none"></div>

<!-- ====== SIDEBAR ====== -->
<aside class="sidebar" id="mainSidebar">
  <div class="sidebar-logo">
    <svg width="36" height="36" viewBox="0 0 42 42" fill="none"><defs><linearGradient id="sLg" x1="0" y1="0" x2="42" y2="42"><stop stop-color="#a855f7"/><stop offset="1" stop-color="#6366f1"/></linearGradient></defs><rect width="42" height="42" rx="12" fill="url(#sLg)"/><path d="M12 14h10a6 6 0 010 12H12V14zm0 6h8a2 2 0 000-6" fill="white" opacity=".95"/><circle cx="30" cy="28" r="3" fill="white" opacity=".8"/></svg>
    <span class="brand-text" style="font-size:1.1rem;">EgiPay <span class="sa-badge">SU</span></span>
  </div>
  <ul class="sidebar-menu">
    <li class="sidebar-section-title">Super Admin</li>
    <li><a href="index.php"    class="sidebar-link sa-active"><span class="icon"><i class="bi bi-shield-shaded"></i></span>Platform Overview</a></li>
    <li><a href="users.php"    class="sidebar-link"><span class="icon"><i class="bi bi-people-fill"></i></span>Kelola Pengguna & Role</a></li>
    <li><a href="activity.php" class="sidebar-link"><span class="icon"><i class="bi bi-activity"></i></span>Log Aktivitas</a></li>

    <li class="sidebar-section-title">Admin Panel</li>
    <li><a href="../admin/index.php"          class="sidebar-link"><span class="icon"><i class="bi bi-speedometer2"></i></span>Admin Overview</a></li>
    <li><a href="../admin/transactions.php"   class="sidebar-link"><span class="icon"><i class="bi bi-arrow-left-right"></i></span>Transaksi</a></li>
    <li><a href="../admin/registrations.php"  class="sidebar-link"><span class="icon"><i class="bi bi-person-check-fill"></i></span>Registrasi</a></li>
    <li><a href="../admin/withdrawals.php"    class="sidebar-link"><span class="icon"><i class="bi bi-cash-coin"></i></span>Penarikan Dana</a></li>

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

<!-- ====== MAIN CONTENT ====== -->
<main class="main-content">

  <!-- Top Bar -->
  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div class="d-flex align-items-center gap-3">
      <button class="btn d-lg-none p-2" style="background:var(--bg-card);border:1px solid var(--border-glass);border-radius:10px;color:var(--text-primary);" onclick="document.getElementById('mainSidebar').classList.add('open');document.getElementById('sidebarOverlay').style.display='block';">
        <i class="bi bi-list" style="font-size:1.2rem;"></i>
      </button>
      <div>
        <h1 style="font-size:1.4rem;font-weight:800;margin:0;">
          Platform Overview <span class="sa-badge ms-2">SUPER ADMIN</span>
        </h1>
        <p style="font-size:.78rem;color:var(--text-muted);margin:0;">
          Halo <?= htmlspecialchars($user['name']) ?> — Pantau seluruh platform EgiPay secara real-time
        </p>
      </div>
    </div>
    <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
      <!-- System health -->
      <span class="health-chip" style="background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.25);color:#10b981;">
        <i class="bi bi-circle-fill" style="font-size:.45rem;"></i> Database Online
      </span>
      <span class="health-chip" style="background:rgba(108,99,255,.1);border:1px solid rgba(108,99,255,.2);color:#a78bfa;">
        <i class="bi bi-clock me-1" style="font-size:.75rem;"></i> <?= date('d M Y · H:i') ?> WIB
      </span>
    </div>
  </div>

  <!-- ── Row 1: Key Metrics ── -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
      <div class="stat-card" style="border-color:rgba(168,85,247,.25);">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;">
          <div>
            <div class="stat-val" style="color:#c084fc;"><?= number_format($usersTotal) ?></div>
            <div class="stat-lbl">Total Pengguna</div>
          </div>
          <i class="bi bi-people-fill" style="font-size:1.5rem;color:rgba(168,85,247,.4);"></i>
        </div>
        <div style="font-size:.72rem;color:var(--text-muted);margin-top:.5rem;">
          <i class="bi bi-arrow-up text-success me-1"></i><?= $newThisMonth ?> baru bulan ini · <?= $usersActive ?> aktif
        </div>
      </div>
    </div>
    <div class="col-6 col-xl-3">
      <div class="stat-card" style="border-color:rgba(16,185,129,.2);">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;">
          <div>
            <div class="stat-val" style="color:#10b981;"><?= number_format($txAll['c']) ?></div>
            <div class="stat-lbl">Transaksi Sukses</div>
          </div>
          <i class="bi bi-graph-up-arrow" style="font-size:1.5rem;color:rgba(16,185,129,.35);"></i>
        </div>
        <div style="font-size:.72rem;color:var(--text-muted);margin-top:.5rem;">
          Vol: <?= formatRupiah((float)$txAll['vol']) ?> · Fee: <?= formatRupiah((float)$txAll['fees']) ?>
        </div>
      </div>
    </div>
    <div class="col-6 col-xl-3">
      <div class="stat-card" style="border-color:rgba(245,158,11,.2);">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;">
          <div>
            <div class="stat-val" style="color:#f59e0b;"><?= $txPending ?></div>
            <div class="stat-lbl">Tx Pending</div>
          </div>
          <i class="bi bi-hourglass-split" style="font-size:1.5rem;color:rgba(245,158,11,.35);"></i>
        </div>
        <div style="font-size:.72rem;color:var(--text-muted);margin-top:.5rem;">
          <?= $txFailed ?> gagal/dibatalkan · <?= $txToday['c'] ?> hari ini
        </div>
      </div>
    </div>
    <div class="col-6 col-xl-3">
      <div class="stat-card" style="border-color:rgba(6,182,212,.2);">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;">
          <div>
            <div class="stat-val" style="color:#06b6d4;"><?= formatRupiah((float)$totalWalletBalance) ?></div>
            <div class="stat-lbl">Total Saldo Wallet</div>
          </div>
          <i class="bi bi-wallet2" style="font-size:1.5rem;color:rgba(6,182,212,.35);"></i>
        </div>
        <div style="font-size:.72rem;color:var(--text-muted);margin-top:.5rem;">
          Ditahan: <?= formatRupiah((float)$totalWalletLocked) ?>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Row 2: User Breakdown + Revenue + Withdrawal ── -->
  <div class="row g-3 mb-4">
    <div class="col-sm-4">
      <div class="stat-card text-center">
        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--text-muted);margin-bottom:.4rem;">Admin</div>
        <div class="stat-val" style="color:#f72585;"><?= $usersByAdmin ?></div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="stat-card text-center">
        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--text-muted);margin-bottom:.4rem;">Merchant</div>
        <div class="stat-val" style="color:#a78bfa;"><?= $usersByMerch ?></div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="stat-card text-center">
        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--text-muted);margin-bottom:.4rem;">Reg. Revenue</div>
        <div style="font-family:'Space Grotesk';font-size:1.1rem;font-weight:800;color:#10b981;"><?= formatRupiah((float)$regStats['revenue']) ?></div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="stat-card text-center">
        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--text-muted);margin-bottom:.4rem;">Akun Suspend</div>
        <div class="stat-val" style="color:#ef4444;"><?= $usersSuspended ?></div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="stat-card text-center">
        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--text-muted);margin-bottom:.4rem;">Penarikan Pending</div>
        <div class="stat-val" style="color:#f59e0b;"><?= (int)($wdrStats['pending'] ?? 0) ?></div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="stat-card text-center">
        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--text-muted);margin-bottom:.4rem;">Total Dicairkan</div>
        <div style="font-family:'Space Grotesk';font-size:1.1rem;font-weight:800;color:#10b981;"><?= formatRupiah((float)$wdrStats['vol_paid']) ?></div>
      </div>
    </div>
  </div>

  <!-- ── Row 3: Chart + Activity ── -->
  <div class="row g-4 mb-4">
    <div class="col-lg-8">
      <div style="background:var(--bg-card);border:1px solid var(--border-glass);border-radius:18px;padding:1.5rem;">
        <h5 style="font-weight:800;margin-bottom:1.25rem;font-size:.95rem;">Volume Transaksi Platform (6 Bulan Terakhir)</h5>
        <?php if (!empty($chartVol)): ?>
        <canvas id="txChart" style="max-height:220px;"></canvas>
        <?php else: ?>
        <div style="text-align:center;padding:3rem;color:var(--text-muted);">Belum ada data transaksi</div>
        <?php endif; ?>
      </div>
    </div>
    <div class="col-lg-4">
      <div style="background:var(--bg-card);border:1px solid var(--border-glass);border-radius:18px;padding:1.5rem;height:100%;">
        <h5 style="font-weight:800;margin-bottom:1rem;font-size:.95rem;">Distribusi Role</h5>
        <canvas id="roleChart" style="max-height:180px;margin-bottom:1rem;"></canvas>
        <div style="display:flex;flex-direction:column;gap:.35rem;">
          <?php
          $roleColors = ['superadmin'=>'#a855f7','admin'=>'#f72585','merchant'=>'#6c63ff','customer'=>'#10b981'];
          foreach ($roleData as $r):
            $rc = $roleColors[$r['role']] ?? '#94a3b8';
          ?>
          <div style="display:flex;align-items:center;justify-content:space-between;font-size:.78rem;">
            <span style="display:flex;align-items:center;gap:6px;color:var(--text-secondary);">
              <span style="width:8px;height:8px;border-radius:50%;background:<?= $rc ?>;display:inline-block;"></span>
              <?= ucfirst($r['role']) ?>
            </span>
            <strong style="color:var(--text-primary);"><?= $r['cnt'] ?></strong>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Row 4: Recent Activity + Top Users ── -->
  <div class="row g-4">
    <div class="col-lg-7">
      <div style="background:var(--bg-card);border:1px solid var(--border-glass);border-radius:18px;overflow:hidden;">
        <div style="padding:1.25rem 1.5rem;border-bottom:1px solid var(--border-glass);display:flex;justify-content:space-between;align-items:center;">
          <h5 style="margin:0;font-weight:800;font-size:.95rem;">Aktivitas Terbaru Platform</h5>
          <a href="activity.php" style="font-size:.75rem;color:#c084fc;text-decoration:none;">Lihat semua →</a>
        </div>
        <div style="padding:.5rem 1.5rem 1rem;">
          <?php
          $actColors = ['login'=>'#10b981','login_failed'=>'#ef4444','register_paid'=>'#6c63ff','admin_wdr_approve'=>'#10b981','admin_wdr_reject'=>'#ef4444','admin_change_role'=>'#f59e0b'];
          foreach ($recentActivity as $a):
            $ac = $actColors[$a['action']] ?? '#94a3b8';
          ?>
          <div class="activity-item">
            <div class="activity-dot" style="background:<?= $ac ?>;"></div>
            <div style="flex:1;min-width:0;">
              <div style="font-size:.8rem;font-weight:600;color:var(--text-primary);"><?= htmlspecialchars(str_replace('_', ' ', $a['action'])) ?></div>
              <?php if ($a['description']): ?>
              <div style="font-size:.72rem;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= htmlspecialchars($a['description']) ?>">
                <?= htmlspecialchars(mb_substr($a['description'], 0, 60)) ?>
              </div>
              <?php endif; ?>
            </div>
            <div style="text-align:right;flex-shrink:0;">
              <div style="font-size:.7rem;color:var(--text-muted);white-space:nowrap;"><?= date('d M H:i', strtotime($a['created_at'])) ?></div>
              <?php if ($a['user_name']): ?>
              <div style="font-size:.68rem;color:var(--text-muted);"><?= htmlspecialchars(mb_substr($a['user_name'], 0, 18)) ?></div>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if (empty($recentActivity)): ?>
          <div style="text-align:center;padding:2rem;color:var(--text-muted);font-size:.83rem;">Belum ada aktivitas tercatat</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="col-lg-5">
      <div style="background:var(--bg-card);border:1px solid var(--border-glass);border-radius:18px;overflow:hidden;">
        <div style="padding:1.25rem 1.5rem;border-bottom:1px solid var(--border-glass);">
          <h5 style="margin:0;font-weight:800;font-size:.95rem;">Top Pengguna Paling Aktif</h5>
        </div>
        <div style="padding:.5rem 0;">
          <?php foreach ($topUsers as $i => $tu): ?>
          <div style="display:flex;align-items:center;gap:.75rem;padding:.65rem 1.5rem;border-bottom:1px solid rgba(255,255,255,.03);">
            <div style="width:24px;text-align:center;font-size:.72rem;font-weight:800;color:var(--text-muted);">#<?= $i+1 ?></div>
            <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#a855f7,#6366f1);display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:800;color:#fff;flex-shrink:0;">
              <?= htmlspecialchars($tu['avatar'] ?? substr($tu['name'],0,1)) ?>
            </div>
            <div style="flex:1;min-width:0;">
              <div style="font-size:.82rem;font-weight:700;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($tu['name']) ?></div>
              <div style="font-size:.7rem;color:var(--text-muted);"><?= ucfirst($tu['role']) ?></div>
            </div>
            <div style="text-align:right;flex-shrink:0;">
              <div style="font-size:.78rem;font-weight:700;color:var(--text-primary);"><?= $tu['tx_count'] ?> tx</div>
              <div style="font-size:.68rem;color:var(--text-muted);"><?= formatRupiah((float)$tu['tx_vol']) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if (empty($topUsers)): ?>
          <div style="padding:2rem;text-align:center;color:var(--text-muted);font-size:.83rem;">Belum ada data</div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div style="margin-top:2rem;padding-top:1rem;border-top:1px solid var(--border-glass);text-align:center;color:var(--text-muted);font-size:.72rem;">
    EgiPay Super Admin Panel v<?= SITE_VERSION ?> &nbsp;·&nbsp; <?= date('d M Y H:i') ?> WIB
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/main.js"></script>
<script>
// Transaction Volume Chart
const txLabels = <?= json_encode($chartLabels) ?>;
const txVol    = <?= json_encode($chartVol) ?>;
const txCtx    = document.getElementById('txChart')?.getContext('2d');
if (txCtx && txLabels.length) {
  const grad = txCtx.createLinearGradient(0,0,0,220);
  grad.addColorStop(0,'rgba(168,85,247,.35)');
  grad.addColorStop(1,'rgba(168,85,247,0)');
  new Chart(txCtx, {
    type:'bar',
    data:{
      labels:txLabels,
      datasets:[{label:'Volume',data:txVol,backgroundColor:grad,borderColor:'#a855f7',borderWidth:2,borderRadius:8,borderSkipped:false}]
    },
    options:{
      responsive:true,maintainAspectRatio:false,
      plugins:{legend:{display:false},tooltip:{backgroundColor:'rgba(15,15,30,.95)',borderColor:'rgba(168,85,247,.3)',borderWidth:1,titleColor:'#f1f5f9',bodyColor:'#94a3b8',padding:12,callbacks:{label:c=>' Rp '+c.raw.toLocaleString('id-ID')}}},
      scales:{x:{grid:{color:'rgba(255,255,255,.04)'},ticks:{color:'#64748b',font:{size:11}}},y:{grid:{color:'rgba(255,255,255,.04)'},ticks:{color:'#64748b',font:{size:11},callback:v=>'Rp '+(v/1000000).toFixed(1)+'jt'}}}
    }
  });
}

// Role donut chart
const roleLabels = <?= json_encode(array_column($roleData,'role')) ?>;
const roleCounts = <?= json_encode(array_map(fn($r)=>(int)$r['cnt'], $roleData)) ?>;
const roleColors = <?= json_encode(array_map(fn($r) => ['superadmin'=>'#a855f7','admin'=>'#f72585','merchant'=>'#6c63ff','customer'=>'#10b981'][$r['role']] ?? '#94a3b8', $roleData)) ?>;
const rCtx = document.getElementById('roleChart')?.getContext('2d');
if (rCtx && roleCounts.length) {
  new Chart(rCtx, {
    type:'doughnut',
    data:{labels:roleLabels,datasets:[{data:roleCounts,backgroundColor:roleColors,borderWidth:2,borderColor:'rgba(15,15,30,.8)'}]},
    options:{responsive:true,maintainAspectRatio:false,cutout:'65%',plugins:{legend:{display:false},tooltip:{callbacks:{label:c=>` ${c.label}: ${c.raw}`}}}}
  });
}

// Sidebar
document.getElementById('sidebarOverlay')?.addEventListener('click',function(){
  document.getElementById('mainSidebar').classList.remove('open');
  this.style.display='none';
});
</script>
</body>
</html>
