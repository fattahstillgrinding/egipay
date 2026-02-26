<?php
require_once __DIR__ . '/includes/config.php';
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>EgiPay – Gateway Pembayaran Digital Terpercaya di Indonesia</title>
  <meta name="description" content="EgiPay adalah platform payment gateway modern dengan transaksi instan, keamanan berlapis, dan biaya kompetitif untuk bisnis Anda." />

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <!-- Custom CSS -->
  <link href="css/style.css" rel="stylesheet" />
</head>
<body>

<!-- Page Loader -->
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

<!-- Particles -->
<canvas id="particles-canvas"></canvas>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<?php if ($flash): ?>
<div id="flashMessage" data-type="<?= $flash['type'] ?>" data-title="<?= htmlspecialchars($flash['title']) ?>" data-message="<?= htmlspecialchars($flash['message']) ?>" style="display:none"></div>
<?php endif; ?>

<div class="content-wrapper">

  <!-- ====== NAVBAR ====== -->
  <nav class="navbar navbar-expand-lg fixed-top">
    <div class="container">
      <a class="navbar-brand" href="index.php">
        <svg class="brand-logo" width="42" height="42" viewBox="0 0 42 42" fill="none">
          <defs><linearGradient id="navLg" x1="0" y1="0" x2="42" y2="42"><stop stop-color="#6c63ff"/><stop offset="1" stop-color="#00d4ff"/></linearGradient></defs>
          <rect width="42" height="42" rx="12" fill="url(#navLg)"/>
          <path d="M12 14h10a6 6 0 010 12H12V14zm0 6h8a2 2 0 000-6" fill="white" opacity="0.95"/>
          <circle cx="30" cy="28" r="3" fill="white" opacity="0.8"/>
        </svg>
        <span class="brand-text">EgiPay</span>
      </a>

      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
        <i class="bi bi-list text-white fs-5"></i>
      </button>

      <div class="collapse navbar-collapse" id="navbarMain">
        <ul class="navbar-nav mx-auto gap-1">
          <li class="nav-item"><a class="nav-link" href="#features">Fitur</a></li>
          <li class="nav-item"><a class="nav-link" href="#how-it-works">Cara Kerja</a></li>
          <li class="nav-item"><a class="nav-link" href="#pricing">Harga</a></li>
          <li class="nav-item"><a class="nav-link" href="docs.php">API Docs</a></li>
        </ul>

        <div class="d-flex gap-2 mt-3 mt-lg-0 align-items-center">
          <a href="login.php"    class="btn btn-outline-glow btn-sm px-4">Masuk</a>
          <a href="register.php" class="btn btn-primary-gradient btn-sm px-4">Daftar Sekarang</a>
        </div>
      </div>
    </div>
  </nav>

  <!-- ====== HERO ====== -->
  <section class="hero-section">
    <div class="hero-bg-orb hero-bg-orb-1"></div>
    <div class="hero-bg-orb hero-bg-orb-2"></div>
    <div class="hero-bg-orb hero-bg-orb-3"></div>

    <div class="container">
      <div class="row align-items-center">
        <div class="col-lg-6">
          <div class="hero-badge">
            <span class="dot"></span>
            Platform Pembayaran #1 di Indonesia
          </div>

          <h1 class="hero-title">
            Solusi <span class="gradient-text" id="typewriter"></span><span class="tw-cursor">|</span><br/>
            Untuk Bisnis Modern
          </h1>

          <p class="hero-subtitle">EgiPay menghadirkan ekosistem pembayaran yang cepat, aman, dan terpercaya. Integrasikan gateway kami dalam hitungan menit dan tingkatkan konversi bisnis Anda.</p>

          <div class="hero-actions">
            <a href="register.php" class="btn btn-primary-gradient px-5 py-3 fs-6">
              <i class="bi bi-rocket-takeoff me-2"></i>Mulai Gratis
            </a>
            <a href="#features" class="btn btn-outline-glow px-5 py-3 fs-6">
              Pelajari Fitur
            </a>
          </div>

          <div class="hero-stats">
            <div class="stat-item">
              <div class="stat-number">50K+</div>
              <div class="stat-label">Merchant Aktif</div>
            </div>
            <div class="stat-item">
              <div class="stat-number">99.9%</div>
              <div class="stat-label">Uptime SLA</div>
            </div>
            <div class="stat-item">
              <div class="stat-number">Rp 2T+</div>
              <div class="stat-label">Volume Transaksi</div>
            </div>
          </div>
        </div>

        <div class="col-lg-6 d-none d-lg-block text-center">
          <div class="hero-visual">
            <!-- Main Card -->
            <div class="float-card card-main">
              <div class="payment-card-visual">
                <div class="card-chip"></div>
                <div class="card-number">•••• •••• •••• 4821</div>
                <div class="card-info">
                  <div>
                    <div class="card-holder">PEMEGANG KARTU</div>
                    <div class="card-holder-name">Egi Supriatna</div>
                  </div>
                  <div class="card-network"><i class="bi bi-credit-card-2-front"></i></div>
                </div>
              </div>
              <div class="d-flex justify-content-between align-items-center mt-3">
                <div>
                  <div style="font-size:0.7rem;color:var(--text-muted)">Saldo Tersedia</div>
                  <div style="font-family:'Space Grotesk',sans-serif;font-weight:800;font-size:1.3rem;">Rp 4.821.500</div>
                </div>
                <div style="background:rgba(16,185,129,0.15);color:#10b981;font-size:0.75rem;font-weight:700;padding:4px 12px;border-radius:50px;">
                  +12.4%
                </div>
              </div>
            </div>

            <!-- Mini Cards -->
            <div class="float-card card-mini card-mini-1">
              <div class="mini-card-icon" style="background:rgba(16,185,129,0.15);color:#10b981;"><i class="bi bi-check-circle-fill"></i></div>
              <div class="mini-card-title">Pembayaran Berhasil</div>
              <div class="mini-card-value" style="color:#10b981;">Rp 250.000</div>
              <div class="mini-card-badge" style="background:rgba(16,185,129,0.1);color:#10b981;">2 dtk lalu</div>
            </div>

            <div class="float-card card-mini card-mini-2">
              <div class="mini-card-icon" style="background:rgba(108,99,255,0.15);color:var(--primary-light);"><i class="bi bi-shield-check"></i></div>
              <div class="mini-card-title">Keamanan SSL</div>
              <div class="mini-card-value">256-bit AES</div>
              <div class="mini-card-badge" style="background:rgba(108,99,255,0.1);color:var(--primary-light);">Terenkripsi</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ====== FEATURES ====== -->
  <section class="features-section" id="features">
    <div class="container">
      <div class="section-header animate-on-scroll">
        <div class="section-badge">Fitur Unggulan</div>
        <h2 class="section-title">Semua yang Anda Butuhkan dalam <br/><span class="gradient-text">Satu Platform</span></h2>
        <p class="section-subtitle">Dari integrasi API hingga manajemen transaksi real-time, EgiPay hadir dengan solusi terlengkap untuk bisnis Anda.</p>
      </div>

      <div class="row g-4">
        <?php
        $features = [
          ['bi-lightning-charge', 'Proses Instan', 'Transaksi diproses dalam hitungan detik dengan integrasi cloud tier-1.'],
          ['bi-shield-lock', 'Keamanan Berlapis', 'Proteksi fraud bertenaga AI dan enkripsi SSL 256-bit standar perbankan.'],
          ['bi-code-slash', 'Integrasi Mudah', 'Dokumentasi SDK lengkap untuk PHP, Node.js, Python, dan React.'],
          ['bi-graph-up-arrow', 'Analitik Canggih', 'Dashboard real-time untuk memantau performa penjualan Anda setiap saat.'],
          ['bi-wallet2', 'Banyak Metode', 'Terima pembayaran via QRIS, E-Wallet, VA Bank, hingga Minimarket.'],
          ['bi-headset', 'Support 24/7', 'Tim ahli yang siap membantu Anda kapan saja melalui live chat dan email.'],
        ];
        foreach ($features as $f): ?>
        <div class="col-md-4">
          <div class="feature-card animate-on-scroll">
            <div class="feature-icon"><i class="bi <?= $f[0] ?>"></i></div>
            <h3 class="feature-title"><?= $f[1] ?></h3>
            <p class="feature-desc"><?= $f[2] ?></p>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ====== HOW IT WORKS ====== -->
  <section class="steps-section" id="how-it-works">
    <div class="container">
      <div class="section-header animate-on-scroll">
        <div class="section-badge">Cara Kerja</div>
        <h2 class="section-title">Mulai Hanya Dalam <span class="gradient-text">4 Langkah</span></h2>
      </div>

      <div class="row g-4">
        <?php
        $steps = [
          ['01', 'Daftar Akun', 'Buat akun dalam 2 menit dengan email bisnis Anda.'],
          ['02', 'Verifikasi', 'Unggah dokumen identitas untuk aktivasi layanan penuh.'],
          ['03', 'Integrasi', 'Gunakan API Key untuk menghubungkan website Anda.'],
          ['04', 'Terima Dana', 'Semua pembayaran masuk otomatis ke dashboard Anda.'],
        ];
        foreach ($steps as $s): ?>
        <div class="col-md-3">
          <div class="step-card animate-on-scroll">
            <div class="step-number"><?= $s[0] ?></div>
            <h3 class="step-title"><?= $s[1] ?></h3>
            <p class="step-desc"><?= $s[2] ?></p>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ====== PRICING ====== -->
  <section class="pricing-section" id="pricing">
    <div class="container">
      <div class="section-header animate-on-scroll">
        <div class="section-badge">Harga Transparan</div>
        <h2 class="section-title">Pilih Paket <span class="gradient-text">Terbaik Anda</span></h2>
      </div>

      <div class="row g-4 justify-content-center">
        <div class="col-md-4">
          <div class="pricing-card featured animate-on-scroll">
            <div class="pricing-badge">Populer</div>
            <div class="pricing-name">Starter</div>
            <div class="pricing-price"><sup>Rp</sup>12K<span>/bln</span></div>
            <ul class="pricing-features">
              <li><i class="bi bi-check-circle-fill check"></i> Transaksi Tanpa Batas</li>
              <li><i class="bi bi-check-circle-fill check"></i> Semua Metode Pembayaran</li>
              <li><i class="bi bi-check-circle-fill check"></i> Prioritas Support 24/7</li>
              <li><i class="bi bi-check-circle-fill check"></i> Dashboard Analitik Pro</li>
            </ul>
            <a href="register.php?plan=starter" class="btn btn-primary-gradient w-100 py-3">Pilih Paket</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ====== CTA ====== -->
  <section class="cta-section">
    <div class="container text-center">
      <div class="cta-box animate-on-scroll">
        <h2 class="cta-title">Siap Tingkatkan Performa Bisnis Anda?</h2>
        <p class="cta-subtitle">Bergabunglah dengan 50.000+ merchant sukses. Daftar sekarang, gratis tanpa biaya setup.</p>
        <div class="d-flex gap-3 justify-content-center flex-wrap">
          <a href="register.php" class="btn btn-primary-gradient px-5 py-3 fs-6">Daftar Sekarang</a>
          <a href="docs.php" class="btn btn-outline-glow px-5 py-3 fs-6">Liat API Docs</a>
        </div>
      </div>
    </div>
  </section>

  <!-- ====== FOOTER ====== -->
  <footer class="footer">
    <div class="container">
      <div class="row g-5">
        <div class="col-lg-4">
          <a class="footer-brand mb-4 d-inline-block" href="#">
            <span class="brand-text">EgiPay</span>
          </a>
          <p class="footer-desc">Platform payment gateway terpercaya untuk bisnis masa depan di Indonesia. Aman, Cepat, dan Mudah.</p>
          <div class="social-links mt-4">
            <a href="#" class="social-link"><i class="bi bi-instagram"></i></a>
            <a href="#" class="social-link"><i class="bi bi-twitter-x"></i></a>
            <a href="#" class="social-link"><i class="bi bi-linkedin"></i></a>
          </div>
        </div>
        <div class="col-6 col-lg-2">
          <div class="footer-heading">Produk</div>
          <ul class="footer-links">
            <li><a href="#">Payment Gateway</a></li>
            <li><a href="#">QRIS</a></li>
            <li><a href="#">E-Wallet</a></li>
          </ul>
        </div>
        <div class="col-6 col-lg-2">
          <div class="footer-heading">Bantuan</div>
          <ul class="footer-links">
            <li><a href="#">Pusat Bantuan</a></li>
            <li><a href="#">API Docs</a></li>
            <li><a href="#">Kontak</a></li>
          </ul>
        </div>
      </div>
      <div class="footer-bottom mt-5 py-4 border-top border-secondary border-opacity-10 text-center">
        <p class="footer-copyright mb-0">© <?= date('Y') ?> EgiPay. Hak Cipta Dilindungi. Dibuat dengan di Jakarta.</p>
      </div>
    </div>
  </footer>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/main.js"></script>
<script>
// Typewriter Effect
const words = ["Pembayaran Digital", "Transfer Instan", "Solusi Bisnis", "QRIS Nasional"];
const SPEED_TYPE    = 90;   // ms per karakter saat mengetik
const SPEED_DELETE  = 45;   // ms per karakter saat menghapus
const PAUSE_AFTER   = 2200; // jeda setelah kata selesai diketik
const PAUSE_BEFORE  = 600;  // jeda sebelum mulai mengetik kata baru

let twIndex = 0, charIndex = 0, isDeleting = false;
const tw = document.getElementById('typewriter');

function type() {
  const current = words[twIndex];

  if (!isDeleting) {
    // Sedang mengetik
    charIndex++;
    tw.textContent = current.substring(0, charIndex);
    if (charIndex === current.length) {
      // Kata selesai diketik – jeda panjang lalu mulai hapus
      isDeleting = true;
      setTimeout(type, PAUSE_AFTER);
    } else {
      setTimeout(type, SPEED_TYPE);
    }
  } else {
    // Sedang menghapus
    charIndex--;
    tw.textContent = current.substring(0, charIndex);
    if (charIndex === 0) {
      // Teks habis dihapus – jeda sebentar lalu pindah kata berikutnya
      isDeleting = false;
      twIndex = (twIndex + 1) % words.length;
      setTimeout(type, PAUSE_BEFORE);
    } else {
      setTimeout(type, SPEED_DELETE);
    }
  }
}

setTimeout(type, 800);
</script>
</body>
</html>
