<?php
require_once __DIR__ . '/includes/config.php';
requireLogin();

$user   = getCurrentUser();
$userId = (int)$_SESSION['user_id'];
$wallet = getUserWallet($userId);
$flash  = getFlash();

// ── Stats ─────────────────────────────────────────────────────
$totalTx = dbFetchOne(
    'SELECT COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total_amount
     FROM transactions WHERE user_id = ? AND status = "success"',
    [$userId]
);
$pendingTx = dbFetchOne(
    'SELECT COUNT(*) AS cnt, COALESCE(SUM(total),0) AS locked_amt
     FROM transactions WHERE user_id = ? AND status = "pending"',
    [$userId]
);
$monthIncome = dbFetchOne(
    'SELECT COALESCE(SUM(amount),0) AS income
     FROM transactions
     WHERE user_id = ? AND status = "success"
       AND MONTH(created_at) = MONTH(NOW())
       AND YEAR(created_at) = YEAR(NOW())',
    [$userId]
);
$lastMonthIncome = dbFetchOne(
    'SELECT COALESCE(SUM(amount),0) AS income
     FROM transactions
     WHERE user_id = ? AND status = "success"
       AND MONTH(created_at) = MONTH(NOW() - INTERVAL 1 MONTH)
       AND YEAR(created_at)  = YEAR(NOW()  - INTERVAL 1 MONTH)',
    [$userId]
);
$incomeGrowth = 0;
// ── Recent transactions ────────────────────────────────────
if ($lastMonthIncome['income'] > 0) {
    $incomeGrowth = round((($monthIncome['income'] - $lastMonthIncome['income']) / $lastMonthIncome['income']) * 100, 1);
}

// ── Referral stats ───────────────────────────────────────────
$referralCode  = $user['referral_code'] ?? null;
$referralLink  = $referralCode ? BASE_URL . '/register.php?ref=' . $referralCode : null;
$referralTotal = $referralCode ? (int)(dbFetchOne('SELECT COUNT(*) AS c FROM referrals WHERE referrer_id = ?', [$userId])['c'] ?? 0) : 0;
$referralRecent = $referralCode ? dbFetchAll(
    'SELECT u.name, u.avatar, r.created_at
     FROM referrals r JOIN users u ON r.referred_id = u.id
     WHERE r.referrer_id = ?
     ORDER BY r.created_at DESC LIMIT 5',
    [$userId]
) : [];

$transactions = dbFetchAll(
    'SELECT t.*, pm.name AS method_name, pm.icon_class, pm.color
     FROM transactions t
     LEFT JOIN payment_methods pm ON t.payment_method_id = pm.id
     WHERE t.user_id = ?
     ORDER BY t.created_at DESC
     LIMIT 10',
    [$userId]
);

// ── Weekly revenue chart data (last 7 days) ───────────────────
$weeklyData = dbFetchAll(
    'SELECT DATE(created_at) AS day, COALESCE(SUM(amount),0) AS total
     FROM transactions
     WHERE user_id = ? AND status = "success"
       AND created_at >= CURDATE() - INTERVAL 6 DAY
     GROUP BY DATE(created_at)
     ORDER BY day ASC',
    [$userId]
);

// Fill in missing days
$weekLabels = [];
$weekValues = [];
$dataMap    = array_column($weeklyData, 'total', 'day');
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $weekLabels[] = date('D', strtotime($d));
    $weekValues[] = (float)($dataMap[$d] ?? 0);
}

// ── Payment method breakdown ──────────────────────────────────
$methodBreakdown = dbFetchAll(
    'SELECT pm.name, pm.color, COUNT(*) AS cnt
     FROM transactions t
     JOIN payment_methods pm ON t.payment_method_id = pm.id
     WHERE t.user_id = ? AND t.status = "success"
     GROUP BY pm.id
     ORDER BY cnt DESC
     LIMIT 5',
    [$userId]
);

// ── Notifications ─────────────────────────────────────────────
$notifCount   = getUnreadNotifCount($userId);
$notifications = dbFetchAll(
    'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5',
    [$userId]
);

// Mark all notifications read on page visit
dbExecute('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [$userId]);

// ── Incentive Wallet ──────────────────────────────────────────
$incentiveWallet = dbFetchOne(
    'SELECT balance, locked, total_received FROM incentive_wallets WHERE user_id = ?',
    [$userId]
);
$incProcessing = (float)(dbFetchOne(
    'SELECT COALESCE(SUM(amount),0) AS amt
     FROM incentive_withdrawals WHERE user_id = ? AND status = "processing"',
    [$userId]
)['amt'] ?? 0);
$insentifBerbagi = (float)(dbFetchOne(
    'SELECT COALESCE(SUM(amount),0) AS amt
     FROM incentive_transfers WHERE to_user_id = ? AND status = "completed"',
    [$userId]
)['amt'] ?? 0);
$totalInsentifDiterima = (float)($incentiveWallet['total_received'] ?? 0);
// Royalty = total received minus direct transfers
$insentifRoyalty = max(0, $totalInsentifDiterima - $insentifBerbagi);

// ── All direct referrals (full list) ─────────────────────────
$allReferrals = $referralCode ? dbFetchAll(
    'SELECT u.name, u.member_code, u.avatar, r.created_at
     FROM referrals r JOIN users u ON r.referred_id = u.id
     WHERE r.referrer_id = ?
     ORDER BY r.created_at ASC',
    [$userId]
) : [];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Dashboard – SolusiMu</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link href="css/style.css" rel="stylesheet"/>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="toast-container" id="toastContainer"></div>

<?php if ($flash): ?>
<div id="flashMessage"
  data-type="<?= $flash['type'] ?>"
  data-title="<?= htmlspecialchars($flash['title']) ?>"
  data-message="<?= htmlspecialchars($flash['message']) ?>"
  style="display:none"></div>
<?php endif; ?>

<?php
$pageTitle = 'Dashboard';
$pageSubtitle = 'Selamat datang kembali, ' . htmlspecialchars($user['name']);
include __DIR__ . '/includes/sidebar.php';
?>

<!-- ====== MAIN CONTENT ====== -->
<main class="main-content">
  <?php include __DIR__ . '/includes/header.php'; ?>

  <!-- Content Body -->
  <div class="content-body">
  
  <!-- ── Stats Cards ──────────────────────────────────────────── -->
  <div class="row g-4 mb-4">
    <div class="col-sm-6 col-xl-3 d-flex">
      <div class="stat-card animate-on-scroll w-100" style="background:linear-gradient(135deg,rgba(108,99,255,0.2),rgba(108,99,255,0.04));border-color:rgba(108,99,255,0.3);">
        <div class="stat-card-icon" style="background:rgba(108,99,255,0.15);">
          <i class="bi bi-wallet2" style="background:linear-gradient(135deg,#6c63ff,#a78bfa);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;"></i>
        </div>
        <div class="stat-card-label">Total Saldo</div>
        <div class="stat-card-value"><?= formatRupiah($wallet['balance'] ?? 0) ?></div>
        <div class="stat-card-trend" style="color:var(--text-muted);">
          <i class="bi bi-lock me-1"></i>Dikunci: <?= formatRupiah($wallet['locked'] ?? 0) ?>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3 d-flex">
      <div class="stat-card animate-on-scroll animate-delay-1 w-100">
        <div class="stat-card-icon" style="background:rgba(16,185,129,0.12);">
          <i class="bi bi-graph-up-arrow" style="background:linear-gradient(135deg,#10b981,#00d4ff);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;"></i>
        </div>
        <div class="stat-card-label">Pendapatan Bulan Ini</div>
        <div class="stat-card-value"><?= formatRupiah($monthIncome['income'] ?? 0) ?></div>
        <div class="stat-card-trend <?= $incomeGrowth >= 0 ? 'trend-up' : 'trend-down' ?>">
          <i class="bi bi-arrow-<?= $incomeGrowth >= 0 ? 'up' : 'down' ?>-right"></i>
          <?= abs($incomeGrowth) ?>% dari bulan lalu
        </div>
      </div>
    </div>

     <div class="col-lg-6">
      <div class="glass-table-wrapper p-4" style="height:100%;">
        <h2 style="font-size:1rem;font-weight:700;margin-bottom:1.5rem;">Metode Terpopuler</h2>
        <?php if ($methodBreakdown): ?>
        <canvas id="methodChart" height="180" style="max-height:180px;"></canvas>
        <div style="margin-top:1.5rem;" id="methodLegend"></div>
        <?php else: ?>
        <div style="text-align:center;padding:3rem 1rem;color:var(--text-muted);">
          <i class="bi bi-bar-chart" style="font-size:2.5rem;margin-bottom:0.75rem;display:block;"></i>
          Belum ada data transaksi
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ── Incentive & Referral Section ─────────────────────────── -->
  <div class="row g-4 mb-4">

    <!-- Sertifikat + Wallet Summary -->
    <div class="col-lg-6">

      <!-- Sertifikat Penghargaan -->
      <div class="animate-on-scroll" style="background:linear-gradient(135deg,rgba(245,158,11,0.18),rgba(247,37,133,0.08));border:1px solid rgba(245,158,11,0.3);border-radius:20px;padding:1.5rem 1.75rem;margin-bottom:1.25rem;">
        <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.9rem;">
          <div style="width:42px;height:42px;border-radius:12px;background:linear-gradient(135deg,#f59e0b,#ef4444);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="bi bi-award-fill" style="color:#fff;font-size:1.2rem;"></i>
          </div>
          <div>
            <div style="font-weight:800;font-size:1rem;color:var(--text-primary);">Sertifikat Penghargaan</div>
            <div style="font-size:0.72rem;color:var(--text-muted);">Total kontribusi insentif yang diterima</div>
          </div>
        </div>
        <div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;">
          <div>
            <div style="font-size:0.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:0.2rem;">Total Insentif Diterima</div>
            <div style="font-family:'Space Grotesk';font-size:1.75rem;font-weight:800;background:linear-gradient(135deg,#f59e0b,#ef4444);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;line-height:1.1;"><?= formatRupiah($totalInsentifDiterima) ?></div>
          </div>
          <a href="incentive_wallet.php" class="btn"
             style="background:linear-gradient(135deg,#f59e0b,#ef4444);border:none;color:#fff;font-weight:700;font-size:0.8rem;border-radius:12px;padding:0.5rem 1.25rem;white-space:nowrap;">
            Selanjutnya <i class="bi bi-arrow-right ms-1"></i>
          </a>
        </div>
      </div>

      <!-- Wallet Summary -->
      <div class="animate-on-scroll" style="background:var(--bg-card);border:1px solid var(--border-glass);border-radius:20px;padding:1.5rem 1.75rem;">
        <div style="font-weight:700;font-size:0.85rem;text-transform:uppercase;letter-spacing:0.07em;color:var(--text-muted);margin-bottom:1rem;">Ringkasan Dompet</div>

        <!-- Dompet Insentif -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
          <div style="display:flex;align-items:center;gap:0.6rem;">
            <div style="width:32px;height:32px;border-radius:9px;background:linear-gradient(135deg,#a855f7,#6c63ff);display:flex;align-items:center;justify-content:center;">
              <i class="bi bi-gift-fill" style="color:#fff;font-size:0.8rem;"></i>
            </div>
            <div style="font-size:0.82rem;font-weight:600;color:var(--text-primary);">Dompet Insentif</div>
          </div>
          <div style="font-family:'Space Grotesk';font-weight:800;font-size:0.95rem;color:#a855f7;"><?= formatRupiah($incentiveWallet['balance'] ?? 0) ?></div>
        </div>

        <!-- Insentif Diproses Pencairan -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;padding-left:0.25rem;">
          <div style="display:flex;align-items:center;gap:0.6rem;">
            <div style="width:32px;height:32px;border-radius:9px;background:rgba(245,158,11,0.15);display:flex;align-items:center;justify-content:center;">
              <i class="bi bi-hourglass-split" style="color:#f59e0b;font-size:0.8rem;"></i>
            </div>
            <div>
              <div style="font-size:0.82rem;font-weight:600;color:var(--text-primary);">Insentif Diproses</div>
              <div style="font-size:0.68rem;color:var(--text-muted);">Sedang dalam pencairan</div>
            </div>
          </div>
          <div style="font-family:'Space Grotesk';font-weight:800;font-size:0.85rem;color:#f59e0b;"><?= formatRupiah($incProcessing) ?></div>
        </div>

        <!-- Divider -->
        <div style="border-top:1px solid var(--border-glass);margin-bottom:0.9rem;"></div>

        <!-- Dompet Utama -->
        <div style="display:flex;align-items:center;justify-content:space-between;">
          <div style="display:flex;align-items:center;gap:0.6rem;">
            <div style="width:32px;height:32px;border-radius:9px;background:linear-gradient(135deg,rgba(108,99,255,0.3),rgba(0,212,255,0.2));display:flex;align-items:center;justify-content:center;">
              <i class="bi bi-wallet2" style="color:#a78bfa;font-size:0.8rem;"></i>
            </div>
            <div style="font-size:0.82rem;font-weight:600;color:var(--text-primary);">Dompet Utama</div>
          </div>
          <div style="font-family:'Space Grotesk';font-weight:800;font-size:0.95rem;color:var(--text-primary);"><?= formatRupiah($wallet['balance'] ?? 0) ?></div>
        </div>
      </div>
    </div>

    <!-- Right: Referrals + Insentif Breakdown -->
    <div class="col-lg-6 d-flex flex-column gap-4">

      <!-- Daftar Nama Direct Referral -->
      <div class="animate-on-scroll" style="background:var(--bg-card);border:1px solid var(--border-glass);border-radius:20px;padding:1.5rem 1.75rem;flex:1;min-height:0;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
          <div style="display:flex;align-items:center;gap:0.6rem;">
            <div style="width:32px;height:32px;border-radius:9px;background:linear-gradient(135deg,#10b981,#00d4ff);display:flex;align-items:center;justify-content:center;">
              <i class="bi bi-people-fill" style="color:#fff;font-size:0.8rem;"></i>
            </div>
            <div style="font-weight:700;font-size:0.88rem;color:var(--text-primary);">Daftar Direct Referral</div>
          </div>
          <span style="background:rgba(108,99,255,0.15);color:#a78bfa;border-radius:20px;padding:2px 10px;font-size:0.72rem;font-weight:700;"><?= count($allReferrals) ?> orang</span>
        </div>
        <?php if ($allReferrals): ?>
        <div style="max-height:180px;overflow-y:auto;display:flex;flex-direction:column;gap:0.45rem;padding-right:4px;">
          <?php foreach ($allReferrals as $i => $rf): ?>
          <div style="display:flex;align-items:center;gap:0.6rem;padding:0.4rem 0.5rem;border-radius:10px;background:rgba(255,255,255,0.03);">
            <span style="width:20px;text-align:right;font-size:0.7rem;color:var(--text-muted);font-weight:700;flex-shrink:0;"><?= $i + 1 ?>.</span>
            <?php if (!empty($rf['avatar']) && file_exists(__DIR__ . '/media/avatars/' . $rf['avatar'])): ?>
            <img src="<?= BASE_URL ?>/media/avatars/<?= htmlspecialchars($rf['avatar']) ?>" alt="<?= htmlspecialchars($rf['name']) ?>" style="width:26px;height:26px;border-radius:50%;object-fit:cover;flex-shrink:0;">
            <?php else: ?>
            <div style="width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,#6c63ff,#00d4ff);display:flex;align-items:center;justify-content:center;font-size:0.62rem;font-weight:800;color:#fff;flex-shrink:0;"><?= mb_strtoupper(mb_substr($rf['name'], 0, 1)) ?></div>
            <?php endif; ?>
            <div style="flex:1;min-width:0;">
              <div style="font-size:0.78rem;font-weight:600;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($rf['name']) ?></div>
              <?php if (!empty($rf['member_code'])): ?>
              <div style="font-size:0.65rem;color:var(--text-muted);"><?= htmlspecialchars($rf['member_code']) ?></div>
              <?php endif; ?>
            </div>
            <div style="font-size:0.65rem;color:var(--text-muted);white-space:nowrap;flex-shrink:0;"><?= date('d M Y', strtotime($rf['created_at'])) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div style="text-align:center;padding:1.5rem 1rem;color:var(--text-muted);">
          <i class="bi bi-people" style="font-size:2rem;display:block;margin-bottom:0.5rem;opacity:0.5;"></i>
          <span style="font-size:0.8rem;">Belum ada referral. <a href="#" onclick="copyRefLink();return false;" style="color:var(--primary-light);">Bagikan link</a></span>
        </div>
        <?php endif; ?>
      </div>

      <!-- Insentif Breakdown -->
      <div class="animate-on-scroll" style="background:linear-gradient(135deg,rgba(168,85,247,0.12),rgba(108,99,255,0.06));border:1px solid rgba(168,85,247,0.25);border-radius:20px;padding:1.5rem 1.75rem;">
        <div style="font-weight:700;font-size:0.85rem;text-transform:uppercase;letter-spacing:0.07em;color:var(--text-muted);margin-bottom:1rem;">Current Insentif</div>
        <div style="display:flex;flex-direction:column;gap:0.85rem;">
          <!-- Insentif Berbagi -->
          <div style="display:flex;align-items:center;justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:0.65rem;">
              <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#a855f7,#6c63ff);display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-share-fill" style="color:#fff;font-size:0.85rem;"></i>
              </div>
              <div>
                <div style="font-size:0.82rem;font-weight:700;color:var(--text-primary);">Insentif Berbagi</div>
                <div style="font-size:0.67rem;color:var(--text-muted);">Dari transfer P2P masuk</div>
              </div>
            </div>
            <div style="font-family:'Space Grotesk';font-weight:800;font-size:1rem;color:#a855f7;"><?= formatRupiah($insentifBerbagi) ?></div>
          </div>
          <!-- Insentif Royalty -->
          <div style="display:flex;align-items:center;justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:0.65rem;">
              <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#f72585,#6c63ff);display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-trophy-fill" style="color:#fff;font-size:0.85rem;"></i>
              </div>
              <div>
                <div style="font-size:0.82rem;font-weight:700;color:var(--text-primary);">Insentif Royalty</div>
                <div style="font-size:0.67rem;color:var(--text-muted);">Dari jaringan &amp; pencapaian</div>
              </div>
            </div>
            <div style="font-family:'Space Grotesk';font-weight:800;font-size:1rem;color:#f72585;"><?= formatRupiah($insentifRoyalty) ?></div>
          </div>
        </div>
      </div>

    </div>
  </div>

  <!-- ── Charts ────────────────────────────────────────────────── -->
  <div class="row g-4 mb-4">
    <div class="col-12">
      <div class="glass-table-wrapper p-4">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
          <h2 style="font-size:1rem;font-weight:700;margin:0;">Pendapatan 7 Hari Terakhir</h2>
          <span style="font-size:0.75rem;color:var(--text-muted);">
            Total: <?= formatRupiah(array_sum($weekValues)) ?>
          </span>
        </div>
        <canvas id="revenueChart" height="240" style="max-height:240px;"></canvas>
      </div>
    </div>
  </div>
  <div class="glass-table-wrapper animate-on-scroll" style="margin-bottom:2rem;">
    <div class="d-flex justify-content-between align-items-center p-4 pb-0 flex-wrap gap-2">
      <h2 style="font-size:1rem;font-weight:700;margin:0;">Transaksi Terbaru</h2>
      <div class="d-flex gap-2 align-items-center flex-wrap">
        <div style="position:relative;">
          <i class="bi bi-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:0.8rem;"></i>
          <input type="text" placeholder="Cari transaksi..." id="txSearch"
            style="background:var(--bg-card);border:1px solid var(--border-glass);border-radius:10px;color:var(--text-primary);padding:6px 12px 6px 32px;font-size:0.8rem;outline:none;width:200px;"/>
        </div>
        <a href="#" class="btn btn-sm"
          style="background:var(--bg-card);border:1px solid var(--border-glass);color:var(--text-muted);border-radius:10px;font-size:0.75rem;padding:6px 14px;">
          <i class="bi bi-download me-1"></i>Export
        </a>
      </div>
    </div>
    <?php if ($transactions): ?>
    <div style="padding:1rem 0 0;" class="table-responsive">
      <table class="glass-table" id="txTable">
        <thead>
          <tr>
            <th>No. Transaksi</th>
            <th>Tanggal</th>
            <th>Metode</th>
            <th>Penerima</th>
            <th>Jumlah</th>
            <th>Fee</th>
            <th>Status</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($transactions as $tx): ?>
          <tr>
            <td><code style="color:var(--primary-light);background:rgba(108,99,255,0.1);padding:2px 8px;border-radius:6px;font-size:0.75rem;"><?= htmlspecialchars($tx['tx_id']) ?></code></td>
            <td style="white-space:nowrap;"><?= date('d M Y', strtotime($tx['created_at'])) ?></td>
            <td>
              <span style="display:flex;align-items:center;gap:6px;">
                <i class="<?= htmlspecialchars($tx['icon_class'] ?? 'bi bi-credit-card') ?>"
                   style="color:<?= htmlspecialchars($tx['color'] ?? '#6c63ff') ?>;"></i>
                <?= htmlspecialchars($tx['method_name'] ?? 'Manual') ?>
              </span>
            </td>
            <td style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($tx['recipient'] ?? '-') ?></td>
            <td style="font-family:'Space Grotesk';font-weight:700;color:var(--text-primary);"><?= formatRupiah($tx['amount']) ?></td>
            <td style="color:var(--text-muted);font-size:0.8rem;"><?= formatRupiah($tx['fee']) ?></td>
            <td><span class="tx-badge tx-<?= htmlspecialchars($tx['status']) ?>"><?= ucfirst($tx['status']) ?></span></td>
            <td>
              <button class="btn btn-sm"
                style="background:transparent;border:1px solid var(--border-glass);color:var(--text-muted);border-radius:8px;font-size:0.7rem;padding:3px 10px;"
                onclick="showToast('Detail Transaksi','<?= htmlspecialchars($tx['tx_id']) ?> — <?= formatRupiah($tx['total']) ?>','info')">
                Detail
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:4rem 2rem;color:var(--text-muted);">
      <i class="bi bi-inbox" style="font-size:3rem;display:block;margin-bottom:0.75rem;"></i>
      Belum ada transaksi. <a href="payment.php" style="color:var(--primary-light);">Buat pembayaran pertama</a>
    </div>
    <?php endif; ?>
  </div>

  <!-- ── Quick Actions ─────────────────────────────────────────── -->

  <!-- ── Referral Link Card ─────────────────────────────────────── -->
  <?php if ($referralLink): ?>
  <div class="animate-on-scroll" style="margin-top:2rem;">
    <div style="background:linear-gradient(135deg,rgba(108,99,255,.18),rgba(0,212,255,.08));border:1px solid rgba(108,99,255,.3);border-radius:20px;padding:1.75rem 2rem;display:flex;flex-wrap:wrap;gap:1.5rem;align-items:center;">
      <div style="flex:1;min-width:220px;">
        <div style="display:flex;align-items:center;gap:.6rem;margin-bottom:.35rem;">
          <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#6c63ff,#00d4ff);display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-share-fill" style="color:#fff;font-size:.95rem;"></i>
          </div>
          <div>
            <div style="font-weight:800;font-size:1rem;">Link Referral Saya</div>
            <div style="font-size:.72rem;color:var(--text-muted);">Bagikan & ajak orang bergabung ke SolusiMu</div>
          </div>
        </div>

        <!-- Link display -->
        <div style="display:flex;align-items:center;gap:.5rem;background:rgba(255,255,255,.05);border:1px solid rgba(108,99,255,.25);border-radius:12px;padding:.55rem .85rem;margin-top:.85rem;flex-wrap:wrap;">
          <i class="bi bi-link-45deg" style="color:#6c63ff;font-size:1rem;flex-shrink:0;"></i>
          <span id="refLinkText" style="font-size:.8rem;color:var(--text-primary);font-family:monospace;flex:1;word-break:break-all;"><?= htmlspecialchars($referralLink) ?></span>
          <button onclick="copyRefLink()" style="background:linear-gradient(135deg,#6c63ff,#00d4ff);color:#fff;border:none;border-radius:8px;padding:.3rem .85rem;font-size:.75rem;font-weight:700;cursor:pointer;flex-shrink:0;white-space:nowrap;">
            <i class="bi bi-clipboard me-1"></i>Salin
          </button>
        </div>

        <!-- Share buttons -->
        <div style="display:flex;gap:.5rem;margin-top:.75rem;flex-wrap:wrap;">
          <a href="https://wa.me/?text=<?= urlencode('Hei! Join SolusiMu dan mulai terima pembayaran digital. Daftar pakai link referral saya: ' . $referralLink) ?>"
             target="_blank" rel="noopener"
             style="display:inline-flex;align-items:center;gap:5px;background:rgba(37,211,102,.12);border:1px solid rgba(37,211,102,.25);color:#25d366;border-radius:9px;padding:.3rem .8rem;font-size:.75rem;font-weight:700;text-decoration:none;">
            <i class="bi bi-whatsapp"></i> WhatsApp
          </a>
          <a href="https://t.me/share/url?url=<?= urlencode($referralLink) ?>&text=<?= urlencode('Daftar SolusiMu pakai link referral saya!') ?>"
             target="_blank" rel="noopener"
             style="display:inline-flex;align-items:center;gap:5px;background:rgba(0,136,204,.1);border:1px solid rgba(0,136,204,.25);color:#0088cc;border-radius:9px;padding:.3rem .8rem;font-size:.75rem;font-weight:700;text-decoration:none;">
            <i class="bi bi-telegram"></i> Telegram
          </a>
          <button onclick="shareNative()" style="display:inline-flex;align-items:center;gap:5px;background:rgba(255,255,255,.06);border:1px solid var(--border-glass);color:var(--text-secondary);border-radius:9px;padding:.3rem .8rem;font-size:.75rem;font-weight:700;cursor:pointer;">
            <i class="bi bi-box-arrow-up"></i> Bagikan
          </button>
        </div>

        <div style="margin-top:.75rem;font-size:.72rem;color:var(--text-muted);">
          Kode unik Anda: <code style="color:#a78bfa;background:rgba(167,139,250,.08);padding:1px 7px;border-radius:5px;"><?= htmlspecialchars($referralCode) ?></code>
        </div>
      </div>

      <!-- Stats -->
      <div style="display:flex;flex-direction:column;gap:.75rem;min-width:140px;">
        <div style="background:rgba(255,255,255,.04);border:1px solid rgba(108,99,255,.15);border-radius:14px;padding:.85rem 1.1rem;text-align:center;">
          <div style="font-family:'Space Grotesk';font-size:2rem;font-weight:800;color:#a78bfa;line-height:1;"><?= $referralTotal ?></div>
          <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin-top:.2rem;">Total Referral</div>
        </div>
        <?php if ($referralRecent): ?>
        <div style="background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.06);border-radius:14px;padding:.75rem .9rem;">
          <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin-bottom:.5rem;">Terbaru</div>
          <?php foreach ($referralRecent as $rf): ?>
          <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.4rem;">
            <div style="width:22px;height:22px;border-radius:50%;background:linear-gradient(135deg,#6c63ff,#00d4ff);display:flex;align-items:center;justify-content:center;font-size:.6rem;font-weight:800;color:#fff;flex-shrink:0;"><?= htmlspecialchars($rf['avatar'] ?? mb_substr($rf['name'],0,1)) ?></div>
            <div>
              <div style="font-size:.73rem;font-weight:600;color:var(--text-primary);"><?= htmlspecialchars(mb_substr($rf['name'],0,14)) ?></div>
              <div style="font-size:.63rem;color:var(--text-muted);"><?= date('d M', strtotime($rf['created_at'])) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div style="margin-top:2rem;padding-top:1rem;border-top:1px solid var(--border-glass);text-align:center;color:var(--text-muted);font-size:0.75rem;">
    SolusiMu Dashboard v<?= SITE_VERSION ?> &nbsp;·&nbsp; <?= date('d M Y H:i') ?> WIB
  </div>
  </div><!-- /.content-body -->
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/main.js"></script>
<script>
// ── PHP data to JS ─────────────────────────────────────────────
const weekLabels = <?= json_encode($weekLabels) ?>;
const weekValues = <?= json_encode($weekValues) ?>;
const methodData = <?= json_encode(array_values($methodBreakdown)) ?>;

// ── Revenue Chart ──────────────────────────────────────────────
const revCtx = document.getElementById('revenueChart')?.getContext('2d');
if (revCtx) {
  const revGrad = revCtx.createLinearGradient(0, 0, 0, 240);
  revGrad.addColorStop(0, 'rgba(108,99,255,0.35)');
  revGrad.addColorStop(1, 'rgba(108,99,255,0)');
  new Chart(revCtx, {
    type: 'line',
    data: {
      labels: weekLabels,
      datasets: [{
        label: 'Pendapatan',
        data: weekValues,
        borderColor: '#6c63ff',
        backgroundColor: revGrad,
        borderWidth: 2.5,
        fill: true,
        tension: 0.4,
        pointBackgroundColor: '#6c63ff',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        pointRadius: 5,
        pointHoverRadius: 8,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { intersect: false, mode: 'index' },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: 'rgba(15,15,30,0.95)',
          borderColor: 'rgba(108,99,255,0.3)',
          borderWidth: 1,
          titleColor: '#f1f5f9',
          bodyColor: '#94a3b8',
          padding: 12,
          callbacks: { label: ctx => ' Rp ' + ctx.raw.toLocaleString('id-ID') }
        }
      },
      scales: {
        x: { grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { color: '#64748b', font: { size: 11 } } },
        y: {
          grid: { color: 'rgba(255,255,255,0.04)' },
          ticks: { color: '#64748b', font: { size: 11 }, callback: v => 'Rp ' + (v/1000000).toFixed(1) + 'jt' }
        }
      }
    }
  });
}

// ── Method Donut Chart ─────────────────────────────────────────
if (methodData.length > 0) {
  const colors = ['#6c63ff','#00d4ff','#10b981','#f72585','#f59e0b'];
  const mCtx = document.getElementById('methodChart')?.getContext('2d');
  if (mCtx) {
    new Chart(mCtx, {
      type: 'doughnut',
      data: {
        labels: methodData.map(m => m.name),
        datasets: [{
          data: methodData.map(m => m.cnt),
          backgroundColor: colors.map(c => c + '99'),
          borderColor: colors,
          borderWidth: 2,
          hoverOffset: 8
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '68%',
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: 'rgba(15,15,30,0.95)',
            borderColor: 'rgba(108,99,255,0.3)',
            borderWidth: 1,
          }
        }
      }
    });
    const legend = document.getElementById('methodLegend');
    if (legend) {
      methodData.forEach((m, i) => {
        legend.innerHTML += `
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.5rem;">
            <div style="display:flex;align-items:center;gap:8px;">
              <div style="width:10px;height:10px;border-radius:50%;background:${colors[i]};"></div>
              <span style="font-size:0.78rem;color:var(--text-secondary);">${m.name}</span>
            </div>
            <span style="font-size:0.78rem;font-weight:700;color:var(--text-primary);">${m.cnt} tx</span>
          </div>`;
      });
    }
  }
}

// ── Search transactions ────────────────────────────────────────
document.getElementById('txSearch')?.addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('#txTable tbody tr').forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
});

// ── Sidebar mobile ─────────────────────────────────────────────
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

// ── Sidebar submenu toggle ─────────────────────────────────────
function toggleSidebarSubmenu(el) {
  const li      = el.closest('.sidebar-has-submenu');
  const submenu = li.querySelector('.sidebar-submenu');
  const isOpen  = submenu.classList.contains('open');
  document.querySelectorAll('.sidebar-submenu.open').forEach(m => {
    m.classList.remove('open');
    m.closest('.sidebar-has-submenu').querySelector('.sidebar-link-toggle').classList.remove('open');
  });
  if (!isOpen) {
    submenu.classList.add('open');
    el.classList.add('open');
  }
}

// ── Referral link helpers ──────────────────────────────────────
function copyRefLink() {
  const link = document.getElementById('refLinkText')?.textContent?.trim();
  if (!link) return;
  navigator.clipboard.writeText(link).then(() => {
    if (typeof showToast === 'function') showToast('Tersalin!', 'Link referral berhasil disalin ke clipboard.', 'success');
  }).catch(() => {
    const ta = document.createElement('textarea');
    ta.value = link; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
    if (typeof showToast === 'function') showToast('Tersalin!', 'Link referral berhasil disalin.', 'success');
  });
}
function shareNative() {
  const link = document.getElementById('refLinkText')?.textContent?.trim();
  if (navigator.share && link) {
    navigator.share({ title: 'SolusiMu – Referral Saya', text: 'Daftar SolusiMu pakai link referral saya!', url: link });
  } else { copyRefLink(); }
}
</script>
</body>
</html>
