<?php
require_once __DIR__ . '/includes/config.php';
requireLogin();

$user   = getCurrentUser();
$userId = (int)$_SESSION['user_id'];
$flash  = getFlash();
$errors = [];

// ── Auto-create incentive wallet jika belum ada ───────────────
$incWallet = dbFetchOne('SELECT * FROM incentive_wallets WHERE user_id = ?', [$userId]);
if (!$incWallet) {
    dbExecute('INSERT INTO incentive_wallets (user_id) VALUES (?)', [$userId]);
    $incWallet = dbFetchOne('SELECT * FROM incentive_wallets WHERE user_id = ?', [$userId]);
}

// ── Hitung jadwal transfer berdasarkan jam 12:00 WIB ─────────
// Asumsi server timezone = WIB (Asia/Jakarta). Ubah jika perlu.
$currentHour   = (int)date('H');
$isBeforeNoon  = ($currentHour < 12);
$scheduledDate = $isBeforeNoon
    ? date('Y-m-d')
    : date('Y-m-d', strtotime('+1 day'));
$scheduledLabel = $isBeforeNoon
    ? 'Hari ini, ' . date('d M Y')
    : 'Besok, ' . date('d M Y', strtotime('+1 day'));
$scheduleInfo = $isBeforeNoon
    ? 'Withdrawal sebelum jam 12:00 WIB → ditransfer hari ini'
    : 'Withdrawal setelah jam 12:00 WIB → ditransfer besok';

// ── Banks ─────────────────────────────────────────────────────
$banks = [
    'BCA'       => ['label' => 'Bank BCA',       'type' => 'bank',    'fee' => 0],
    'BNI'       => ['label' => 'Bank BNI',       'type' => 'bank',    'fee' => 0],
    'BRI'       => ['label' => 'Bank BRI',       'type' => 'bank',    'fee' => 0],
    'Mandiri'   => ['label' => 'Bank Mandiri',   'type' => 'bank',    'fee' => 0],
    'CIMB'      => ['label' => 'Bank CIMB Niaga','type' => 'bank',    'fee' => 0],
    'BSI'       => ['label' => 'Bank BSI',       'type' => 'bank',    'fee' => 0],
    'GoPay'     => ['label' => 'GoPay',          'type' => 'ewallet', 'fee' => 0],
    'OVO'       => ['label' => 'OVO',            'type' => 'ewallet', 'fee' => 0],
    'DANA'      => ['label' => 'DANA',           'type' => 'ewallet', 'fee' => 0],
    'ShopeePay' => ['label' => 'ShopeePay',      'type' => 'ewallet', 'fee' => 0],
];
$MIN_INC = 10000;

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    // ── TRANSFER antar username ───────────────────────────────
    if ($action === 'transfer') {
        $targetCode = strtoupper(trim($_POST['target_code'] ?? ''));
        $rawAmount  = str_replace(['.', ','], ['', '.'], trim($_POST['transfer_amount'] ?? '0'));
        $amount     = (float)$rawAmount;
        $note       = substr(trim($_POST['transfer_note'] ?? ''), 0, 255);

        $balance = (float)($incWallet['balance'] ?? 0);

        if (!$targetCode)                  $errors['target_code']      = 'Masukkan kode member / username tujuan.';
        if ($amount < $MIN_INC)            $errors['transfer_amount']  = 'Minimum transfer ' . formatRupiah($MIN_INC) . '.';
        elseif ($amount > $balance)        $errors['transfer_amount']  = 'Saldo Insentif tidak mencukupi.';

        $targetUser = null;
        if (empty($errors['target_code'])) {
            // Cari berdasarkan member_code ATAU email
            $targetUser = dbFetchOne(
                'SELECT id, name, email, avatar, member_code FROM users
                 WHERE (member_code = ? OR email = ?) AND status = "active" AND id != ? LIMIT 1',
                [$targetCode, strtolower($targetCode), $userId]
            );
            if (!$targetUser) {
                $errors['target_code'] = 'Member tidak ditemukan atau tidak aktif.';
            }
        }

        if (empty($errors)) {
            // Auto-create incentive wallet for recipient if not exists
            dbExecute(
                'INSERT IGNORE INTO incentive_wallets (user_id) VALUES (?)',
                [(int)$targetUser['id']]
            );

            do {
                $refNo  = 'INC-' . strtoupper(bin2hex(random_bytes(4)));
                $exists = dbFetchOne('SELECT id FROM incentive_transfers WHERE ref_no = ?', [$refNo]);
            } while ($exists);

            try {
                $pdo = getDB();
                $pdo->beginTransaction();

                // Kurangi saldo pengirim
                dbExecute(
                    'UPDATE incentive_wallets SET balance = balance - ?, total_transferred = total_transferred + ? WHERE user_id = ?',
                    [$amount, $amount, $userId]
                );
                // Tambah saldo penerima
                dbExecute(
                    'UPDATE incentive_wallets SET balance = balance + ?, total_received = total_received + ? WHERE user_id = ?',
                    [$amount, $amount, (int)$targetUser['id']]
                );
                // Catat transfer
                dbExecute(
                    'INSERT INTO incentive_transfers (ref_no, from_user_id, to_user_id, amount, note) VALUES (?,?,?,?,?)',
                    [$refNo, $userId, (int)$targetUser['id'], $amount, $note ?: null]
                );
                // Notifikasi penerima
                dbExecute(
                    'INSERT INTO notifications (user_id, type, title, message) VALUES (?, "success", ?, ?)',
                    [
                        (int)$targetUser['id'],
                        'Insentif Diterima! 🎉',
                        htmlspecialchars($user['name']) . ' mengirim ' . formatRupiah($amount) . ' ke Dompet Insentif Anda. Ref: ' . $refNo,
                    ]
                );
                // Notifikasi pengirim
                dbExecute(
                    'INSERT INTO notifications (user_id, type, title, message) VALUES (?, "info", ?, ?)',
                    [
                        $userId,
                        'Transfer Insentif Berhasil',
                        'Transfer ' . formatRupiah($amount) . ' ke ' . htmlspecialchars($targetUser['name']) . ' berhasil. Ref: ' . $refNo,
                    ]
                );

                auditLog($userId, 'incentive_transfer', "{$refNo} → {$targetUser['member_code']} | " . formatRupiah($amount));
                $pdo->commit();

                setFlash('success', 'Transfer Berhasil!',
                    formatRupiah($amount) . ' berhasil dikirim ke ' . htmlspecialchars($targetUser['name']) . ' · Ref: ' . $refNo
                );
                redirect(BASE_URL . '/incentive_wallet.php');
            } catch (PDOException $e) {
                $pdo->rollBack();
                $errors['general'] = 'Gagal memproses transfer. Silakan coba lagi.';
            }
        }
    }

    // ── WITHDRAWAL dari Dompet Insentif ───────────────────────
    if ($action === 'withdraw') {
        $rawAmount  = str_replace(['.', ','], ['', '.'], trim($_POST['wdr_amount'] ?? '0'));
        $amount     = (float)$rawAmount;
        $bankCode   = trim($_POST['wdr_bank']      ?? '');
        $accNo      = trim($_POST['wdr_acc_no']    ?? '');
        $accName    = trim($_POST['wdr_acc_name']  ?? '');
        $note       = substr(trim($_POST['wdr_note'] ?? ''), 0, 255);

        $balance = (float)($incWallet['balance'] ?? 0);

        if ($amount < $MIN_INC)       $errors['wdr_amount']   = 'Minimum pencairan ' . formatRupiah($MIN_INC) . '.';
        elseif ($amount > $balance)   $errors['wdr_amount']   = 'Saldo Insentif tidak mencukupi. Tersedia: ' . formatRupiah($balance) . '.';
        if (!isset($banks[$bankCode])) $errors['wdr_bank']    = 'Pilih tujuan transfer yang valid.';
        if (!$accNo)                   $errors['wdr_acc_no']  = 'Nomor rekening/akun wajib diisi.';
        if (!$accName)                 $errors['wdr_acc_name']= 'Nama pemilik rekening wajib diisi.';

        // Max 3 pending
        if (empty($errors)) {
            $pendingCnt = (int)(dbFetchOne(
                'SELECT COUNT(*) AS c FROM incentive_withdrawals WHERE user_id=? AND status="pending"',
                [$userId]
            )['c'] ?? 0);
            if ($pendingCnt >= 3) {
                $errors['general'] = 'Anda sudah memiliki 3 permintaan pencairan pending. Tunggu hingga diproses.';
            }
        }

        if (empty($errors)) {
            do {
                $wdrNo  = 'IWR-' . strtoupper(bin2hex(random_bytes(4)));
                $exists = dbFetchOne('SELECT id FROM incentive_withdrawals WHERE wdr_no=?', [$wdrNo]);
            } while ($exists);

            $transferInfo = $isBeforeNoon ? 'Hari ini' : 'Besok';

            try {
                $pdo = getDB();
                $pdo->beginTransaction();

                // Lock balance
                dbExecute(
                    'UPDATE incentive_wallets SET balance = balance - ?, locked = locked + ? WHERE user_id = ?',
                    [$amount, $amount, $userId]
                );
                // Insert request
                dbExecute(
                    'INSERT INTO incentive_withdrawals
                       (wdr_no, user_id, amount, fee, net_amount, bank_name, bank_account_no, bank_account_name, note, scheduled_date, transfer_info)
                     VALUES (?,?,?,0,?,?,?,?,?,?,?)',
                    [$wdrNo, $userId, $amount, $amount, $bankCode, $accNo, $accName, $note ?: null, $scheduledDate, $transferInfo]
                );
                // Notifikasi
                dbExecute(
                    'INSERT INTO notifications (user_id, type, title, message) VALUES (?, "info", ?, ?)',
                    [
                        $userId,
                        'Pencairan Insentif Diterima',
                        "Permintaan pencairan {$wdrNo} sebesar " . formatRupiah($amount) . " dijadwalkan ditransfer {$transferInfo}, {$scheduledDate}.",
                    ]
                );

                auditLog($userId, 'incentive_withdrawal', "{$wdrNo} | {$bankCode} {$accNo} | " . formatRupiah($amount) . " | Jadwal: {$scheduledDate}");
                $pdo->commit();

                setFlash('success', 'Pencairan Diajukan!',
                    "{$wdrNo} sebesar " . formatRupiah($amount) . " akan ditransfer {$transferInfo} ({$scheduledDate})."
                );
                redirect(BASE_URL . '/incentive_wallet.php');
            } catch (PDOException $e) {
                $pdo->rollBack();
                $errors['general_wdr'] = 'Gagal memproses pencairan. Silakan coba lagi.';
            }
        }
    }
}

// ── Refresh wallet ────────────────────────────────────────────
$incWallet = dbFetchOne('SELECT * FROM incentive_wallets WHERE user_id = ?', [$userId]);
$balance   = (float)($incWallet['balance'] ?? 0);
$locked    = (float)($incWallet['locked']  ?? 0);

// ── Riwayat Transfer ─────────────────────────────────────────
$transfers = dbFetchAll(
    'SELECT t.*,
            f.name AS from_name, f.member_code AS from_code, f.avatar AS from_avatar,
            r.name AS to_name,   r.member_code AS to_code,   r.avatar AS to_avatar
     FROM incentive_transfers t
     JOIN users f ON t.from_user_id = f.id
     JOIN users r ON t.to_user_id   = r.id
     WHERE t.from_user_id = ? OR t.to_user_id = ?
     ORDER BY t.created_at DESC LIMIT 20',
    [$userId, $userId]
);

// ── Riwayat Pencairan ─────────────────────────────────────────
$withdrawals = dbFetchAll(
    'SELECT * FROM incentive_withdrawals WHERE user_id = ? ORDER BY created_at DESC LIMIT 20',
    [$userId]
);

// ── Summary stats ─────────────────────────────────────────────
$wdrStats = dbFetchOne(
    'SELECT
       COALESCE(SUM(CASE WHEN status="approved" THEN amount END), 0) AS total_approved,
       COUNT(CASE WHEN status="pending"  THEN 1 END) AS cnt_pending
     FROM incentive_withdrawals WHERE user_id = ?',
    [$userId]
);

$notifCount = getUnreadNotifCount($userId);

// Default tab
$activeTab = isset($errors['target_code']) || isset($errors['transfer_amount'])
    ? 'transfer'
    : (isset($errors['wdr_amount']) || isset($errors['wdr_bank']) || isset($errors['wdr_acc_no']) || isset($errors['wdr_acc_name']) || isset($errors['general_wdr']) ? 'withdraw' : 'transfer');

$formT = [
    'target_code'     => $_POST['target_code']     ?? '',
    'transfer_amount' => $_POST['transfer_amount'] ?? '',
    'transfer_note'   => $_POST['transfer_note']   ?? '',
];
$formW = [
    'wdr_amount'   => $_POST['wdr_amount']   ?? '',
    'wdr_bank'     => $_POST['wdr_bank']     ?? '',
    'wdr_acc_no'   => $_POST['wdr_acc_no']   ?? '',
    'wdr_acc_name' => $_POST['wdr_acc_name'] ?? '',
    'wdr_note'     => $_POST['wdr_note']     ?? '',
];

$statusColors = [
    'pending'    => ['bg' => 'rgba(245,158,11,.12)',  'color' => '#f59e0b', 'label' => 'Menunggu'],
    'processing' => ['bg' => 'rgba(0,212,255,.12)',   'color' => '#00d4ff', 'label' => 'Diproses'],
    'approved'   => ['bg' => 'rgba(16,185,129,.12)',  'color' => '#10b981', 'label' => 'Disetujui'],
    'rejected'   => ['bg' => 'rgba(239,68,68,.12)',   'color' => '#ef4444', 'label' => 'Ditolak'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Dompet Insentif – EgiPay</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link href="css/style.css" rel="stylesheet"/>
  <style>
    /* ── Stat Cards ── */
    .inc-stat{background:var(--bg-card);border:1px solid var(--border-glass);border-radius:16px;padding:1.25rem 1.5rem;}
    .inc-stat .value{font-family:'Space Grotesk',sans-serif;font-size:1.4rem;font-weight:800;line-height:1.2;}
    .inc-stat .label{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin-top:.25rem;}

    /* ── Balance hero ── */
    .balance-hero{
      background:linear-gradient(135deg,#6c63ff 0%,#a855f7 50%,#00d4ff 100%);
      border-radius:20px;padding:2rem;color:#fff;position:relative;overflow:hidden;
    }
    .balance-hero::after{
      content:'';position:absolute;right:-30px;top:-30px;
      width:180px;height:180px;border-radius:50%;
      background:rgba(255,255,255,.08);
    }

    /* ── Schedule badge ── */
    .schedule-badge{
      display:inline-flex;align-items:center;gap:6px;
      padding:.35rem .9rem;border-radius:20px;font-size:.75rem;font-weight:700;
    }
    .schedule-today{background:rgba(16,185,129,.15);border:1px solid rgba(16,185,129,.3);color:#10b981;}
    .schedule-tomorrow{background:rgba(245,158,11,.15);border:1px solid rgba(245,158,11,.3);color:#f59e0b;}

    /* ── Form card ── */
    .form-card{background:var(--bg-card);border:1px solid var(--border-glass);border-radius:18px;overflow:hidden;}
    .tab-buttons{display:flex;border-bottom:1px solid var(--border-glass);}
    .tab-btn{flex:1;padding:.85rem;border:none;background:transparent;color:var(--text-muted);font-size:.82rem;font-weight:700;cursor:pointer;transition:all .2s;border-bottom:2px solid transparent;}
    .tab-btn.active{color:#6c63ff;border-bottom-color:#6c63ff;background:rgba(108,99,255,.05);}
    .tab-btn:hover:not(.active){color:var(--text-primary);}
    .tab-pane{display:none;padding:1.5rem;}
    .tab-pane.active{display:block;}

    /* ── Fields ── */
    .field-label{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin-bottom:.4rem;display:block;}
    .form-control-dark{background:rgba(255,255,255,.04);border:1.5px solid var(--border-glass);color:var(--text-primary);border-radius:10px;padding:.65rem 1rem;font-size:.88rem;width:100%;transition:border-color .2s,box-shadow .2s;}
    .form-control-dark:focus{outline:none;border-color:#6c63ff;box-shadow:0 0 0 3px rgba(108,99,255,.18);background:rgba(108,99,255,.04);}
    .form-control-dark.is-invalid{border-color:#ef4444;}
    .form-control-dark option{background:#1a1a2e;color:#f1f5f9;}
    .err-msg{font-size:.75rem;color:#ef4444;margin-top:.35rem;}
    .err-msg i{margin-right:.2rem;}

    /* ── Bank Grid ── */
    .bank-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.4rem;margin-bottom:.25rem;}
    .bank-btn{background:rgba(255,255,255,.04);border:1.5px solid var(--border-glass);border-radius:10px;padding:.45rem .4rem;text-align:center;cursor:pointer;transition:all .2s;font-size:.7rem;font-weight:600;color:var(--text-secondary);}
    .bank-btn:hover{border-color:#6c63ff;color:#6c63ff;}
    .bank-btn.selected{background:rgba(108,99,255,.12);border-color:#6c63ff;color:#6c63ff;}

    /* ── History ── */
    .history-card{background:var(--bg-card);border:1px solid var(--border-glass);border-radius:18px;overflow:hidden;}
    .hist-tabs{display:flex;gap:.35rem;padding:.85rem 1.25rem;border-bottom:1px solid var(--border-glass);}
    .hist-tab{padding:.25rem .85rem;border-radius:20px;font-size:.73rem;font-weight:600;border:1.5px solid var(--border-glass);color:var(--text-muted);cursor:pointer;transition:all .2s;}
    .hist-tab.active{background:rgba(108,99,255,.12);border-color:#6c63ff;color:#6c63ff;}

    .htable th{background:rgba(255,255,255,.03);color:var(--text-muted);font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;padding:.6rem 1rem;border-bottom:1px solid var(--border-glass);}
    .htable td{padding:.7rem 1rem;border-bottom:1px solid rgba(255,255,255,.04);font-size:.82rem;color:var(--text-secondary);vertical-align:middle;}
    .htable tr:last-child td{border-bottom:none;}
    .htable tr:hover td{background:rgba(255,255,255,.02);}

    .status-pill{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:20px;font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;}
    .pill-in{background:rgba(16,185,129,.12);color:#10b981;}
    .pill-out{background:rgba(247,37,133,.12);color:#f72585;}

    /* ── Free badge ── */
    .free-badge{background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.3);color:#10b981;border-radius:8px;padding:2px 8px;font-size:.68rem;font-weight:700;}
  </style>
</head>
<body>
<div class="page-loader" id="pageLoader">
  <div class="loader-logo">
    <svg width="60" height="60" viewBox="0 0 60 60" fill="none"><defs><linearGradient id="lg1" x1="0" y1="0" x2="60" y2="60"><stop stop-color="#6c63ff"/><stop offset="1" stop-color="#00d4ff"/></linearGradient></defs><rect width="60" height="60" rx="16" fill="url(#lg1)"/><path d="M18 20h14a8 8 0 010 16H18V20zm0 8h12a4 4 0 000-8" fill="white" opacity="0.9"/><circle cx="42" cy="40" r="4" fill="white" opacity="0.7"/></svg>
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
    <svg width="36" height="36" viewBox="0 0 42 42" fill="none"><defs><linearGradient id="sLg" x1="0" y1="0" x2="42" y2="42"><stop stop-color="#6c63ff"/><stop offset="1" stop-color="#00d4ff"/></linearGradient></defs><rect width="42" height="42" rx="12" fill="url(#sLg)"/><path d="M12 14h10a6 6 0 010 12H12V14zm0 6h8a2 2 0 000-6" fill="white" opacity=".95"/><circle cx="30" cy="28" r="3" fill="white" opacity=".8"/></svg>
    <span class="brand-text" style="font-size:1.2rem;">EgiPay</span>
  </div>
  <ul class="sidebar-menu">
    <li class="sidebar-section-title">Utama</li>
    <li><a href="dashboard.php"  class="sidebar-link"><span class="icon"><i class="bi bi-grid-1x2-fill"></i></span>Dashboard</a></li>
    <li><a href="payment.php"    class="sidebar-link"><span class="icon"><i class="bi bi-send-fill"></i></span>Kirim Pembayaran</a></li>
    <li><a href="#"              class="sidebar-link"><span class="icon"><i class="bi bi-arrow-left-right"></i></span>Transaksi</a></li>
    <li class="sidebar-has-submenu open">
      <a href="#" class="sidebar-link sidebar-link-toggle open" onclick="toggleSidebarSubmenu(this);return false;">
        <span class="icon"><i class="bi bi-wallet2"></i></span>
        Dompet
        <i class="bi bi-chevron-down sidebar-chevron ms-auto"></i>
      </a>
      <ul class="sidebar-submenu" style="display:block;">
        <li><a href="#" class="sidebar-sublink"><i class="bi bi-file-earmark-text me-2"></i>Wallet Statement</a></li>
        <li><a href="withdrawal.php"       class="sidebar-sublink"><i class="bi bi-box-arrow-up me-2"></i>Penarikan Dana</a></li>
        <li><a href="incentive_wallet.php" class="sidebar-sublink active"><i class="bi bi-gift me-2"></i>Dompet Insentif</a></li>
      </ul>
    </li>
    <li class="sidebar-section-title">Bisnis</li>
    <li><a href="#" class="sidebar-link"><span class="icon"><i class="bi bi-graph-up"></i></span>Analitik</a></li>
    <li><a href="#" class="sidebar-link"><span class="icon"><i class="bi bi-people"></i></span>Pelanggan</a></li>
    <li><a href="#" class="sidebar-link"><span class="icon"><i class="bi bi-receipt"></i></span>Invoice</a></li>
    <li class="sidebar-section-title">Developer</li>
    <li><a href="docs.php" class="sidebar-link"><span class="icon"><i class="bi bi-code-slash"></i></span>API Docs</a></li>
    <li><a href="#" class="sidebar-link"><span class="icon"><i class="bi bi-key"></i></span>API Keys</a></li>
    <li class="sidebar-section-title">Akun</li>
    <li><a href="#" class="sidebar-link"><span class="icon"><i class="bi bi-gear"></i></span>Pengaturan</a></li>
    <li><a href="#" class="sidebar-link"><span class="icon"><i class="bi bi-headset"></i></span>Support</a></li>
    <?php if ($user['role'] === 'admin'): ?>
    <li class="sidebar-section-title">Administrasi</li>
    <li><a href="admin/index.php" class="sidebar-link" style="color:#f72585;"><span class="icon"><i class="bi bi-shield-lock-fill"></i></span>Admin Panel</a></li>
    <?php endif; ?>
    <li><a href="logout.php" class="sidebar-link" style="color:#ef4444;"><span class="icon"><i class="bi bi-box-arrow-left"></i></span>Keluar</a></li>
  </ul>
  <div class="sidebar-bottom">
    <div class="sidebar-profile">
      <div class="profile-avatar-sm"><?= htmlspecialchars($user['avatar'] ?? '?') ?></div>
      <div>
        <div class="profile-name"><?= htmlspecialchars($user['name']) ?></div>
        <div class="profile-role">Membership Plan</div>
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
          <i class="bi bi-gift" style="color:#a855f7;"></i> Dompet Insentif
        </h1>
        <p style="font-size:.78rem;color:var(--text-muted);margin:0;">Transfer gratis antar member · Pencairan otomatis terjadwal</p>
      </div>
    </div>
    <!-- Notif & current time -->
    <div class="d-flex align-items-center gap-2">
      <div class="schedule-badge <?= $isBeforeNoon ? 'schedule-today' : 'schedule-tomorrow' ?>">
        <i class="bi bi-clock"></i>
        <?= date('H:i') ?> WIB &nbsp;·&nbsp; <?= $isBeforeNoon ? 'Transfer hari ini' : 'Transfer besok' ?>
      </div>
    </div>
  </div>

  <!-- ── Balance Hero ── -->
  <div class="balance-hero mb-4">
    <div class="row align-items-center g-3">
      <div class="col-md-6">
        <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;opacity:.75;margin-bottom:.35rem;">
          <i class="bi bi-gift me-1"></i>Saldo Dompet Insentif
        </div>
        <div style="font-family:'Space Grotesk',sans-serif;font-size:2.5rem;font-weight:800;line-height:1.1;">
          <?= formatRupiah($balance) ?>
        </div>
        <?php if ($locked > 0): ?>
        <div style="font-size:.78rem;opacity:.75;margin-top:.35rem;">
          <i class="bi bi-lock-fill me-1"></i>Ditahan: <?= formatRupiah($locked) ?>
        </div>
        <?php endif; ?>
        <div style="margin-top:.75rem;display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
          <span style="background:rgba(255,255,255,.15);border-radius:20px;padding:3px 12px;font-size:.72rem;font-weight:700;">
            <i class="bi bi-arrow-left-right me-1"></i>Transfer: GRATIS
          </span>
          <span style="background:rgba(255,255,255,.15);border-radius:20px;padding:3px 12px;font-size:.72rem;font-weight:700;">
            <i class="bi bi-0-circle me-1"></i>Biaya Admin: Rp 0
          </span>
        </div>
      </div>
      <div class="col-md-6">
        <div class="row g-2">
          <div class="col-6">
            <div style="background:rgba(255,255,255,.1);border-radius:12px;padding:.85rem;">
              <div style="font-size:.68rem;opacity:.75;font-weight:700;text-transform:uppercase;margin-bottom:.25rem;">Total Diterima</div>
              <div style="font-family:'Space Grotesk',sans-serif;font-size:1.05rem;font-weight:800;"><?= formatRupiah((float)($incWallet['total_received'] ?? 0)) ?></div>
            </div>
          </div>
          <div class="col-6">
            <div style="background:rgba(255,255,255,.1);border-radius:12px;padding:.85rem;">
              <div style="font-size:.68rem;opacity:.75;font-weight:700;text-transform:uppercase;margin-bottom:.25rem;">Total Transfer</div>
              <div style="font-family:'Space Grotesk',sans-serif;font-size:1.05rem;font-weight:800;"><?= formatRupiah((float)($incWallet['total_transferred'] ?? 0)) ?></div>
            </div>
          </div>
          <div class="col-6">
            <div style="background:rgba(255,255,255,.1);border-radius:12px;padding:.85rem;">
              <div style="font-size:.68rem;opacity:.75;font-weight:700;text-transform:uppercase;margin-bottom:.25rem;">Total Dicairkan</div>
              <div style="font-family:'Space Grotesk',sans-serif;font-size:1.05rem;font-weight:800;"><?= formatRupiah((float)($wdrStats['total_approved'] ?? 0)) ?></div>
            </div>
          </div>
          <div class="col-6">
            <div style="background:rgba(255,255,255,.1);border-radius:12px;padding:.85rem;">
              <div style="font-size:.68rem;opacity:.75;font-weight:700;text-transform:uppercase;margin-bottom:.25rem;">Pending</div>
              <div style="font-family:'Space Grotesk',sans-serif;font-size:1.05rem;font-weight:800;"><?= (int)($wdrStats['cnt_pending'] ?? 0) ?></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Schedule Info Banner ── -->
  <div style="background:rgba(108,99,255,.07);border:1px solid rgba(108,99,255,.2);border-radius:14px;padding:.85rem 1.25rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:.85rem;flex-wrap:wrap;">
    <div style="width:40px;height:40px;border-radius:10px;background:rgba(108,99,255,.15);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
      <i class="bi bi-calendar-check" style="color:#6c63ff;font-size:1.1rem;"></i>
    </div>
    <div style="flex:1;min-width:200px;">
      <div style="font-weight:700;color:var(--text-primary);font-size:.875rem;">Jadwal Transfer Pencairan</div>
      <div style="font-size:.78rem;color:var(--text-muted);margin-top:2px;">
        <i class="bi bi-check-circle-fill text-success me-1"></i>Pengajuan <strong style="color:#10b981;">sebelum jam 12:00 WIB</strong> → ditransfer <strong>hari yang sama</strong>
        &nbsp;&nbsp;
        <i class="bi bi-clock text-warning me-1"></i>Pengajuan <strong style="color:#f59e0b;">setelah jam 12:00 WIB</strong> → ditransfer <strong>hari berikutnya</strong>
      </div>
    </div>
    <div class="schedule-badge <?= $isBeforeNoon ? 'schedule-today' : 'schedule-tomorrow' ?>">
      <i class="bi bi-send"></i>
      Saat ini: <?= $scheduledLabel ?>
    </div>
  </div>

  <div class="row g-4">

    <!-- ── Form Panel ── -->
    <div class="col-lg-5">
      <div class="form-card">
        <!-- Tabs -->
        <div class="tab-buttons">
          <button type="button" class="tab-btn <?= $activeTab === 'transfer' ? 'active' : '' ?>" onclick="switchTab('transfer')">
            <i class="bi bi-arrow-left-right me-1"></i>Transfer
          </button>
          <button type="button" class="tab-btn <?= $activeTab === 'withdraw' ? 'active' : '' ?>" onclick="switchTab('withdraw')">
            <i class="bi bi-bank me-1"></i>Cairkan
          </button>
        </div>

        <!-- ═══ TAB: TRANSFER ═══ -->
        <div class="tab-pane <?= $activeTab === 'transfer' ? 'active' : '' ?>" id="tab-transfer">
          <?php if (!empty($errors['general'])): ?>
          <div style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);border-radius:10px;padding:.75rem 1rem;margin-bottom:1rem;font-size:.83rem;color:#ef4444;display:flex;gap:8px;">
            <i class="bi bi-exclamation-circle-fill mt-1"></i>
            <span><?= htmlspecialchars($errors['general']) ?></span>
          </div>
          <?php endif; ?>

          <!-- Info gratis -->
          <div style="background:rgba(16,185,129,.07);border:1px solid rgba(16,185,129,.2);border-radius:12px;padding:.75rem 1rem;margin-bottom:1.25rem;font-size:.8rem;color:#10b981;display:flex;gap:8px;align-items:center;">
            <i class="bi bi-shield-check-fill" style="font-size:1.1rem;flex-shrink:0;"></i>
            <div>
              <strong>100% Gratis</strong> — Transfer antar username tidak dikenakan biaya apapun.
            </div>
          </div>

          <form method="POST" action="incentive_wallet.php" id="transferForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="transfer"/>

            <!-- Kode member tujuan -->
            <div class="mb-3">
              <label class="field-label">Kode Member / Email Tujuan</label>
              <div style="position:relative;">
                <input type="text" name="target_code" id="targetCodeInput"
                  class="form-control-dark <?= isset($errors['target_code']) ? 'is-invalid' : '' ?>"
                  placeholder="Contoh: SMU-0005 atau email@mail.com"
                  value="<?= htmlspecialchars($formT['target_code']) ?>"
                  autocomplete="off"
                  oninput="lookupMember(this.value)"/>
                <div id="memberPreview" style="display:none;margin-top:.5rem;background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.25);border-radius:10px;padding:.6rem .85rem;font-size:.8rem;display:flex;align-items:center;gap:.5rem;"></div>
              </div>
              <?php if (isset($errors['target_code'])): ?>
              <div class="err-msg"><i class="bi bi-exclamation-circle"></i><?= htmlspecialchars($errors['target_code']) ?></div>
              <?php endif; ?>
              <div style="font-size:.7rem;color:var(--text-muted);margin-top:.35rem;">
                <i class="bi bi-info-circle me-1"></i>Kode Member Anda: <strong style="color:#a78bfa;"><?= htmlspecialchars($user['member_code'] ?? '-') ?></strong>
              </div>
            </div>

            <!-- Nominal -->
            <div class="mb-3">
              <label class="field-label">Nominal Transfer</label>
              <input type="text" name="transfer_amount" id="transferAmountInput" inputmode="numeric"
                class="form-control-dark <?= isset($errors['transfer_amount']) ? 'is-invalid' : '' ?>"
                placeholder="Contoh: 50.000"
                value="<?= htmlspecialchars($formT['transfer_amount']) ?>"
                autocomplete="off"/>
              <?php if (isset($errors['transfer_amount'])): ?>
              <div class="err-msg"><i class="bi bi-exclamation-circle"></i><?= htmlspecialchars($errors['transfer_amount']) ?></div>
              <?php endif; ?>
              <div style="font-size:.72rem;color:var(--text-muted);margin-top:.35rem;">
                Min <?= formatRupiah($MIN_INC) ?> · Saldo <?= formatRupiah($balance) ?>
              </div>
              <!-- Quick amounts -->
              <div style="display:flex;gap:.3rem;flex-wrap:wrap;margin-top:.5rem;">
                <?php foreach ([25000, 50000, 100000, 200000] as $qa): ?>
                <button type="button" onclick="setTransferAmount(<?= $qa ?>)"
                  style="background:rgba(168,85,247,.08);border:1px solid rgba(168,85,247,.2);color:#a855f7;border-radius:8px;padding:3px 9px;font-size:.7rem;font-weight:600;cursor:pointer;">
                  <?= formatRupiah($qa) ?>
                </button>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Catatan -->
            <div class="mb-4">
              <label class="field-label">Catatan (opsional)</label>
              <input type="text" name="transfer_note"
                class="form-control-dark"
                placeholder="Keterangan..."
                value="<?= htmlspecialchars($formT['transfer_note']) ?>" maxlength="255"/>
            </div>

            <!-- Preview biaya -->
            <div style="background:rgba(16,185,129,.06);border:1px solid rgba(16,185,129,.18);border-radius:12px;padding:.85rem 1rem;margin-bottom:1.25rem;font-size:.83rem;">
              <div style="display:flex;justify-content:space-between;padding:2px 0;color:var(--text-muted);">
                <span>Nominal Transfer</span>
                <span id="tp-amount" style="font-weight:600;color:var(--text-primary);">Rp 0</span>
              </div>
              <div style="display:flex;justify-content:space-between;padding:2px 0;color:var(--text-muted);">
                <span>Biaya Admin</span>
                <span class="free-badge">GRATIS</span>
              </div>
              <div style="display:flex;justify-content:space-between;padding:6px 0 0;border-top:1px solid rgba(16,185,129,.2);margin-top:.4rem;font-weight:800;">
                <span style="color:var(--text-primary);">Diterima Tujuan</span>
                <span id="tp-net" style="color:#10b981;font-size:1rem;">Rp 0</span>
              </div>
            </div>

            <button type="submit" style="width:100%;background:linear-gradient(135deg,#6c63ff,#a855f7);color:#fff;border:none;border-radius:12px;padding:.85rem;font-size:.95rem;font-weight:700;cursor:pointer;transition:opacity .2s;" onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'">
              <i class="bi bi-arrow-left-right me-2"></i>Kirim Insentif – Gratis
            </button>
          </form>
        </div><!-- /tab-transfer -->

        <!-- ═══ TAB: CAIRKAN ═══ -->
        <div class="tab-pane <?= $activeTab === 'withdraw' ? 'active' : '' ?>" id="tab-withdraw">
          <?php if (!empty($errors['general_wdr'])): ?>
          <div style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);border-radius:10px;padding:.75rem 1rem;margin-bottom:1rem;font-size:.83rem;color:#ef4444;display:flex;gap:8px;">
            <i class="bi bi-exclamation-circle-fill mt-1"></i>
            <span><?= htmlspecialchars($errors['general_wdr']) ?></span>
          </div>
          <?php endif; ?>

          <?php if ($balance <= 0): ?>
          <div style="text-align:center;padding:2rem;color:var(--text-muted);font-size:.875rem;">
            <i class="bi bi-wallet2" style="font-size:2.5rem;display:block;margin-bottom:.75rem;color:#6c63ff;"></i>
            Saldo Insentif Anda kosong. Terima atau dapatkan insentif terlebih dahulu.
          </div>
          <?php else: ?>

          <!-- Schedule info -->
          <div class="schedule-badge <?= $isBeforeNoon ? 'schedule-today' : 'schedule-tomorrow' ?> mb-4 w-100" style="border-radius:10px;padding:.6rem .9rem;">
            <i class="bi bi-calendar-event"></i>
            Pencairan ini akan ditransfer: <strong><?= $scheduledLabel ?></strong>
          </div>

          <form method="POST" action="incentive_wallet.php" id="wdrForm">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="withdraw"/>

            <!-- Nominal -->
            <div class="mb-3">
              <label class="field-label">Nominal Pencairan</label>
              <input type="text" name="wdr_amount" id="wdrAmountInput" inputmode="numeric"
                class="form-control-dark <?= isset($errors['wdr_amount']) ? 'is-invalid' : '' ?>"
                placeholder="Contoh: 100.000"
                value="<?= htmlspecialchars($formW['wdr_amount']) ?>"
                autocomplete="off"/>
              <?php if (isset($errors['wdr_amount'])): ?>
              <div class="err-msg"><i class="bi bi-exclamation-circle"></i><?= htmlspecialchars($errors['wdr_amount']) ?></div>
              <?php endif; ?>
              <div style="font-size:.72rem;color:var(--text-muted);margin-top:.35rem;">
                Min <?= formatRupiah($MIN_INC) ?> · Tersedia <?= formatRupiah($balance) ?>
              </div>
              <div style="display:flex;gap:.3rem;flex-wrap:wrap;margin-top:.5rem;">
                <?php foreach ([50000, 100000, 250000, 500000] as $qa): ?>
                <button type="button" onclick="setWdrAmount(<?= $qa ?>)"
                  style="background:rgba(108,99,255,.08);border:1px solid rgba(108,99,255,.2);color:#a78bfa;border-radius:8px;padding:3px 9px;font-size:.7rem;font-weight:600;cursor:pointer;">
                  <?= formatRupiah($qa) ?>
                </button>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Tujuan transfer -->
            <div class="mb-3">
              <label class="field-label">Tujuan Pencairan</label>
              <div class="bank-grid" id="bankGrid">
                <?php foreach ($banks as $key => $b): ?>
                <div class="bank-btn <?= $formW['wdr_bank'] === $key ? 'selected' : '' ?>"
                     onclick="selectBank('<?= $key ?>')">
                  <?= htmlspecialchars($b['label']) ?>
                </div>
                <?php endforeach; ?>
              </div>
              <input type="hidden" name="wdr_bank" id="bankInput" value="<?= htmlspecialchars($formW['wdr_bank']) ?>"/>
              <?php if (isset($errors['wdr_bank'])): ?>
              <div class="err-msg"><i class="bi bi-exclamation-circle"></i><?= htmlspecialchars($errors['wdr_bank']) ?></div>
              <?php endif; ?>
            </div>

            <!-- Nomor rekening -->
            <div class="mb-3">
              <label class="field-label">Nomor Rekening / Akun</label>
              <input type="text" name="wdr_acc_no" inputmode="numeric"
                class="form-control-dark <?= isset($errors['wdr_acc_no']) ? 'is-invalid' : '' ?>"
                placeholder="Nomor rekening atau nomor HP"
                value="<?= htmlspecialchars($formW['wdr_acc_no']) ?>"/>
              <?php if (isset($errors['wdr_acc_no'])): ?>
              <div class="err-msg"><i class="bi bi-exclamation-circle"></i><?= htmlspecialchars($errors['wdr_acc_no']) ?></div>
              <?php endif; ?>
            </div>

            <!-- Nama pemilik -->
            <div class="mb-3">
              <label class="field-label">Nama Pemilik Rekening</label>
              <input type="text" name="wdr_acc_name"
                class="form-control-dark <?= isset($errors['wdr_acc_name']) ? 'is-invalid' : '' ?>"
                placeholder="Sesuai buku tabungan / KTP"
                value="<?= htmlspecialchars($formW['wdr_acc_name']) ?>"/>
              <?php if (isset($errors['wdr_acc_name'])): ?>
              <div class="err-msg"><i class="bi bi-exclamation-circle"></i><?= htmlspecialchars($errors['wdr_acc_name']) ?></div>
              <?php endif; ?>
            </div>

            <!-- Catatan -->
            <div class="mb-3">
              <label class="field-label">Catatan (opsional)</label>
              <input type="text" name="wdr_note"
                class="form-control-dark"
                placeholder="Keterangan tambahan..."
                value="<?= htmlspecialchars($formW['wdr_note']) ?>" maxlength="255"/>
            </div>

            <!-- Fee preview (0 biaya) -->
            <div style="background:rgba(16,185,129,.06);border:1px solid rgba(16,185,129,.18);border-radius:12px;padding:.85rem 1rem;margin-bottom:1.25rem;font-size:.83rem;">
              <div style="display:flex;justify-content:space-between;padding:2px 0;color:var(--text-muted);">
                <span>Nominal Pencairan</span>
                <span id="wp-amount" style="font-weight:600;color:var(--text-primary);">Rp 0</span>
              </div>
              <div style="display:flex;justify-content:space-between;padding:2px 0;color:var(--text-muted);">
                <span>Biaya Admin</span>
                <span class="free-badge">GRATIS</span>
              </div>
              <div style="display:flex;justify-content:space-between;padding:6px 0 0;border-top:1px solid rgba(16,185,129,.2);margin-top:.4rem;font-weight:800;">
                <span style="color:var(--text-primary);">Dana Diterima</span>
                <span id="wp-net" style="color:#10b981;font-size:1rem;">Rp 0</span>
              </div>
              <div style="margin-top:.6rem;padding-top:.6rem;border-top:1px solid rgba(16,185,129,.2);font-size:.75rem;color:var(--text-muted);">
                <i class="bi bi-calendar-check me-1" style="color:#6c63ff;"></i>
                Dijadwalkan: <strong style="color:var(--text-primary);"><?= $scheduledLabel ?></strong>
              </div>
            </div>

            <button type="submit" style="width:100%;background:linear-gradient(135deg,#10b981,#00d4ff);color:#fff;border:none;border-radius:12px;padding:.85rem;font-size:.95rem;font-weight:700;cursor:pointer;transition:opacity .2s;" onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'">
              <i class="bi bi-bank me-2"></i>Cairkan Sekarang
            </button>
            <div style="font-size:.72rem;color:var(--text-muted);text-align:center;margin-top:.5rem;">
              <?= $scheduleInfo ?>
            </div>
          </form>
          <?php endif; ?>
        </div><!-- /tab-withdraw -->
      </div><!-- /form-card -->

      <!-- Info box -->
      <div style="background:rgba(108,99,255,.06);border:1px solid rgba(108,99,255,.15);border-radius:14px;padding:1rem 1.25rem;margin-top:1rem;font-size:.8rem;color:var(--text-secondary);line-height:1.8;">
        <div style="font-weight:700;color:var(--text-primary);margin-bottom:.5rem;"><i class="bi bi-info-circle me-2" style="color:#6c63ff;"></i>Ketentuan Dompet Insentif</div>
        <ul style="margin:0;padding-left:1.2rem;">
          <li>Transfer antar username: <strong class="text-success">GRATIS 100%</strong></li>
          <li>Biaya pencairan ke bank/e-wallet: <strong class="text-success">GRATIS</strong></li>
          <li>Pengajuan <strong>&lt; 12:00 WIB</strong> → transfer <strong>hari yang sama</strong></li>
          <li>Pengajuan <strong>&gt; 12:00 WIB</strong> → transfer <strong>hari berikutnya</strong></li>
          <li>Minimum transfer/pencairan: <strong><?= formatRupiah($MIN_INC) ?></strong></li>
          <li>Maksimum 3 permintaan pencairan pending sekaligus</li>
        </ul>
      </div>
    </div><!-- /col-form -->

    <!-- ── History ── -->
    <div class="col-lg-7">
      <div class="history-card">
        <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--border-glass);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;">
          <h5 style="font-weight:800;margin:0;font-size:1rem;"><i class="bi bi-clock-history me-2" style="color:#6c63ff;"></i>Riwayat</h5>
          <div class="hist-tabs">
            <span class="hist-tab active" onclick="switchHist('transfers',this)">Transfer</span>
            <span class="hist-tab" onclick="switchHist('withdrawals',this)">Pencairan</span>
          </div>
        </div>

        <!-- Transfer history -->
        <div id="hist-transfers">
          <?php if (empty($transfers)): ?>
          <div style="padding:2.5rem;text-align:center;color:var(--text-muted);font-size:.875rem;">
            <i class="bi bi-arrow-left-right" style="font-size:2rem;display:block;margin-bottom:.75rem;"></i>
            Belum ada riwayat transfer
          </div>
          <?php else: ?>
          <div class="table-responsive">
            <table class="htable w-100">
              <thead>
                <tr>
                  <th>Ref</th>
                  <th>Dari / Ke</th>
                  <th>Nominal</th>
                  <th>Arah</th>
                  <th>Waktu</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($transfers as $t):
                  $isSender = ((int)$t['from_user_id'] === $userId);
                ?>
                <tr>
                  <td>
                    <div style="font-weight:700;font-size:.78rem;color:var(--text-primary);"><?= htmlspecialchars($t['ref_no']) ?></div>
                  </td>
                  <td>
                    <?php if ($isSender): ?>
                      <div style="font-size:.8rem;font-weight:600;color:var(--text-primary);"><?= htmlspecialchars($t['to_name']) ?></div>
                      <div style="font-size:.68rem;color:var(--text-muted);"><?= htmlspecialchars($t['to_code'] ?? '') ?></div>
                    <?php else: ?>
                      <div style="font-size:.8rem;font-weight:600;color:var(--text-primary);"><?= htmlspecialchars($t['from_name']) ?></div>
                      <div style="font-size:.68rem;color:var(--text-muted);"><?= htmlspecialchars($t['from_code'] ?? '') ?></div>
                    <?php endif; ?>
                  </td>
                  <td style="font-weight:700;color:<?= $isSender ? '#f72585' : '#10b981' ?>;"><?= $isSender ? '-' : '+' ?><?= formatRupiah((float)$t['amount']) ?></td>
                  <td>
                    <?php if ($isSender): ?>
                    <span class="status-pill pill-out"><i class="bi bi-arrow-up-right"></i>Keluar</span>
                    <?php else: ?>
                    <span class="status-pill pill-in"><i class="bi bi-arrow-down-left"></i>Masuk</span>
                    <?php endif; ?>
                    <div class="free-badge mt-1">GRATIS</div>
                  </td>
                  <td style="font-size:.75rem;white-space:nowrap;">
                    <?= date('d M Y', strtotime($t['created_at'])) ?><br>
                    <span style="color:var(--text-muted);"><?= date('H:i', strtotime($t['created_at'])) ?></span>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div><!-- /hist-transfers -->

        <!-- Withdrawal history -->
        <div id="hist-withdrawals" style="display:none;">
          <?php if (empty($withdrawals)): ?>
          <div style="padding:2.5rem;text-align:center;color:var(--text-muted);font-size:.875rem;">
            <i class="bi bi-bank" style="font-size:2rem;display:block;margin-bottom:.75rem;"></i>
            Belum ada riwayat pencairan
          </div>
          <?php else: ?>
          <div class="table-responsive">
            <table class="htable w-100">
              <thead>
                <tr>
                  <th>No. Pencairan</th>
                  <th>Nominal</th>
                  <th>Tujuan</th>
                  <th>Jadwal</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($withdrawals as $w):
                  $sc = $statusColors[$w['status']] ?? ['bg'=>'rgba(148,163,184,.1)','color'=>'#94a3b8','label'=>$w['status']];
                ?>
                <tr>
                  <td>
                    <div style="font-weight:700;font-size:.78rem;color:var(--text-primary);"><?= htmlspecialchars($w['wdr_no']) ?></div>
                    <div style="font-size:.68rem;color:var(--text-muted);"><?= date('d M Y H:i', strtotime($w['created_at'])) ?></div>
                  </td>
                  <td>
                    <div style="font-weight:700;color:#f72585;"><?= formatRupiah((float)$w['amount']) ?></div>
                    <div class="free-badge mt-1">0 Biaya</div>
                  </td>
                  <td>
                    <div style="font-size:.8rem;font-weight:600;color:var(--text-primary);"><?= htmlspecialchars($w['bank_name']) ?></div>
                    <div style="font-size:.68rem;color:var(--text-muted);"><?= htmlspecialchars($w['bank_account_no']) ?></div>
                    <div style="font-size:.68rem;color:var(--text-muted);"><?= htmlspecialchars($w['bank_account_name']) ?></div>
                  </td>
                  <td>
                    <?php $schedClass = ($w['transfer_info'] === 'Hari ini') ? 'schedule-today' : 'schedule-tomorrow'; ?>
                    <span class="schedule-badge <?= $schedClass ?>" style="font-size:.68rem;">
                      <i class="bi bi-calendar-check"></i>
                      <?= htmlspecialchars($w['transfer_info'] ?? $w['scheduled_date']) ?>
                    </span>
                    <div style="font-size:.68rem;color:var(--text-muted);margin-top:3px;"><?= htmlspecialchars($w['scheduled_date']) ?></div>
                  </td>
                  <td>
                    <span class="status-pill" style="background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;">
                      <?= $sc['label'] ?>
                    </span>
                    <?php if ($w['status'] === 'rejected' && $w['admin_note']): ?>
                    <div style="font-size:.67rem;color:#ef4444;margin-top:2px;"><?= htmlspecialchars(mb_substr($w['admin_note'],0,30)) ?>…</div>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div><!-- /hist-withdrawals -->
      </div><!-- /history-card -->
    </div><!-- /col-history -->
  </div><!-- /row -->
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/main.js"></script>
<script>
// ── Tab switching ─────────────────────────────────────────────
function switchTab(tab) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
  document.getElementById('tab-' + tab).classList.add('active');
  event.currentTarget.classList.add('active');
}

// ── History tab ───────────────────────────────────────────────
function switchHist(id, el) {
  ['transfers','withdrawals'].forEach(k => {
    document.getElementById('hist-' + k).style.display = k === id ? '' : 'none';
  });
  document.querySelectorAll('.hist-tab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
}

// ── Transfer amount formatting ────────────────────────────────
const transferAmountInput = document.getElementById('transferAmountInput');
if (transferAmountInput) {
  transferAmountInput.addEventListener('input', function() {
    let raw = this.value.replace(/\D/g, '');
    this.value = raw ? parseInt(raw).toLocaleString('id-ID') : '';
    updateTransferPreview();
  });
}
function setTransferAmount(val) {
  if (transferAmountInput) {
    transferAmountInput.value = val.toLocaleString('id-ID');
    updateTransferPreview();
  }
}
function updateTransferPreview() {
  const amount = parseFloat((transferAmountInput?.value||'0').replace(/\./g,'').replace(',','.')) || 0;
  document.getElementById('tp-amount').textContent = 'Rp ' + amount.toLocaleString('id-ID');
  document.getElementById('tp-net').textContent    = 'Rp ' + amount.toLocaleString('id-ID');
}
updateTransferPreview();

// ── Withdrawal amount formatting ──────────────────────────────
const wdrAmountInput = document.getElementById('wdrAmountInput');
if (wdrAmountInput) {
  wdrAmountInput.addEventListener('input', function() {
    let raw = this.value.replace(/\D/g, '');
    this.value = raw ? parseInt(raw).toLocaleString('id-ID') : '';
    updateWdrPreview();
  });
}
function setWdrAmount(val) {
  if (wdrAmountInput) {
    wdrAmountInput.value = val.toLocaleString('id-ID');
    updateWdrPreview();
  }
}
function updateWdrPreview() {
  const amount = parseFloat((wdrAmountInput?.value||'0').replace(/\./g,'').replace(',','.')) || 0;
  if (document.getElementById('wp-amount')) document.getElementById('wp-amount').textContent = 'Rp ' + amount.toLocaleString('id-ID');
  if (document.getElementById('wp-net'))    document.getElementById('wp-net').textContent    = 'Rp ' + amount.toLocaleString('id-ID');
}
updateWdrPreview();

// ── Bank selection ────────────────────────────────────────────
function selectBank(key) {
  document.querySelectorAll('#bankGrid .bank-btn').forEach(b => b.classList.remove('selected'));
  event.currentTarget.classList.add('selected');
  document.getElementById('bankInput').value = key;
}

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
