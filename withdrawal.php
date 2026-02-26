<?php
require_once __DIR__ . '/includes/config.php';
requireLogin();

$user   = getCurrentUser();
$userId = (int)$_SESSION['user_id'];
$wallet = getUserWallet($userId);
$flash  = getFlash();
$errors = [];

// ── Withdrawal fee calculator ─────────────────────────────────
$banks = [
    'BCA'       => ['label' => 'Bank BCA',       'type' => 'bank',    'fee' => 6500],
    'BNI'       => ['label' => 'Bank BNI',       'type' => 'bank',    'fee' => 6500],
    'BRI'       => ['label' => 'Bank BRI',       'type' => 'bank',    'fee' => 6500],
    'Mandiri'   => ['label' => 'Bank Mandiri',   'type' => 'bank',    'fee' => 6500],
    'CIMB'      => ['label' => 'Bank CIMB Niaga','type' => 'bank',    'fee' => 6500],
    'BSI'       => ['label' => 'Bank BSI',       'type' => 'bank',    'fee' => 6500],
    'GoPay'     => ['label' => 'GoPay',          'type' => 'ewallet', 'fee_pct' => 1.5],
    'OVO'       => ['label' => 'OVO',            'type' => 'ewallet', 'fee_pct' => 1.5],
    'DANA'      => ['label' => 'DANA',           'type' => 'ewallet', 'fee_pct' => 1.5],
    'ShopeePay' => ['label' => 'ShopeePay',      'type' => 'ewallet', 'fee_pct' => 1.5],
];
$MIN_WDR = 50000;
$MAX_WDR = 50000000;

function calcWdrFee(string $bankCode, float $amount, array $banks): float {
    if (!isset($banks[$bankCode])) return 6500;
    if ($banks[$bankCode]['type'] === 'ewallet') {
        return round($amount * ($banks[$bankCode]['fee_pct'] / 100));
    }
    return (float)$banks[$bankCode]['fee'];
}

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $rawAmount = str_replace(['.', ','], ['', '.'], trim($_POST['amount'] ?? '0'));
    $amount    = (float)$rawAmount;
    $bankCode  = trim($_POST['bank_name']         ?? '');
    $accNo     = trim($_POST['bank_account_no']   ?? '');
    $accName   = trim($_POST['bank_account_name'] ?? '');
    $note      = substr(trim($_POST['note'] ?? ''), 0, 255);

    $balance = (float)($wallet['balance'] ?? 0);
    $fee     = calcWdrFee($bankCode, $amount, $banks);
    $net     = $amount - $fee;

    // Validations
    if ($amount < $MIN_WDR)  $errors['amount']           = 'Minimum penarikan ' . formatRupiah($MIN_WDR) . '.';
    elseif ($amount > $MAX_WDR) $errors['amount']        = 'Maksimum penarikan ' . formatRupiah($MAX_WDR) . '.';
    elseif ($amount > $balance) $errors['amount']        = 'Saldo tidak mencukupi. Saldo tersedia: ' . formatRupiah($balance) . '.';
    elseif ($net <= 0)          $errors['amount']        = 'Nominal terlalu kecil setelah biaya admin.';
    if (!isset($banks[$bankCode]))  $errors['bank_name'] = 'Pilih tujuan transfer yang valid.';
    if (!$accNo)  $errors['bank_account_no']             = 'Nomor rekening/akun wajib diisi.';
    if (!$accName) $errors['bank_account_name']          = 'Nama pemilik rekening wajib diisi.';

    // Max 3 pending at a time
    if (empty($errors)) {
        $pendingCnt = (int)(dbFetchOne(
            'SELECT COUNT(*) AS c FROM withdrawals WHERE user_id=? AND status="pending"', [$userId]
        )['c'] ?? 0);
        if ($pendingCnt >= 3) {
            $errors['general'] = 'Anda sudah memiliki 3 permintaan pending. Tunggu hingga diproses.';
        }
    }

    if (empty($errors)) {
        do {
            $wdrNo  = 'WDR-' . strtoupper(bin2hex(random_bytes(4)));
            $exists = dbFetchOne('SELECT id FROM withdrawals WHERE wdr_no=?', [$wdrNo]);
        } while ($exists);

        try {
            $pdo = getDB();
            $pdo->beginTransaction();

            // Lock balance (deduct available, add to locked)
            dbExecute(
                'UPDATE wallets SET balance = balance - ?, locked = locked + ? WHERE user_id = ?',
                [$amount, $amount, $userId]
            );

            dbExecute(
                'INSERT INTO withdrawals (wdr_no, user_id, amount, fee, net_amount, bank_name, bank_account_no, bank_account_name, note)
                 VALUES (?,?,?,?,?,?,?,?,?)',
                [$wdrNo, $userId, $amount, $fee, $net, $bankCode, $accNo, $accName, $note ?: null]
            );

            dbExecute(
                'INSERT INTO notifications (user_id, type, title, message)
                 VALUES (?, "info", "Permintaan Penarikan Diterima", ?)',
                [$userId, "Penarikan {$wdrNo} sebesar " . formatRupiah($amount) . " sedang diproses admin."]
            );

            auditLog($userId, 'withdrawal_request', "{$wdrNo} | {$bankCode} {$accNo} | " . formatRupiah($amount));
            $pdo->commit();

            setFlash('success', 'Permintaan Terkirim!', "Penarikan {$wdrNo} sebesar " . formatRupiah($amount) . " sedang menunggu konfirmasi admin.");
            redirect(BASE_URL . '/withdrawal.php');
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors['general'] = 'Gagal memproses permintaan. Silakan coba lagi.';
        }
    }
}

// ── Refresh wallet ────────────────────────────────────────────
$wallet  = getUserWallet($userId);
$balance = (float)($wallet['balance'] ?? 0);
$locked  = (float)($wallet['locked']  ?? 0);

// ── Withdrawal history ────────────────────────────────────────
$sfilt   = $_GET['status'] ?? '';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 8;
$offset  = ($page - 1) * $perPage;

$whrParts = ['user_id = ?'];
$whrParams = [$userId];
if ($sfilt) { $whrParts[] = 'status = ?'; $whrParams[] = $sfilt; }
$whrStr = implode(' AND ', $whrParts);

$totalRows  = (int)(dbFetchOne("SELECT COUNT(*) AS c FROM withdrawals WHERE {$whrStr}", $whrParams)['c'] ?? 0);
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$history    = dbFetchAll("SELECT * FROM withdrawals WHERE {$whrStr} ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}", $whrParams);

// Totals
$wdrStats = dbFetchOne(
    'SELECT
       COALESCE(SUM(CASE WHEN status="approved" THEN net_amount END), 0) AS total_approved,
       COUNT(CASE WHEN status="pending"  THEN 1 END)                     AS cnt_pending,
       COUNT(CASE WHEN status="approved" THEN 1 END)                     AS cnt_approved,
       COUNT(CASE WHEN status="rejected" THEN 1 END)                     AS cnt_rejected
     FROM withdrawals WHERE user_id = ?',
    [$userId]
);

$notifCount = getUnreadNotifCount($userId);

$statusColors = [
    'pending'    => ['bg' => 'rgba(245,158,11,.12)',  'color' => '#f59e0b',  'label' => 'Menunggu'],
    'processing' => ['bg' => 'rgba(0,212,255,.12)',   'color' => '#00d4ff',  'label' => 'Diproses'],
    'approved'   => ['bg' => 'rgba(16,185,129,.12)',  'color' => '#10b981',  'label' => 'Disetujui'],
    'rejected'   => ['bg' => 'rgba(239,68,68,.12)',   'color' => '#ef4444',  'label' => 'Ditolak'],
];

// Preserve form values on error
$form = [
    'amount'            => $_POST['amount']            ?? '',
    'bank_name'         => $_POST['bank_name']         ?? '',
    'bank_account_no'   => $_POST['bank_account_no']   ?? '',
    'bank_account_name' => $_POST['bank_account_name'] ?? '',
    'note'              => $_POST['note']              ?? '',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Penarikan Dana – EgiPay</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link href="css/style.css" rel="stylesheet"/>
  <style>
    .wdr-stat-card{background:var(--bg-card);border:1px solid var(--border-glass);border-radius:16px;padding:1.25rem 1.5rem;}
    .wdr-stat-card .value{font-family:'Space Grotesk',sans-serif;font-size:1.5rem;font-weight:800;line-height:1.2;}
    .wdr-stat-card .label{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin-top:.25rem;}

    .form-card{background:var(--bg-card);border:1px solid var(--border-glass);border-radius:18px;padding:1.75rem;}
    .history-card{background:var(--bg-card);border:1px solid var(--border-glass);border-radius:18px;overflow:hidden;}

    .field-label{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin-bottom:.4rem;display:block;}
    .form-control-dark{background:rgba(255,255,255,.04);border:1.5px solid var(--border-glass);color:var(--text-primary);border-radius:10px;padding:.65rem 1rem;font-size:.88rem;width:100%;transition:border-color .2s,box-shadow .2s;}
    .form-control-dark:focus{outline:none;border-color:#6c63ff;box-shadow:0 0 0 3px rgba(108,99,255,.18);background:rgba(108,99,255,.04);}
    .form-control-dark.is-invalid{border-color:#ef4444;}
    .form-control-dark option{background:#1a1a2e;color:#f1f5f9;}

    .bank-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem;margin-bottom:.25rem;}
    .bank-btn{background:rgba(255,255,255,.04);border:1.5px solid var(--border-glass);border-radius:10px;padding:.5rem .4rem;text-align:center;cursor:pointer;transition:all .2s;font-size:.72rem;font-weight:600;color:var(--text-secondary);}
    .bank-btn:hover{border-color:#6c63ff;color:#6c63ff;}
    .bank-btn.selected{background:rgba(108,99,255,.12);border-color:#6c63ff;color:#6c63ff;}

    .fee-preview{background:rgba(108,99,255,.06);border:1px solid rgba(108,99,255,.2);border-radius:12px;padding:.85rem 1rem;font-size:.83rem;}
    .fee-preview .row-item{display:flex;justify-content:space-between;padding:3px 0;}
    .fee-preview .total-row{border-top:1px solid rgba(108,99,255,.2);margin-top:.5rem;padding-top:.5rem;font-weight:800;font-size:.95rem;}

    .wdr-table th{background:rgba(255,255,255,.03);color:var(--text-muted);font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;padding:.65rem 1rem;border-bottom:1px solid var(--border-glass);white-space:nowrap;}
    .wdr-table td{padding:.75rem 1rem;border-bottom:1px solid rgba(255,255,255,.04);font-size:.83rem;color:var(--text-secondary);vertical-align:middle;}
    .wdr-table tr:last-child td{border-bottom:none;}
    .wdr-table tr:hover td{background:rgba(255,255,255,.02);}

    .status-pill{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;}

    .filter-tabs{display:flex;gap:.35rem;flex-wrap:wrap;}
    .filter-tab{padding:.3rem .9rem;border-radius:20px;font-size:.75rem;font-weight:600;text-decoration:none;border:1.5px solid var(--border-glass);color:var(--text-muted);transition:all .2s;}
    .filter-tab:hover,.filter-tab.active{background:rgba(108,99,255,.12);border-color:#6c63ff;color:#6c63ff;}
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
        <li><a href="withdrawal.php"       class="sidebar-sublink active"><i class="bi bi-box-arrow-up me-2"></i>Penarikan Dana</a></li>
        <li><a href="incentive_wallet.php" class="sidebar-sublink"><i class="bi bi-gift me-2"></i>Dompet Insentif</a></li>
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
        <div class="profile-role"><?= ucfirst($user['plan']) ?> Plan</div>
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
        <h1 style="font-size:1.4rem;font-weight:800;margin:0;">Penarikan Dana</h1>
        <p style="font-size:.78rem;color:var(--text-muted);margin:0;">Cairkan saldo wallet ke rekening atau dompet digital Anda</p>
      </div>
    </div>
    <div class="d-flex align-items-center gap-2">
      <div style="background:var(--bg-card);border:1px solid var(--border-glass);border-radius:12px;padding:.45rem 1rem;font-size:.83rem;color:var(--text-secondary);">
        <i class="bi bi-wallet2 me-2" style="color:#6c63ff;"></i>
        Saldo: <strong style="color:var(--text-primary);"><?= formatRupiah($balance) ?></strong>
      </div>
    </div>
  </div>

  <!-- ── Stat Cards ── -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="wdr-stat-card">
        <div class="value" style="color:#6c63ff;"><?= formatRupiah($balance) ?></div>
        <div class="label">Saldo Tersedia</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="wdr-stat-card">
        <div class="value" style="color:#f59e0b;"><?= formatRupiah($locked) ?></div>
        <div class="label">Saldo Ditahan</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="wdr-stat-card">
        <div class="value" style="color:#10b981;"><?= formatRupiah((float)($wdrStats['total_approved'] ?? 0)) ?></div>
        <div class="label">Total Dicairkan</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="wdr-stat-card">
        <div class="value" style="color:#94a3b8;"><?= (int)($wdrStats['cnt_pending'] ?? 0) ?></div>
        <div class="label">Pending</div>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <!-- ── Withdrawal Form ── -->
    <div class="col-lg-5">
      <div class="form-card">
        <h5 style="font-weight:800;margin-bottom:1.25rem;display:flex;align-items:center;gap:.5rem;">
          <i class="bi bi-box-arrow-up" style="color:#6c63ff;"></i> Ajukan Penarikan
        </h5>

        <?php if (!empty($errors['general'])): ?>
        <div style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);border-radius:10px;padding:.75rem 1rem;margin-bottom:1rem;font-size:.83rem;color:#ef4444;display:flex;gap:8px;align-items:flex-start;">
          <i class="bi bi-exclamation-circle-fill mt-1"></i>
          <span><?= htmlspecialchars($errors['general']) ?></span>
        </div>
        <?php endif; ?>

        <?php if ($balance < $MIN_WDR): ?>
        <div style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.2);border-radius:12px;padding:1rem;text-align:center;color:#f59e0b;font-size:.85rem;">
          <i class="bi bi-exclamation-triangle-fill d-block" style="font-size:2rem;margin-bottom:.5rem;"></i>
          Saldo tidak mencukupi untuk melakukan penarikan.<br>
          <span style="font-size:.78rem;color:var(--text-muted);">Minimum penarikan: <?= formatRupiah($MIN_WDR) ?></span>
        </div>
        <?php else: ?>
        <form method="POST" action="withdrawal.php" id="wdrForm">
          <?= csrfField() ?>

          <!-- Nominal -->
          <div class="mb-3">
            <label class="field-label">Nominal Penarikan</label>
            <input type="text" name="amount" id="amountInput" inputmode="numeric"
              class="form-control-dark <?= isset($errors['amount']) ? 'is-invalid' : '' ?>"
              placeholder="Contoh: 500.000"
              value="<?= htmlspecialchars($form['amount']) ?>"
              autocomplete="off"/>
            <?php if (isset($errors['amount'])): ?>
            <div style="font-size:.75rem;color:#ef4444;margin-top:.35rem;"><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($errors['amount']) ?></div>
            <?php endif; ?>
            <div style="font-size:.72rem;color:var(--text-muted);margin-top:.35rem;">
              Min <?= formatRupiah($MIN_WDR) ?> · Tersedia <?= formatRupiah($balance) ?>
            </div>
            <!-- Quick amounts -->
            <div style="display:flex;gap:.35rem;flex-wrap:wrap;margin-top:.5rem;">
              <?php foreach ([100000, 250000, 500000, 1000000] as $qa): ?>
              <button type="button" onclick="setAmount(<?= $qa ?>)"
                style="background:rgba(108,99,255,.08);border:1px solid rgba(108,99,255,.2);color:#a78bfa;border-radius:8px;padding:3px 10px;font-size:.72rem;font-weight:600;cursor:pointer;">
                <?= formatRupiah($qa) ?>
              </button>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Tujuan transfer -->
          <div class="mb-3">
            <label class="field-label">Tujuan Transfer</label>
            <div class="bank-grid" id="bankGrid">
              <?php foreach ($banks as $key => $b): ?>
              <div class="bank-btn <?= $form['bank_name'] === $key ? 'selected' : '' ?>"
                   onclick="selectBank('<?= $key ?>')">
                <?= htmlspecialchars($b['label']) ?>
              </div>
              <?php endforeach; ?>
            </div>
            <input type="hidden" name="bank_name" id="bankInput" value="<?= htmlspecialchars($form['bank_name']) ?>"/>
            <?php if (isset($errors['bank_name'])): ?>
            <div style="font-size:.75rem;color:#ef4444;margin-top:.35rem;"><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($errors['bank_name']) ?></div>
            <?php endif; ?>
          </div>

          <!-- Nomor rekening -->
          <div class="mb-3">
            <label class="field-label">Nomor Rekening / Akun</label>
            <input type="text" name="bank_account_no" inputmode="numeric"
              class="form-control-dark <?= isset($errors['bank_account_no']) ? 'is-invalid' : '' ?>"
              placeholder="Nomor rekening atau nomor HP"
              value="<?= htmlspecialchars($form['bank_account_no']) ?>"/>
            <?php if (isset($errors['bank_account_no'])): ?>
            <div style="font-size:.75rem;color:#ef4444;margin-top:.35rem;"><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($errors['bank_account_no']) ?></div>
            <?php endif; ?>
          </div>

          <!-- Nama pemilik -->
          <div class="mb-3">
            <label class="field-label">Nama Pemilik Rekening</label>
            <input type="text" name="bank_account_name"
              class="form-control-dark <?= isset($errors['bank_account_name']) ? 'is-invalid' : '' ?>"
              placeholder="Sesuai buku tabungan / KTP"
              value="<?= htmlspecialchars($form['bank_account_name']) ?>"/>
            <?php if (isset($errors['bank_account_name'])): ?>
            <div style="font-size:.75rem;color:#ef4444;margin-top:.35rem;"><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($errors['bank_account_name']) ?></div>
            <?php endif; ?>
          </div>

          <!-- Catatan -->
          <div class="mb-3">
            <label class="field-label">Catatan (opsional)</label>
            <input type="text" name="note"
              class="form-control-dark"
              placeholder="Keterangan tambahan..."
              value="<?= htmlspecialchars($form['note']) ?>" maxlength="255"/>
          </div>

          <!-- Fee preview -->
          <div class="fee-preview mb-4" id="feePreview">
            <div class="row-item"><span style="color:var(--text-muted);">Nominal penarikan</span><span id="fp-amount" style="font-weight:600;">Rp 0</span></div>
            <div class="row-item"><span style="color:var(--text-muted);">Biaya admin</span><span id="fp-fee" style="color:#f59e0b;font-weight:600;">Rp 0</span></div>
            <div class="row-item total-row"><span>Dana diterima</span><span id="fp-net" style="color:#10b981;">Rp 0</span></div>
          </div>

          <button type="submit" id="btnSubmit"
            style="width:100%;background:linear-gradient(135deg,#6c63ff,#00d4ff);color:#fff;border:none;border-radius:12px;padding:.85rem;font-size:.95rem;font-weight:700;cursor:pointer;transition:opacity .2s;"
            onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'">
            <i class="bi bi-box-arrow-up me-2"></i>Ajukan Penarikan
          </button>
          <div style="font-size:.72rem;color:var(--text-muted);text-align:center;margin-top:.5rem;">
            Proses 1–2 hari kerja setelah dikonfirmasi admin
          </div>
        </form>
        <?php endif; ?>
      </div>

      <!-- Info box -->
      <div style="background:rgba(108,99,255,.06);border:1px solid rgba(108,99,255,.15);border-radius:14px;padding:1rem 1.25rem;margin-top:1rem;font-size:.8rem;color:var(--text-secondary);line-height:1.7;">
        <div style="font-weight:700;color:var(--text-primary);margin-bottom:.5rem;"><i class="bi bi-info-circle me-2" style="color:#6c63ff;"></i>Informasi Penarikan</div>
        <ul style="margin:0;padding-left:1.2rem;">
          <li>Minimum penarikan: <strong><?= formatRupiah($MIN_WDR) ?></strong></li>
          <li>Biaya transfer bank: <strong>Rp 6.500</strong></li>
          <li>Biaya e-wallet (GoPay/OVO/DANA/ShopeePay): <strong>1.5%</strong></li>
          <li>Diproses dalam <strong>1–2 hari kerja</strong></li>
          <li>Maksimum 3 permintaan pending sekaligus</li>
        </ul>
      </div>
    </div>

    <!-- ── History ── -->
    <div class="col-lg-7">
      <div class="history-card">
        <div style="padding:1.25rem 1.5rem;border-bottom:1px solid var(--border-glass);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem;">
          <h5 style="font-weight:800;margin:0;font-size:1rem;">Riwayat Penarikan</h5>
          <div class="filter-tabs">
            <a href="withdrawal.php" class="filter-tab <?= !$sfilt ? 'active' : '' ?>">Semua</a>
            <a href="withdrawal.php?status=pending"    class="filter-tab <?= $sfilt==='pending' ? 'active' : '' ?>">Menunggu</a>
            <a href="withdrawal.php?status=approved"   class="filter-tab <?= $sfilt==='approved' ? 'active' : '' ?>">Disetujui</a>
            <a href="withdrawal.php?status=rejected"   class="filter-tab <?= $sfilt==='rejected' ? 'active' : '' ?>">Ditolak</a>
          </div>
        </div>

        <?php if (empty($history)): ?>
        <div style="padding:3rem;text-align:center;">
          <i class="bi bi-inbox" style="font-size:2.5rem;color:var(--text-muted);margin-bottom:.75rem;display:block;"></i>
          <div style="color:var(--text-muted);font-size:.875rem;">Belum ada riwayat penarikan</div>
        </div>
        <?php else: ?>
        <div class="table-responsive">
          <table class="wdr-table w-100">
            <thead>
              <tr>
                <th>No. Penarikan</th>
                <th>Nominal</th>
                <th>Tujuan</th>
                <th>Status</th>
                <th>Tanggal</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($history as $w): ?>
              <?php $sc = $statusColors[$w['status']] ?? ['bg'=>'rgba(148,163,184,.1)','color'=>'#94a3b8','label'=>$w['status']]; ?>
              <tr>
                <td>
                  <div style="font-weight:700;color:var(--text-primary);font-size:.8rem;"><?= htmlspecialchars($w['wdr_no']) ?></div>
                  <div style="font-size:.7rem;color:var(--text-muted);">Net: <?= formatRupiah((float)$w['net_amount']) ?></div>
                </td>
                <td>
                  <div style="font-weight:700;color:var(--text-primary);"><?= formatRupiah((float)$w['amount']) ?></div>
                  <div style="font-size:.7rem;color:#f59e0b;">-<?= formatRupiah((float)$w['fee']) ?> biaya</div>
                </td>
                <td>
                  <div style="font-weight:600;color:var(--text-primary);font-size:.82rem;"><?= htmlspecialchars($w['bank_name']) ?></div>
                  <div style="font-size:.7rem;color:var(--text-muted);"><?= htmlspecialchars($w['bank_account_no']) ?></div>
                  <div style="font-size:.7rem;color:var(--text-muted);"><?= htmlspecialchars($w['bank_account_name']) ?></div>
                </td>
                <td>
                  <span class="status-pill" style="background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;">
                    <?= $sc['label'] ?>
                  </span>
                  <?php if ($w['status'] === 'rejected' && $w['admin_note']): ?>
                  <div style="font-size:.68rem;color:#ef4444;margin-top:3px;" title="<?= htmlspecialchars($w['admin_note']) ?>">
                    <i class="bi bi-chat-square-text me-1"></i><?= htmlspecialchars(mb_substr($w['admin_note'], 0, 30)) . (mb_strlen($w['admin_note']) > 30 ? '…' : '') ?>
                  </div>
                  <?php endif; ?>
                </td>
                <td style="white-space:nowrap;font-size:.75rem;">
                  <?= date('d M Y', strtotime($w['created_at'])) ?><br>
                  <span style="color:var(--text-muted);"><?= date('H:i', strtotime($w['created_at'])) ?></span>
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
    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/main.js"></script>
<script>
// ── Bank selection ─────────────────────────────────────────────
const bankFees = <?= json_encode(array_map(fn($b) => $b['type'] === 'ewallet' ? ['type'=>'ewallet','fee_pct'=>$b['fee_pct']] : ['type'=>'bank','fee'=>$b['fee']], $banks)) ?>;

function selectBank(key) {
  document.querySelectorAll('#bankGrid .bank-btn').forEach(b => b.classList.remove('selected'));
  event.currentTarget.classList.add('selected');
  document.getElementById('bankInput').value = key;
  updateFeePreview();
}

// ── Amount formatting ─────────────────────────────────────────
const amountInput = document.getElementById('amountInput');
if (amountInput) {
  amountInput.addEventListener('input', function() {
    let raw = this.value.replace(/\D/g, '');
    this.value = raw ? parseInt(raw).toLocaleString('id-ID') : '';
    updateFeePreview();
  });
}

function setAmount(val) {
  if (amountInput) {
    amountInput.value = val.toLocaleString('id-ID');
    updateFeePreview();
  }
}

function parseAmount() {
  if (!amountInput) return 0;
  return parseFloat(amountInput.value.replace(/\./g, '').replace(',', '.')) || 0;
}

function updateFeePreview() {
  const amount  = parseAmount();
  const bankKey = document.getElementById('bankInput').value;
  let fee = 0;
  if (bankKey && bankFees[bankKey]) {
    const bf = bankFees[bankKey];
    fee = bf.type === 'ewallet' ? Math.round(amount * bf.fee_pct / 100) : bf.fee;
  }
  const net = Math.max(0, amount - fee);

  document.getElementById('fp-amount').textContent = 'Rp ' + amount.toLocaleString('id-ID');
  document.getElementById('fp-fee').textContent    = 'Rp ' + fee.toLocaleString('id-ID');
  document.getElementById('fp-net').textContent    = 'Rp ' + net.toLocaleString('id-ID');
}

updateFeePreview();

// ── Sidebar toggle ────────────────────────────────────────────
document.getElementById('sidebarOverlay')?.addEventListener('click', function() {
  document.getElementById('mainSidebar').classList.remove('open');
  this.style.display = 'none';
});

function toggleSidebarSubmenu(el) {
  const li = el.closest('.sidebar-has-submenu');
  const sub = li.querySelector('.sidebar-submenu');
  const isOpen = el.classList.contains('open');
  // Close others
  document.querySelectorAll('.sidebar-link-toggle.open').forEach(m => {
    if (m !== el) {
      m.classList.remove('open');
      m.closest('.sidebar-has-submenu').querySelector('.sidebar-submenu').style.display = 'none';
    }
  });
  if (isOpen) { el.classList.remove('open'); sub.style.display = 'none'; }
  else { el.classList.add('open'); sub.style.display = 'block'; }
}
</script>
</body>
</html>
