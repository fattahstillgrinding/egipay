<?php
require_once __DIR__ . '/../includes/config.php';
requireAdmin();

$user  = getCurrentUser();
$flash = getFlash();

// ── Handle POST actions ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action    = $_POST['action']    ?? '';
    $wdrId     = (int)($_POST['wdr_id'] ?? 0);
    $adminNote = substr(trim($_POST['admin_note'] ?? ''), 0, 500);
    $adminId   = (int)$_SESSION['user_id'];

    if ($wdrId && in_array($action, ['approve', 'reject', 'processing'])) {
        $wdr = dbFetchOne('SELECT * FROM withdrawals WHERE id = ?', [$wdrId]);

        if ($wdr && $wdr['status'] === 'pending') {
            try {
                $pdo = getDB();
                $pdo->beginTransaction();

                if ($action === 'approve') {
                    // 1. Mark approved
                    dbExecute(
                        'UPDATE withdrawals SET status="approved", admin_id=?, admin_note=?, processed_at=NOW() WHERE id=?',
                        [$adminId, $adminNote ?: null, $wdrId]
                    );
                    // 2. Release locked balance → into total_out (balance was already deducted on request)
                    dbExecute(
                        'UPDATE wallets SET locked = locked - ?, total_out = total_out + ? WHERE user_id = ?',
                        [$wdr['amount'], $wdr['amount'], $wdr['user_id']]
                    );
                    // 3. Create transaction record
                    do {
                        $txId   = 'WDR-TX-' . strtoupper(bin2hex(random_bytes(3)));
                        $exists = dbFetchOne('SELECT id FROM transactions WHERE tx_id=?', [$txId]);
                    } while ($exists);

                    dbExecute(
                        'INSERT INTO transactions (tx_id, user_id, type, amount, fee, total, recipient, recipient_bank, note, status, paid_at)
                         VALUES (?, ?, "withdrawal", ?, ?, ?, ?, ?, ?, "success", NOW())',
                        [
                            $txId,
                            $wdr['user_id'],
                            $wdr['amount'],
                            $wdr['fee'],
                            $wdr['net_amount'],
                            $wdr['bank_account_name'] . ' · ' . $wdr['bank_account_no'],
                            $wdr['bank_name'],
                            $wdr['wdr_no'],
                        ]
                    );
                    // 4. Notify user
                    dbExecute(
                        'INSERT INTO notifications (user_id, type, title, message) VALUES (?, "success", ?, ?)',
                        [
                            $wdr['user_id'],
                            'Penarikan Disetujui ✓',
                            "Penarikan {$wdr['wdr_no']} sebesar " . formatRupiah($wdr['amount']) . " telah disetujui. Dana akan segera masuk ke rekening Anda.",
                        ]
                    );
                    auditLog($adminId, 'admin_wdr_approve', "WDR {$wdr['wdr_no']} disetujui");
                    $pdo->commit();
                    setFlash('success', 'Penarikan Disetujui', "{$wdr['wdr_no']} berhasil disetujui. Dana sedang dikirimkan.");

                } elseif ($action === 'reject') {
                    // 1. Reject & restore balance
                    dbExecute(
                        'UPDATE withdrawals SET status="rejected", admin_id=?, admin_note=?, processed_at=NOW() WHERE id=?',
                        [$adminId, $adminNote ?: 'Ditolak oleh admin.', $wdrId]
                    );
                    // 2. Restore balance from locked
                    dbExecute(
                        'UPDATE wallets SET balance = balance + ?, locked = locked - ? WHERE user_id = ?',
                        [$wdr['amount'], $wdr['amount'], $wdr['user_id']]
                    );
                    // 3. Notify user
                    $reason = $adminNote ?: 'Permintaan tidak memenuhi syarat.';
                    dbExecute(
                        'INSERT INTO notifications (user_id, type, title, message) VALUES (?, "error", ?, ?)',
                        [
                            $wdr['user_id'],
                            'Penarikan Ditolak',
                            "Penarikan {$wdr['wdr_no']} sebesar " . formatRupiah($wdr['amount']) . " ditolak. Alasan: {$reason} Saldo telah dikembalikan.",
                        ]
                    );
                    auditLog($adminId, 'admin_wdr_reject', "WDR {$wdr['wdr_no']} ditolak: $reason");
                    $pdo->commit();
                    setFlash('success', 'Penarikan Ditolak', "{$wdr['wdr_no']} ditolak dan saldo dikembalikan ke member.");

                } elseif ($action === 'processing') {
                    dbExecute(
                        'UPDATE withdrawals SET status="processing", admin_id=?, admin_note=? WHERE id=?',
                        [$adminId, $adminNote ?: null, $wdrId]
                    );
                    dbExecute(
                        'INSERT INTO notifications (user_id, type, title, message) VALUES (?, "info", ?, ?)',
                        [$wdr['user_id'], 'Penarikan Sedang Diproses', "Penarikan {$wdr['wdr_no']} sedang dalam proses transfer."]
                    );
                    auditLog($adminId, 'admin_wdr_processing', "WDR {$wdr['wdr_no']} → processing");
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

    redirect(BASE_URL . '/admin/withdrawals.php?' . http_build_query(array_filter([
        'status' => $_POST['_status'] ?? '',
        'q'      => $_POST['_q']      ?? '',
        'page'   => $_POST['_page']   ?? '',
    ])));
}

// ── Filters ───────────────────────────────────────────────────
$sfilt  = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];

if ($sfilt) { $where[] = 'w.status = ?'; $params[] = $sfilt; }
if ($search) {
    $where[] = '(w.wdr_no LIKE ? OR u.name LIKE ? OR u.email LIKE ? OR w.bank_account_no LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%";
    $params[] = "%$search%"; $params[] = "%$search%";
}
$whrStr = implode(' AND ', $where);

$totalRows  = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM withdrawals w JOIN users u ON w.user_id=u.id WHERE {$whrStr}", $params)['c'] ?? 0);
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$withdrawals = dbFetchAll(
    "SELECT w.*, u.name AS user_name, u.email AS user_email, u.phone AS user_phone, u.avatar AS user_avatar,
            a.name AS admin_name
     FROM withdrawals w
     JOIN users u ON w.user_id = u.id
     LEFT JOIN users a ON w.admin_id = a.id
     WHERE {$whrStr}
     ORDER BY FIELD(w.status,'pending','processing','approved','rejected'), w.created_at DESC
     LIMIT {$perPage} OFFSET {$offset}",
    $params
);

// ── Summary stats ─────────────────────────────────────────────
$stats = dbFetchOne(
    'SELECT
       COUNT(*) AS total,
       COALESCE(SUM(amount),0) AS vol_total,
       COUNT(CASE WHEN status="pending"    THEN 1 END) AS cnt_pending,
       COUNT(CASE WHEN status="processing" THEN 1 END) AS cnt_processing,
       COUNT(CASE WHEN status="approved"   THEN 1 END) AS cnt_approved,
       COUNT(CASE WHEN status="rejected"   THEN 1 END) AS cnt_rejected,
       COALESCE(SUM(CASE WHEN status="approved" THEN net_amount END),0) AS vol_approved
     FROM withdrawals'
);

$statusInfo = [
    'pending'    => ['label'=>'Menunggu',    'bg'=>'rgba(245,158,11,.12)', 'color'=>'#f59e0b', 'icon'=>'bi-clock'],
    'processing' => ['label'=>'Diproses',    'bg'=>'rgba(0,212,255,.12)', 'color'=>'#00d4ff',  'icon'=>'bi-arrow-repeat'],
    'approved'   => ['label'=>'Disetujui',   'bg'=>'rgba(16,185,129,.12)','color'=>'#10b981',  'icon'=>'bi-check-circle-fill'],
    'rejected'   => ['label'=>'Ditolak',     'bg'=>'rgba(239,68,68,.12)', 'color'=>'#ef4444',  'icon'=>'bi-x-circle-fill'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Kelola Penarikan – Admin EgiPay</title>
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
    .modal-dark{background:#1a1a35;border:1px solid rgba(108,99,255,.3);border-radius:20px;padding:2rem;max-width:440px;width:100%;}
    .modal-overlay{position:fixed;inset:0;background:rgba(10,10,30,.75);backdrop-filter:blur(8px);z-index:1060;display:flex;align-items:center;justify-content:center;padding:1rem;}
    .form-ctrl-dark{background:rgba(255,255,255,.04);border:1.5px solid rgba(255,255,255,.1);color:var(--text-primary);border-radius:10px;padding:.6rem 1rem;font-size:.85rem;width:100%;resize:vertical;}
    .form-ctrl-dark:focus{outline:none;border-color:#6c63ff;}
    .filter-tab{padding:.3rem .9rem;border-radius:20px;font-size:.75rem;font-weight:600;text-decoration:none;border:1.5px solid var(--border-glass);color:var(--text-muted);transition:all .2s;display:inline-block;}
    .filter-tab:hover,.filter-tab.active{background:rgba(247,37,133,.12);border-color:#f72585;color:#f72585;}
  </style>
</head>
<body>
<div class="page-loader" id="pageLoader"><div class="loader-logo"><svg width="60" height="60" viewBox="0 0 60 60" fill="none"><defs><linearGradient id="lg1" x1="0" y1="0" x2="60" y2="60"><stop stop-color="#f72585"/><stop offset="1" stop-color="#6c63ff"/></linearGradient></defs><rect width="60" height="60" rx="16" fill="url(#lg1)"/><path d="M18 20h14a8 8 0 010 16H18V20zm0 8h12a4 4 0 000-8" fill="white" opacity="0.9"/><circle cx="42" cy="40" r="4" fill="white" opacity="0.7"/></svg></div><div class="loader-bar"><div class="loader-bar-fill"></div></div></div>
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
    <li><a href="withdrawals.php"   class="sidebar-link admin-active">
      <span class="icon"><i class="bi bi-cash-coin"></i></span>Penarikan Dana
      <?php if ($stats['cnt_pending'] > 0): ?>
      <span style="margin-left:auto;background:#f72585;color:#fff;font-size:.6rem;font-weight:700;border-radius:20px;padding:2px 7px;"><?= (int)$stats['cnt_pending'] ?></span>
      <?php endif; ?>
    </a></li>
    <li><a href="incentive_withdrawals.php" class="sidebar-link" style="color:#a855f7;"><span class="icon"><i class="bi bi-gift"></i></span>Pencairan Insentif</a></li>
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
        <h1 style="font-size:1.4rem;font-weight:800;margin:0;">
          Kelola Penarikan Dana
          <?php if ($stats['cnt_pending'] > 0): ?>
          <span style="background:rgba(247,37,133,.15);color:#f72585;border:1px solid rgba(247,37,133,.3);font-size:.72rem;font-weight:700;border-radius:8px;padding:2px 10px;vertical-align:middle;"><?= (int)$stats['cnt_pending'] ?> pending</span>
          <?php endif; ?>
        </h1>
        <p style="font-size:.78rem;color:var(--text-muted);margin:0;">Setujui atau tolak permintaan pencairan dana member</p>
      </div>
    </div>
  </div>

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
        <div class="lbl">Disetujui</div>
      </div>
    </div>
    <div class="col-6 col-xl-3">
      <div class="stat-card">
        <div class="val" style="color:#6c63ff;"><?= formatRupiah((float)$stats['vol_approved']) ?></div>
        <div class="lbl">Total Dicairkan</div>
      </div>
    </div>
  </div>

  <!-- ── Filter Bar ── -->
  <div class="filter-bar mb-3 d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div style="display:flex;gap:.35rem;flex-wrap:wrap;align-items:center;">
      <a href="withdrawals.php"                   class="filter-tab <?= !$sfilt ? 'active' : '' ?>">Semua (<?= (int)$stats['total'] ?>)</a>
      <a href="withdrawals.php?status=pending"    class="filter-tab <?= $sfilt==='pending' ? 'active' : '' ?>">Menunggu (<?= (int)$stats['cnt_pending'] ?>)</a>
      <a href="withdrawals.php?status=processing" class="filter-tab <?= $sfilt==='processing' ? 'active' : '' ?>">Diproses (<?= (int)$stats['cnt_processing'] ?>)</a>
      <a href="withdrawals.php?status=approved"   class="filter-tab <?= $sfilt==='approved' ? 'active' : '' ?>">Selesai (<?= (int)$stats['cnt_approved'] ?>)</a>
      <a href="withdrawals.php?status=rejected"   class="filter-tab <?= $sfilt==='rejected' ? 'active' : '' ?>">Ditolak (<?= (int)$stats['cnt_rejected'] ?>)</a>
    </div>
    <form method="GET" action="withdrawals.php" style="display:flex;gap:.5rem;align-items:center;">
      <?php if ($sfilt): ?><input type="hidden" name="status" value="<?= htmlspecialchars($sfilt) ?>"/><?php endif; ?>
      <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
        placeholder="Cari nama, email, no. rekening…"
        style="background:rgba(255,255,255,.05);border:1.5px solid var(--border-glass);color:var(--text-primary);border-radius:10px;padding:.45rem .9rem;font-size:.83rem;width:220px;"
        oninput="this.style.borderColor='#6c63ff'" onblur="this.style.borderColor=''"/>
      <button type="submit" style="background:rgba(108,99,255,.15);border:1.5px solid rgba(108,99,255,.25);color:#a78bfa;border-radius:10px;padding:.45rem 1rem;font-size:.83rem;cursor:pointer;">
        <i class="bi bi-search"></i>
      </button>
    </form>
  </div>

  <!-- ── Table ── -->
  <div style="background:var(--bg-card);border:1px solid var(--border-glass);border-radius:18px;overflow:hidden;">
    <?php if (empty($withdrawals)): ?>
    <div style="padding:3.5rem;text-align:center;">
      <i class="bi bi-inbox" style="font-size:2.5rem;color:var(--text-muted);display:block;margin-bottom:.75rem;"></i>
      <div style="color:var(--text-muted);font-size:.875rem;">
        <?= $search ? 'Tidak ada hasil untuk "' . htmlspecialchars($search) . '"' : 'Belum ada permintaan penarikan' ?>
      </div>
    </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="wdr-table w-100">
        <thead>
          <tr>
            <th>No. Penarikan</th>
            <th>Member</th>
            <th>Nominal</th>
            <th>Tujuan</th>
            <th>Status</th>
            <th>Tanggal</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($withdrawals as $w): ?>
          <?php $si = $statusInfo[$w['status']] ?? ['label'=>$w['status'],'bg'=>'rgba(148,163,184,.1)','color'=>'#94a3b8','icon'=>'bi-circle']; ?>
          <tr>
            <!-- No. Penarikan -->
            <td>
              <div style="font-weight:700;font-size:.82rem;color:var(--text-primary);"><?= htmlspecialchars($w['wdr_no']) ?></div>
              <?php if ($w['note']): ?>
              <div style="font-size:.7rem;color:var(--text-muted);margin-top:2px;" title="<?= htmlspecialchars($w['note']) ?>">
                <i class="bi bi-chat-left-text me-1"></i><?= htmlspecialchars(mb_substr($w['note'], 0, 25)) . (mb_strlen($w['note']) > 25 ? '…' : '') ?>
              </div>
              <?php endif; ?>
            </td>
            <!-- Member -->
            <td>
              <div style="display:flex;align-items:center;gap:.5rem;">
                <div style="width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,#6c63ff,#00d4ff);display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:800;color:#fff;flex-shrink:0;">
                  <?= htmlspecialchars($w['user_avatar'] ?? '?') ?>
                </div>
                <div>
                  <div style="font-weight:600;color:var(--text-primary);font-size:.82rem;"><?= htmlspecialchars($w['user_name']) ?></div>
                  <div style="font-size:.7rem;color:var(--text-muted);"><?= htmlspecialchars($w['user_email']) ?></div>
                </div>
              </div>
            </td>
            <!-- Nominal -->
            <td>
              <div style="font-weight:800;color:var(--text-primary);font-size:.9rem;"><?= formatRupiah((float)$w['amount']) ?></div>
              <div style="font-size:.7rem;color:#f59e0b;">Biaya: <?= formatRupiah((float)$w['fee']) ?></div>
              <div style="font-size:.7rem;color:#10b981;">Diterima: <?= formatRupiah((float)$w['net_amount']) ?></div>
            </td>
            <!-- Tujuan -->
            <td>
              <div style="font-weight:700;color:var(--text-primary);font-size:.82rem;"><?= htmlspecialchars($w['bank_name']) ?></div>
              <div style="font-size:.72rem;color:var(--text-secondary);font-family:'Space Grotesk',sans-serif;"><?= htmlspecialchars($w['bank_account_no']) ?></div>
              <div style="font-size:.7rem;color:var(--text-muted);"><?= htmlspecialchars($w['bank_account_name']) ?></div>
            </td>
            <!-- Status -->
            <td>
              <span class="status-pill" style="background:<?= $si['bg'] ?>;color:<?= $si['color'] ?>;">
                <i class="bi <?= $si['icon'] ?>"></i> <?= $si['label'] ?>
              </span>
              <?php if ($w['admin_name'] && in_array($w['status'], ['approved','rejected','processing'])): ?>
              <div style="font-size:.68rem;color:var(--text-muted);margin-top:3px;">
                oleh <?= htmlspecialchars($w['admin_name']) ?>
              </div>
              <?php endif; ?>
              <?php if ($w['admin_note'] && $w['status'] === 'rejected'): ?>
              <div style="font-size:.68rem;color:#ef4444;margin-top:2px;" title="<?= htmlspecialchars($w['admin_note']) ?>">
                "<?= htmlspecialchars(mb_substr($w['admin_note'], 0, 30)) . (mb_strlen($w['admin_note']) > 30 ? '…' : '') ?>"
              </div>
              <?php endif; ?>
            </td>
            <!-- Tanggal -->
            <td style="white-space:nowrap;font-size:.75rem;">
              <?= date('d M Y', strtotime($w['created_at'])) ?><br>
              <span style="color:var(--text-muted);"><?= date('H:i', strtotime($w['created_at'])) ?></span>
              <?php if ($w['processed_at']): ?>
              <div style="font-size:.68rem;color:var(--text-muted);margin-top:2px;">
                Diproses: <?= date('d M H:i', strtotime($w['processed_at'])) ?>
              </div>
              <?php endif; ?>
            </td>
            <!-- Aksi -->
            <td>
              <?php if ($w['status'] === 'pending'): ?>
              <div style="display:flex;flex-direction:column;gap:.35rem;">
                <button class="btn-approve" onclick="openAction(<?= $w['id'] ?>,'approve','<?= htmlspecialchars(addslashes($w['wdr_no'])) ?>','<?= formatRupiah((float)$w['amount']) ?>')">
                  <i class="bi bi-check-lg me-1"></i>Setujui
                </button>
                <button class="btn-process" onclick="openAction(<?= $w['id'] ?>,'processing','<?= htmlspecialchars(addslashes($w['wdr_no'])) ?>','<?= formatRupiah((float)$w['amount']) ?>')">
                  <i class="bi bi-arrow-repeat me-1"></i>Proses
                </button>
                <button class="btn-reject"  onclick="openAction(<?= $w['id'] ?>,'reject','<?= htmlspecialchars(addslashes($w['wdr_no'])) ?>','<?= formatRupiah((float)$w['amount']) ?>')">
                  <i class="bi bi-x-lg me-1"></i>Tolak
                </button>
              </div>
              <?php elseif ($w['status'] === 'processing'): ?>
              <div style="display:flex;flex-direction:column;gap:.35rem;">
                <button class="btn-approve" onclick="openAction(<?= $w['id'] ?>,'approve','<?= htmlspecialchars(addslashes($w['wdr_no'])) ?>','<?= formatRupiah((float)$w['amount']) ?>')">
                  <i class="bi bi-check-lg me-1"></i>Selesaikan
                </button>
                <button class="btn-reject"  onclick="openAction(<?= $w['id'] ?>,'reject','<?= htmlspecialchars(addslashes($w['wdr_no'])) ?>','<?= formatRupiah((float)$w['amount']) ?>')">
                  <i class="bi bi-x-lg me-1"></i>Tolak
                </button>
              </div>
              <?php else: ?>
              <span style="font-size:.72rem;color:var(--text-muted);">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div style="padding:.75rem 1.25rem;border-top:1px solid var(--border-glass);display:flex;gap:.35rem;justify-content:center;flex-wrap:wrap;align-items:center;">
      <span style="font-size:.75rem;color:var(--text-muted);margin-right:.5rem;"><?= $totalRows ?> hasil</span>
      <?php for ($p = 1; $p <= $totalPages; $p++): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['page'=>$p])) ?>"
        style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:8px;font-size:.8rem;font-weight:600;text-decoration:none;<?= $p===$page ? 'background:linear-gradient(135deg,#f72585,#6c63ff);color:#fff;' : 'background:rgba(255,255,255,.04);border:1px solid var(--border-glass);color:var(--text-muted);' ?>">
        <?= $p ?>
      </a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</main>

<!-- ═══ Modal Konfirmasi Aksi ═══ -->
<div id="actionModal" class="modal-overlay" style="display:none;">
  <div class="modal-dark">
    <div id="modal-icon-wrapper" style="text-align:center;margin-bottom:1.25rem;">
      <div id="modal-icon-ring" style="width:60px;height:60px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto .75rem;">
        <i id="modal-icon" style="font-size:1.8rem;"></i>
      </div>
      <h5 id="modal-title" style="font-weight:800;color:var(--text-primary);margin-bottom:.25rem;"></h5>
      <p id="modal-desc" style="font-size:.83rem;color:var(--text-muted);margin:0;"></p>
    </div>

    <form method="POST" action="withdrawals.php" id="actionForm">
      <?= csrfField() ?>
      <input type="hidden" name="_status" value="<?= htmlspecialchars($sfilt) ?>"/>
      <input type="hidden" name="_q"      value="<?= htmlspecialchars($search) ?>"/>
      <input type="hidden" name="_page"   value="<?= $page ?>"/>
      <input type="hidden" name="action"  id="modal-action-input" value=""/>
      <input type="hidden" name="wdr_id"  id="modal-id-input"     value=""/>

      <div style="margin-bottom:1.25rem;">
        <label style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);display:block;margin-bottom:.4rem;">
          Catatan Admin <span id="note-required" style="color:#ef4444;display:none;">*</span>
          <span id="note-optional" style="font-weight:400;">(opsional)</span>
        </label>
        <textarea name="admin_note" id="modal-note" class="form-ctrl-dark" rows="3"
          placeholder="Catatan atau alasan..."></textarea>
      </div>

      <div style="display:flex;gap:.75rem;">
        <button type="button" onclick="closeActionModal()"
          style="flex:1;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);color:var(--text-muted);border-radius:12px;padding:.75rem;font-size:.875rem;font-weight:600;cursor:pointer;">
          Batal
        </button>
        <button type="submit" id="modal-submit-btn"
          style="flex:2;border:none;border-radius:12px;padding:.75rem;font-size:.875rem;font-weight:700;cursor:pointer;color:#fff;">
          Konfirmasi
        </button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../js/main.js"></script>
<script>
// ── Action modal ──────────────────────────────────────────────
const actionConfig = {
  approve: {
    title: 'Setujui Penarikan',
    desc: (wdrNo, amount) => `Setujui <strong>${wdrNo}</strong> sebesar <strong>${amount}</strong>? Dana akan segera ditransfer ke rekening member.`,
    iconClass: 'bi-check-circle-fill', iconColor: '#10b981',
    ringBg: 'rgba(16,185,129,.12)', ringBorder: 'rgba(16,185,129,.3)',
    btnBg: 'linear-gradient(135deg,#10b981,#00d4ff)',
    noteRequired: false, noteLabel: 'opsional'
  },
  reject: {
    title: 'Tolak Penarikan',
    desc: (wdrNo, amount) => `Tolak <strong>${wdrNo}</strong> sebesar <strong>${amount}</strong>? Saldo akan dikembalikan ke pembuat permintaan.`,
    iconClass: 'bi-x-circle-fill', iconColor: '#ef4444',
    ringBg: 'rgba(239,68,68,.12)', ringBorder: 'rgba(239,68,68,.3)',
    btnBg: 'linear-gradient(135deg,#ef4444,#f72585)',
    noteRequired: true, noteLabel: 'wajib'
  },
  processing: {
    title: 'Tandai Sedang Diproses',
    desc: (wdrNo, amount) => `Tandai <strong>${wdrNo}</strong> sebagai sedang diproses (transfer sedang dikirim).`,
    iconClass: 'bi-arrow-repeat', iconColor: '#00d4ff',
    ringBg: 'rgba(0,212,255,.12)', ringBorder: 'rgba(0,212,255,.3)',
    btnBg: 'linear-gradient(135deg,#00d4ff,#6c63ff)',
    noteRequired: false, noteLabel: 'opsional'
  }
};

function openAction(id, action, wdrNo, amount) {
  const cfg = actionConfig[action];
  if (!cfg) return;

  document.getElementById('modal-action-input').value = action;
  document.getElementById('modal-id-input').value     = id;
  document.getElementById('modal-note').value         = '';
  document.getElementById('modal-title').textContent  = cfg.title;
  document.getElementById('modal-desc').innerHTML     = cfg.desc(wdrNo, amount);
  document.getElementById('modal-icon').className     = 'bi ' + cfg.iconClass;
  document.getElementById('modal-icon').style.color   = cfg.iconColor;
  document.getElementById('modal-icon-ring').style.background  = cfg.ringBg;
  document.getElementById('modal-icon-ring').style.border      = '2px solid ' + cfg.ringBorder;
  document.getElementById('modal-submit-btn').style.background = cfg.btnBg;
  document.getElementById('modal-submit-btn').textContent      = cfg.title;
  document.getElementById('note-required').style.display       = cfg.noteRequired ? 'inline' : 'none';
  document.getElementById('note-optional').style.display       = cfg.noteRequired ? 'none' : 'inline';

  document.getElementById('actionModal').style.display = 'flex';
}

function closeActionModal() {
  document.getElementById('actionModal').style.display = 'none';
}

document.getElementById('actionModal').addEventListener('click', function(e) {
  if (e.target === this) closeActionModal();
});

// Validate reject note required
document.getElementById('actionForm').addEventListener('submit', function(e) {
  const action = document.getElementById('modal-action-input').value;
  const note   = document.getElementById('modal-note').value.trim();
  if (action === 'reject' && !note) {
    e.preventDefault();
    document.getElementById('modal-note').style.borderColor = '#ef4444';
    document.getElementById('modal-note').focus();
    if (typeof showToast === 'function') showToast('Wajib Diisi', 'Masukkan alasan penolakan.', 'error');
  }
});

// ── Sidebar ───────────────────────────────────────────────────
document.getElementById('sidebarOverlay')?.addEventListener('click', function() {
  document.getElementById('mainSidebar').classList.remove('open');
  this.style.display = 'none';
});
</script>
</body>
</html>
