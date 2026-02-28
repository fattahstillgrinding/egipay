<?php
require_once __DIR__ . '/includes/config.php';
// No requireLogin() – halaman ini diakses via token, sebelum akun dibuat

// ── Ambil token dari URL ───────────────────────────────────────
$token = trim($_GET['token'] ?? '');
if (!$token) {
    setFlash('error', 'Link Tidak Valid', 'Link registrasi tidak ditemukan.');
    redirect(BASE_URL . '/register.php');
}

$reg = dbFetchOne('SELECT * FROM registration_payments WHERE token = ?', [$token]);
if (!$reg) {
    setFlash('error', 'Invoice Tidak Ditemukan', 'Link pembayaran tidak valid atau sudah kadaluarsa.');
    redirect(BASE_URL . '/register.php');
}

// ── Handle POST: konfirmasi pembayaran ─────────────────────────
$payError = '';
if ($reg['status'] === 'pending' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action    = $_POST['action']         ?? '';
    $payMethod = trim($_POST['pay_method'] ?? 'GOPAY');

    if ($action === 'confirm_payment') {
        // Validasi ulang tidak kadaluarsa
        $fresh = dbFetchOne(
            'SELECT * FROM registration_payments
             WHERE token = ? AND status = "pending" AND expires_at > NOW() LIMIT 1',
            [$token]
        );

        if (!$fresh) {
            dbExecute('UPDATE registration_payments SET status="expired" WHERE token=?', [$token]);
        } else {
            // ── Buat akun member ──────────────────────────────
            $uName    = $fresh['name'];
            $uEmail   = $fresh['email'];
            $uPhone   = $fresh['phone'];
            $uPlan    = $fresh['plan'];
            $uHashed  = $fresh['password_hash'];

            $initials = strtoupper(substr($uName, 0, 1));
            $parts    = explode(' ', $uName);
            if (count($parts) > 1) $initials .= strtoupper(substr($parts[1], 0, 1));

            try {
                $pdo = getDB();
                $pdo->beginTransaction();

                dbExecute(
                    'INSERT INTO users (name, email, password, phone, role, plan, status, avatar, email_verified_at)
                     VALUES (?, ?, ?, ?, "merchant", ?, "active", ?, NOW())',
                    [$uName, $uEmail, $uHashed, $uPhone, $uPlan, $initials]
                );
                $userId     = (int)dbLastId();
                $memberCode = 'SMU-' . sprintf('%04d', $userId);
                $newRefCode = generateReferralCode($uName);
                dbExecute(
                    'UPDATE users SET member_code=?, referral_code=?, referred_by=? WHERE id=?',
                    [$memberCode, $newRefCode, $fresh['referred_by'] ?: null, $userId]
                );

                dbExecute('INSERT INTO wallets (user_id, balance) VALUES (?, 0.00)', [$userId]);

                dbExecute(
                    'INSERT INTO api_keys (user_id, name, key_type, client_key, server_key) VALUES (?, ?, ?, ?, ?)',
                    [$userId, 'Sandbox Key', 'sandbox', generateApiKey('sandbox'), generateApiKey('sandbox')]
                );

                dbExecute(
                    'INSERT INTO notifications (user_id, type, title, message) VALUES (?, "success", ?, ?)',
                    [
                        $userId,
                        'Selamat Datang di SolusiMu!',
                        'Pendaftaran berhasil! Biaya registrasi Rp 12.000 telah diterima. Akun Anda siap digunakan.',
                    ]
                );

                dbExecute(
                    'UPDATE registration_payments SET status="paid", paid_at=NOW(), payment_method=? WHERE token=?',
                    [$payMethod, $token]
                );

                // ── Catat referral jika ada ─────────────────────
                if (!empty($fresh['referred_by'])) {
                    dbExecute(
                        'INSERT IGNORE INTO referrals (referrer_id, referred_id, referral_code) VALUES (?, ?, ?)',
                        [$fresh['referred_by'], $userId, $fresh['referral_code']]
                    );
                    // Notif untuk referrer
                    $refCount = dbFetchOne('SELECT COUNT(*) AS c FROM referrals WHERE referrer_id = ?', [$fresh['referred_by']])['c'] ?? 0;
                    dbExecute(
                        'INSERT INTO notifications (user_id, type, title, message) VALUES (?, "success", ?, ?)',
                        [
                            $fresh['referred_by'],
                            'Referral Berhasil! 🎉',
                            htmlspecialchars($uName) . ' baru saja mendaftar menggunakan link referral Anda. Total referral Anda: ' . $refCount,
                        ]
                    );
                }

                // Hapus ref_code dari session
                unset($_SESSION['ref_code']);

                $pdo->commit();

                auditLog($userId, 'register_paid', 'Pembayaran registrasi: ' . $uEmail . ' via ' . $payMethod);

                // Auto-login
                session_regenerate_id(true);
                $_SESSION['user_id'] = $userId;

            } catch (PDOException $e) {
                $pdo->rollBack();
                $payError = 'Gagal memproses pembayaran. Silakan coba lagi.';
            }
        }
    }
}

// ── Refresh status terkini ─────────────────────────────────────
$reg = dbFetchOne('SELECT * FROM registration_payments WHERE token = ?', [$token]);

$isPaid    = ($reg['status'] === 'paid');
$isExpired = false;
$remainingSecs = 0;

if ($reg['status'] === 'pending') {
    $dbNow     = dbFetchOne('SELECT NOW() AS t')['t'];
    $expiresAt = strtotime($reg['expires_at']);
    $nowTs     = strtotime($dbNow);
    if ($nowTs >= $expiresAt) {
        dbExecute('UPDATE registration_payments SET status="expired" WHERE token=?', [$token]);
        $isExpired = true;
    } else {
        $remainingSecs = $expiresAt - $nowTs;
    }
} elseif ($reg['status'] === 'expired') {
    $isExpired = true;
}

// Payment method friendly names
$pmLabels = [
    'GOPAY'         => ['icon' => 'bi-phone',           'color' => '#00d4ff', 'label' => 'GoPay'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title><?= $isPaid ? 'Invoice Lunas' : ($isExpired ? 'Sesi Habis' : 'Selesaikan Pembayaran') ?> – SolusiMu</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link href="css/style.css" rel="stylesheet"/>
  <style>
    /* ─── Print ─── */
    @media print {
      .no-print { display:none !important; }
      body { background:#fff !important; }
      .pay-card { box-shadow:none !important; border:1px solid #ddd !important; }
    }

    /* ─── Layout ─── */
    body { background:linear-gradient(135deg,#0f0f1e 0%,#1a1a35 100%); min-height:100vh; }

    /* ─── Payment Card ─── */
    .pay-card {
      background:#fff; color:#1e293b; border-radius:20px;
      box-shadow:0 24px 72px rgba(0,0,0,0.25);
      max-width:700px; margin:0 auto; overflow:hidden;
    }
    .pay-header {
      background:linear-gradient(135deg,#6c63ff 0%,#00d4ff 100%);
      padding:2rem 2.5rem; color:#fff;
    }
    .pay-body { padding:2rem 2.5rem; }

    /* ─── Countdown ─── */
    .countdown-box {
      background:linear-gradient(135deg,rgba(247,37,133,0.08),rgba(108,99,255,0.08));
      border:2px solid rgba(247,37,133,0.35);
      border-radius:16px; padding:1rem 1.5rem;
      display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem;
    }
    .countdown-num {
      font-family:'Space Grotesk',sans-serif; font-size:2.4rem; font-weight:800;
      color:#f72585; line-height:1;
      text-shadow:0 0 20px rgba(247,37,133,0.3);
    }
    .countdown-num.warning { color:#ef4444; animation:pulse 1s infinite; }
    @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.6} }

    /* ─── Pay method tabs ─── */
    .pm-tab {
      background:#f8fafc; border:2px solid #e2e8f0; border-radius:12px;
      padding:.65rem 1rem; cursor:pointer; transition:all .2s;
      display:flex; align-items:center; gap:8px; font-size:.82rem; font-weight:600; color:#475569;
    }
    .pm-tab.active {
      background:linear-gradient(135deg,rgba(108,99,255,.12),rgba(0,212,255,.08));
      border-color:#6c63ff; color:#6c63ff;
    }
    .pm-tab:hover:not(.active) { border-color:#a78bfa; }

    /* ─── QR placeholder ─── */
    .qr-placeholder {
      width:160px; height:160px; margin:0 auto;
      background:repeating-conic-gradient(#1e293b 0% 25%,#fff 0% 50%) 0 0 / 12px 12px;
      border-radius:8px; border:4px solid #fff; box-shadow:0 4px 20px rgba(0,0,0,.1);
    }

    /* ─── Invoice table ─── */
    .inv-table th { background:#f8fafc; color:#64748b; font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; padding:.65rem 1rem; border:none; }
    .inv-table td { padding:.8rem 1rem; border-bottom:1px solid #f1f5f9; font-size:.875rem; color:#334155; }
    .inv-table tr:last-child td { border-bottom:none; }

    /* ─── Status pages ─── */
    .page-expired { background:#fff; border-radius:20px; max-width:500px; margin:0 auto; padding:3rem 2.5rem; text-align:center; }
    .page-paid-bar { background:linear-gradient(135deg,#10b981,#00d4ff); padding:2rem 2.5rem; color:#fff; }

    /* ─── Dark overlay backdrop ─── */
    .confirm-overlay {
      position:fixed;inset:0;background:rgba(15,15,30,.7);backdrop-filter:blur(8px);
      z-index:1050;display:flex;align-items:center;justify-content:center;
      padding:1rem;
    }
    .confirm-modal {
      background:#1a1a35;border:1px solid rgba(108,99,255,.35);
      border-radius:20px;padding:2rem;max-width:420px;width:100%;
    }
  </style>
</head>
<body style="padding:2rem 1rem;">
<div class="toast-container" id="toastContainer"></div>

<?php /* ═══════════════════════════════════════════════════════
       CASE 1 — EXPIRED
═══════════════════════════════════════════════════════ */ ?>
<?php if ($isExpired): ?>
<div style="max-width:500px;margin:3rem auto;">
  <div class="page-expired" style="box-shadow:0 24px 72px rgba(0,0,0,.25);">
    <div style="width:80px;height:80px;border-radius:50%;background:rgba(239,68,68,.12);border:2px solid rgba(239,68,68,.3);display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;">
      <i class="bi bi-clock-history" style="font-size:2.2rem;color:#ef4444;"></i>
    </div>
    <h2 style="font-size:1.4rem;font-weight:800;color:#1e293b;margin-bottom:.5rem;">Sesi Pendaftaran Habis</h2>
    <p style="color:#64748b;font-size:.9rem;margin-bottom:.5rem;">
      Waktu pembayaran untuk <strong><?= htmlspecialchars($reg['email']) ?></strong> telah melebihi <strong>15 menit</strong>.
    </p>
    <p style="color:#94a3b8;font-size:.8rem;margin-bottom:2rem;">
      No. Invoice: <code><?= htmlspecialchars($reg['inv_no']) ?></code>
    </p>
    <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:12px;padding:1rem;margin-bottom:2rem;font-size:.82rem;color:#b91c1c;">
      <i class="bi bi-info-circle me-2"></i>
      Data pendaftaran Anda telah dihapus. Silakan isi formulir kembali untuk membuat tagihan baru.
    </div>
    <a href="<?= BASE_URL ?>/register.php" class="btn btn-primary-gradient w-100 py-3" style="border-radius:12px;font-weight:700;">
      <i class="bi bi-arrow-repeat me-2"></i>Daftar Ulang
    </a>
    <a href="<?= BASE_URL ?>/login.php" style="display:block;margin-top:1rem;font-size:.82rem;color:#64748b;text-decoration:none;">
      Sudah punya akun? <span style="color:#6c63ff;font-weight:600;">Masuk di sini</span>
    </a>
  </div>
</div>

<?php /* ═══════════════════════════════════════════════════════
       CASE 2 — PAID (Invoice Lunas / Printable)
═══════════════════════════════════════════════════════ */ ?>
<?php elseif ($isPaid): ?>
<div class="no-print text-center mb-4">
  <div style="display:flex;align-items:center;justify-content:center;gap:.75rem;flex-wrap:wrap;">
    <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-sm px-4"
      style="background:linear-gradient(135deg,#6c63ff,#00d4ff);color:#fff;border:none;border-radius:10px;">
      <i class="bi bi-grid-1x2-fill me-2"></i>Ke Dashboard
    </a>
    <button onclick="window.print()" class="btn btn-outline-light btn-sm px-4">
      <i class="bi bi-printer me-2"></i>Cetak Invoice
    </button>
  </div>
</div>

<div class="pay-card">
  <!-- Paid Header -->
  <div class="page-paid-bar">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;">
      <div>
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:.5rem;">
          <div style="width:48px;height:48px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-check-lg" style="font-size:1.5rem;"></i>
          </div>
          <div>
            <div style="font-size:1.4rem;font-weight:800;">PEMBAYARAN LUNAS</div>
            <div style="font-size:.8rem;opacity:.85;">Member SolusiMu aktif</div>
          </div>
        </div>
        <div style="font-size:.78rem;opacity:.75;">
          SolusiMu · support@solusimu.com<br/>
          Digenerate: <?= date('d M Y H:i') ?> WIB
        </div>
      </div>
      <div style="text-align:right;">
        <div style="font-size:1.6rem;font-weight:800;">INVOICE</div>
        <div style="font-weight:700;opacity:.9;"><?= htmlspecialchars($reg['inv_no']) ?></div>
        <div style="font-size:.75rem;opacity:.75;margin-top:.25rem;">
          Lunas: <?= $reg['paid_at'] ? date('d M Y H:i', strtotime($reg['paid_at'])) : date('d M Y H:i') ?> WIB
        </div>
        <div style="margin-top:.5rem;">
          <span style="background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.4);border-radius:8px;padding:3px 12px;font-size:.72rem;font-weight:700;">LUNAS</span>
        </div>
      </div>
    </div>
  </div>

  <div class="pay-body">
    <!-- Billing Info -->
    <div class="row g-4 mb-4">
      <div class="col-sm-6">
        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;margin-bottom:.5rem;">Member Baru</div>
        <div style="font-weight:700;font-size:1.05rem;"><?= htmlspecialchars($reg['name']) ?></div>
        <div style="color:#64748b;font-size:.875rem;"><?= htmlspecialchars($reg['email']) ?></div>
        <div style="color:#64748b;font-size:.875rem;"><?= htmlspecialchars($reg['phone'] ?? '-') ?></div>
        <div style="margin-top:.5rem;display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
          <span style="background:rgba(108,99,255,.12);color:#6c63ff;border:1px solid rgba(108,99,255,.3);border-radius:8px;padding:3px 10px;font-size:.72rem;font-weight:700;">
            Membership Plan
          </span>
          <?php
            $paidUser = dbFetchOne('SELECT member_code FROM users WHERE email=?', [$reg['email']]);
            if ($paidUser && $paidUser['member_code']):
          ?>
          <span style="background:rgba(247,37,133,.1);color:#f72585;border:1px solid rgba(247,37,133,.25);border-radius:8px;padding:3px 10px;font-size:.72rem;font-weight:700;letter-spacing:.04em;">
            <?= htmlspecialchars($paidUser['member_code']) ?>
          </span>
          <?php endif; ?>
        </div>
      </div>
      <div class="col-sm-6 text-sm-end">
        <table style="width:100%;font-size:.875rem;color:#64748b;" class="ms-sm-auto">
          <tr><td style="padding:2px 0;">No. Invoice:</td><td style="font-weight:600;color:#1e293b;padding-left:1rem;"><?= htmlspecialchars($reg['inv_no']) ?></td></tr>
          <tr><td>Metode Bayar:</td><td style="font-weight:600;color:#1e293b;padding-left:1rem;"><?= htmlspecialchars($reg['payment_method'] ?? '-') ?></td></tr>
          <tr><td>Tanggal Daftar:</td><td style="font-weight:600;color:#1e293b;padding-left:1rem;"><?= date('d M Y', strtotime($reg['created_at'])) ?></td></tr>
          <tr><td>Status:</td><td style="padding-left:1rem;"><span style="color:#10b981;font-weight:700;">LUNAS</span></td></tr>
        </table>
      </div>
    </div>
    <hr style="border-color:#e2e8f0;margin:1.25rem 0;"/>

    <!-- Line Items -->
    <div class="table-responsive mb-4">
      <table class="inv-table w-100" style="border-collapse:collapse;">
        <thead><tr><th>#</th><th>Deskripsi</th><th>Keterangan</th><th class="text-end">Harga</th></tr></thead>
        <tbody>
          <tr>
            <td style="color:#94a3b8;font-weight:600;">01</td>
            <td>
              <div style="font-weight:700;">Biaya Registrasi Member</div>
              <div style="font-size:.75rem;color:#94a3b8;">Pendaftaran akun merchant SolusiMu</div>
            </td>
            <td><span style="background:rgba(108,99,255,.1);color:#6c63ff;border-radius:6px;padding:2px 8px;font-size:.72rem;font-weight:700;"><?= ucfirst($reg['plan']) ?></span></td>
            <td class="text-end" style="font-weight:700;"><?= formatRupiah($reg['amount']) ?></td>
          </tr>
          <tr>
            <td style="color:#94a3b8;font-weight:600;">02</td>
            <td>
              <div style="font-weight:700;">E-Book Digital</div>
              <div style="font-size:.75rem;color:#94a3b8;">E-Book Digital</div>
            </td>
            <td></td>
            <td class="text-end" style="color:#10b981;font-weight:700;">GRATIS</td>
          </tr>
          <tr>
            <td style="color:#94a3b8;font-weight:600;">03</td>
            <td>
              <div style="font-weight:700;">Dompet Digital (Wallet)</div>
              <div style="font-size:.75rem;color:#94a3b8;">Dompet untuk menerima pembayaran</div>
            </td>
            <td></td>
            <td class="text-end" style="color:#10b981;font-weight:700;">GRATIS</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Total -->
    <div class="row justify-content-end mb-4">
      <div class="col-sm-6 col-md-5">
        <table style="width:100%;font-size:.875rem;margin-bottom:.75rem;">
          <tr><td style="padding:4px 0;color:#64748b;">Subtotal:</td><td class="text-end" style="font-weight:600;"><?= formatRupiah($reg['amount']) ?></td></tr>
          <tr><td style="padding:4px 0;color:#64748b;">Pajak:</td><td class="text-end" style="color:#10b981;font-weight:600;">Rp 0</td></tr>
        </table>
        <div style="background:linear-gradient(135deg,#10b981,#00d4ff);border-radius:12px;padding:1rem 1.25rem;color:#fff;">
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <span style="font-weight:600;">TOTAL DIBAYAR</span>
            <span style="font-size:1.4rem;font-weight:800;"><?= formatRupiah($reg['amount']) ?></span>
          </div>
        </div>
      </div>
    </div>
    <hr style="border-color:#e2e8f0;margin:1.25rem 0;"/>

    <!-- Footer note -->
    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:1rem;font-size:.8rem;color:#166534;">
      <i class="bi bi-check-circle-fill me-2 text-success"></i>
      Invoice ini adalah bukti resmi aktivasi member SolusiMu. Simpan sebagai referensi.<br/>      <?php if (!empty($paidUser['member_code'])): ?>
      Kode Member Anda: <strong style="font-size:.9rem;letter-spacing:.04em;"><?= htmlspecialchars($paidUser['member_code']) ?></strong> &nbsp;&middot;&nbsp;
      <?php endif; ?>      Pertanyaan: <strong>support@solusimu.com</strong> · Kode invoice: <strong><?= htmlspecialchars($reg['inv_no']) ?></strong>
    </div>
  </div>
</div>

<div class="no-print text-center mt-4 pb-4">
  <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-primary-gradient px-5 py-2">
    <i class="bi bi-rocket-takeoff me-2"></i>Mulai Gunakan SolusiMu
  </a>
</div>

<?php /* ═══════════════════════════════════════════════════════
       CASE 3 — PENDING (Menunggu Pembayaran)
═══════════════════════════════════════════════════════ */ ?>
<?php else: ?>
<?php
// Payment method yang dipilih
$selectedPm = 'GOPAY';
// Virtual account numbers (dummy)
$vaNumbers = [
    'GOPAY' => '0877-8400-3055',
];
?>

<!-- Navbar mini -->
<div class="no-print" style="max-width:700px;margin:0 auto 1.25rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;">
  <a href="<?= BASE_URL ?>/register.php"
    style="color:rgba(255,255,255,.5);text-decoration:none;font-size:.78rem;display:flex;align-items:center;gap:6px;"
    onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,.5)'">
    <i class="bi bi-arrow-left-circle"></i> Kembali ke Formulir
  </a>
  <a href="<?= BASE_URL ?>/login.php" style="color:rgba(255,255,255,.5);text-decoration:none;font-size:.78rem;">
    Sudah member? <span style="color:#a78bfa;">Masuk</span>
  </a>
</div>

<div class="pay-card">
  <!-- ── Header ── -->
  <div class="pay-header">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;">
      <div>
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:.25rem;">
          <svg width="36" height="36" viewBox="0 0 42 42" fill="none">
            <rect width="42" height="42" rx="12" fill="rgba(255,255,255,.2)"/>
            <path d="M12 14h10a6 6 0 010 12H12V14zm0 6h8a2 2 0 000-6" fill="white" opacity=".95"/>
            <circle cx="30" cy="28" r="3" fill="white" opacity=".8"/>
          </svg>
          <div>
            <div style="font-size:1.3rem;font-weight:800;">SolusiMu</div>
            <div style="font-size:.72rem;opacity:.8;">Tagihan Registrasi Member</div>
          </div>
        </div>
      </div>
      <div style="text-align:right;">
        <div style="font-size:1rem;font-weight:800;opacity:.9;"><?= htmlspecialchars($reg['inv_no']) ?></div>
        <div style="font-size:.75rem;opacity:.75;">Dibuat: <?= date('d M Y, H:i', strtotime($reg['created_at'])) ?></div>
      </div>
    </div>
  </div>

  <div class="pay-body">

    <?php if ($payError): ?>
    <div style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);border-radius:12px;padding:.75rem 1rem;margin-bottom:1.25rem;display:flex;gap:8px;align-items:center;font-size:.85rem;color:#ef4444;">
      <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($payError) ?>
    </div>
    <?php endif; ?>

    <!-- ── Countdown ── -->
    <div class="countdown-box mb-4">
      <div>
        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;margin-bottom:.25rem;">Selesaikan Pembayaran Dalam</div>
        <div id="timer" class="countdown-num"><?= sprintf('%02d:%02d', intdiv($remainingSecs, 60), $remainingSecs % 60) ?></div>
        <div style="font-size:.72rem;color:#94a3b8;margin-top:.25rem;">Sebelum <?= date('H:i', strtotime($reg['expires_at'])) ?> WIB</div>
      </div>
      <div style="text-align:right;">
        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;margin-bottom:.25rem;">Total Tagihan</div>
        <div style="font-family:'Space Grotesk',sans-serif;font-size:1.8rem;font-weight:800;color:#f72585;line-height:1;">
          <?= formatRupiah($reg['amount']) ?>
        </div>
        <div style="font-size:.72rem;color:#94a3b8;margin-top:.25rem;">Biaya registrasi member</div>
      </div>
    </div>

    <!-- ── Info member ── -->
    <div style="background:#f8fafc;border-radius:12px;padding:1rem 1.25rem;margin-bottom:1.5rem;display:flex;flex-wrap:wrap;gap:1.5rem;font-size:.83rem;">
      <div>
        <div style="color:#94a3b8;font-size:.7rem;font-weight:700;text-transform:uppercase;margin-bottom:2px;">Nama</div>
        <div style="font-weight:700;color:#1e293b;"><?= htmlspecialchars($reg['name']) ?></div>
      </div>
      <div>
        <div style="color:#94a3b8;font-size:.7rem;font-weight:700;text-transform:uppercase;margin-bottom:2px;">Email</div>
        <div style="font-weight:600;color:#475569;"><?= htmlspecialchars($reg['email']) ?></div>
      </div>
      <div>
        <div style="color:#94a3b8;font-size:.7rem;font-weight:700;text-transform:uppercase;margin-bottom:2px;">Paket</div>
        <div style="font-weight:700;color:#6c63ff;"><?= ucfirst($reg['plan']) ?></div>
      </div>
      <div>
        <div style="color:#94a3b8;font-size:.7rem;font-weight:700;text-transform:uppercase;margin-bottom:2px;">Nominal</div>
        <div style="font-weight:800;color:#f72585;"><?= formatRupiah($reg['amount']) ?></div>
      </div>
    </div>

    <!-- ── Pilih metode pembayaran ── -->
    <div style="font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748b;margin-bottom:.75rem;">
      <i class="bi bi-credit-card me-1"></i> Pilih Metode Pembayaran
    </div>
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.5rem;" id="pm-tabs">
      <?php foreach ($pmLabels as $pmKey => $pm): ?>
      <button type="button"
        class="pm-tab <?= $selectedPm===$pmKey ? 'active' : '' ?>"
        onclick="selectPm('<?= $pmKey ?>')">
        <i class="bi <?= $pm['icon'] ?>" style="color:<?= $pm['color'] ?>;"></i>
        <?= $pm['label'] ?>
      </button>
      <?php endforeach; ?>
    </div>

    <!-- ── QRIS Panel ── -->
    <div id="pm-QRIS" class="pm-panel" style="<?= $selectedPm==='QRIS'?'':'display:none;' ?>">
      <div style="text-align:center;padding:1.5rem;background:#f8fafc;border-radius:16px;border:1px solid #e2e8f0;">
        <div class="qr-placeholder mb-3"></div>
        <div style="font-weight:700;color:#1e293b;margin-bottom:.25rem;">Scan QRIS</div>
        <div style="font-size:.8rem;color:#64748b;margin-bottom:.75rem;">
          Bayar dengan aplikasi GoPay, OVO, DANA, ShopeePay, mBanking, dll
        </div>
        <div style="background:linear-gradient(135deg,rgba(108,99,255,.1),rgba(0,212,255,.08));border:1px solid rgba(108,99,255,.2);border-radius:12px;padding:.75rem;display:inline-block;">
          <div style="font-size:.7rem;color:#64748b;margin-bottom:2px;">Nominal Tepat</div>
          <div style="font-family:'Space Grotesk';font-size:1.3rem;font-weight:800;color:#6c63ff;">
            <?= formatRupiah($reg['amount']) ?>
          </div>
        </div>
        <div style="font-size:.72rem;color:#94a3b8;margin-top:.75rem;">
          <i class="bi bi-shield-check me-1"></i>QR Code berlaku selama sisa waktu pembayaran
        </div>
      </div>
    </div>

    <!-- ── BCA Panel ── -->
    <div id="pm-BCA" class="pm-panel" style="<?= $selectedPm==='BCA'?'':'display:none;' ?>">
      <div style="background:#f8fafc;border-radius:16px;border:1px solid #e2e8f0;padding:1.5rem;">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:1.25rem;">
          <div style="width:42px;height:42px;background:linear-gradient(135deg,#f59e0b,#ef4444);border-radius:10px;display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-bank" style="color:#fff;font-size:1.2rem;"></i>
          </div>
          <div>
            <div style="font-weight:800;color:#1e293b;">Transfer Bank BCA</div>
            <div style="font-size:.75rem;color:#64748b;">Virtual Account</div>
          </div>
        </div>
        <div style="background:#fff;border-radius:12px;padding:1rem;border:1px solid #e2e8f0;margin-bottom:1rem;">
          <div style="font-size:.7rem;color:#94a3b8;font-weight:700;text-transform:uppercase;margin-bottom:.25rem;">Nomor Rekening / VA</div>
          <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;flex-wrap:wrap;">
            <span id="bca-va" style="font-family:'Space Grotesk';font-size:1.4rem;font-weight:800;color:#1e293b;letter-spacing:.05em;">
              <?= $vaNumbers['BCA'] ?>
            </span>
            <button onclick="copyText('bca-va','BCA')" class="btn btn-sm"
              style="background:rgba(108,99,255,.1);border:1px solid rgba(108,99,255,.25);color:#6c63ff;border-radius:8px;font-size:.72rem;padding:4px 10px;">
              <i class="bi bi-clipboard me-1"></i>Salin
            </button>
          </div>
          <div style="font-size:.72rem;color:#94a3b8;margin-top:.5rem;">Atas nama: SolusiMu Payment</div>
        </div>
        <div style="font-size:.78rem;color:#64748b;line-height:1.6;">
          <strong style="color:#1e293b;">Langkah Transfer:</strong><br/>
          1. Buka aplikasi BCA Mobile / ATM BCA<br/>
          2. Pilih Transfer → Virtual Account<br/>
          3. Masukkan nomor VA di atas<br/>
          4. Masukkan nominal <strong><?= formatRupiah($reg['amount']) ?></strong> — tepat!<br/>
          5. Konfirmasi &amp; selesai
        </div>
      </div>
    </div>

    <!-- ── GoPay Panel ── -->
    <div id="pm-GOPAY" class="pm-panel" style="<?= $selectedPm==='GOPAY'?'':'display:none;' ?>">
      <div style="background:#f8fafc;border-radius:16px;border:1px solid #e2e8f0;padding:1.5rem;">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:1.25rem;">
          <div style="width:42px;height:42px;background:linear-gradient(135deg,#00d4ff,#6c63ff);border-radius:10px;display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-phone" style="color:#fff;font-size:1.2rem;"></i>
          </div>
          <div>
            <div style="font-weight:800;color:#1e293b;">GoPay</div>
            <div style="font-size:.75rem;color:#64748b;">Transfer ke nomor GoPay</div>
          </div>
        </div>
        <div style="background:#fff;border-radius:12px;padding:1rem;border:1px solid #e2e8f0;margin-bottom:1rem;">
          <div style="font-size:.7rem;color:#94a3b8;font-weight:700;text-transform:uppercase;margin-bottom:.25rem;">Nomor GoPay Tujuan</div>
          <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;flex-wrap:wrap;">
            <span id="gopay-num" style="font-family:'Space Grotesk';font-size:1.3rem;font-weight:800;color:#1e293b;">
              <?= $vaNumbers['GOPAY'] ?>
            </span>
            <button onclick="copyText('gopay-num','GoPay')" class="btn btn-sm"
              style="background:rgba(0,212,255,.1);border:1px solid rgba(0,212,255,.25);color:#00d4ff;border-radius:8px;font-size:.72rem;padding:4px 10px;">
              <i class="bi bi-clipboard me-1"></i>Salin
            </button>
          </div>
          <div style="font-size:.72rem;color:#94a3b8;margin-top:.5rem;">Atas nama: SolusiMu Official</div>
        </div>
        <div style="font-size:.78rem;color:#64748b;line-height:1.6;">
          <strong style="color:#1e293b;">Cara Transfer:</strong><br/>
          1. Buka Gojek App → GoPay<br/>
          2. Pilih Kirim → masukkan nomor di atas<br/>
          3. Nominal <strong><?= formatRupiah($reg['amount']) ?></strong>, konfirmasi
        </div>
      </div>
    </div>

    <!-- ── OVO Panel ── -->
    <div id="pm-OVO" class="pm-panel" style="<?= $selectedPm==='OVO'?'':'display:none;' ?>">
      <div style="background:#f8fafc;border-radius:16px;border:1px solid #e2e8f0;padding:1.5rem;">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:1.25rem;">
          <div style="width:42px;height:42px;background:linear-gradient(135deg,#a78bfa,#6c63ff);border-radius:10px;display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-phone-fill" style="color:#fff;font-size:1.2rem;"></i>
          </div>
          <div>
            <div style="font-weight:800;color:#1e293b;">OVO</div>
            <div style="font-size:.75rem;color:#64748b;">Transfer ke nomor OVO</div>
          </div>
        </div>
        <div style="background:#fff;border-radius:12px;padding:1rem;border:1px solid #e2e8f0;margin-bottom:1rem;">
          <div style="font-size:.7rem;color:#94a3b8;font-weight:700;text-transform:uppercase;margin-bottom:.25rem;">Nomor OVO Tujuan</div>
          <div style="display:flex;align-items:center;justify-content:space-between;gap:.5rem;flex-wrap:wrap;">
            <span id="ovo-num" style="font-family:'Space Grotesk';font-size:1.3rem;font-weight:800;color:#1e293b;">
              <?= $vaNumbers['OVO'] ?>
            </span>
            <button onclick="copyText('ovo-num','OVO')" class="btn btn-sm"
              style="background:rgba(167,139,250,.1);border:1px solid rgba(167,139,250,.25);color:#a78bfa;border-radius:8px;font-size:.72rem;padding:4px 10px;">
              <i class="bi bi-clipboard me-1"></i>Salin
            </button>
          </div>
          <div style="font-size:.72rem;color:#94a3b8;margin-top:.5rem;">Atas nama: SolusiMu Official</div>
        </div>
        <div style="font-size:.78rem;color:#64748b;line-height:1.6;">
          <strong style="color:#1e293b;">Cara Transfer:</strong><br/>
          1. Buka OVO App → Transfer<br/>
          2. Pilih ke sesama OVO<br/>
          3. Masukkan nomor di atas, nominal <strong><?= formatRupiah($reg['amount']) ?></strong>
        </div>
      </div>
    </div>

    <!-- ── Tombol Konfirmasi ── -->
    <div style="margin-top:1.5rem;">
      <button type="button" id="btnConfirm"
        style="width:100%;background:linear-gradient(135deg,#10b981,#00d4ff);color:#fff;border:none;border-radius:14px;padding:1rem;font-size:1rem;font-weight:700;cursor:pointer;transition:opacity .2s;"
        onmouseover="this.style.opacity='.9'" onmouseout="this.style.opacity='1'"
        onclick="openConfirmModal()">
        <i class="bi bi-check2-circle me-2"></i>Saya Sudah Membayar — Konfirmasi
      </button>
      <div style="font-size:.72rem;color:#94a3b8;text-align:center;margin-top:.5rem;">
        <i class="bi bi-shield-check me-1"></i>Klik setelah Anda menyelesaikan transfer pembayaran
      </div>
    </div>

    <!-- ── Info kadaluarsa ── -->
    <div style="margin-top:1.25rem;background:rgba(245,158,11,.06);border:1px solid rgba(245,158,11,.2);border-radius:12px;padding:.75rem 1rem;font-size:.78rem;color:#92400e;">
      <i class="bi bi-exclamation-triangle me-1" style="color:#f59e0b;"></i>
      Invoice ini <strong>kadaluarsa pada <?= date('H:i', strtotime($reg['expires_at'])) ?> WIB</strong>.
      Jika melewati batas waktu, Anda harus mengisi formulir pendaftaran kembali.
      Jika menggunakan email yang sama, tagihan ini masih akan muncul selama belum habis waktu.
    </div>

  </div><!-- /.pay-body -->
</div><!-- /.pay-card -->

<!-- ═══ Modal Konfirmasi ═══ -->
<div id="confirmModal" class="confirm-overlay" style="display:none;">
  <div class="confirm-modal">
    <div style="text-align:center;margin-bottom:1.5rem;">
      <div style="width:60px;height:60px;border-radius:50%;background:rgba(16,185,129,.12);border:2px solid rgba(16,185,129,.3);display:flex;align-items:center;justify-content:center;margin:0 auto .75rem;">
        <i class="bi bi-check2-circle" style="font-size:1.8rem;color:#10b981;"></i>
      </div>
      <h5 style="font-weight:800;color:#f1f5f9;margin-bottom:.25rem;">Konfirmasi Pembayaran</h5>
      <p style="font-size:.83rem;color:#94a3b8;margin:0;">Pastikan Anda sudah mengirim <strong style="color:#f1f5f9;"><?= formatRupiah($reg['amount']) ?></strong> via <span id="modal-pm-label" style="color:#a78bfa;"><?= $selectedPm ?></span></p>
    </div>

    <form method="POST" action="invoice_register.php?token=<?= htmlspecialchars($token) ?>" id="confirmForm">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="confirm_payment"/>
      <input type="hidden" name="pay_method" id="modal-pm-input" value="<?= htmlspecialchars($selectedPm) ?>"/>

      <div style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:12px;padding:1rem;margin-bottom:1.25rem;font-size:.8rem;color:#94a3b8;line-height:1.7;">
        <div><i class="bi bi-person me-2"></i>Nama: <strong style="color:#f1f5f9;"><?= htmlspecialchars($reg['name']) ?></strong></div>
        <div><i class="bi bi-envelope me-2"></i>Email: <strong style="color:#f1f5f9;"><?= htmlspecialchars($reg['email']) ?></strong></div>
        <div><i class="bi bi-tag me-2"></i>Paket: <strong style="color:#a78bfa;"><?= ucfirst($reg['plan']) ?></strong></div>
        <div><i class="bi bi-cash me-2"></i>Dibayar: <strong style="color:#10b981;"><?= formatRupiah($reg['amount']) ?></strong></div>
      </div>

      <div style="display:flex;gap:.75rem;">
        <button type="button" onclick="closeConfirmModal()"
          style="flex:1;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);color:#94a3b8;border-radius:12px;padding:.75rem;font-size:.875rem;font-weight:600;cursor:pointer;">
          Batal
        </button>
        <button type="submit"
          style="flex:2;background:linear-gradient(135deg,#10b981,#00d4ff);color:#fff;border:none;border-radius:12px;padding:.75rem;font-size:.875rem;font-weight:700;cursor:pointer;">
          <i class="bi bi-check-circle me-2"></i>Ya, Konfirmasi Sekarang
        </button>
      </div>
    </form>
  </div>
</div>

<?php endif; /* end pending */ ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/main.js"></script>
<script>
// ── Countdown Timer ────────────────────────────────────────────
<?php if (!$isPaid && !$isExpired): ?>
(function() {
  let remaining = <?= $remainingSecs ?>;
  const timerEl = document.getElementById('timer');
  if (!timerEl) return;

  function fmt(s) {
    const m = String(Math.floor(s / 60)).padStart(2,'0');
    const sec = String(s % 60).padStart(2,'0');
    return m + ':' + sec;
  }

  function tick() {
    if (remaining <= 0) {
      timerEl.textContent = '00:00';
      timerEl.classList.add('warning');
      // Reload page after short delay to show expired state
      setTimeout(() => location.reload(), 1500);
      return;
    }
    timerEl.textContent = fmt(remaining);
    if (remaining <= 60) timerEl.classList.add('warning');
    else timerEl.classList.remove('warning');
    remaining--;
    setTimeout(tick, 1000);
  }
  tick();
})();
<?php endif; ?>

// ── Payment method switcher ────────────────────────────────────
function selectPm(key) {
  // Hide all panels
  document.querySelectorAll('.pm-panel').forEach(p => p.style.display = 'none');
  // Deactivate all tabs
  document.querySelectorAll('.pm-tab').forEach(t => t.classList.remove('active'));
  // Show selected
  const panel = document.getElementById('pm-' + key);
  if (panel) panel.style.display = '';
  // Activate tab
  const tabs = document.querySelectorAll('#pm-tabs .pm-tab');
  tabs.forEach(t => { if (t.onclick.toString().includes("'" + key + "'")) t.classList.add('active'); });
  // Update modal hidden input & label
  const inp = document.getElementById('modal-pm-input');
  if (inp) inp.value = key;
  const lbl = document.getElementById('modal-pm-label');
  if (lbl) {
    const labels = {QRIS:'QRIS', BCA:'Transfer BCA', GOPAY:'GoPay', OVO:'OVO'};
    lbl.textContent = labels[key] || key;
  }
}

// Fix onclick for tab buttons (re-attach using dataset approach is cleaner but let's keep it simple)
document.querySelectorAll('#pm-tabs .pm-tab').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('#pm-tabs .pm-tab').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
  });
});

// ── Copy to clipboard ──────────────────────────────────────────
function copyText(elId, label) {
  const el = document.getElementById(elId);
  if (!el) return;
  navigator.clipboard.writeText(el.textContent.trim()).then(() => {
    if (typeof showToast === 'function') showToast('Disalin!', label + ' berhasil disalin ke clipboard.', 'success');
  });
}

// ── Confirm modal ──────────────────────────────────────────────
function openConfirmModal() {
  document.getElementById('confirmModal').style.display = 'flex';
}
function closeConfirmModal() {
  document.getElementById('confirmModal').style.display = 'none';
}
// Close on backdrop click
document.getElementById('confirmModal')?.addEventListener('click', function(e) {
  if (e.target === this) closeConfirmModal();
});
</script>
</body>
</html>
