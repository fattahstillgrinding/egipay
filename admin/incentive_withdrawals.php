<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();

$user  = getCurrentUser();
$flash = getFlash();

// ── Handle POST actions ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action    = $_POST['action']     ?? '';
    $wdrId     = (int)($_POST['wdr_id']     ?? 0);
    $adminNote = substr(trim($_POST['admin_note'] ?? ''), 0, 500);
    $adminId   = (int)$_SESSION['user_id'];

    if ($wdrId && in_array($action, ['approve', 'reject', 'processing'])) {
        $wdr = dbFetchOne('SELECT * FROM incentive_withdrawals WHERE id = ?', [$wdrId]);

        if ($wdr && $wdr['status'] === 'pending') {
            try {
                $pdo = getDB();
                $pdo->beginTransaction();

                if ($action === 'approve') {
                    dbExecute(
                        'UPDATE incentive_withdrawals SET status="approved", admin_id=?, admin_note=?, processed_at=NOW() WHERE id=?',
                        [$adminId, $adminNote ?: null, $wdrId]
                    );
                    // Release locked → total_withdrawn
                    dbExecute(
                        'UPDATE incentive_wallets SET locked = locked - ?, total_withdrawn = total_withdrawn + ? WHERE user_id = ?',
                        [$wdr['amount'], $wdr['amount'], $wdr['user_id']]
                    );
                    dbExecute(
                        'INSERT INTO notifications (user_id, type, title, message) VALUES (?, "success", ?, ?)',
                        [
                            $wdr['user_id'],
                            'Pencairan Insentif Disetujui ✓',
                            "Pencairan {$wdr['wdr_no']} sebesar " . formatRupiah($wdr['amount']) . " ke {$wdr['bank_name']} telah disetujui. Dana masuk hari ini.",
                        ]
                    );
                    auditLog($adminId, 'admin_inc_wdr_approve', "IWR {$wdr['wdr_no']} disetujui");
                    $pdo->commit();
                    setFlash('success', 'Pencairan Disetujui', "{$wdr['wdr_no']} berhasil disetujui. Dana sedang dikirimkan.");

                } elseif ($action === 'reject') {
                    dbExecute(
                        'UPDATE incentive_withdrawals SET status="rejected", admin_id=?, admin_note=?, processed_at=NOW() WHERE id=?',
                        [$adminId, $adminNote ?: 'Ditolak oleh admin.', $wdrId]
                    );
                    // Restore balance from locked
                    dbExecute(
                        'UPDATE incentive_wallets SET balance = balance + ?, locked = locked - ? WHERE user_id = ?',
                        [$wdr['amount'], $wdr['amount'], $wdr['user_id']]
                    );
                    $reason = $adminNote ?: 'Permintaan tidak memenuhi syarat.';
                    dbExecute(
                        'INSERT INTO notifications (user_id, type, title, message) VALUES (?, "error", ?, ?)',
                        [
                            $wdr['user_id'],
                            'Pencairan Insentif Ditolak',
                            "Pencairan {$wdr['wdr_no']} sebesar " . formatRupiah($wdr['amount']) . " ditolak. Alasan: {$reason} Saldo dikembalikan.",
                        ]
                    );
                    auditLog($adminId, 'admin_inc_wdr_reject', "IWR {$wdr['wdr_no']} ditolak: $reason");
                    $pdo->commit();
                    setFlash('success', 'Ditolak', "{$wdr['wdr_no']} ditolak dan saldo insentif dikembalikan ke member.");

                } elseif ($action === 'processing') {
                    dbExecute(
                        'UPDATE incentive_withdrawals SET status="processing", admin_id=?, admin_note=? WHERE id=?',
                        [$adminId, $adminNote ?: null, $wdrId]
                    );
                    dbExecute(
                        'INSERT INTO notifications (user_id, type, title, message) VALUES (?, "info", ?, ?)',
                        [$wdr['user_id'], 'Pencairan Insentif Diproses', "Pencairan {$wdr['wdr_no']} sedang dalam proses transfer ke {$wdr['bank_name']}."]
                    );
                    auditLog($adminId, 'admin_inc_wdr_processing', "IWR {$wdr['wdr_no']} → processing");
                    $pdo->commit();
                    setFlash('success', 'Status Diperbarui', "{$wdr['wdr_no']} ditandai sedang diproses.");
                }
            } catch (PDOException $e) {
                if (isset($pdo)) $pdo->rollBack();
                setFlash('error', 'Gagal', 'Terjadi kesalahan sistem. Silakan coba lagi.');
            }
        } else {
            setFlash('error', 'Tidak Valid', 'Permintaan tidak ditemukan atau sudah diproses.');
        }
    }

    redirect(BASE_URL . '/admin/incentive_withdrawals.php?' . http_build_query(array_filter([
        'status' => $_POST['_status'] ?? '',
        'q'      => $_POST['_q']      ?? '',
    ])));
}

// ── Filters ───────────────────────────────────────────────────
$sfilt   = $_GET['status'] ?? '';
$search  = trim($_GET['q'] ?? '');
$dateFilter = $_GET['date'] ?? '';   // kosong = semua, 'today' = hari ini, 'tomorrow' = besok
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];

if ($sfilt) { $where[] = 'iw.status = ?'; $params[] = $sfilt; }
if ($search) {
    $where[] = '(iw.wdr_no LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR iw.bank_account_no LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%";
    $params[] = "%$search%"; $params[] = "%$search%";
}
if ($dateFilter === 'today')    { $where[] = 'iw.scheduled_date = CURDATE()';               }
if ($dateFilter === 'tomorrow') { $where[] = 'iw.scheduled_date = CURDATE() + INTERVAL 1 DAY'; }

$whrStr = implode(' AND ', $where);

$totalRows  = (int)(dbFetchOne(
    "SELECT COUNT(*) AS c FROM incentive_withdrawals iw JOIN users u ON iw.user_id=u.id WHERE {$whrStr}",
    $params
)['c'] ?? 0);
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$withdrawals = dbFetchAll(
    "SELECT iw.*, u.name AS user_name, u.email AS user_email, u.avatar AS user_avatar, u.member_code,
            a.name AS admin_name
     FROM incentive_withdrawals iw
     JOIN users u ON iw.user_id = u.id
     LEFT JOIN users a ON iw.admin_id = a.id
     WHERE {$whrStr}
     ORDER BY FIELD(iw.status,'pending','processing','approved','rejected'), iw.scheduled_date ASC, iw.created_at ASC
     LIMIT {$perPage} OFFSET {$offset}",
    $params
);

// ── Stats ─────────────────────────────────────────────────────
$stats = dbFetchOne(
    'SELECT
       COUNT(*) AS total,
       COUNT(CASE WHEN status="pending"    THEN 1 END) AS cnt_pending,
       COUNT(CASE WHEN status="processing" THEN 1 END) AS cnt_processing,
       COUNT(CASE WHEN status="approved"   THEN 1 END) AS cnt_approved,
       COUNT(CASE WHEN status="rejected"   THEN 1 END) AS cnt_rejected,
       COALESCE(SUM(CASE WHEN status="approved" THEN amount END),0) AS vol_approved,
       -- Harus diproses hari ini (scheduled_date = today, status pending)
       COUNT(CASE WHEN status="pending" AND scheduled_date = CURDATE() THEN 1 END) AS due_today,
       -- Harus diproses besok
       COUNT(CASE WHEN status="pending" AND scheduled_date = CURDATE() + INTERVAL 1 DAY THEN 1 END) AS due_tomorrow
     FROM incentive_withdrawals'
);

$statusInfo = [
    'pending'    => ['label'=>'Menunggu',  'bg'=>'rgba(245,158,11,.12)', 'color'=>'#f59e0b', 'icon'=>'bi-clock'],
    'processing' => ['label'=>'Diproses',  'bg'=>'rgba(0,212,255,.12)',  'color'=>'#00d4ff', 'icon'=>'bi-arrow-repeat'],
    'approved'   => ['label'=>'Disetujui', 'bg'=>'rgba(16,185,129,.12)', 'color'=>'#10b981', 'icon'=>'bi-check-circle-fill'],
    'rejected'   => ['label'=>'Ditolak',   'bg'=>'rgba(239,68,68,.12)',  'color'=>'#ef4444', 'icon'=>'bi-x-circle-fill'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Pencairan Insentif – Admin EgiPay</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link href="../css/style.css" rel="stylesheet"/>
  <style>
    .admin-badge{background:linear-gradient(135deg,#f72585,#b5179e);color:#fff;font-size:.65rem;font-weight:700;padding:2px 8px;border-radius:6px;text-transform:uppercase;letter-spacing:.06em;}
    .sidebar-link.admin-active{background:linear-gradient(135deg,rgba(247,37,133,.15),rgba(108,99,255,.1))!important;border-left-color:#f72585!important;color:#f72585!important}
    .stat-card{background:var(--bg-card);border:1px solid var(--border-glass);border-radius:16px;padding:1.25rem 1.5rem;}
    .stat-card .val{font-family:'Space Grotesk',sans-serif;font-size:1.5rem;font-weight:800;line-height:1.1;}
    .stat-card .lbl{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin-top:.25rem;}
    .filter-bar{background:var(--bg-card);border:1px solid var(--border-glass);border-radius:14px;padding:1rem 1.25rem;}
    .status-pill{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;}
    .wdr-table th{background:rgba(255,255,255,.03);color:var(--text-muted);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;padding:.65rem 1rem;border-bottom:1px solid var(--border-glass);white-space:nowrap;}
    .wdr-table td{padding:.8rem 1rem;border-bottom:1px solid rgba(255,255,255,.04);font-size:.83rem;color:var(--text-secondary);vertical-align:middle;}
    .wdr-table tr:last-child td{border-bottom:none;}
    .wdr-table tr:hover td{background:rgba(255,255,255,.02);}
    .btn-approve{background:linear-gradient(135deg,rgba(16,185,129,.15),rgba(0,212,255,.1));border:1.5px solid rgba(16,185,129,.4);color:#10b981;font-size:.72rem;font-weight:700;border-radius:8px;padding:4px 12px;cursor:pointer;transition:all .2s;}
    .btn-approve:hover{background:rgba(16,185,129,.25);border-color:#10b981;}
    .btn-reject{background:rgba(239,68,68,.1);border:1.5px solid rgba(239,68,68,.3);color:#ef4444;font-size:.72rem;font-weight:700;border-radius:8px;padding:4px 12px;cursor:pointer;transition:all .2s;}
    .btn-reject:hover{background:rgba(239,68,68,.2);border-color:#ef4444;}
    .btn-process{background:rgba(0,212,255,.08);border:1.5px solid rgba(0,212,255,.25);color:#00d4ff;font-size:.72rem;font-weight:700;border-radius:8px;padding:4px 12px;cursor:pointer;transition:all .2s;}
    .btn-process:hover{background:rgba(0,212,255,.18);border-color:#00d4ff;}
    .modal-overlay{position:fixed;inset:0;background:rgba(10,10,30,.75);backdrop-filter:blur(8px);z-index:1060;display:flex;align-items:center;justify-content:center;padding:1rem;}
    .modal-dark{background:#1a1a35;border:1px solid rgba(108,99,255,.3);border-radius:20px;padding:2rem;max-width:440px;width:100%;}
    .form-ctrl-dark{background:rgba(255,255,255,.04);border:1.5px solid rgba(255,255,255,.1);color:var(--text-primary);border-radius:10px;padding:.6rem 1rem;font-size:.85rem;width:100%;resize:vertical;}
    .form-ctrl-dark:focus{outline:none;border-color:#6c63ff;}
    .filter-tab{padding:.3rem .9rem;border-radius:20px;font-size:.75rem;font-weight:600;text-decoration:none;border:1.5px solid var(--border-glass);color:var(--text-muted);transition:all .2s;display:inline-block;}
    .filter-tab:hover,.filter-tab.active{background:rgba(247,37,133,.12);border-color:#f72585;color:#f72585;}
    .sched-today{background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.3);color:#10b981;}
    .sched-tomorrow{background:rgba(245,158,11,.12);border:1px solid rgba(245,158,11,.3);color:#f59e0b;}
    .sched-overdue{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);color:#ef4444;}
    .row-overdue td{background:rgba(239,68,68,.04)!important;}
    .row-today td{background:rgba(16,185,129,.03)!important;}
    .free-badge{background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.3);color:#10b981;border-radius:8px;padding:2px 8px;font-size:.65rem;font-weight:700;}
  </style>
</head>
<body>
<div class="page-loader" id="pageLoader">
  <div class="loader-logo">
    <svg width="60" height="60" viewBox="0 0 60 60" fill="none"><defs><linearGradient id="lg1" x1="0" y1="0" x2="60" y2="60"><stop stop-color="#f72585"/><stop offset="1" stop-color="#6c63ff"/></linearGradient></defs><rect width="60" height="60" rx="16" fill="url(#lg1)"/><path d="M18 20h14a8 8 0 010 16H18V20zm0 8h12a4 4 0 000-8" fill="white" opacity="0.9"/><circle cx="42" cy="40" r="4" fill="white" opacity="0.7"/></svg>
  </div>
  <div class="loader-bar"><div class="loader-bar-fill"></div></div>
</div>
<div class="toast-container" id="toastContainer"></div>

<?php if ($flash): ?>
<div id="flashMessage" data-type="<?= $flash['type'] ?>" data-title="<?= htmlspecialchars($flash['title']) ?>" data-message="<?= htmlspecialchars($flash['message']) ?>" style="display:none"></div>
<?php endif; ?>

<div id="sidebarOverlay" style="position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:999;display:none;backdrop-filter:blur(4px);" class="d-lg-none"></div>

<!-- ====== SIDEBAR ====== -->
<aside class="sidebar" id="mainSidebar">
  <div class="sidebar-logo">
    <svg width="36" height="36" viewBox="0 0 42 42" fill="none"><defs><linearGradient id="sLg" x1="0" y1="0" x2="42" y2="42"><stop stop-color="#f72585"/><stop offset="1" stop-color="#6c63ff"/></linearGradient></defs><rect width="42" height="42" rx="12" fill="url(#sLg)"/><path d="M12 14h10a6 6 0 010 12H12V14zm0 6h8a2 2 0 000-6" fill="white" opacity=".95"/><circle cx="30" cy="28" r="3" fill="white" opacity=".8"/></svg>
    <span class="brand-text" style="font-size:1.1rem;">EgiPay <span class="admin-badge ms-1">ADMIN</span></span>
  </div>
  <ul class="sidebar-menu">
    <li class="sidebar-section-title">Admin Panel</li>
    <li><a href="index.php"         class="sidebar-link"><span class="icon"><i class="bi bi-speedometer2"></i></span>Overview</a></li>
    <li><a href="users.php"         class="sidebar-link"><span class="icon"><i class="bi bi-people-fill"></i></span>Pengguna</a></li>
    <li><a href="transactions.php"  class="sidebar-link"><span class="icon"><i class="bi bi-arrow-left-right"></i></span>Semua Transaksi</a></li>
    <li><a href="registrations.php" class="sidebar-link"><span class="icon"><i class="bi bi-person-check-fill"></i></span>Registrasi Member</a></li>
    <li><a href="withdrawals.php"   class="sidebar-link"><span class="icon"><i class="bi bi-cash-coin"></i></span>Penarikan Dana</a></li>
    <li class="sidebar-has-submenu open">
      <a href="#" class="sidebar-link sidebar-link-toggle open" onclick="toggleSidebarSubmenu(this);return false;" style="color:#a855f7;">
        <span class="icon"><i class="bi bi-gift"></i></span>
        Dompet Insentif
        <i class="bi bi-chevron-down sidebar-chevron ms-auto"></i>
      </a>
      <ul class="sidebar-submenu" style="display:block;">
        <li><a href="incentive_withdrawals.php" class="sidebar-sublink active"><i class="bi bi-bank me-2"></i>Pencairan Insentif
          <?php if ((int)$stats['cnt_pending'] > 0): ?>
          <span style="margin-left:auto;background:#f72585;color:#fff;font-size:.6rem;font-weight:700;border-radius:20px;padding:1px 6px;"><?= (int)$stats['cnt_pending'] ?></span>
          <?php endif; ?>
        </a></li>
      </ul>
    </li>
    <li class="sidebar-section-title">Navigasi</li>
    <?php if (isSuperAdmin()): ?>
    <li><a href="../superadmin/index.php" class="sidebar-link" style="color:#c084fc;"><span class="icon"><i class="bi bi-shield-shaded"></i></span>Super Admin Panel</a></li>
    <?php endif; ?>
    <li><a href="../dashboard.php" class="sidebar-link"><span class="icon"><i class="bi bi-house-fill"></i></span>Kembali ke Beranda</a></li>
    <li><a href="../logout.php"    class="sidebar-link" style="color:#ef4444;"><span class="icon"><i class="bi bi-box-arrow-left"></i></span>Keluar</a></li>
  </ul>
  <div class="sidebar-bottom">
    <div class="sidebar-profile">
      <div class="profile-avatar-sm"><?= htmlspecialchars($user['avatar'] ?? '?') ?></div>
      <div>
        <div class="profile-name"><?= htmlspecialchars($user['name']) ?></div>
        <div class="profile-role" style="color:#f72585;">Administrator</div>
      </div>
    </div>
  </div>
</aside>

<!-- ====== MAIN CONTENT ====== -->
<main class="main-content">

  <!-- Top bar -->
  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div class="d-flex align-items-center gap-3">
      <button class="btn d-lg-none p-2" style="background:var(--bg-card);border:1px solid var(--border-glass);border-radius:10px;color:var(--text-primary);" onclick="document.getElementById('mainSidebar').classList.add('open');document.getElementById('sidebarOverlay').style.display='block';">
        <i class="bi bi-list" style="font-size:1.2rem;"></i>
      </button>
      <div>
        <h1 style="font-size:1.4rem;font-weight:800;margin:0;display:flex;align-items:center;gap:.5rem;">
          <i class="bi bi-gift" style="color:#a855f7;"></i> Pencairan Dompet Insentif
          <?php if ((int)$stats['cnt_pending'] > 0): ?>
          <span style="background:rgba(247,37,133,.15);color:#f72585;border:1px solid rgba(247,37,133,.3);font-size:.72rem;font-weight:700;border-radius:8px;padding:2px 10px;vertical-align:middle;"><?= (int)$stats['cnt_pending'] ?> pending</span>
          <?php endif; ?>
        </h1>
        <p style="font-size:.78rem;color:var(--text-muted);margin:0;">
          Setujui pencairan sesuai jadwal · Transfer bebas biaya · <?= date('d M Y H:i') ?> WIB
        </p>
      </div>
    </div>
  </div>

  <!-- ── Jadwal Alert ── -->
  <?php if ((int)$stats['due_today'] > 0): ?>
  <div style="background:rgba(16,185,129,.08);border:1.5px solid rgba(16,185,129,.3);border-radius:14px;padding:.85rem 1.25rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:.85rem;">
    <i class="bi bi-alarm-fill" style="color:#10b981;font-size:1.5rem;flex-shrink:0;"></i>
    <div>
      <div style="font-weight:700;color:#10b981;">
        <?= (int)$stats['due_today'] ?> pencairan harus ditransfer <strong>hari ini</strong>
      </div>
      <div style="font-size:.78rem;color:var(--text-muted);">
        Member mengajukan sebelum jam 12:00 WIB – mohon segera diproses.
        <a href="?status=pending&date=today" style="color:#10b981;font-weight:700;margin-left:.5rem;">Lihat →</a>
      </div>
    </div>
  </div>
  <?php endif; ?>
  <?php if ((int)$stats['due_tomorrow'] > 0): ?>
  <div style="background:rgba(245,158,11,.06);border:1px solid rgba(245,158,11,.2);border-radius:14px;padding:.75rem 1.25rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:.85rem;">
    <i class="bi bi-calendar-event" style="color:#f59e0b;font-size:1.3rem;flex-shrink:0;"></i>
    <div style="font-size:.82rem;color:var(--text-muted);">
      <strong style="color:#f59e0b;"><?= (int)$stats['due_tomorrow'] ?></strong> pencairan dijadwalkan ditransfer <strong>besok</strong>.
      <a href="?status=pending&date=tomorrow" style="color:#f59e0b;font-weight:700;margin-left:.5rem;">Lihat →</a>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Stat Cards ── -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
      <div class="stat-card">
        <div class="val" style="color:#f59e0b;"><?= (int)$stats['cnt_pending'] ?></div>
        <div class="lbl">Menunggu</div>
      </div>
    </div>
    <div class="col-6 col-xl-3">
      <div class="stat-card">
        <div class="val" style="color:#00d4ff;"><?= (int)$stats['cnt_processing'] ?></div>
        <div class="lbl">Sedang Diproses</div>
      </div>
    </div>
    <div class="col-6 col-xl-3">
      <div class="stat-card">
        <div class="val" style="color:#10b981;"><?= (int)$stats['cnt_approved'] ?></div>
        <div class="lbl">Selesai</div>
      </div>
    </div>
    <div class="col-6 col-xl-3">
      <div class="stat-card">
        <div class="val" style="color:#a855f7;"><?= formatRupiah((float)$stats['vol_approved']) ?></div>
        <div class="lbl">Total Dicairkan</div>
      </div>
    </div>
  </div>

  <!-- ── Filter Bar ── -->
  <div class="filter-bar mb-3">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
      <div style="display:flex;gap:.35rem;flex-wrap:wrap;align-items:center;">
        <a href="incentive_withdrawals.php"                   class="filter-tab <?= !$sfilt && !$dateFilter ? 'active' : '' ?>">Semua</a>
        <a href="?status=pending"    class="filter-tab <?= $sfilt==='pending' && !$dateFilter ? 'active' : '' ?>">Menunggu (<?= (int)$stats['cnt_pending'] ?>)</a>
        <a href="?status=pending&date=today"    class="filter-tab <?= $dateFilter==='today' ? 'active' : '' ?>" style="<?= $dateFilter==='today' ? '' : '' ?>">
          <i class="bi bi-sun me-1"></i>Hari Ini (<?= (int)$stats['due_today'] ?>)
        </a>
        <a href="?status=pending&date=tomorrow" class="filter-tab <?= $dateFilter==='tomorrow' ? 'active' : '' ?>">
          <i class="bi bi-moon me-1"></i>Besok (<?= (int)$stats['due_tomorrow'] ?>)
        </a>
        <a href="?status=approved"   class="filter-tab <?= $sfilt==='approved' && !$dateFilter ? 'active' : '' ?>">Selesai (<?= (int)$stats['cnt_approved'] ?>)</a>
        <a href="?status=rejected"   class="filter-tab <?= $sfilt==='rejected' && !$dateFilter ? 'active' : '' ?>">Ditolak (<?= (int)$stats['cnt_rejected'] ?>)</a>
      </div>
      <form method="GET" action="incentive_withdrawals.php" style="display:flex;gap:.5rem;align-items:center;">
        <?php if ($sfilt): ?><input type="hidden" name="status" value="<?= htmlspecialchars($sfilt) ?>"/><?php endif; ?>
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
          placeholder="Cari nama, email, no. rekening…"
          style="background:rgba(255,255,255,.05);border:1.5px solid var(--border-glass);color:var(--text-primary);border-radius:10px;padding:.45rem .9rem;font-size:.83rem;width:220px;"/>
        <button type="submit" style="background:rgba(108,99,255,.15);border:1.5px solid rgba(108,99,255,.25);color:#a78bfa;border-radius:10px;padding:.45rem 1rem;font-size:.83rem;cursor:pointer;">
          <i class="bi bi-search"></i>
        </button>
      </form>
    </div>
  </div>

  <!-- ── Table ── -->
  <div style="background:var(--bg-card);border:1px solid var(--border-glass);border-radius:18px;overflow:hidden;">
    <?php if (empty($withdrawals)): ?>
    <div style="padding:3.5rem;text-align:center;">
      <i class="bi bi-inbox" style="font-size:2.5rem;color:var(--text-muted);display:block;margin-bottom:.75rem;"></i>
      <div style="color:var(--text-muted);font-size:.875rem;">
        <?= $search ? 'Tidak ada hasil untuk "' . htmlspecialchars($search) . '"' : 'Belum ada permintaan pencairan insentif' ?>
      </div>
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="wdr-table w-100">
        <thead>
          <tr>
            <th>No. Pencairan</th>
            <th>Member</th>
            <th>Nominal</th>
            <th>Tujuan</th>
            <th>Jadwal Transfer</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($withdrawals as $w):
            $si       = $statusInfo[$w['status']] ?? ['label'=>$w['status'],'bg'=>'rgba(148,163,184,.1)','color'=>'#94a3b8','icon'=>'bi-dash'];
            $today    = date('Y-m-d');
            $tomr     = date('Y-m-d', strtotime('+1 day'));
            $isToday    = ($w['scheduled_date'] === $today);
            $isTomorrow = ($w['scheduled_date'] === $tomr);
            $isOverdue  = ($w['scheduled_date'] < $today && $w['status'] === 'pending');
            $rowClass   = $isOverdue ? 'row-overdue' : ($isToday && $w['status'] === 'pending' ? 'row-today' : '');
            $schedClass = $isOverdue ? 'sched-overdue' : ($isToday ? 'sched-today' : 'sched-tomorrow');
            $schedLabel = $isOverdue ? '⚠ Terlambat' : ($isToday ? 'Hari Ini' : ($isTomorrow ? 'Besok' : date('d M', strtotime($w['scheduled_date']))));
          ?>
          <tr class="<?= $rowClass ?>">
            <!-- No -->
            <td>
              <div style="font-weight:700;color:var(--text-primary);font-size:.8rem;"><?= htmlspecialchars($w['wdr_no']) ?></div>
              <div style="font-size:.68rem;color:var(--text-muted);"><?= date('d M Y H:i', strtotime($w['created_at'])) ?></div>
            </td>
            <!-- Member -->
            <td>
              <div style="display:flex;align-items:center;gap:.5rem;">
                <div style="width:30px;height:30px;border-radius:8px;background:linear-gradient(135deg,#6c63ff,#a855f7);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.75rem;font-weight:700;flex-shrink:0;"><?= htmlspecialchars($w['user_avatar'] ?? '?') ?></div>
                <div>
                  <div style="font-weight:600;color:var(--text-primary);font-size:.82rem;"><?= htmlspecialchars($w['user_name']) ?></div>
                  <div style="font-size:.68rem;color:var(--text-muted);"><?= htmlspecialchars($w['member_code'] ?? $w['user_email']) ?></div>
                </div>
              </div>
            </td>
            <!-- Nominal -->
            <td>
              <div style="font-weight:700;color:#a855f7;font-size:.9rem;"><?= formatRupiah((float)$w['amount']) ?></div>
              <span class="free-badge">0 Biaya</span>
            </td>
            <!-- Tujuan -->
            <td>
              <div style="font-weight:600;color:var(--text-primary);font-size:.82rem;"><?= htmlspecialchars($w['bank_name']) ?></div>
              <div style="font-size:.7rem;color:var(--text-muted);"><?= htmlspecialchars($w['bank_account_no']) ?></div>
              <div style="font-size:.7rem;color:var(--text-muted);"><?= htmlspecialchars($w['bank_account_name']) ?></div>
            </td>
            <!-- Jadwal -->
            <td>
              <span class="status-pill <?= $schedClass ?>" style="padding:4px 10px;border-radius:8px;border:1px solid current">
                <i class="bi bi-calendar-check me-1"></i><?= $schedLabel ?>
              </span>
              <div style="font-size:.68rem;color:var(--text-muted);margin-top:3px;"><?= htmlspecialchars($w['scheduled_date']) ?></div>
              <?php if ($isOverdue): ?>
              <div style="font-size:.67rem;color:#ef4444;font-weight:700;">Harus segera diproses!</div>
              <?php endif; ?>
            </td>
            <!-- Status -->
            <td>
              <span class="status-pill" style="background:<?= $si['bg'] ?>;color:<?= $si['color'] ?>;">
                <i class="bi <?= $si['icon'] ?>"></i><?= $si['label'] ?>
              </span>
              <?php if ($w['admin_name'] && $w['status'] !== 'pending'): ?>
              <div style="font-size:.67rem;color:var(--text-muted);margin-top:2px;">
                <i class="bi bi-person-check me-1"></i><?= htmlspecialchars($w['admin_name']) ?>
              </div>
              <?php endif; ?>
            </td>
            <!-- Aksi -->
            <td>
              <?php if ($w['status'] === 'pending'): ?>
              <div style="display:flex;gap:.35rem;flex-wrap:wrap;align-items:center;">
                <button class="btn-process"
                  onclick="openModal('processing', <?= $w['id'] ?>,'<?= htmlspecialchars($w['wdr_no']) ?>','<?= htmlspecialchars($w['user_name']) ?>','<?= formatRupiah((float)$w['amount']) ?>')">
                  Proses
                </button>
                <button class="btn-approve"
                  onclick="openModal('approve', <?= $w['id'] ?>,'<?= htmlspecialchars($w['wdr_no']) ?>','<?= htmlspecialchars($w['user_name']) ?>','<?= formatRupiah((float)$w['amount']) ?>')">
                  Setujui
                </button>
                <button class="btn-reject"
                  onclick="openModal('reject', <?= $w['id'] ?>,'<?= htmlspecialchars($w['wdr_no']) ?>','<?= htmlspecialchars($w['user_name']) ?>','<?= formatRupiah((float)$w['amount']) ?>')">
                  Tolak
                </button>
              </div>
              <?php elseif ($w['status'] === 'processing'): ?>
              <div style="display:flex;gap:.35rem;flex-wrap:wrap;">
                <button class="btn-approve"
                  onclick="openModal('approve', <?= $w['id'] ?>,'<?= htmlspecialchars($w['wdr_no']) ?>','<?= htmlspecialchars($w['user_name']) ?>','<?= formatRupiah((float)$w['amount']) ?>')">
                  Konfirmasi Kirim
                </button>
                <button class="btn-reject"
                  onclick="openModal('reject', <?= $w['id'] ?>,'<?= htmlspecialchars($w['wdr_no']) ?>','<?= htmlspecialchars($w['user_name']) ?>','<?= formatRupiah((float)$w['amount']) ?>')">
                  Tolak
                </button>
              </div>
              <?php else: ?>
              <span style="font-size:.75rem;color:var(--text-muted);">
                <?= $w['processed_at'] ? date('d M H:i', strtotime($w['processed_at'])) : '—' ?>
              </span>
              <?php if ($w['admin_note']): ?>
              <div style="font-size:.67rem;color:var(--text-muted);margin-top:2px;" title="<?= htmlspecialchars($w['admin_note']) ?>">
                <i class="bi bi-chat-square-text me-1"></i><?= htmlspecialchars(mb_substr($w['admin_note'], 0, 25)) . (mb_strlen($w['admin_note']) > 25 ? '…' : '') ?>
              </div>
              <?php endif; ?>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div style="padding:.75rem 1.25rem;border-top:1px solid var(--border-glass);display:flex;gap:.35rem;justify-content:center;flex-wrap:wrap;">
      <?php for ($p = 1; $p <= $totalPages; $p++): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"
        style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:8px;font-size:.8rem;font-weight:600;text-decoration:none;<?= $p===$page ? 'background:linear-gradient(135deg,#6c63ff,#00d4ff);color:#fff;' : 'background:rgba(255,255,255,.04);border:1px solid var(--border-glass);color:var(--text-muted);' ?>">
        <?= $p ?>
      </a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</main>

<!-- ═══ Modal Konfirmasi ═══ -->
<div class="modal-overlay" id="actionModal" style="display:none;">
  <div class="modal-dark">
    <div id="modalHeader" style="margin-bottom:1.25rem;"></div>
    <div style="font-size:.83rem;color:var(--text-muted);margin-bottom:1rem;" id="modalDetail"></div>
    <form method="POST" action="incentive_withdrawals.php" id="actionForm">
      <?= csrfField() ?>
      <input type="hidden" name="action"   id="modalAction"/>
      <input type="hidden" name="wdr_id"   id="modalWdrId"/>
      <input type="hidden" name="_status"  value="<?= htmlspecialchars($sfilt) ?>"/>
      <input type="hidden" name="_q"       value="<?= htmlspecialchars($search) ?>"/>

      <div style="margin-bottom:1.25rem;">
        <label style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin-bottom:.4rem;display:block;">
          Catatan Admin (opsional)
        </label>
        <textarea name="admin_note" id="modalNote" class="form-ctrl-dark" rows="3" placeholder="Catatan / alasan untuk member..."></textarea>
      </div>

      <div style="display:flex;gap:.75rem;justify-content:flex-end;">
        <button type="button" onclick="closeModal()"
          style="background:rgba(255,255,255,.06);border:1.5px solid var(--border-glass);color:var(--text-muted);border-radius:10px;padding:.55rem 1.25rem;font-size:.85rem;cursor:pointer;">
          Batal
        </button>
        <button type="submit" id="modalSubmitBtn"
          style="border:none;border-radius:10px;padding:.55rem 1.5rem;font-size:.85rem;font-weight:700;cursor:pointer;color:#fff;">
          Konfirmasi
        </button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/main.js"></script>
<script>
// ── Modal ─────────────────────────────────────────────────────
function openModal(action, id, wdrNo, userName, amount) {
  const configs = {
    approve: {
      title: '✓ Setujui Pencairan',
      color: '#10b981',
      bg:    'rgba(16,185,129,.1)',
      btnBg: 'linear-gradient(135deg,#10b981,#00d4ff)',
      btnTxt:'Setujui & Kirim Dana',
    },
    reject: {
      title: '✗ Tolak Pencairan',
      color: '#ef4444',
      bg:    'rgba(239,68,68,.08)',
      btnBg: 'linear-gradient(135deg,#ef4444,#f43f5e)',
      btnTxt:'Tolak & Kembalikan Saldo',
    },
    processing: {
      title: '↻ Tandai Sedang Diproses',
      color: '#00d4ff',
      bg:    'rgba(0,212,255,.08)',
      btnBg: 'linear-gradient(135deg,#00d4ff,#6c63ff)',
      btnTxt:'Tandai Diproses',
    },
  };
  const c = configs[action];
  document.getElementById('modalAction').value  = action;
  document.getElementById('modalWdrId').value   = id;
  document.getElementById('modalNote').value    = '';
  document.getElementById('modalHeader').innerHTML = `
    <div style="font-size:1.1rem;font-weight:800;color:${c.color};">${c.title}</div>
    <div style="font-size:.75rem;color:var(--text-muted);margin-top:4px;">${wdrNo} · ${amount}</div>
  `;
  document.getElementById('modalDetail').innerHTML = `
    <div style="background:${c.bg};border-radius:10px;padding:.75rem;font-size:.8rem;">
      <i class="bi bi-person me-1"></i><strong>${userName}</strong> —
      Pencairan Dompet Insentif sebesar <strong>${amount}</strong>
      <br><span style="color:var(--text-muted);font-size:.72rem;">Transfer: GRATIS · Biaya Admin: Rp 0</span>
    </div>
  `;
  const btn = document.getElementById('modalSubmitBtn');
  btn.textContent = c.btnTxt;
  btn.style.background = c.btnBg;
  document.getElementById('actionModal').style.display = 'flex';
}

function closeModal() {
  document.getElementById('actionModal').style.display = 'none';
}
document.getElementById('actionModal')?.addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});

// ── Sidebar ───────────────────────────────────────────────────
document.getElementById('sidebarOverlay')?.addEventListener('click', function() {
  document.getElementById('mainSidebar').classList.remove('open');
  this.style.display = 'none';
});
function toggleSidebarSubmenu(el) {
  const li  = el.closest('.sidebar-has-submenu');
  const sub = li.querySelector('.sidebar-submenu');
  const isOpen = el.classList.contains('open');
  document.querySelectorAll('.sidebar-link-toggle.open').forEach(m => {
    if (m !== el) { m.classList.remove('open'); m.closest('.sidebar-has-submenu').querySelector('.sidebar-submenu').style.display='none'; }
  });
  if (isOpen) { el.classList.remove('open'); sub.style.display='none'; }
  else        { el.classList.add('open');    sub.style.display='block'; }
}
</script>
</body>
</html>
