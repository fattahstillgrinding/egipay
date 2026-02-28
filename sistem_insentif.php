<?php
require_once __DIR__ . '/includes/config.php';
requireLogin();

$user   = getCurrentUser();
$userId = (int)$_SESSION['user_id'];
$wallet = getUserWallet($userId);
$flash  = getFlash();

$notifCount    = getUnreadNotifCount($userId);
$notifications = dbFetchAll(
    'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5',
    [$userId]
);
dbExecute('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [$userId]);

$generations = [
    ['gen' => 'G-1',  'multiplier' => '10',                'total' => '10.000'],
    ['gen' => 'G-2',  'multiplier' => '100',               'total' => '100.000'],
    ['gen' => 'G-3',  'multiplier' => '1.000',             'total' => '1.000.000'],
    ['gen' => 'G-4',  'multiplier' => '10.000',            'total' => '10.000.000'],
    ['gen' => 'G-5',  'multiplier' => '100.000',           'total' => '100.000.000'],
    ['gen' => 'G-6',  'multiplier' => '1.000.000',         'total' => '1.000.000.000'],
    ['gen' => 'G-7',  'multiplier' => '10.000.000',        'total' => '10.000.000.000'],
    ['gen' => 'G-8',  'multiplier' => '100.000.000',       'total' => '100.000.000.000'],
    ['gen' => 'G-9',  'multiplier' => '1.000.000.000',     'total' => '1.000.000.000.000'],
    ['gen' => 'G-10', 'multiplier' => '10.000.000.000',    'total' => '10.000.000.000.000'],
];
$colors = [
    ['bg'=>'#1e3a5f','border'=>'#2563eb','badge'=>'#3b82f6'],
    ['bg'=>'#1a3550','border'=>'#1d4ed8','badge'=>'#2563eb'],
    ['bg'=>'#162f47','border'=>'#1e40af','badge'=>'#1d4ed8'],
    ['bg'=>'#12293d','border'=>'#1e3a8a','badge'=>'#1e40af'],
    ['bg'=>'#0f2337','border'=>'#1e3482','badge'=>'#1e3a8a'],
    ['bg'=>'#0c1e30','border'=>'#1e2d6e','badge'=>'#1e3482'],
    ['bg'=>'#091828','border'=>'#1a255a','badge'=>'#1e2d6e'],
    ['bg'=>'#071422','border'=>'#161e46','badge'=>'#1a255a'],
    ['bg'=>'#05101a','border'=>'#121832','badge'=>'#161e46'],
    ['bg'=>'#030c14','border'=>'#0e121e','badge'=>'#121832'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Sistem Insentif Edukasi – SolusiMu</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link href="css/style.css" rel="stylesheet"/>
  <style>
    .gen-row {
      display: flex;
      align-items: center;
      gap: 1rem;
      padding: 0.85rem 1.25rem;
      border-radius: 14px;
      border: 1px solid rgba(255,255,255,0.07);
      background: rgba(255,255,255,0.025);
      transition: transform .2s, box-shadow .2s;
      position: relative;
    }
    .gen-row:hover {
      transform: translateX(4px);
      box-shadow: 0 4px 24px rgba(37,99,235,0.18);
      border-color: rgba(59,130,246,0.35);
    }
    .gen-badge {
      min-width: 64px;
      height: 36px;
      border-radius: 10px;
      background: linear-gradient(135deg,#1e3a8a,#2563eb);
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: 'Space Grotesk', sans-serif;
      font-weight: 800;
      font-size: 0.88rem;
      color: #fff;
      letter-spacing: .04em;
      flex-shrink: 0;
      box-shadow: 0 2px 10px rgba(37,99,235,0.3);
    }
    .gen-connector {
      width: 2px;
      height: 12px;
      background: linear-gradient(180deg, rgba(37,99,235,0.5), rgba(37,99,235,0.1));
      margin: 0 auto;
    }
    .you-badge {
      width: 80px;
      height: 44px;
      border-radius: 12px;
      background: linear-gradient(135deg,#1e3a8a,#1d4ed8);
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: 'Space Grotesk', sans-serif;
      font-weight: 800;
      font-size: 1rem;
      color: #fff;
      letter-spacing: .05em;
      margin: 0 auto 4px;
      box-shadow: 0 4px 16px rgba(37,99,235,0.4);
    }
    .formula-text {
      font-family: 'Space Grotesk', sans-serif;
      font-size: 0.85rem;
      color: #94a3b8;
      flex: 1;
    }
    .formula-result {
      font-family: 'Space Grotesk', sans-serif;
      font-weight: 800;
      font-size: 0.92rem;
      color: #e2e8f0;
      white-space: nowrap;
    }
    .formula-mult {
      color: #60a5fa;
      font-weight: 700;
    }
    .pola-card {
      background: linear-gradient(135deg, rgba(30,58,95,0.3), rgba(15,35,60,0.2));
      border: 1px solid rgba(37,99,235,0.2);
      border-radius: 16px;
      padding: 1.5rem;
    }
    .grand-total-card {
      background: linear-gradient(135deg, rgba(37,99,235,0.15), rgba(29,78,216,0.08));
      border: 1px solid rgba(37,99,235,0.35);
      border-radius: 20px;
      padding: 1.75rem 2rem;
    }
  </style>
</head>
<body>
<div class="page-loader" id="pageLoader">
  <div class="loader-ring"></div>
</div>

<div class="toast-container" id="toastContainer"></div>

<?php if ($flash): ?>
<div id="flashMessage"
  data-type="<?= $flash['type'] ?>"
  data-title="<?= htmlspecialchars($flash['title']) ?>"
  data-message="<?= htmlspecialchars($flash['message']) ?>"
  style="display:none"></div>
<?php endif; ?>

<div id="sidebarOverlay"
  style="position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:999;display:none;backdrop-filter:blur(4px);"
  class="d-lg-none"></div>

<!-- ====== SIDEBAR ====== -->
<aside class="sidebar" id="mainSidebar">
  <div class="sidebar-logo">
    <svg width="36" height="36" viewBox="0 0 42 42" fill="none">
      <defs><linearGradient id="sLg" x1="0" y1="0" x2="42" y2="42"><stop stop-color="#6c63ff"/><stop offset="1" stop-color="#00d4ff"/></linearGradient></defs>
      <rect width="42" height="42" rx="12" fill="url(#sLg)"/>
      <path d="M12 14h10a6 6 0 010 12H12V14zm0 6h8a2 2 0 000-6" fill="white" opacity="0.95"/>
      <circle cx="30" cy="28" r="3" fill="white" opacity="0.8"/>
    </svg>
    <span class="brand-text" style="font-size:1.2rem;">SolusiMu</span>
  </div>

  <ul class="sidebar-menu">
    <li class="sidebar-section-title">Utama</li>
    <li><a href="dashboard.php" class="sidebar-link"><span class="icon"><i class="bi bi-grid-1x2-fill"></i></span>Dashboard</a></li>
    <li><a href="payment.php" class="sidebar-link"><span class="icon"><i class="bi bi-send-fill"></i></span>Kirim Pembayaran</a></li>
    <li><a href="#" class="sidebar-link"><span class="icon"><i class="bi bi-arrow-left-right"></i></span>Transaksi</a></li>
    <li class="sidebar-has-submenu">
      <a href="#" class="sidebar-link sidebar-link-toggle" onclick="toggleSidebarSubmenu(this);return false;">
        <span class="icon"><i class="bi bi-wallet2"></i></span>
        Dompet
        <i class="bi bi-chevron-down sidebar-chevron ms-auto"></i>
      </a>
      <ul class="sidebar-submenu">
        <li><a href="#" class="sidebar-sublink"><i class="bi bi-file-earmark-text me-2"></i>Wallet Statement</a></li>
        <li><a href="withdrawal.php" class="sidebar-sublink"><i class="bi bi-box-arrow-up me-2"></i>Penarikan Dana</a></li>
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
    <?php if (isAdmin()): ?>
    <li class="sidebar-section-title">Administrasi</li>
    <li><a href="admin/index.php" class="sidebar-link" style="color:#f72585;"><span class="icon"><i class="bi bi-shield-lock-fill"></i></span>Admin Panel</a></li>
    <?php endif; ?>
    <?php if (isSuperAdmin()): ?>
    <li><a href="superadmin/index.php" class="sidebar-link" style="color:#c084fc;"><span class="icon"><i class="bi bi-shield-shaded"></i></span>Super Admin Panel</a></li>
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
      <button class="btn d-lg-none" id="sidebarToggle"
        style="background:var(--bg-card);border:1px solid var(--border-glass);border-radius:10px;color:var(--text-primary);padding:8px 12px;">
        <i class="bi bi-list fs-5"></i>
      </button>
      <div>
        <h1 class="dash-title">Sistem Insentif</h1>
        <p class="dash-subtitle">Pola Pengembangan Sistem Edukasi Digital Payment</p>
      </div>
    </div>
    <div class="d-flex align-items-center gap-2">
      <div class="dropdown">
        <button class="btn dropdown-toggle" id="notifBtn"
          data-bs-toggle="dropdown" aria-expanded="false"
          style="background:var(--bg-card);border:1px solid var(--border-glass);border-radius:12px;color:var(--text-primary);padding:8px 14px;position:relative;">
          <i class="bi bi-bell"></i>
          <?php if ($notifCount > 0): ?>
          <span style="position:absolute;top:5px;right:8px;width:18px;height:18px;background:#f72585;border-radius:50%;border:2px solid var(--bg-dark);font-size:0.6rem;font-weight:700;display:flex;align-items:center;justify-content:center;"><?= $notifCount ?></span>
          <?php endif; ?>
        </button>
        <div class="dropdown-menu dropdown-menu-end p-0"
          style="background:var(--bg-card);border:1px solid var(--border-glass);border-radius:16px;min-width:300px;box-shadow:0 20px 60px rgba(0,0,0,0.5);overflow:hidden;">
          <div style="padding:1rem 1.25rem 0.75rem;border-bottom:1px solid var(--border-glass);">
            <span style="font-weight:700;font-size:0.9rem;">Notifikasi</span>
          </div>
          <?php if ($notifications): ?>
          <?php foreach ($notifications as $notif): ?>
          <div style="padding:0.75rem 1.25rem;border-bottom:1px solid rgba(255,255,255,0.04);font-size:0.82rem;color:var(--text-secondary);">
            <div style="font-weight:600;color:var(--text-primary);margin-bottom:2px;"><?= htmlspecialchars($notif['title']) ?></div>
            <?= htmlspecialchars(mb_substr($notif['message'], 0, 60)) ?>...
          </div>
          <?php endforeach; ?>
          <?php else: ?>
          <div style="padding:1.5rem;text-align:center;color:var(--text-muted);font-size:0.82rem;">Tidak ada notifikasi</div>
          <?php endif; ?>
        </div>
      </div>
      <a href="dashboard.php" class="btn"
        style="background:var(--bg-card);border:1px solid var(--border-glass);border-radius:12px;color:var(--text-muted);padding:8px 16px;font-size:0.82rem;">
        <i class="bi bi-arrow-left me-1"></i>Kembali
      </a>
    </div>
  </div>

  <!-- ── Hero Banner ─────────────────────────────────────────── -->
  <div class="animate-on-scroll" style="background:linear-gradient(135deg,rgba(30,58,95,0.5),rgba(15,23,42,0.6));border:1px solid rgba(37,99,235,0.35);border-radius:24px;padding:2rem 2.5rem;margin-bottom:2rem;position:relative;overflow:hidden;">
    <!-- Decorative circles -->
    <div style="position:absolute;top:-40px;right:-40px;width:200px;height:200px;background:radial-gradient(circle,rgba(37,99,235,0.15),transparent 70%);border-radius:50%;pointer-events:none;"></div>
    <div style="position:absolute;bottom:-30px;left:10%;width:150px;height:150px;background:radial-gradient(circle,rgba(29,78,216,0.1),transparent 70%);border-radius:50%;pointer-events:none;"></div>
    <div class="row align-items-center g-3">
      <div class="col-md-8">
        <div style="display:flex;align-items:center;gap:1rem;margin-bottom:0.75rem;">
          <div style="width:52px;height:52px;border-radius:15px;background:linear-gradient(135deg,#1e3a8a,#2563eb);display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 6px 20px rgba(37,99,235,0.4);">
            <i class="bi bi-diagram-3-fill" style="color:#fff;font-size:1.4rem;"></i>
          </div>
          <div>
            <div style="font-family:'Space Grotesk';font-weight:900;font-size:1.5rem;color:#e2e8f0;line-height:1.2;">Sistem Insentif Edukasi</div>
            <div style="font-size:0.8rem;color:#60a5fa;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;">10 Bagian Generasi</div>
          </div>
        </div>
        <p style="color:#94a3b8;font-size:0.88rem;margin:0;line-height:1.6;">
          Setiap anggota yang bergabung melalui referral Anda akan menghasilkan insentif bertingkat hingga <strong style="color:#60a5fa;">10 generasi</strong>. 
          Setiap anggota baru memberikan kontribusi <strong style="color:#60a5fa;">Rp 1.000</strong> per orang, 
          dan jaringan Anda berkembang secara eksponensial dengan faktor pengali <strong style="color:#60a5fa;">×10</strong> per generasi.
        </p>
      </div>
      <div class="col-md-4 text-md-end">
        <div style="display:inline-block;background:rgba(37,99,235,0.12);border:1px solid rgba(37,99,235,0.25);border-radius:16px;padding:1rem 1.5rem;text-align:center;">
          <div style="font-size:0.7rem;color:#64748b;text-transform:uppercase;letter-spacing:0.08em;margin-bottom:0.3rem;">Potensi Total</div>
          <div style="font-family:'Space Grotesk';font-weight:900;font-size:1.3rem;background:linear-gradient(135deg,#3b82f6,#60a5fa);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">Rp 10 Triliun+</div>
          <div style="font-size:0.68rem;color:#64748b;margin-top:0.2rem;">akumulasi 10 generasi</div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Main Content ────────────────────────────────────────── -->
  <div class="row g-4">

    <!-- Left: Diagram Visual -->
    <div class="col-lg-7">
      <div class="glass-table-wrapper p-4">
        <div style="font-weight:700;font-size:0.88rem;text-transform:uppercase;letter-spacing:0.07em;color:var(--text-muted);margin-bottom:1.5rem;display:flex;align-items:center;gap:0.5rem;">
          <i class="bi bi-diagram-3" style="color:#3b82f6;"></i>
          Struktur Generasi
        </div>

        <!-- ANDA node -->
        <div style="text-align:center;margin-bottom:2px;">
          <div class="you-badge">ANDA</div>
          <div style="font-size:0.68rem;color:#64748b;margin-bottom:4px;">Titik Mulai</div>
        </div>
        <div class="gen-connector" style="height:16px;"></div>

        <!-- Generasi rows -->
        <?php foreach ($generations as $idx => $gen):
          $isLast = ($idx === count($generations) - 1);
        ?>
        <div class="gen-row" style="margin-bottom:<?= $isLast ? '0' : '4px' ?>;">
          <!-- Arrow connector on left -->
          <div style="position:absolute;left:-1px;top:50%;transform:translateY(-50%);width:4px;height:60%;background:linear-gradient(180deg,rgba(37,99,235,0.5),rgba(37,99,235,0.1));border-radius:2px;"></div>

          <div class="gen-badge"><?= $gen['gen'] ?></div>

          <div style="display:flex;align-items:center;gap:0.4rem;flex:1;">
            <i class="bi bi-arrow-return-right" style="color:#3b82f6;font-size:0.75rem;flex-shrink:0;"></i>
            <span class="formula-text">
              <span class="formula-mult"><?= $gen['multiplier'] ?></span>
              <span style="color:#64748b;margin:0 4px;">× Rp 1.000 =</span>
            </span>
          </div>

          <div class="formula-result">Rp <?= $gen['total'] ?></div>
        </div>
        <?php if (!$isLast): ?>
        <div class="gen-connector"></div>
        <?php endif; ?>
        <?php endforeach; ?>

        <!-- Grand Total -->
        <div class="grand-total-card" style="margin-top:1.5rem;">
          <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;">
            <div style="display:flex;align-items:center;gap:0.75rem;">
              <div style="width:40px;height:40px;border-radius:11px;background:linear-gradient(135deg,#1e3a8a,#2563eb);display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-trophy-fill" style="color:#fff;font-size:1rem;"></i>
              </div>
              <div>
                <div style="font-weight:800;font-size:0.9rem;color:var(--text-primary);">Total Akumulasi 10 Generasi</div>
                <div style="font-size:0.7rem;color:var(--text-muted);">Jika jaringan berkembang penuh tiap generasi</div>
              </div>
            </div>
            <div style="font-family:'Space Grotesk';font-weight:900;font-size:1.2rem;background:linear-gradient(135deg,#3b82f6,#60a5fa);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">
              Rp 11.111.111.110.000
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Right: Info Cards -->
    <div class="col-lg-5 d-flex flex-column gap-4">

      <!-- Pola Pengembangan -->
      <div class="animate-on-scroll pola-card">
        <div style="display:flex;align-items:center;gap:0.65rem;margin-bottom:1.25rem;">
          <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#1e3a8a,#2563eb);display:flex;align-items:center;justify-content:center;">
            <i class="bi bi-layers-fill" style="color:#fff;font-size:0.9rem;"></i>
          </div>
          <div>
            <div style="font-weight:800;font-size:0.9rem;color:var(--text-primary);">Pola Pengembangan</div>
            <div style="font-size:0.7rem;color:var(--text-muted);">Sistem Edukasi Digital Payment</div>
          </div>
        </div>
        <div style="display:flex;flex-direction:column;gap:0.65rem;">
          <?php
          $polaItems = [
            ['bi bi-1-circle-fill','#3b82f6','Setiap anggota baru menghasilkan','Rp 1.000 insentif'],
            ['bi bi-arrow-repeat','#2563eb','Pengali jaringan naik','×10 per generasi'],
            ['bi bi-people-fill','#1d4ed8','Generasi ke-1 (Direct Referral)','10 orang × Rp 1.000'],
            ['bi bi-infinity','#1e40af','Berkembang hingga generasi ke-10','Potensi tak terbatas'],
          ];
          foreach ($polaItems as [$icon, $color, $label, $value]): ?>
          <div style="display:flex;align-items:flex-start;gap:0.65rem;padding:0.65rem 0.85rem;border-radius:11px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.05);">
            <i class="<?= $icon ?>" style="color:<?= $color ?>;font-size:1rem;flex-shrink:0;margin-top:1px;"></i>
            <div>
              <div style="font-size:0.78rem;color:var(--text-secondary);"><?= $label ?></div>
              <div style="font-size:0.8rem;font-weight:700;color:var(--text-primary);"><?= $value ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Ringkasan Cepat -->
      <div class="animate-on-scroll" style="background:var(--bg-card);border:1px solid var(--border-glass);border-radius:20px;padding:1.5rem;">
        <div style="font-weight:700;font-size:0.85rem;text-transform:uppercase;letter-spacing:0.07em;color:var(--text-muted);margin-bottom:1rem;">Ringkasan Per Generasi</div>
        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:0.6rem;">
          <?php foreach ($generations as $idx => $gen): ?>
          <div style="background:rgba(30,58,95,<?= 0.15 + $idx * 0.04 ?>);border:1px solid rgba(37,99,235,<?= 0.15 + $idx * 0.02 ?>);border-radius:12px;padding:0.65rem 0.8rem;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.2rem;">
              <span style="font-family:'Space Grotesk';font-weight:800;font-size:0.78rem;color:#60a5fa;"><?= $gen['gen'] ?></span>
              <span style="font-size:0.62rem;color:#64748b;"><?= $gen['multiplier'] ?> org</span>
            </div>
            <div style="font-family:'Space Grotesk';font-weight:700;font-size:0.8rem;color:#e2e8f0;">Rp <?= $gen['total'] ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- CTA -->
      <div class="animate-on-scroll" style="background:linear-gradient(135deg,rgba(37,99,235,0.2),rgba(29,78,216,0.1));border:1px solid rgba(37,99,235,0.3);border-radius:20px;padding:1.5rem;text-align:center;">
        <i class="bi bi-rocket-takeoff-fill" style="font-size:2rem;background:linear-gradient(135deg,#3b82f6,#60a5fa);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;display:block;margin-bottom:0.75rem;"></i>
        <div style="font-weight:800;font-size:0.95rem;color:var(--text-primary);margin-bottom:0.4rem;">Mulai Kembangkan Jaringan</div>
        <div style="font-size:0.78rem;color:var(--text-muted);margin-bottom:1.1rem;">Bagikan link referral Anda dan raih potensi insentif hingga 10 generasi</div>
        <a href="dashboard.php" class="btn w-100"
           style="background:linear-gradient(135deg,#1e3a8a,#2563eb);border:none;color:#fff;font-weight:700;font-size:0.85rem;border-radius:12px;padding:0.65rem;">
          <i class="bi bi-share-fill me-2"></i>Bagikan Link Referral
        </a>
      </div>

    </div>
  </div>

  <div style="margin-top:2rem;padding-top:1rem;border-top:1px solid var(--border-glass);text-align:center;color:var(--text-muted);font-size:0.75rem;">
    SolusiMu Dashboard v<?= SITE_VERSION ?> &nbsp;·&nbsp; <?= date('d M Y H:i') ?> WIB
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/main.js"></script>
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
</script>
</body>
</html>
