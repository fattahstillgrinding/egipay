<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();

$user  = getCurrentUser();
$flash = getFlash();

// ── Handle POST: change transaction status ───────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $txId   = trim($_POST['tx_id'] ?? '');

    if ($action === 'change_tx_status' && $txId) {
        $newStatus = $_POST['new_status'] ?? '';
        if (in_array($newStatus, ['pending','success','failed','cancelled','refunded'])) {
            $paidAt = ($newStatus === 'success') ? ', paid_at = NOW()' : '';
            dbExecute("UPDATE transactions SET status=? $paidAt WHERE tx_id=?", [$newStatus, $txId]);
            auditLog((int)$_SESSION['user_id'], 'admin_change_tx_status', "Tx $txId → $newStatus");
            setFlash('success', 'Status Diperbarui', "Transaksi $txId → $newStatus.");
        }
    }

    redirect(BASE_URL . '/admin/transactions.php?' . http_build_query(array_filter([
        'q'      => $_POST['_q']      ?? '',
        'status' => $_POST['_status'] ?? '',
        'page'   => $_POST['_page']   ?? '',
    ])));
}

// ── Filters ──────────────────────────────────────────────────
$search  = trim($_GET['q']      ?? '');
$sfilt   = $_GET['status']      ?? '';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];
if ($search) {
    $where[]  = '(t.tx_id LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($sfilt) { $where[] = 't.status=?'; $params[] = $sfilt; }

$whereStr = implode(' AND ', $where);

$countRow = dbFetchOne(
    "SELECT COUNT(*) AS cnt FROM transactions t JOIN users u ON t.user_id=u.id WHERE $whereStr",
    $params
);
$total = (int)($countRow['cnt'] ?? 0);
$pages = max(1, (int)ceil($total / $perPage));

$transactions = dbFetchAll(
    "SELECT t.*, u.name AS user_name, u.email AS user_email, pm.name AS method_name, pm.icon_class, pm.color
     FROM transactions t
     JOIN users u ON t.user_id = u.id
     LEFT JOIN payment_methods pm ON t.payment_method_id = pm.id
     WHERE $whereStr
     ORDER BY t.created_at DESC
     LIMIT $perPage OFFSET $offset",
    $params
);

// Summary stats
$summaryAll   = dbFetchOne('SELECT COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS vol FROM transactions');
$summarySuccess = dbFetchOne('SELECT COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS vol FROM transactions WHERE status="success"');
$summaryPending = dbFetchOne('SELECT COUNT(*) AS cnt FROM transactions WHERE status="pending"');
$summaryFailed  = dbFetchOne('SELECT COUNT(*) AS cnt FROM transactions WHERE status IN ("failed","cancelled")');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Semua Transaksi – Admin SolusiMu</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link href="../css/style.css" rel="stylesheet"/>
  <style>
    .admin-badge{background:linear-gradient(135deg,#f72585,#b5179e);color:#fff;font-size:.65rem;font-weight:700;padding:2px 8px;border-radius:6px;text-transform:uppercase;letter-spacing:.06em;vertical-align:middle}
    .sidebar-link.admin-active{background:linear-gradient(135deg,rgba(247,37,133,.15),rgba(108,99,255,.1))!important;border-left-color:#f72585!important;color:#f72585!important}
    .filter-bar{background:var(--bg-card);border:1px solid var(--border-glass);border-radius:14px;padding:1rem 1.25rem}
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
    <span class="brand-text" style="font-size:1.1rem;">SolusiMu <span class="admin-badge">Admin</span></span>
  </div>
  <ul class="sidebar-menu">
    <li class="sidebar-section-title">Dashboard</li>
    <li><a href="index.php" class="sidebar-link"><span class="icon"><i class="bi bi-speedometer2"></i></span>Overview</a></li>
    <li class="sidebar-section-title">Manajemen</li>
    <li><a href="users.php" class="sidebar-link"><span class="icon"><i class="bi bi-people-fill"></i></span>Pengguna</a></li>
    <li><a href="transactions.php" class="sidebar-link admin-active"><span class="icon"><i class="bi bi-arrow-left-right"></i></span>Semua Transaksi</a></li>
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
        <h1 class="dash-title">Semua Transaksi</h1>
        <p class="dash-subtitle">Total <?= number_format($total) ?> transaksi <?= $sfilt ? "berstatus <em>$sfilt</em>" : '' ?></p>
      </div>
    </div>
  </div>

  <!-- Summary Cards -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <a href="transactions.php" class="stat-card text-decoration-none d-block" style="<?= !$sfilt ? 'border-color:rgba(108,99,255,0.4);' : '' ?>padding:1rem;">
        <div style="font-size:0.72rem;color:var(--text-muted);margin-bottom:0.25rem;">Semua</div>
        <div style="font-size:1.4rem;font-weight:800;"><?= number_format($summaryAll['cnt']) ?></div>
        <div style="font-size:0.72rem;color:var(--text-muted);"><?= formatRupiah($summaryAll['vol']) ?></div>
      </a>
    </div>
    <div class="col-6 col-md-3">
      <a href="transactions.php?status=success" class="stat-card text-decoration-none d-block" style="<?= $sfilt==='success' ? 'border-color:rgba(16,185,129,0.4);' : '' ?>padding:1rem;">
        <div style="font-size:0.72rem;color:var(--text-muted);margin-bottom:0.25rem;">Sukses</div>
        <div style="font-size:1.4rem;font-weight:800;color:#10b981;"><?= number_format($summarySuccess['cnt']) ?></div>
        <div style="font-size:0.72rem;color:var(--text-muted);"><?= formatRupiah($summarySuccess['vol']) ?></div>
      </a>
    </div>
    <div class="col-6 col-md-3">
      <a href="transactions.php?status=pending" class="stat-card text-decoration-none d-block" style="<?= $sfilt==='pending' ? 'border-color:rgba(245,158,11,0.4);' : '' ?>padding:1rem;">
        <div style="font-size:0.72rem;color:var(--text-muted);margin-bottom:0.25rem;">Pending</div>
        <div style="font-size:1.4rem;font-weight:800;color:#f59e0b;"><?= number_format($summaryPending['cnt']) ?></div>
        <div style="font-size:0.72rem;color:var(--text-muted);">Perlu tindakan</div>
      </a>
    </div>
    <div class="col-6 col-md-3">
      <a href="transactions.php?status=failed" class="stat-card text-decoration-none d-block" style="<?= $sfilt==='failed' ? 'border-color:rgba(239,68,68,0.4);' : '' ?>padding:1rem;">
        <div style="font-size:0.72rem;color:var(--text-muted);margin-bottom:0.25rem;">Gagal/Batal</div>
        <div style="font-size:1.4rem;font-weight:800;color:#ef4444;"><?= number_format($summaryFailed['cnt']) ?></div>
        <div style="font-size:0.72rem;color:var(--text-muted);">Failed &amp; Cancelled</div>
      </a>
    </div>
  </div>

  <!-- Filter Bar -->
  <div class="filter-bar mb-4">
    <form method="GET" action="transactions.php" class="row g-2 align-items-end">
      <div class="col-sm-5">
        <label style="font-size:0.72rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;">Cari</label>
        <div style="position:relative;">
          <i class="bi bi-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:0.8rem;"></i>
          <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="ID transaksi, nama, atau email..."
            style="background:var(--bg-dark);border:1px solid var(--border-glass);border-radius:10px;color:var(--text-primary);padding:7px 12px 7px 32px;font-size:0.83rem;outline:none;width:100%;"/>
        </div>
      </div>
      <div class="col-sm-3">
        <label style="font-size:0.72rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;">Status</label>
        <select name="status" class="form-select form-select-sm" style="background:var(--bg-dark);border:1px solid var(--border-glass);color:var(--text-primary);border-radius:10px;">
          <option value="">Semua Status</option>
          <?php foreach (['pending','success','failed','cancelled','refunded'] as $s): ?>
          <option value="<?= $s ?>" <?= $sfilt===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-sm px-4" style="background:linear-gradient(135deg,#6c63ff,#00d4ff);color:#fff;border:none;border-radius:10px;padding:7px 16px;">
          <i class="bi bi-search me-1"></i>Filter
        </button>
        <a href="transactions.php" class="btn btn-sm px-3 ms-1" style="background:var(--bg-card);border:1px solid var(--border-glass);color:var(--text-muted);border-radius:10px;padding:7px 14px;">Reset</a>
      </div>
    </form>
  </div>

  <!-- Transactions Table -->
  <div class="glass-table-wrapper animate-on-scroll">
    <?php if ($transactions): ?>
    <div class="table-responsive">
      <table class="glass-table">
        <thead>
          <tr>
            <th>ID Transaksi</th>
            <th>Pengguna</th>
            <th>Metode</th>
            <th>Tipe</th>
            <th>Jumlah</th>
            <th>Fee</th>
            <th>Total</th>
            <th>Status</th>
            <th>Tanggal</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($transactions as $tx): ?>
          <tr>
            <td><code style="color:var(--primary-light);background:rgba(108,99,255,0.1);padding:2px 8px;border-radius:6px;font-size:0.72rem;"><?= htmlspecialchars($tx['tx_id']) ?></code></td>
            <td>
              <div style="font-weight:600;font-size:0.8rem;"><?= htmlspecialchars($tx['user_name']) ?></div>
              <div style="font-size:0.7rem;color:var(--text-muted);"><?= htmlspecialchars($tx['user_email']) ?></div>
            </td>
            <td>
              <span style="display:flex;align-items:center;gap:5px;font-size:0.8rem;">
                <i class="<?= htmlspecialchars($tx['icon_class'] ?? 'bi bi-credit-card') ?>" style="color:<?= htmlspecialchars($tx['color'] ?? '#6c63ff') ?>;"></i>
                <?= htmlspecialchars($tx['method_name'] ?? 'Manual') ?>
              </span>
            </td>
            <td style="font-size:0.75rem;text-transform:capitalize;color:var(--text-secondary);"><?= $tx['type'] ?></td>
            <td style="font-family:'Space Grotesk';font-weight:700;font-size:0.85rem;"><?= formatRupiah($tx['amount']) ?></td>
            <td style="color:var(--text-muted);font-size:0.78rem;"><?= formatRupiah($tx['fee']) ?></td>
            <td style="font-family:'Space Grotesk';font-weight:700;font-size:0.85rem;color:var(--primary-light);"><?= formatRupiah($tx['total']) ?></td>
            <td><span class="tx-badge tx-<?= $tx['status'] ?>"><?= ucfirst($tx['status']) ?></span></td>
            <td style="font-size:0.75rem;color:var(--text-muted);white-space:nowrap;"><?= date('d M Y', strtotime($tx['created_at'])) ?></td>
            <td>
              <div class="dropdown">
                <button class="btn btn-sm dropdown-toggle" data-bs-toggle="dropdown"
                  style="background:transparent;border:1px solid var(--border-glass);color:var(--text-muted);border-radius:8px;font-size:0.7rem;padding:4px 10px;">
                  Status
                </button>
                <ul class="dropdown-menu" style="background:rgba(15,15,30,0.97);border:1px solid var(--border-glass);border-radius:12px;padding:0.5rem;min-width:160px;">
                  <?php foreach (['pending','success','failed','cancelled','refunded'] as $s): ?>
                  <?php if ($s !== $tx['status']): ?>
                  <li>
                    <form method="POST" style="margin:0;">
                      <?= csrfField() ?>
                      <input type="hidden" name="action" value="change_tx_status">
                      <input type="hidden" name="tx_id" value="<?= htmlspecialchars($tx['tx_id']) ?>">
                      <input type="hidden" name="new_status" value="<?= $s ?>">
                      <input type="hidden" name="_q" value="<?= htmlspecialchars($search) ?>">
                      <input type="hidden" name="_status" value="<?= htmlspecialchars($sfilt) ?>">
                      <input type="hidden" name="_page" value="<?= $page ?>">
                      <button type="submit" class="dropdown-item"
                        style="background:none;border:none;color:var(--text-secondary);font-size:0.78rem;padding:0.35rem 0.75rem;width:100%;text-align:left;border-radius:8px;cursor:pointer;">
                        <i class="bi bi-circle-fill me-2" style="font-size:0.5rem;color:<?= $s==='success'?'#10b981':($s==='pending'?'#f59e0b':'#ef4444') ?>;"></i>
                        Set <?= ucfirst($s) ?>
                      </button>
                    </form>
                  </li>
                  <?php endif; ?>
                  <?php endforeach; ?>
                </ul>
              </div>
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
        Menampilkan <?= (($page-1)*$perPage)+1 ?>–<?= min($page*$perPage,$total) ?> dari <?= $total ?>
      </span>
      <div style="display:flex;gap:4px;flex-wrap:wrap;">
        <?php
        $baseUrl = 'transactions.php?' . http_build_query(array_filter(['q'=>$search,'status'=>$sfilt]));
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
      <i class="bi bi-inbox" style="font-size:3rem;display:block;margin-bottom:0.75rem;"></i>
      Tidak ada transaksi ditemukan.
    </div>
    <?php endif; ?>
  </div>

  <div style="margin-top:2rem;padding-top:1rem;border-top:1px solid var(--border-glass);text-align:center;color:var(--text-muted);font-size:0.75rem;">
    SolusiMu Admin Panel v<?= SITE_VERSION ?> &nbsp;·&nbsp; <?= date('d M Y H:i') ?> WIB
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
