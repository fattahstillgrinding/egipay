<?php
require_once __DIR__ . '/includes/config.php';
requireLogin();

$user   = getCurrentUser();
$userId = (int)$_SESSION['user_id'];
$wallet = getUserWallet($userId);
$flash  = getFlash();
$errors = [];
$success = null;

// Load payment methods from DB
$paymentMethods = dbFetchAll('SELECT * FROM payment_methods WHERE is_active = 1 ORDER BY sort_order');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $amount    = (float)($_POST['amount'] ?? 0);
    $methodId  = (int)($_POST['method_id'] ?? 0);
    $recipient = trim($_POST['recipient'] ?? '');
    $note      = trim($_POST['note'] ?? '');

    // Validate
    if ($amount < 1000) {
        $errors['amount'] = 'Jumlah minimal Rp 1.000.';
    }
    if (!$methodId) {
        $errors['method'] = 'Pilih metode pembayaran.';
    }
    if (!$recipient) {
        $errors['recipient'] = 'Penerima wajib diisi.';
    }

    // Find method
    $method = null;
    foreach ($paymentMethods as $pm) {
        if ((int)$pm['id'] === $methodId) { $method = $pm; break; }
    }
    if (!$method) $errors['method'] = 'Metode pembayaran tidak valid.';

    if (empty($errors)) {
        // Calculate fee
        $fee   = round(($amount * $method['fee_percent'] / 100) + $method['fee_flat']);
        $total = $amount + $fee;

        // Check balance
        if ($wallet['balance'] < $total) {
            $errors['amount'] = 'Saldo tidak mencukupi. Saldo Anda: ' . formatRupiah($wallet['balance']);
        }
    }

    if (empty($errors)) {
        $fee   = round(($amount * $method['fee_percent'] / 100) + $method['fee_flat']);
        $total = $amount + $fee;

        try {
            $pdo = getDB();
            $pdo->beginTransaction();

            $txId = generateTxId();

            // Create transaction
            dbExecute(
                'INSERT INTO transactions (tx_id, user_id, payment_method_id, type, amount, fee, total, recipient, note, status, paid_at)
                 VALUES (?, ?, ?, "payment", ?, ?, ?, ?, ?, "success", NOW())',
                [$txId, $userId, $method['id'], $amount, $fee, $total, $recipient, $note ?: null]
            );
            $txDbId = dbLastId();

            // Deduct balance
            dbExecute(
                'UPDATE wallets SET balance = balance - ?, total_out = total_out + ?, updated_at = NOW() WHERE user_id = ?',
                [$total, $total, $userId]
            );

            // Notification
            dbExecute(
                'INSERT INTO notifications (user_id, type, title, message) VALUES (?, "success", "Pembayaran Berhasil", ?)',
                [$userId, "Transaksi {$txId} sebesar " . formatRupiah($amount) . " berhasil diproses."]
            );

            $pdo->commit();

            auditLog($userId, 'payment', "Pembayaran {$txId} — {$recipient}");
            $success = [
                'tx_id'     => $txId,
                'amount'    => $amount,
                'fee'       => $fee,
                'total'     => $total,
                'method'    => $method['name'],
                'recipient' => $recipient,
            ];
            // Refresh wallet
            $wallet = getUserWallet($userId);

        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors['general'] = 'Gagal memproses pembayaran. Silakan coba lagi.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Kirim Pembayaran – SolusiMu</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link href="css/style.css" rel="stylesheet"/>
</head>
<body>
<div class="page-loader" id="pageLoader">
  <div class="loader-logo">
    <svg width="60" height="60" viewBox="0 0 60 60" fill="none">
      <defs><linearGradient id="lg1" x1="0" y1="0" x2="60" y2="60"><stop stop-color="#6c63ff"/><stop offset="1" stop-color="#00d4ff"/></linearGradient></defs>
      <rect width="60" height="60" rx="16" fill="url(#lg1)"/>
      <path d="M18 20h14a8 8 0 010 16H18V20zm0 8h12a4 4 0 000-8" fill="white" opacity="0.9"/>
      <circle cx="42" cy="40" r="4" fill="white" opacity="0.7"/>
    </svg>
  </div>
  <div class="loader-bar"><div class="loader-bar-fill"></div></div>
</div>
<div class="toast-container" id="toastContainer"></div>

<!-- Mobile sidebar overlay -->
<div id="sidebarOverlay"
  style="position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:999;display:none;"
  class="d-lg-none"></div>

<!-- == SIDEBAR ======================================================== -->
<aside class="sidebar" id="mainSidebar">
  <div class="sidebar-logo">
    <svg width="36" height="36" viewBox="0 0 42 42" fill="none">
      <defs><linearGradient id="sLg2" x1="0" y1="0" x2="42" y2="42"><stop stop-color="#6c63ff"/><stop offset="1" stop-color="#00d4ff"/></linearGradient></defs>
      <rect width="42" height="42" rx="12" fill="url(#sLg2)"/>
      <path d="M12 14h10a6 6 0 010 12H12V14zm0 6h8a2 2 0 000-6" fill="white" opacity="0.95"/>
      <circle cx="30" cy="28" r="3" fill="white" opacity="0.8"/>
    </svg>
    <span class="brand-text" style="font-size:1.2rem;">SolusiMu</span>
  </div>
  <ul class="sidebar-menu">
    <li class="sidebar-section-title">Utama</li>
    <li><a href="dashboard.php" class="sidebar-link"><span class="icon"><i class="bi bi-grid-1x2-fill"></i></span>Dashboard</a></li>
    <li><a href="payment.php"  class="sidebar-link active"><span class="icon"><i class="bi bi-send-fill"></i></span>Kirim Pembayaran</a></li>
    <li><a href="#" class="sidebar-link"><span class="icon"><i class="bi bi-arrow-left-right"></i></span>Transaksi</a></li>
    <li><a href="#" class="sidebar-link"><span class="icon"><i class="bi bi-wallet2"></i></span>Dompet</a></li>
    <li class="sidebar-section-title">Akun</li>
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

<!-- ====== MAIN ====================================================== -->
<main class="main-content">
  <div class="d-flex align-items-center gap-3 mb-4">
    <button class="btn d-lg-none" id="sidebarToggle"
      style="background:var(--bg-card);border:1px solid var(--border-glass);border-radius:10px;color:var(--text-primary);padding:8px 12px;">
      <i class="bi bi-list fs-5"></i>
    </button>
    <div>
      <h1 class="dash-title">Kirim Pembayaran</h1>
      <p class="dash-subtitle">Saldo: <strong style="color:var(--primary-light);"><?= formatRupiah($wallet['balance'] ?? 0) ?></strong></p>
    </div>
  </div>

  <?php if (!empty($errors['general'])): ?>
  <div style="background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.3);border-radius:12px;padding:0.75rem 1rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:8px;font-size:0.875rem;color:#ef4444;">
    <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($errors['general']) ?>
  </div>
  <?php endif; ?>

  <?php if ($success): ?>
  <!-- SUCCESS VIEW -->
  <div class="glass-table-wrapper text-center" style="max-width:520px;margin:0 auto;padding:3rem 2rem;">
    <div style="width:80px;height:80px;border-radius:50%;background:rgba(16,185,129,0.15);display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;font-size:2.5rem;color:#10b981;">
      <i class="bi bi-check-circle-fill"></i>
    </div>
    <h2 style="font-size:1.5rem;font-weight:800;margin-bottom:0.5rem;">Pembayaran Berhasil!</h2>
    <p style="color:var(--text-muted);margin-bottom:2rem;">Transaksi Anda telah berhasil diproses.</p>

    <div style="background:var(--bg-card);border-radius:16px;padding:1.25rem;margin-bottom:2rem;text-align:left;">
      <?php
      $rows = [
        ['No. Transaksi', '<code style="color:var(--primary-light);">' . $success['tx_id'] . '</code>'],
        ['Penerima',      htmlspecialchars($success['recipient'])],
        ['Metode',        htmlspecialchars($success['method'])],
        ['Jumlah',        formatRupiah($success['amount'])],
        ['Biaya',         formatRupiah($success['fee'])],
        ['Total Dibayar', '<strong>' . formatRupiah($success['total']) . '</strong>'],
      ];
      foreach ($rows as [$k, $v]):
      ?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:0.6rem 0;border-bottom:1px solid var(--border-glass);">
        <div style="font-size:0.8rem;color:var(--text-muted);"><?= $k ?></div>
        <div style="font-size:0.875rem;"><?= $v ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="d-flex gap-2 justify-content-center">
      <a href="payment.php" class="btn btn-primary-gradient px-4 py-2">
        <i class="bi bi-plus-lg me-2"></i>Bayar Lagi
      </a>
      <a href="dashboard.php" class="btn btn-outline-glow px-4 py-2">
        <i class="bi bi-grid me-2"></i>Dashboard
      </a>
    </div>
  </div>

  <?php else: ?>
  <!-- PAYMENT FORM -->
  <div style="max-width:640px;">
    <form method="POST" action="payment.php" id="paymentForm">
      <?= csrfField() ?>

      <!-- Amount -->
      <div class="glass-table-wrapper mb-4" style="padding:1.5rem;">
        <h2 style="font-size:0.95rem;font-weight:700;margin-bottom:1rem;"><i class="bi bi-cash me-2" style="color:var(--primary-light);"></i>Jumlah Pembayaran</h2>
        <div style="position:relative;margin-bottom:1rem;">
          <span style="position:absolute;left:1rem;top:50%;transform:translateY(-50%);font-weight:700;color:var(--text-muted);font-size:0.9rem;">Rp</span>
          <input type="number" name="amount" id="amount" min="1000" step="1000"
            value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>"
            placeholder="Masukkan jumlah"
            class="form-control-modern <?= !empty($errors['amount']) ? 'border-danger' : '' ?>"
            style="padding-left:2.5rem;font-size:1.2rem;font-weight:700;font-family:'Space Grotesk';" required/>
        </div>
        <?php if (!empty($errors['amount'])): ?><div style="color:#ef4444;font-size:0.75rem;margin-bottom:0.5rem;"><?= htmlspecialchars($errors['amount']) ?></div><?php endif; ?>
        <!-- Quick amounts -->
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
          <?php foreach ([50000,100000,250000,500000,1000000,2000000] as $q): ?>
          <button type="button" class="quick-amount-btn" data-amount="<?= $q ?>">
            <?= 'Rp ' . number_format($q/1000,0).'K' ?>
          </button>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Payment method -->
      <div class="glass-table-wrapper mb-4" style="padding:1.5rem;">
        <h2 style="font-size:0.95rem;font-weight:700;margin-bottom:1rem;"><i class="bi bi-wallet2 me-2" style="color:var(--primary-light);"></i>Metode Pembayaran</h2>
        <?php if (!empty($errors['method'])): ?><div style="color:#ef4444;font-size:0.75rem;margin-bottom:0.75rem;"><?= htmlspecialchars($errors['method']) ?></div><?php endif; ?>
        <div class="row g-2" id="methodGrid">
          <?php
          $types = [
            'ewallet'     => 'E-Wallet',
            'bank_transfer' => 'Transfer Bank',
            'qris'        => 'QRIS',
            'credit_card' => 'Kartu',
            'minimarket'  => 'Minimarket',
            'paylater'    => 'Paylater',
          ];
          $grouped = [];
          foreach ($paymentMethods as $pm) {
            $grouped[$pm['type']][] = $pm;
          }
          foreach ($grouped as $type => $methods): ?>
          <div class="col-12"><div style="font-size:0.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin:0.5rem 0 0.25rem;"><?= $types[$type] ?? $type ?></div></div>
          <?php foreach ($methods as $pm): $sel = (int)($_POST['method_id'] ?? 0) === (int)$pm['id']; ?>
          <div class="col-6 col-md-4">
            <label class="payment-method-card <?= $sel ? 'selected' : '' ?>"
              data-fee-percent="<?= $pm['fee_percent'] ?>"
              data-fee-flat="<?= $pm['fee_flat'] ?>"
              data-method-name="<?= htmlspecialchars($pm['name']) ?>"
              style="cursor:pointer;display:block;">
              <input type="radio" name="method_id" value="<?= $pm['id'] ?>" style="display:none;" <?= $sel ? 'checked' : '' ?> required/>
              <div style="display:flex;align-items:center;gap:0.75rem;">
                <i class="<?= htmlspecialchars($pm['icon_class']) ?>" style="color:<?= htmlspecialchars($pm['color']) ?>;font-size:1.3rem;"></i>
                <div>
                  <div style="font-weight:600;font-size:0.82rem;"><?= htmlspecialchars($pm['name']) ?></div>
                  <div style="font-size:0.68rem;color:var(--text-muted);">
                    <?= $pm['fee_percent'] > 0 ? $pm['fee_percent'].'%' : '' ?>
                    <?= $pm['fee_flat'] > 0 ? (($pm['fee_percent']>0?'+':'').'Rp'.number_format($pm['fee_flat'],0)) : '' ?>
                    <?= ($pm['fee_percent'] == 0 && $pm['fee_flat'] == 0) ? 'Gratis' : '' ?>
                  </div>
                </div>
              </div>
            </label>
          </div>
          <?php endforeach; endforeach; ?>
        </div>
      </div>

      <!-- Recipient & Note -->
      <div class="glass-table-wrapper mb-4" style="padding:1.5rem;">
        <h2 style="font-size:0.95rem;font-weight:700;margin-bottom:1rem;"><i class="bi bi-person-fill me-2" style="color:var(--primary-light);"></i>Data Penerima</h2>
        <div class="mb-3">
          <label class="form-label-modern">Penerima / No. Tujuan</label>
          <div class="input-icon-wrapper">
            <i class="bi bi-person input-icon"></i>
            <input type="text" name="recipient" id="recipient"
              class="form-control-modern input-with-icon <?= !empty($errors['recipient']) ? 'border-danger' : '' ?>"
              placeholder="Nama, nomor rekening, atau nomor HP"
              value="<?= htmlspecialchars($_POST['recipient'] ?? '') ?>" required/>
          </div>
          <?php if (!empty($errors['recipient'])): ?><div style="color:#ef4444;font-size:0.75rem;margin-top:4px;"><?= htmlspecialchars($errors['recipient']) ?></div><?php endif; ?>
        </div>
        <div>
          <label class="form-label-modern">Catatan <span style="color:var(--text-muted);font-weight:400;">(opsional)</span></label>
          <textarea name="note" rows="2"
            class="form-control-modern"
            placeholder="Tambahkan catatan untuk penerima..."
            style="resize:none;"><?= htmlspecialchars($_POST['note'] ?? '') ?></textarea>
        </div>
      </div>

      <!-- Fee Summary -->
      <div class="glass-table-wrapper mb-4" style="padding:1.5rem;" id="feeSummary">
        <h2 style="font-size:0.95rem;font-weight:700;margin-bottom:1rem;"><i class="bi bi-receipt me-2" style="color:var(--primary-light);"></i>Ringkasan</h2>
        <div style="display:flex;flex-direction:column;gap:0.5rem;">
          <div style="display:flex;justify-content:space-between;font-size:0.85rem;">
            <span style="color:var(--text-muted);">Jumlah Transfer</span>
            <span id="summaryAmount">Rp 0</span>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:0.85rem;">
            <span style="color:var(--text-muted);">Biaya (<span id="summaryMethodName">—</span>)</span>
            <span id="summaryFee" style="color:var(--warning);">Rp 0</span>
          </div>
          <div style="border-top:1px solid var(--border-glass);margin:0.5rem 0;"></div>
          <div style="display:flex;justify-content:space-between;font-size:1rem;font-weight:800;font-family:'Space Grotesk';">
            <span>Total Dibayar</span>
            <span id="summaryTotal" style="background:var(--gradient-1);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">Rp 0</span>
          </div>
          <div style="font-size:0.75rem;color:var(--text-muted);display:flex;align-items:center;gap:4px;margin-top:0.25rem;">
            <i class="bi bi-wallet2"></i> Saldo setelah: <strong id="summaryAfter" style="color:var(--text-secondary);"><?= formatRupiah($wallet['balance'] ?? 0) ?></strong>
          </div>
        </div>
      </div>

      <button type="submit" class="btn btn-primary-gradient w-100 py-3 fs-6">
        <i class="bi bi-shield-check me-2"></i>Bayar Sekarang
      </button>
      <div style="text-align:center;font-size:0.72rem;color:var(--text-muted);margin-top:0.75rem;">
        <i class="bi bi-lock me-1"></i>Transaksi diproses aman dengan enkripsi SSL 256-bit
      </div>
    </form>
  </div>
  <?php endif; ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/main.js"></script>
<script>
const walletBalance = <?= (float)($wallet['balance'] ?? 0) ?>;
const amountInput   = document.getElementById('amount');

function fmt(n) {
  return 'Rp ' + Math.round(n).toLocaleString('id-ID');
}

function updateSummary() {
  const amount     = parseFloat(amountInput?.value || 0);
  const selected   = document.querySelector('.payment-method-card.selected');
  const feeP       = selected ? parseFloat(selected.dataset.feePercent || 0) : 0;
  const feeF       = selected ? parseFloat(selected.dataset.feeFlat    || 0) : 0;
  const methodName = selected ? (selected.dataset.methodName || '—') : '—';
  const fee        = Math.round(amount * feeP / 100 + feeF);
  const total      = amount + fee;
  const after      = walletBalance - total;

  document.getElementById('summaryAmount')?.textContent && (document.getElementById('summaryAmount').textContent = fmt(amount));
  document.getElementById('summaryFee')?.textContent    && (document.getElementById('summaryFee').textContent    = fmt(fee));
  document.getElementById('summaryTotal')?.textContent  && (document.getElementById('summaryTotal').textContent  = fmt(total));
  document.getElementById('summaryMethodName')?.textContent && (document.getElementById('summaryMethodName').textContent = methodName);
  const afterEl = document.getElementById('summaryAfter');
  if (afterEl) {
    afterEl.textContent = fmt(Math.max(0, after));
    afterEl.style.color = after < 0 ? '#ef4444' : 'var(--text-secondary)';
  }
}

// Method card selection
document.querySelectorAll('.payment-method-card').forEach(card => {
  card.addEventListener('click', () => {
    document.querySelectorAll('.payment-method-card').forEach(c => c.classList.remove('selected'));
    card.classList.add('selected');
    card.querySelector('input[type=radio]').checked = true;
    updateSummary();
  });
});

// Quick amount buttons
document.querySelectorAll('.quick-amount-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    if (amountInput) { amountInput.value = btn.dataset.amount; updateSummary(); }
  });
});

amountInput?.addEventListener('input', updateSummary);
updateSummary();

// Sidebar mobile
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
