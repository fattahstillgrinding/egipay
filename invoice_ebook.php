<?php
require_once __DIR__ . '/includes/config.php';
requireLogin();

$user   = getCurrentUser();
$userId = (int)$_SESSION['user_id'];

// ── Ambil token dari URL ───────────────────────────────────────
$token = trim($_GET['token'] ?? '');
if (!$token) {
    setFlash('error', 'Link Tidak Valid', 'Link invoice tidak ditemukan.');
    redirect(BASE_URL . '/ebooks.php');
}

$order = dbFetchOne('SELECT * FROM ebook_orders WHERE token = ? AND user_id = ?', [$token, $userId]);
if (!$order) {
    setFlash('error', 'Invoice Tidak Ditemukan', 'Link invoice tidak valid atau bukan milik Anda.');
    redirect(BASE_URL . '/ebooks.php');
}

// ── Handle POST: konfirmasi pembayaran ─────────────────────────
$payError = '';
if ($order['status'] === 'pending' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action    = $_POST['action']         ?? '';
    $payMethod = trim($_POST['pay_method'] ?? 'GOPAY');

    if ($action === 'confirm_payment') {
        $fresh = dbFetchOne(
            'SELECT * FROM ebook_orders
             WHERE token = ? AND user_id = ? AND status = "pending" AND expires_at > NOW() LIMIT 1',
            [$token, $userId]
        );

        if (!$fresh) {
            dbExecute('UPDATE ebook_orders SET status="expired" WHERE token=?', [$token]);
        } else {
            try {
                $pdo = getDB();
                $pdo->beginTransaction();

                dbExecute(
                    'UPDATE ebook_orders SET status="paid", paid_at=NOW(), payment_method=? WHERE token=?',
                    [$payMethod, $token]
                );

                $freshTotal = (float)$fresh['amount'] + (int)($fresh['unique_code'] ?? 0);

                dbExecute(
                    'INSERT INTO notifications (user_id, type, title, message) VALUES (?, "success", ?, ?)',
                    [
                        $userId,
                        'Pembelian E-Book Berhasil!',
                        'E-Book "' . $fresh['ebook_title'] . '" senilai ' . formatRupiah($freshTotal) . ' berhasil dibeli.',
                    ]
                );

                $pdo->commit();
                auditLog($userId, 'ebook_paid', 'Pembelian e-book: ' . $fresh['ebook_title'] . ' — ' . $fresh['inv_no'] . ' — ' . formatRupiah($freshTotal));

            } catch (PDOException $e) {
                $pdo->rollBack();
                $payError = 'Gagal memproses pembayaran. Silakan coba lagi.';
            }
        }
    }
}

// ── Refresh status terkini ─────────────────────────────────────
$order = dbFetchOne('SELECT * FROM ebook_orders WHERE token = ?', [$token]);

$isPaid    = ($order['status'] === 'paid');
$isExpired = false;
$remainingSecs = 0;

if ($order['status'] === 'pending') {
    $dbNow     = dbFetchOne('SELECT NOW() AS t')['t'];
    $expiresAt = strtotime($order['expires_at']);
    $nowTs     = strtotime($dbNow);
    if ($nowTs >= $expiresAt) {
        dbExecute('UPDATE ebook_orders SET status="expired" WHERE token=?', [$token]);
        $isExpired = true;
    } else {
        $remainingSecs = $expiresAt - $nowTs;
    }
} elseif ($order['status'] === 'expired') {
    $isExpired = true;
}

$pmLabels = [
    'GOPAY' => ['icon' => 'bi-phone', 'color' => '#00d4ff', 'label' => 'GoPay'],
];
$vaNumbers = [
    'GOPAY' => '0878-8413-5999',
];
$selectedPm  = 'GOPAY';
$uniqueCode  = (int)($order['unique_code'] ?? 0);
$totalBayar  = (float)$order['amount'] + $uniqueCode;
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
    @media print {
      .no-print { display:none !important; }
      body { background:#fff !important; }
      .pay-card { box-shadow:none !important; border:1px solid #ddd !important; }
    }

    body { background:linear-gradient(135deg,#0f0f1e 0%,#1a1a35 100%); min-height:100vh; }

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

    .inv-table th { background:#f8fafc; color:#64748b; font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; padding:.65rem 1rem; border:none; }
    .inv-table td { padding:.8rem 1rem; border-bottom:1px solid #f1f5f9; font-size:.875rem; color:#334155; }
    .inv-table tr:last-child td { border-bottom:none; }

    .page-expired { background:#fff; border-radius:20px; max-width:500px; margin:0 auto; padding:3rem 2.5rem; text-align:center; }
    .page-paid-bar { background:linear-gradient(135deg,#10b981,#00d4ff); padding:2rem 2.5rem; color:#fff; }

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
    <h2 style="font-size:1.4rem;font-weight:800;color:#1e293b;margin-bottom:.5rem;">Sesi Pembelian Habis</h2>
    <p style="color:#64748b;font-size:.9rem;margin-bottom:.5rem;">
      Waktu pembayaran untuk <strong><?= htmlspecialchars($order['ebook_title']) ?></strong> telah melebihi <strong>15 menit</strong>.
    </p>
    <p style="color:#94a3b8;font-size:.8rem;margin-bottom:2rem;">
      No. Invoice: <code><?= htmlspecialchars($order['inv_no']) ?></code>
    </p>
    <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:12px;padding:1rem;margin-bottom:2rem;font-size:.82rem;color:#b91c1c;">
      <i class="bi bi-info-circle me-2"></i>
      Sesi pembelian telah kadaluarsa. Silakan kembali ke halaman e-book untuk membeli kembali.
    </div>
    <a href="<?= BASE_URL ?>/ebooks.php" class="btn btn-primary-gradient w-100 py-3" style="border-radius:12px;font-weight:700;">
      <i class="bi bi-arrow-repeat me-2"></i>Kembali ke E-Book
    </a>
    <a href="<?= BASE_URL ?>/dashboard.php" style="display:block;margin-top:1rem;font-size:.82rem;color:#64748b;text-decoration:none;">
      Kembali ke <span style="color:#6c63ff;font-weight:600;">Dashboard</span>
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
    <a href="<?= BASE_URL ?>/ebooks.php" class="btn btn-sm px-4"
      style="background:rgba(255,255,255,.1);color:#fff;border:1px solid rgba(255,255,255,.25);border-radius:10px;">
      <i class="bi bi-book me-2"></i>Beli E-Book Lain
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
            <div style="font-size:.8rem;opacity:.85;">Pembelian E-Book SolusiMu</div>
          </div>
        </div>
        <div style="font-size:.78rem;opacity:.75;">
          SolusiMu · support@solusimu.com<br/>
          Digenerate: <?= date('d M Y H:i') ?> WIB
        </div>
      </div>
      <div style="text-align:right;">
        <div style="font-size:1.6rem;font-weight:800;">INVOICE</div>
        <div style="font-weight:700;opacity:.9;"><?= htmlspecialchars($order['inv_no']) ?></div>
        <div style="font-size:.75rem;opacity:.75;margin-top:.25rem;">
          Lunas: <?= $order['paid_at'] ? date('d M Y H:i', strtotime($order['paid_at'])) : date('d M Y H:i') ?> WIB
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
        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;margin-bottom:.5rem;">Pembeli</div>
        <div style="font-weight:700;font-size:1.05rem;"><?= htmlspecialchars($user['name']) ?></div>
        <div style="color:#64748b;font-size:.875rem;"><?= htmlspecialchars($user['email']) ?></div>
        <div style="color:#64748b;font-size:.875rem;"><?= htmlspecialchars($user['phone'] ?? '-') ?></div>
        <div style="margin-top:.5rem;display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
          <?php if (!empty($user['member_code'])): ?>
          <span style="background:rgba(247,37,133,.1);color:#f72585;border:1px solid rgba(247,37,133,.25);border-radius:8px;padding:3px 10px;font-size:.72rem;font-weight:700;letter-spacing:.04em;">
            <?= htmlspecialchars($user['member_code']) ?>
          </span>
          <?php endif; ?>
        </div>
      </div>
      <div class="col-sm-6 text-sm-end">
        <table style="width:100%;font-size:.875rem;color:#64748b;" class="ms-sm-auto">
          <tr><td style="padding:2px 0;">No. Invoice:</td><td style="font-weight:600;color:#1e293b;padding-left:1rem;"><?= htmlspecialchars($order['inv_no']) ?></td></tr>
          <tr><td>Metode Bayar:</td><td style="font-weight:600;color:#1e293b;padding-left:1rem;"><?= htmlspecialchars($order['payment_method'] ?? '-') ?></td></tr>
          <tr><td>Tanggal Beli:</td><td style="font-weight:600;color:#1e293b;padding-left:1rem;"><?= date('d M Y', strtotime($order['created_at'])) ?></td></tr>
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
              <div style="font-weight:700;"><?= htmlspecialchars($order['ebook_title']) ?></div>
              <div style="font-size:.75rem;color:#94a3b8;">E-Book Digital SolusiMu</div>
            </td>
            <td><span style="background:rgba(108,99,255,.1);color:#6c63ff;border-radius:6px;padding:2px 8px;font-size:.72rem;font-weight:700;">E-BOOK</span></td>
            <td class="text-end" style="font-weight:700;"><?= formatRupiah((float)$order['amount']) ?></td>
          </tr>

        </tbody>
      </table>
    </div>

    <!-- Total -->
    <div class="row justify-content-end mb-4">
      <div class="col-sm-6 col-md-5">
        <table style="width:100%;font-size:.875rem;margin-bottom:.75rem;">
          <tr><td style="padding:4px 0;color:#64748b;">Subtotal:</td><td class="text-end" style="font-weight:600;"><?= formatRupiah($totalBayar) ?></td></tr>
          <tr><td style="padding:4px 0;color:#64748b;">Pajak:</td><td class="text-end" style="color:#10b981;font-weight:600;">Rp 0</td></tr>
        </table>
        <div style="background:linear-gradient(135deg,#10b981,#00d4ff);border-radius:12px;padding:1rem 1.25rem;color:#fff;">
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <span style="font-weight:600;">TOTAL DIBAYAR</span>
            <span style="font-size:1.4rem;font-weight:800;"><?= formatRupiah($totalBayar) ?></span>
          </div>
        </div>
      </div>
    </div>
    <hr style="border-color:#e2e8f0;margin:1.25rem 0;"/>

    <!-- Footer note -->
    <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:1rem;font-size:.8rem;color:#166534;">
      <i class="bi bi-check-circle-fill me-2 text-success"></i>
      Invoice ini adalah bukti resmi pembelian e-book SolusiMu. Simpan sebagai referensi.<br/>
      Pertanyaan: <strong>support@solusimu.com</strong> · Kode invoice: <strong><?= htmlspecialchars($order['inv_no']) ?></strong>
    </div>
  </div>
</div>

<div class="no-print text-center mt-4 pb-4">
  <a href="<?= BASE_URL ?>/ebooks.php" class="btn btn-primary-gradient px-5 py-2">
    <i class="bi bi-book me-2"></i>Beli E-Book Lainnya
  </a>
</div>

<?php /* ═══════════════════════════════════════════════════════
       CASE 3 — PENDING (Menunggu Pembayaran)
═══════════════════════════════════════════════════════ */ ?>
<?php else: ?>

<!-- Navbar mini -->
<div class="no-print" style="max-width:700px;margin:0 auto 1.25rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;">
  <a href="<?= BASE_URL ?>/ebooks.php"
    style="color:rgba(255,255,255,.5);text-decoration:none;font-size:.78rem;display:flex;align-items:center;gap:6px;"
    onmouseover="this.style.color='#fff'" onmouseout="this.style.color='rgba(255,255,255,.5)'">
    <i class="bi bi-arrow-left-circle"></i> Kembali ke E-Book
  </a>
  <a href="<?= BASE_URL ?>/dashboard.php" style="color:rgba(255,255,255,.5);text-decoration:none;font-size:.78rem;">
    Ke <span style="color:#a78bfa;">Dashboard</span>
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
            <div style="font-size:.72rem;opacity:.8;">Tagihan Pembelian E-Book</div>
          </div>
        </div>
      </div>
      <div style="text-align:right;">
        <div style="font-size:1rem;font-weight:800;opacity:.9;"><?= htmlspecialchars($order['inv_no']) ?></div>
        <div style="font-size:.75rem;opacity:.75;">Dibuat: <?= date('d M Y, H:i', strtotime($order['created_at'])) ?></div>
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
        <div style="font-size:.72rem;color:#94a3b8;margin-top:.25rem;">Sebelum <?= date('H:i', strtotime($order['expires_at'])) ?> WIB</div>
      </div>
      <div style="text-align:right;">
        <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;margin-bottom:.25rem;">Total Tagihan</div>
        <div style="font-family:'Space Grotesk',sans-serif;font-size:1.8rem;font-weight:800;color:#f72585;line-height:1;">
          <?= formatRupiah($totalBayar) ?>
        </div>
        <div style="font-size:.72rem;color:#94a3b8;margin-top:.25rem;">Selesaikan sebelum waktu habis</div>
      </div>
    </div>

    <!-- ── Info E-Book ── -->
    <div style="background:#f8fafc;border-radius:12px;padding:1rem 1.25rem;margin-bottom:1.5rem;font-size:.83rem;">
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <div style="width:42px;height:42px;background:linear-gradient(135deg,#6c63ff,#00d4ff);border-radius:10px;flex-shrink:0;display:flex;align-items:center;justify-content:center;">
          <i class="bi bi-book-fill" style="color:#fff;font-size:1.1rem;"></i>
        </div>
        <div style="flex:1;min-width:0;">
          <div style="font-weight:700;color:#1e293b;"><?= htmlspecialchars($order['ebook_title']) ?></div>
          <div style="color:#64748b;font-size:.78rem;">E-Book Digital</div>
        </div>
        <div style="font-family:'Space Grotesk';font-size:1.1rem;font-weight:800;color:#6c63ff;">
          <?= formatRupiah((float)$order['amount']) ?>
        </div>
      </div>
    </div>

    <!-- ── Info pembeli ── -->
    <div style="background:#f8fafc;border-radius:12px;padding:1rem 1.25rem;margin-bottom:1.5rem;display:flex;flex-wrap:wrap;gap:1.5rem;font-size:.83rem;">
      <div>
        <div style="color:#94a3b8;font-size:.7rem;font-weight:700;text-transform:uppercase;margin-bottom:2px;">Nama</div>
        <div style="font-weight:700;color:#1e293b;"><?= htmlspecialchars($user['name']) ?></div>
      </div>
      <div>
        <div style="color:#94a3b8;font-size:.7rem;font-weight:700;text-transform:uppercase;margin-bottom:2px;">Email</div>
        <div style="font-weight:600;color:#475569;"><?= htmlspecialchars($user['email']) ?></div>
      </div>
      <div>
        <div style="color:#94a3b8;font-size:.7rem;font-weight:700;text-transform:uppercase;margin-bottom:2px;">Nominal</div>
        <div style="font-weight:800;color:#f72585;"><?= formatRupiah((float)$order['amount']) ?></div>
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
        <!-- ── Kotak kode unik ── -->
        <div style="background:linear-gradient(135deg,rgba(247,37,133,.06),rgba(108,99,255,.06));border:2px dashed rgba(247,37,133,.35);border-radius:12px;padding:.85rem 1rem;margin-bottom:1rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem;">
          <div>
            <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;color:#94a3b8;margin-bottom:2px;">Transfer Tepat Sejumlah</div>
            <div style="font-family:'Space Grotesk';font-size:1.5rem;font-weight:800;color:#f72585;"><?= formatRupiah($totalBayar) ?></div>
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
          <div style="font-size:.72rem;color:#94a3b8;margin-top:.5rem;">Atas nama: SUPRIYATI (SolusiMu)</div>
        </div>
        <div style="font-size:.78rem;color:#64748b;line-height:1.6;">
          <strong style="color:#1e293b;">Cara Transfer:</strong><br/>
          1. Buka Gojek App → GoPay<br/>
          2. Pilih Kirim → masukkan nomor di atas<br/>
          3. Nominal <strong style="color:#f72585;"><?= formatRupiah($totalBayar) ?></strong>, konfirmasi
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
      Invoice ini <strong>kadaluarsa pada <?= date('H:i', strtotime($order['expires_at'])) ?> WIB</strong>.
      Jika melewati batas waktu, silakan beli ulang dari halaman e-book.
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
      <p style="font-size:.83rem;color:#94a3b8;margin:0;">Pastikan Anda sudah mengirim <strong style="color:#f72585;"><?= formatRupiah($totalBayar) ?></strong> via <span id="modal-pm-label" style="color:#a78bfa;"><?= $selectedPm ?></span></p>
    </div>

    <form method="POST" action="invoice_ebook.php?token=<?= htmlspecialchars($token) ?>" id="confirmForm">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="confirm_payment"/>
      <input type="hidden" name="pay_method" id="modal-pm-input" value="<?= htmlspecialchars($selectedPm) ?>"/>

      <div style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:12px;padding:1rem;margin-bottom:1.25rem;font-size:.8rem;color:#94a3b8;line-height:1.7;">
        <div><i class="bi bi-person me-2"></i>Pembeli: <strong style="color:#f1f5f9;"><?= htmlspecialchars($user['name']) ?></strong></div>
        <div><i class="bi bi-book me-2"></i>E-Book: <strong style="color:#a78bfa;"><?= htmlspecialchars($order['ebook_title']) ?></strong></div>
        <div><i class="bi bi-cash me-2"></i>Dibayar: <strong style="color:#f72585;"><?= formatRupiah($totalBayar) ?></strong></div>
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
    const m   = String(Math.floor(s / 60)).padStart(2,'0');
    const sec = String(s % 60).padStart(2,'0');
    return m + ':' + sec;
  }

  function tick() {
    if (remaining <= 0) {
      timerEl.textContent = '00:00';
      timerEl.classList.add('warning');
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
  document.querySelectorAll('.pm-panel').forEach(p => p.style.display = 'none');
  document.querySelectorAll('.pm-tab').forEach(t => t.classList.remove('active'));
  const panel = document.getElementById('pm-' + key);
  if (panel) panel.style.display = '';
  document.querySelectorAll('#pm-tabs .pm-tab').forEach(t => {
    if (t.getAttribute('onclick') && t.getAttribute('onclick').includes("'" + key + "'")) {
      t.classList.add('active');
    }
  });
  const inp = document.getElementById('modal-pm-input');
  if (inp) inp.value = key;
  const lbl = document.getElementById('modal-pm-label');
  if (lbl) {
    const labels = { GOPAY: 'GoPay' };
    lbl.textContent = labels[key] || key;
  }
}

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
document.getElementById('confirmModal')?.addEventListener('click', function(e) {
  if (e.target === this) closeConfirmModal();
});
</script>
</body>
</html>
