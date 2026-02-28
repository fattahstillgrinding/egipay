<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();

$user   = getCurrentUser();
$flash  = getFlash();

// ── Platform Stats ──────────────────────────────────────────
$totalUsers    = dbFetchOne('SELECT COUNT(*) AS cnt FROM users')['cnt'] ?? 0;
$activeUsers   = dbFetchOne('SELECT COUNT(*) AS cnt FROM users WHERE status="active"')['cnt'] ?? 0;
$totalMerchant = dbFetchOne('SELECT COUNT(*) AS cnt FROM users WHERE role="merchant"')['cnt'] ?? 0;
$totalAdmin    = dbFetchOne('SELECT COUNT(*) AS cnt FROM users WHERE role="admin"')['cnt'] ?? 0;

$totalTx     = dbFetchOne('SELECT COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS vol FROM transactions WHERE status="success"');
$pendingTx   = dbFetchOne('SELECT COUNT(*) AS cnt FROM transactions WHERE status="pending"')['cnt'] ?? 0;
$totalRevenue = dbFetchOne('SELECT COALESCE(SUM(fee),0) AS rev FROM transactions WHERE status="success"')['rev'] ?? 0;

// New users this month
$newUsersMonth = dbFetchOne(
    'SELECT COUNT(*) AS cnt FROM users WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())'
)['cnt'] ?? 0;

// Registration payment stats
dbExecute('UPDATE registration_payments SET status="expired" WHERE status="pending" AND expires_at <= NOW()');
$regPending = dbFetchOne('SELECT COUNT(*) AS cnt FROM registration_payments WHERE status="pending"')['cnt'] ?? 0;
$regPaid    = dbFetchOne('SELECT COUNT(*) AS cnt FROM registration_payments WHERE status="paid"')['cnt'] ?? 0;
$regRevenue = dbFetchOne('SELECT COALESCE(SUM(amount),0) AS s FROM registration_payments WHERE status="paid"')['s'] ?? 0;

// Recent paid registrations for overview
$recentRegs = dbFetchAll(
    'SELECT rp.inv_no, rp.name, rp.email, rp.plan, rp.payment_method, rp.paid_at, u.member_code
     FROM registration_payments rp
     LEFT JOIN users u ON u.email = rp.email
     WHERE rp.status="paid"
     ORDER BY rp.paid_at DESC LIMIT 6'
);

// Recent user registrations
$recentUsers = dbFetchAll(
    'SELECT id, name, email, role, plan, status, member_code, created_at FROM users ORDER BY created_at DESC LIMIT 8'
);

// Recent transactions across all users
$recentTx = dbFetchAll(
    'SELECT t.tx_id, t.amount, t.fee, t.status, t.created_at, u.name AS user_name, pm.name AS method_name
     FROM transactions t
     JOIN users u ON t.user_id = u.id
     LEFT JOIN payment_methods pm ON t.payment_method_id = pm.id
     ORDER BY t.created_at DESC LIMIT 8'
);

// Monthly revenue chart
$monthlyTx = dbFetchAll(
    'SELECT MONTH(created_at) AS m, YEAR(created_at) AS y, COALESCE(SUM(amount),0) AS total
     FROM transactions WHERE status="success"
       AND created_at >= NOW() - INTERVAL 6 MONTH
     GROUP BY YEAR(created_at), MONTH(created_at)
     ORDER BY y, m'
);
$chartLabels = [];
$chartValues = [];
foreach ($monthlyTx as $row) {
    $chartLabels[] = date('M Y', mktime(0, 0, 0, $row['m'], 1, $row['y']));
    $chartValues[] = (float)$row['total'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Admin Panel – SolusiMu</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link href="../css/style.css" rel="stylesheet"/>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <style>
    .admin-badge {
      background: linear-gradient(135deg, #f72585, #b5179e);
      color: #fff;
      font-size: 0.65rem;
      font-weight: 700;
      padding: 2px 8px;
      border-radius: 6px;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      vertical-align: middle;
    }
    .sidebar-link.admin-active {
      background: linear-gradient(135deg,rgba(247,37,133,0.15),rgba(108,99,255,0.1)) !important;
      border-left-color: #f72585 !important;
      color: #f72585 !important;
    }
  </style>
</head>
<body>
<div class="page-loader" id="pageLoader">
  <div class="loader-logo">
    <svg width="60" height="60" viewBox="0 0 60 60" fill="none">
      <defs><linearGradient id="lg1" x1="0" y1="0" x2="60" y2="60"><stop stop-color="#f72585"/><stop offset="1" stop-color="#6c63ff"/></linearGradient></defs>
      <rect width="60" height="60" rx="16" fill="url(#lg1)"/>
      <path d="M18 20h14a8 8 0 010 16H18V20zm0 8h12a4 4 0 000-8" fill="white" opacity="0.9"/>
      <circle cx="42" cy="40" r="4" fill="white" opacity="0.7"/>
    </svg>
  </div>
  <div class="loader-bar"><div class="loader-bar-fill"></div></div>
</div>
<div class="toast-container" id="toastContainer"></div>

<?php if ($flash): ?>
<div id="flashMessage"
  data-type="<?= $flash['type'] ?>"
  data-title="<?= htmlspecialchars($flash['title']) ?>"
  data-message="<?= htmlspecialchars($flash['message']) ?>"
  style="display:none"></div>
<?php endif; ?>

<div id="sidebarOverlay"
  style="position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:999;display:none;backdrop-filter:blur(4px);"
  class="d-lg-none"></div>

<!-- ====== ADMIN SIDEBAR ====== -->
<aside class="sidebar" id="mainSidebar">
  <div class="sidebar-logo">
    <svg width="36" height="36" viewBox="0 0 42 42" fill="none">
      <defs><linearGradient id="sLg" x1="0" y1="0" x2="42" y2="42"><stop stop-color="#f72585"/><stop offset="1" stop-color="#6c63ff"/></linearGradient></defs>
      <rect width="42" height="42" rx="12" fill="url(#sLg)"/>
      <path d="M12 14h10a6 6 0 010 12H12V14zm0 6h8a2 2 0 000-6" fill="white" opacity="0.95"/>
      <circle cx="30" cy="28" r="3" fill="white" opacity="0.8"/>
    </svg>
    <span class="brand-text" style="font-size:1.1rem;">SolusiMu <span class="admin-badge">Admin</span></span>
  </div>

  <ul class="sidebar-menu">
    <li class="sidebar-section-title">Dashboard</li>
    <li><a href="index.php" class="sidebar-link admin-active"><span class="icon"><i class="bi bi-speedometer2"></i></span>Overview</a></li>

    <li class="sidebar-section-title">Manajemen</li>
    <li><a href="users.php" class="sidebar-link"><span class="icon"><i class="bi bi-people-fill"></i></span>Pengguna</a></li>
    <li><a href="transactions.php" class="sidebar-link"><span class="icon"><i class="bi bi-arrow-left-right"></i></span>Semua Transaksi</a></li>
    <li><a href="registrations.php" class="sidebar-link"><span class="icon"><i class="bi bi-person-check-fill"></i></span>Registrasi Member
      <?php if ($regPending > 0): ?><span style="background:#f59e0b;color:#000;border-radius:10px;padding:1px 6px;font-size:0.62rem;font-weight:700;"><?= $regPending ?></span><?php endif; ?>
    </a></li>
    <li><a href="withdrawals.php" class="sidebar-link"><span class="icon"><i class="bi bi-cash-coin"></i></span>Penarikan Dana
      <?php $__wdrPending=(int)(dbFetchOne('SELECT COUNT(*) AS c FROM withdrawals WHERE status="pending"')['c']??0);if($__wdrPending>0):?><span style="margin-left:auto;background:#f72585;color:#fff;font-size:.6rem;font-weight:700;border-radius:20px;padding:2px 7px;"><?= $__wdrPending ?></span><?php endif;?>
    </a></li>

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
      <div>
        <div class="profile-name"><?= htmlspecialchars($user['name']) ?></div>
        <div class="profile-role" style="color:#f72585;">Administrator</div>
      </div>
    </div>
  </div>
</aside>

<!-- ====== MAIN CONTENT ====== -->
<main class="main-content">
  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div class="d-flex align-items-center gap-3">
      <button class="btn d-lg-none" id="sidebarToggle"
        style="background:var(--bg-card);border:1px solid var(--border-glass);border-radius:10px;color:var(--text-primary);padding:8px 12px;">
        <i class="bi bi-list fs-5"></i>
      </button>
      <div>
        <h1 class="dash-title">Admin Panel <span class="admin-badge ms-2">ADMIN</span></h1>
        <p class="dash-subtitle">Halo, <?= htmlspecialchars($user['name']) ?> — Kelola seluruh platform SolusiMu</p>
      </div>
    </div>
    <div class="d-flex gap-2">
      <a href="users.php" class="btn btn-sm px-4"
        style="background:linear-gradient(135deg,#f72585,#6c63ff);color:#fff;border:none;border-radius:10px;">
        <i class="bi bi-people me-1"></i>Kelola Pengguna
      </a>
    </div>
  </div>

  <!-- ── Stat Cards ──── -->
  <div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3">
      <div class="stat-card animate-on-scroll" style="background:linear-gradient(135deg,rgba(247,37,133,0.2),rgba(247,37,133,0.04));border-color:rgba(247,37,133,0.3);">
        <div class="stat-card-icon" style="background:rgba(247,37,133,0.15);"><i class="bi bi-people-fill" style="background:linear-gradient(135deg,#f72585,#b5179e);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;"></i></div>
        <div class="stat-card-label">Total Pengguna</div>
        <div class="stat-card-value"><?= number_format($totalUsers) ?></div>
        <div class="stat-card-trend" style="color:var(--text-muted);"><i class="bi bi-person-plus me-1"></i><?= $newUsersMonth ?> baru bulan ini</div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="stat-card animate-on-scroll animate-delay-1">
        <div class="stat-card-icon" style="background:rgba(16,185,129,0.12);"><i class="bi bi-graph-up-arrow" style="background:linear-gradient(135deg,#10b981,#00d4ff);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;"></i></div>
        <div class="stat-card-label">Total Transaksi Sukses</div>
        <div class="stat-card-value"><?= number_format($totalTx['cnt']) ?></div>
        <div class="stat-card-trend" style="color:var(--text-muted);">Volume: <?= formatRupiah($totalTx['vol']) ?></div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="stat-card animate-on-scroll animate-delay-2">
        <div class="stat-card-icon" style="background:rgba(245,158,11,0.12);"><i class="bi bi-clock-history" style="background:linear-gradient(135deg,#f59e0b,#ef4444);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;"></i></div>
        <div class="stat-card-label">Transaksi Pending</div>
        <div class="stat-card-value"><?= $pendingTx ?></div>
        <div class="stat-card-trend" style="color:var(--warning);">Perlu ditindaklanjuti</div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="stat-card animate-on-scroll animate-delay-3">
        <div class="stat-card-icon" style="background:rgba(108,99,255,0.12);"><i class="bi bi-wallet2" style="background:linear-gradient(135deg,#6c63ff,#00d4ff);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;"></i></div>
        <div class="stat-card-label">Total Pendapatan Fee</div>
        <div class="stat-card-value"><?= formatRupiah($totalRevenue) ?></div>
        <div class="stat-card-trend" style="color:var(--text-muted);">Dari <?= number_format($totalTx['cnt']) ?> transaksi sukses</div>
      </div>
    </div>
  </div>

  <!-- ── Second Row Stats ── -->
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="glass-table-wrapper p-3 text-center">
        <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:0.5rem;">Merchant Aktif</div>
        <div style="font-size:2rem;font-weight:800;color:var(--primary-light);"><?= $totalMerchant ?></div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="glass-table-wrapper p-3 text-center">
        <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:0.5rem;">Pengguna Aktif</div>
        <div style="font-size:2rem;font-weight:800;color:#10b981;"><?= $activeUsers ?></div>
      </div>
    </div>
    <div class="col-md-3">
      <a href="registrations.php?status=pending" style="text-decoration:none;">
      <div class="glass-table-wrapper p-3 text-center" style="border-color:rgba(245,158,11,.3);">
        <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:0.5rem;">Registrasi Pending</div>
        <div style="font-size:2rem;font-weight:800;color:#f59e0b;"><?= $regPending ?></div>
        <div style="font-size:0.68rem;color:var(--text-muted);">Menunggu bayar</div>
      </div></a>
    </div>
    <div class="col-md-3">
      <a href="registrations.php?status=paid" style="text-decoration:none;">
      <div class="glass-table-wrapper p-3 text-center" style="border-color:rgba(16,185,129,.25);">
        <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:0.5rem;">Reg. Lunas</div>
        <div style="font-size:2rem;font-weight:800;color:#10b981;"><?= $regPaid ?></div>
        <div style="font-size:0.68rem;color:#10b981;"><?= formatRupiah($regRevenue) ?></div>
      </div></a>
    </div>
  </div>

  <!-- ── Revenue Chart ── -->
  <div class="glass-table-wrapper p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 style="font-size:1rem;font-weight:700;margin:0;">Volume Transaksi Platform (6 Bulan)</h2>
    </div>
    <?php if (!empty($chartValues)): ?>
    <canvas id="platformChart" height="220" style="max-height:220px;"></canvas>
    <?php else: ?>
    <div style="text-align:center;padding:3rem;color:var(--text-muted);">Belum ada data transaksi</div>
    <?php endif; ?>
  </div>

  <!-- ── Tables Row ── -->
  <div class="row g-4">
    <!-- Recent Registrations -->
    <div class="col-lg-6">
      <div class="glass-table-wrapper">
        <div class="d-flex justify-content-between align-items-center p-4 pb-2 flex-wrap gap-2">
          <h2 style="font-size:0.95rem;font-weight:700;margin:0;">Pendaftaran Terbaru</h2>
          <a href="users.php" style="font-size:0.75rem;color:var(--primary-light);">Lihat semua &rarr;</a>
        </div>
        <div class="table-responsive">
          <table class="glass-table">
            <thead><tr><th>Member</th><th>Kode</th><th>Paket</th><th>Status</th><th>Tanggal</th></tr></thead>
            <tbody>
              <?php foreach ($recentUsers as $u): ?>
              <tr>
                <td>
                  <div style="font-weight:600;font-size:0.83rem;"><?= htmlspecialchars($u['name']) ?></div>
                  <div style="font-size:0.72rem;color:var(--text-muted);"><?= htmlspecialchars($u['email']) ?></div>
                </td>
                <td>
                  <?php if ($u['member_code']): ?>
                  <span style="font-family:'Space Grotesk';font-size:0.72rem;font-weight:700;background:linear-gradient(135deg,rgba(247,37,133,.1),rgba(108,99,255,.08));color:#f72585;border:1px solid rgba(247,37,133,.2);border-radius:7px;padding:2px 7px;letter-spacing:.04em;"><?= htmlspecialchars($u['member_code']) ?></span>
                  <?php else: ?><span style="color:var(--text-muted);font-size:0.72rem;">—</span><?php endif; ?>
                </td>
                <td><span style="font-size:0.72rem;background:rgba(108,99,255,0.12);color:var(--primary-light);border-radius:6px;padding:2px 8px;"><?= ucfirst($u['plan']) ?></span></td>
                <td>
                  <span class="tx-badge tx-<?= $u['status'] === 'active' ? 'success' : ($u['status'] === 'suspended' ? 'failed' : 'pending') ?>">
                    <?= ucfirst($u['status']) ?>
                  </span>
                </td>
                <td style="font-size:0.75rem;color:var(--text-muted);white-space:nowrap;"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Recent Transactions -->
    <div class="col-lg-6">
      <div class="glass-table-wrapper">
        <div class="d-flex justify-content-between align-items-center p-4 pb-2 flex-wrap gap-2">
          <h2 style="font-size:0.95rem;font-weight:700;margin:0;">Transaksi Terkini</h2>
          <a href="transactions.php" style="font-size:0.75rem;color:var(--primary-light);">Lihat semua &rarr;</a>
        </div>
        <div class="table-responsive">
          <table class="glass-table">
            <thead><tr><th>ID</th><th>Pengguna</th><th>Jumlah</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($recentTx as $tx): ?>
              <tr>
                <td><code style="color:var(--primary-light);font-size:0.72rem;"><?= htmlspecialchars($tx['tx_id']) ?></code></td>
                <td style="font-size:0.8rem;"><?= htmlspecialchars($tx['user_name']) ?></td>
                <td style="font-weight:700;font-family:'Space Grotesk';font-size:0.83rem;"><?= formatRupiah($tx['amount']) ?></td>
                <td><span class="tx-badge tx-<?= $tx['status'] ?>"><?= ucfirst($tx['status']) ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div style="margin-top:2rem;padding-top:1rem;border-top:1px solid var(--border-glass);text-align:center;color:var(--text-muted);font-size:0.75rem;">
    SolusiMu Admin Panel v<?= SITE_VERSION ?> &nbsp;·&nbsp; <?= date('d M Y H:i') ?> WIB
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/main.js"></script>
<script>
// Platform Chart
const chartLabels = <?= json_encode($chartLabels) ?>;
const chartValues = <?= json_encode($chartValues) ?>;
const pCtx = document.getElementById('platformChart')?.getContext('2d');
if (pCtx && chartLabels.length) {
  const grad = pCtx.createLinearGradient(0, 0, 0, 220);
  grad.addColorStop(0, 'rgba(247,37,133,0.3)');
  grad.addColorStop(1, 'rgba(247,37,133,0)');
  new Chart(pCtx, {
    type: 'bar',
    data: {
      labels: chartLabels,
      datasets: [{
        label: 'Volume Transaksi',
        data: chartValues,
        backgroundColor: grad,
        borderColor: '#f72585',
        borderWidth: 2,
        borderRadius: 8,
        borderSkipped: false,
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: 'rgba(15,15,30,0.95)',
          borderColor: 'rgba(247,37,133,0.3)',
          borderWidth: 1, titleColor: '#f1f5f9', bodyColor: '#94a3b8', padding: 12,
          callbacks: { label: ctx => ' Rp ' + ctx.raw.toLocaleString('id-ID') }
        }
      },
      scales: {
        x: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#64748b', font: { size: 11 } } },
        y: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#64748b', font: { size: 11 }, callback: v => 'Rp ' + (v/1000000).toFixed(1) + 'jt' } }
      }
    }
  });
}

// Sidebar toggle (mobile)
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
  document.querySelectorAll('.sidebar-submenu.open').forEach(m => {
    m.classList.remove('open');
    m.closest('.sidebar-has-submenu').querySelector('.sidebar-link-toggle').classList.remove('open');
  });
  if (!isOpen) { submenu.classList.add('open'); el.classList.add('open'); }
}
</script>
</body>
</html>
