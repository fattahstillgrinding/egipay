<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();

$admin = getCurrentUser();
$flash = getFlash();

// ── Handle POST Actions ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $regId  = (int)($_POST['reg_id'] ?? 0);

    // ── Manual confirm payment & create member ───────────────
    if ($action === 'manual_confirm' && $regId > 0) {
        $reg = dbFetchOne(
            'SELECT * FROM registration_payments WHERE id=? AND status="pending" LIMIT 1',
            [$regId]
        );
        if (!$reg) {
            setFlash('error', 'Gagal', 'Data tidak ditemukan atau bukan status pending.');
            redirect(BASE_URL . '/admin/registrations.php');
        }

        // Check email not already a member
        $exists = dbFetchOne('SELECT id FROM users WHERE email=?', [$reg['email']]);
        if ($exists) {
            dbExecute('UPDATE registration_payments SET status="paid", paid_at=NOW(), payment_method="Manual Admin" WHERE id=?', [$regId]);
            setFlash('success', 'Diperbarui', 'Status diubah ke paid (akun sudah ada).');
            redirect(BASE_URL . '/admin/registrations.php');
        }

        try {
            $pdo = getDB();
            $pdo->beginTransaction();

            $initials = strtoupper(substr($reg['name'], 0, 1));
            $parts    = explode(' ', $reg['name']);
            if (count($parts) > 1) $initials .= strtoupper(substr($parts[1], 0, 1));

            dbExecute(
                'INSERT INTO users (name, email, password, phone, role, plan, status, avatar, email_verified_at)
                 VALUES (?, ?, ?, ?, "merchant", ?, "active", ?, NOW())',
                [$reg['name'], $reg['email'], $reg['password_hash'], $reg['phone'], $reg['plan'], $initials]
            );
            $userId     = (int)dbLastId();
            $memberCode = 'SMU-' . sprintf('%04d', $userId);
            dbExecute('UPDATE users SET member_code=? WHERE id=?', [$memberCode, $userId]);
            dbExecute('INSERT INTO wallets (user_id, balance) VALUES (?, 0.00)', [$userId]);
            dbExecute(
                'INSERT INTO api_keys (user_id, name, key_type, client_key, server_key) VALUES (?, ?, ?, ?, ?)',
                [$userId, 'Sandbox Key', 'sandbox', generateApiKey('sandbox'), generateApiKey('sandbox')]
            );
            dbExecute(
                'INSERT INTO notifications (user_id, type, title, message) VALUES (?, "success", ?, ?)',
                [$userId, 'Selamat Datang di SolusiMu!', 'Pendaftaran dikonfirmasi oleh Admin. Akun Anda aktif!']
            );
            dbExecute(
                'UPDATE registration_payments SET status="paid", paid_at=NOW(), payment_method="Manual Admin" WHERE id=?',
                [$regId]
            );
            $pdo->commit();
            auditLog((int)$_SESSION['user_id'], 'admin_manual_confirm', "Manual konfirmasi registrasi #$regId → member $memberCode");
            setFlash('success', 'Member Diaktifkan', "Akun berhasil dibuat. Kode Member: $memberCode");
        } catch (PDOException $e) {
            $pdo->rollBack();
            setFlash('error', 'Gagal', 'Terjadi kesalahan: ' . $e->getMessage());
        }
        redirect(BASE_URL . '/admin/registrations.php');
    }

    // ── Delete single record ─────────────────────────────────
    if ($action === 'delete_reg' && $regId > 0) {
        dbExecute('DELETE FROM registration_payments WHERE id=? AND status != "paid"', [$regId]);
        auditLog((int)$_SESSION['user_id'], 'admin_delete_reg', "Hapus data registrasi #$regId");
        setFlash('success', 'Dihapus', 'Data registrasi berhasil dihapus.');
        redirect(BASE_URL . '/admin/registrations.php');
    }

    // ── Purge all expired records ────────────────────────────
    if ($action === 'purge_expired') {
        $cnt = dbFetchOne('SELECT COUNT(*) AS cnt FROM registration_payments WHERE status="expired"')['cnt'] ?? 0;
        dbExecute('DELETE FROM registration_payments WHERE status="expired"');
        auditLog((int)$_SESSION['user_id'], 'admin_purge_expired', "Purge $cnt expired registrations");
        setFlash('success', 'Berhasil', "$cnt data expired berhasil dibersihkan.");
        redirect(BASE_URL . '/admin/registrations.php');
    }

    redirect(BASE_URL . '/admin/registrations.php');
}

// ── Auto-mark overdue pending as expired ────────────────────
dbExecute(
    'UPDATE registration_payments SET status="expired" WHERE status="pending" AND expires_at <= NOW()'
);

// ── Stats ────────────────────────────────────────────────────
$statAll     = dbFetchOne('SELECT COUNT(*) AS cnt FROM registration_payments')['cnt'] ?? 0;
$statPending = dbFetchOne('SELECT COUNT(*) AS cnt FROM registration_payments WHERE status="pending"')['cnt'] ?? 0;
$statPaid    = dbFetchOne('SELECT COUNT(*) AS cnt FROM registration_payments WHERE status="paid"')['cnt'] ?? 0;
$statExpired = dbFetchOne('SELECT COUNT(*) AS cnt FROM registration_payments WHERE status="expired"')['cnt'] ?? 0;
$totalFee    = dbFetchOne('SELECT COALESCE(SUM(amount),0) AS s FROM registration_payments WHERE status="paid"')['s'] ?? 0;

// ── Filters ──────────────────────────────────────────────────
$search       = trim($_GET['q']      ?? '');
$statusFilter = $_GET['status']      ?? '';
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 20;
$offset       = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];
if ($search) {
    $where[]  = '(name LIKE ? OR email LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($statusFilter && in_array($statusFilter, ['pending','paid','expired'])) {
    $where[]  = 'rp.status=?';
    $params[] = $statusFilter;
}
$whereStr = implode(' AND ', $where);

$total = (int)(dbFetchOne(
    "SELECT COUNT(*) AS cnt FROM registration_payments rp WHERE $whereStr", $params
)['cnt'] ?? 0);
$pages = max(1, (int)ceil($total / $perPage));

$regs = dbFetchAll(
    "SELECT rp.*, u.member_code, u.id AS user_id
     FROM registration_payments rp
     LEFT JOIN users u ON u.email = rp.email AND rp.status = 'paid'
     WHERE $whereStr ORDER BY rp.created_at DESC LIMIT $perPage OFFSET $offset",
    $params
);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Registrasi Member – Admin SolusiMu</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link href="../css/style.css" rel="stylesheet"/>
  <style>
    .admin-badge { background:linear-gradient(135deg,#f72585,#b5179e);color:#fff;font-size:0.65rem;font-weight:700;padding:2px 8px;border-radius:6px;text-transform:uppercase;letter-spacing:0.06em;vertical-align:middle; }
    .sidebar-link.admin-active { background:linear-gradient(135deg,rgba(247,37,133,0.15),rgba(108,99,255,0.1)) !important; border-left-color:#f72585 !important; color:#f72585 !important; }
    .reg-status-pending { background:rgba(245,158,11,.12);color:#f59e0b;border:1px solid rgba(245,158,11,.3);border-radius:6px;padding:3px 9px;font-size:0.7rem;font-weight:700; }
    .reg-status-paid    { background:rgba(16,185,129,.12);color:#10b981;border:1px solid rgba(16,185,129,.3);border-radius:6px;padding:3px 9px;font-size:0.7rem;font-weight:700; }
    .reg-status-expired { background:rgba(239,68,68,.1);color:#ef4444;border:1px solid rgba(239,68,68,.25);border-radius:6px;padding:3px 9px;font-size:0.7rem;font-weight:700; }
    .member-code-pill { font-family:'Space Grotesk';font-size:0.78rem;font-weight:700;background:linear-gradient(135deg,rgba(247,37,133,.12),rgba(108,99,255,.1));color:#f72585;border:1px solid rgba(247,37,133,.25);border-radius:8px;padding:3px 10px;letter-spacing:.05em; }
  </style>
</head>
<body>
<div class="page-loader" id="pageLoader"><div class="loader-logo"><svg width="60" height="60" viewBox="0 0 60 60" fill="none"><defs><linearGradient id="lg1" x1="0" y1="0" x2="60" y2="60"><stop stop-color="#f72585"/><stop offset="1" stop-color="#6c63ff"/></linearGradient></defs><rect width="60" height="60" rx="16" fill="url(#lg1)"/><path d="M18 20h14a8 8 0 010 16H18V20zm0 8h12a4 4 0 000-8" fill="white" opacity="0.9"/><circle cx="42" cy="40" r="4" fill="white" opacity="0.7"/></svg></div><div class="loader-bar"><div class="loader-bar-fill"></div></div></div>
<div class="toast-container" id="toastContainer"></div>
<?php if ($flash): ?><div id="flashMessage" data-type="<?= $flash['type'] ?>" data-title="<?= htmlspecialchars($flash['title']) ?>" data-message="<?= htmlspecialchars($flash['message']) ?>" style="display:none"></div><?php endif; ?>

<div id="sidebarOverlay" style="position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:999;display:none;backdrop-filter:blur(4px);" class="d-lg-none"></div>

<!-- ── Sidebar ── -->
<aside class="sidebar" id="mainSidebar">
  <div class="sidebar-logo">
    <svg width="36" height="36" viewBox="0 0 42 42" fill="none">
      <defs><linearGradient id="sLg" x1="0" y1="0" x2="42" y2="42"><stop stop-color="#f72585"/><stop offset="1" stop-color="#6c63ff"/></linearGradient></defs>
      <rect width="42" height="42" rx="12" fill="url(#sLg)"/>
      <path d="M12 14h10a6 6 0 010 12H12V14zm0 6h8a2 2 0 000-6" fill="white" opacity="0.95"/>
      <circle cx="30" cy="28" r="3" fill="white" opacity="0.8"/>
    </svg>
    <div class="sidebar-logo-text">
      <span>SolusiMu</span>
      <small>Admin Panel <span class="admin-badge">ADMIN</span></small>
    </div>
  </div>
  <ul class="sidebar-nav">
    <li class="sidebar-section-title">Dashboard</li>
    <li><a href="index.php" class="sidebar-link"><span class="icon"><i class="bi bi-speedometer2"></i></span>Overview</a></li>
    <li class="sidebar-section-title">Manajemen</li>
    <li><a href="users.php" class="sidebar-link"><span class="icon"><i class="bi bi-people-fill"></i></span>Pengguna</a></li>
    <li><a href="transactions.php" class="sidebar-link"><span class="icon"><i class="bi bi-arrow-left-right"></i></span>Semua Transaksi</a></li>
    <li><a href="registrations.php" class="sidebar-link admin-active"><span class="icon"><i class="bi bi-person-check-fill"></i></span>Registrasi Member</a></li>
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
      <div class="sidebar-avatar"><?= htmlspecialchars($admin['avatar'] ?? '?') ?></div>
      <div class="sidebar-profile-info">
        <strong><?= htmlspecialchars($admin['name']) ?></strong>
        <small><?= htmlspecialchars($admin['email']) ?></small>
      </div>
    </div>
  </div>
</aside>

<!-- ── Main ── -->
<main class="main-content">
  <!-- Top bar -->
  <div class="topbar">
    <button class="topbar-toggle d-lg-none" id="sidebarToggle"><i class="bi bi-list"></i></button>
    <div class="topbar-left">
      <h1 class="topbar-title">Registrasi Member</h1>
      <p class="topbar-subtitle">Status pembayaran & aktivasi akun member baru</p>
    </div>
    <div class="topbar-right">
      <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus semua <?= $statExpired ?> data expired?')">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="purge_expired"/>
        <button type="submit" class="btn btn-sm"
          style="background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.25);color:#ef4444;border-radius:10px;font-size:0.78rem;padding:6px 14px;"
          <?= $statExpired === 0 ? 'disabled' : '' ?>>
          <i class="bi bi-trash me-1"></i>Bersihkan Expired (<?= $statExpired ?>)
        </button>
      </form>
    </div>
  </div>

  <!-- ── Stat Cards ── -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
      <div class="stat-card animate-on-scroll">
        <div class="stat-icon" style="background:linear-gradient(135deg,#6c63ff,#00d4ff);">
          <i class="bi bi-person-plus-fill"></i></div>
        <div class="stat-content">
          <div class="stat-value"><?= number_format($statAll) ?></div>
          <div class="stat-label">Total Pendaftar</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-xl-3">
      <div class="stat-card animate-on-scroll">
        <div class="stat-icon" style="background:linear-gradient(135deg,#f59e0b,#ef4444);">
          <i class="bi bi-hourglass-split"></i></div>
        <div class="stat-content">
          <div class="stat-value"><?= number_format($statPending) ?></div>
          <div class="stat-label">Menunggu Bayar</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-xl-3">
      <div class="stat-card animate-on-scroll">
        <div class="stat-icon" style="background:linear-gradient(135deg,#10b981,#00d4ff);">
          <i class="bi bi-person-check-fill"></i></div>
        <div class="stat-content">
          <div class="stat-value"><?= number_format($statPaid) ?></div>
          <div class="stat-label">Member Aktif</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-xl-3">
      <div class="stat-card animate-on-scroll">
        <div class="stat-icon" style="background:linear-gradient(135deg,#f72585,#b5179e);">
          <i class="bi bi-cash-coin"></i></div>
        <div class="stat-content">
          <div class="stat-value" style="font-size:1rem;"><?= formatRupiah($totalFee) ?></div>
          <div class="stat-label">Pendapatan Reg.</div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Filter bar ── -->
  <div class="glass-table-wrapper mb-3 animate-on-scroll" style="padding:1rem 1.25rem;">
    <form method="GET" action="registrations.php">
      <div class="row g-2 align-items-end">
        <div class="col-sm-5">
          <label style="font-size:0.72rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;">Cari Nama / Email</label>
          <div style="position:relative;">
            <i class="bi bi-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:0.8rem;"></i>
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
              placeholder="Nama atau Email..."
              class="form-control form-control-sm" style="padding-left:32px;background:var(--bg-dark);border:1px solid var(--border-glass);color:var(--text-primary);border-radius:10px;"/>
          </div>
        </div>
        <div class="col-sm-3">
          <label style="font-size:0.72rem;color:var(--text-muted);font-weight:600;text-transform:uppercase;">Status</label>
          <select name="status" class="form-select form-select-sm" style="background:var(--bg-dark);border:1px solid var(--border-glass);color:var(--text-primary);border-radius:10px;">
            <option value="">Semua Status</option>
            <option value="pending"  <?= $statusFilter==='pending'  ? 'selected' : '' ?>>⏳ Pending</option>
            <option value="paid"     <?= $statusFilter==='paid'     ? 'selected' : '' ?>>✅ Paid (Aktif)</option>
            <option value="expired"  <?= $statusFilter==='expired'  ? 'selected' : '' ?>>❌ Expired</option>
          </select>
        </div>
        <div class="col-auto">
          <button type="submit" class="btn btn-sm px-4" style="background:linear-gradient(135deg,#6c63ff,#00d4ff);color:#fff;border:none;border-radius:10px;padding:7px 16px;">
            <i class="bi bi-search me-1"></i>Filter
          </button>
          <a href="registrations.php" class="btn btn-sm px-3 ms-1" style="background:var(--bg-card);border:1px solid var(--border-glass);color:var(--text-muted);border-radius:10px;padding:7px 14px;">Reset</a>
        </div>
      </div>
    </form>
  </div>

  <!-- ── Table ── -->
  <div class="glass-table-wrapper animate-on-scroll">
    <?php if ($regs): ?>
    <div class="table-responsive">
      <table class="glass-table" id="regsTable">
        <thead>
          <tr>
            <th>No. Invoice</th>
            <th>Calon Member</th>
            <th>Paket</th>
            <th>Nominal</th>
            <th>Status Bayar</th>
            <th>Kode Member</th>
            <th>Expired / Bayar</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($regs as $r):
            $secsLeft = 0;
            if ($r['status'] === 'pending') {
                $secsLeft = max(0, strtotime($r['expires_at']) - time());
            }
          ?>
          <tr>
            <!-- Invoice No -->
            <td>
              <div style="font-family:'Space Grotesk';font-size:0.78rem;font-weight:700;color:var(--text-secondary);">
                <?= htmlspecialchars($r['inv_no']) ?>
              </div>
              <div style="font-size:0.68rem;color:var(--text-muted);"><?= date('d M Y H:i', strtotime($r['created_at'])) ?></div>
            </td>

            <!-- Member info -->
            <td>
              <div style="font-weight:600;font-size:0.83rem;"><?= htmlspecialchars($r['name']) ?></div>
              <div style="font-size:0.72rem;color:var(--text-muted);"><?= htmlspecialchars($r['email']) ?></div>
              <?php if ($r['phone']): ?>
              <div style="font-size:0.68rem;color:var(--text-muted);"><?= htmlspecialchars($r['phone']) ?></div>
              <?php endif; ?>
            </td>

            <!-- Plan -->
            <td>
              <span style="font-size:0.75rem;font-weight:700;
                color:<?= $r['plan']==='enterprise'?'#f72585':($r['plan']==='business'?'#f59e0b':'#a78bfa') ?>;">
                <?= ucfirst($r['plan']) ?>
              </span>
            </td>

            <!-- Amount -->
            <td style="font-family:'Space Grotesk';font-weight:700;font-size:0.83rem;color:var(--text-primary);">
              <?= formatRupiah($r['amount']) ?>
            </td>

            <!-- Status -->
            <td>
              <span class="reg-status-<?= $r['status'] ?>">
                <?php if ($r['status'] === 'pending'): ?>
                  <i class="bi bi-hourglass-split me-1"></i>Pending
                <?php elseif ($r['status'] === 'paid'): ?>
                  <i class="bi bi-check-circle-fill me-1"></i>Lunas
                <?php else: ?>
                  <i class="bi bi-x-circle me-1"></i>Expired
                <?php endif; ?>
              </span>
              <?php if ($r['status'] === 'paid' && $r['payment_method']): ?>
              <div style="font-size:0.68rem;color:var(--text-muted);margin-top:2px;"><?= htmlspecialchars($r['payment_method']) ?></div>
              <?php endif; ?>
              <?php if ($r['status'] === 'pending' && $secsLeft > 0): ?>
              <div style="font-size:0.68rem;color:#f59e0b;margin-top:3px;">
                <i class="bi bi-clock me-1"></i><?= sprintf('%02d:%02d', intdiv($secsLeft, 60), $secsLeft % 60) ?> tersisa
              </div>
              <?php endif; ?>
            </td>

            <!-- Member Code -->
            <td>
              <?php if ($r['status'] === 'paid' && $r['member_code']): ?>
              <span class="member-code-pill"><?= htmlspecialchars($r['member_code']) ?></span>
              <?php if ($r['user_id']): ?>
              <div style="margin-top:4px;">
                <a href="users.php?q=<?= urlencode($r['member_code']) ?>" style="font-size:0.68rem;color:#6c63ff;text-decoration:none;">
                  <i class="bi bi-person-lines-fill me-1"></i>Lihat Profil
                </a>
              </div>
              <?php endif; ?>
              <?php else: ?>
              <span style="font-size:0.72rem;color:var(--text-muted);">—</span>
              <?php endif; ?>
            </td>

            <!-- Date info -->
            <td style="font-size:0.72rem;color:var(--text-muted);white-space:nowrap;">
              <?php if ($r['status'] === 'paid'): ?>
                <?= $r['paid_at'] ? date('d M Y H:i', strtotime($r['paid_at'])) : '—' ?>
                <div style="color:#10b981;font-weight:600;font-size:0.68rem;">Lunas</div>
              <?php else: ?>
                <?= date('d M Y H:i', strtotime($r['expires_at'])) ?>
                <div style="font-size:0.68rem;"><?= $r['status'] === 'expired' ? '(habis)' : '(batas)' ?></div>
              <?php endif; ?>
            </td>

            <!-- Actions -->
            <td>
              <div style="display:flex;gap:4px;flex-wrap:wrap;">
                <!-- View Invoice -->
                <a href="../invoice_register.php?token=<?= urlencode($r['token']) ?>" target="_blank"
                  class="btn btn-sm"
                  style="background:transparent;border:1px solid var(--border-glass);color:var(--text-muted);border-radius:8px;font-size:0.7rem;padding:4px 8px;"
                  title="Buka invoice">
                  <i class="bi bi-eye"></i>
                </a>

                <?php if ($r['status'] === 'pending'): ?>
                <!-- Manual Confirm -->
                <form method="POST" style="margin:0;"
                  onsubmit="return confirm('Konfirmasi pembayaran manual untuk <?= htmlspecialchars(addslashes($r['name'])) ?>?')">
                  <?= csrfField() ?>
                  <input type="hidden" name="action"  value="manual_confirm"/>
                  <input type="hidden" name="reg_id"  value="<?= $r['id'] ?>"/>
                  <button type="submit" class="btn btn-sm"
                    style="background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.3);color:#10b981;border-radius:8px;font-size:0.7rem;padding:4px 8px;"
                    title="Konfirmasi manual">
                    <i class="bi bi-check2-circle"></i>
                  </button>
                </form>
                <?php endif; ?>

                <?php if ($r['status'] !== 'paid'): ?>
                <!-- Delete -->
                <form method="POST" style="margin:0;"
                  onsubmit="return confirm('Hapus data registrasi ini?')">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="delete_reg"/>
                  <input type="hidden" name="reg_id" value="<?= $r['id'] ?>"/>
                  <button type="submit" class="btn btn-sm"
                    style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);color:#ef4444;border-radius:8px;font-size:0.7rem;padding:4px 8px;"
                    title="Hapus">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
                <?php endif; ?>
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
        Menampilkan <?= (($page-1)*$perPage) + 1 ?>–<?= min($page*$perPage, $total) ?> dari <?= $total ?> data
      </span>
      <div style="display:flex;gap:4px;">
        <?php
        $base = 'registrations.php?' . http_build_query(array_filter(['q' => $search, 'status' => $statusFilter]));
        for ($p = 1; $p <= $pages; $p++):
        ?>
        <a href="<?= $base ?>&page=<?= $p ?>"
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
      <i class="bi bi-person-x" style="font-size:3rem;display:block;margin-bottom:0.75rem;opacity:0.4;"></i>
      Tidak ada data registrasi ditemukan.
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
</script>
</body>
</html>
