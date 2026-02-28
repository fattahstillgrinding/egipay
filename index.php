<?php
require_once __DIR__ . '/includes/config.php';
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>SolusiMu – Platform komunitas pembelajaran digital bagi Masyarakat Nusantara
“Membangunkesejahteraan melalui literasi, bukan spekulasi.”
</title>
  <meta name="description" content="SolusiMu adalah platform payment gateway modern dengan transaksi instan, keamanan berlapis, dan biaya kompetitif untuk bisnis Anda." />

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
        <img src="media/logo/solusi-removebg-preview (3).png" alt="SolusiMu" class="brand-logo" style="height: 70px; width: auto; object-fit: contain;">
      </a>

      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
        <i class="bi bi-list text-white fs-5"></i>
      </button>

      <div class="collapse navbar-collapse" id="navbarMain">
        <ul class="navbar-nav mx-auto gap-1">
          <li class="nav-item"><a class="nav-link" href="#features">E - Book</a></li>
          <li class="nav-item"><a class="nav-link" href="#how-it-works">Cara Kerja</a></li>
          <li class="nav-item"><a class="nav-link" href="#pricing">Paket</a></li>
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
            Platform Edukasi Digital
          </div>

          <h1 class="hero-title">
            Edukasi <br/>
            Digital Untuk Nusantara
          </h1>

          <p class="hero-subtitle">Platform komunitas pembelajaran digital bagi Masyarakat Nusantara
“Membangun kesejahteraan melalui literasi, bukan spekulasi.”
</p>

          <div class="hero-actions">
            <a href="register.php" class="btn btn-primary-gradient px-5 py-3 fs-6">
              <i class="bi bi-rocket-takeoff me-2"></i>Mulai Gratis
            </a>
          </div>
        </div>

        <div class="col-lg-6 d-none d-lg-block text-center">
          <div class="hero-visual">
            <!-- Main Card -->
            <div class="float-card card-main">
              <div class="payment-card-visual">
                <img src="media/logo/Screenshot_2026-02-28_133755-removebg-preview.png" alt="Payment Card" style="width: 100%; height: auto; display: block; border-radius: 16px;">
              </div>
              <div class="d-flex justify-content-between align-items-center mt-3">
              </div>
            </div>

            <!-- Mini Cards -->
            <div class="float-card card-mini card-mini-1">
              <img src="media/poster/WhatsApp Image 2026-02-27 at 11.10.42.jpeg" alt="Poster" style="width: 100%; height: 100%; object-fit: cover; border-radius: 16px;">
            </div>

            <div class="float-card card-mini card-mini-2">
                <img src="media/poster/WhatsApp Image 2026-02-28 at 10.41.57.jpeg" alt="Poster" style="width: 100%; height: 100%; object-fit: cover; border-radius: 16px;">
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ====== FEATURES ====== -->
  <section class="features-section" id="features" style="scroll-margin-top: 100px;">
    <div class="container">
      <div class="section-header animate-on-scroll">
        <h2 class="section-title">Pelajari strategi pasar, manajemen keuangan, dan kewirausahaan dari para ahli. dalam <br/><span class="gradient-text">Satu Platform</span></h2>
      </div>

      <div class="row g-4 justify-content-center">
        <?php
        $features = [
          ['media/Screenshot 2026-02-28 142422.png', 'E - Book Kaya Dengan Prioritas', 'Panduan Realistis Mengelola Uang dari  
Gaji Pertama hingga Bebas Finansial '],
        ];
        foreach ($features as $f): ?>
        <div class="col-md-6 col-lg-4">
          <div class="feature-card animate-on-scroll" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 20px; padding: 2rem; text-align: center; transition: all 0.3s ease; min-height: 650px; display: flex; flex-direction: column; justify-content: center;">
            <div style="margin-bottom: 1.5rem; overflow: hidden; border-radius: 12px;">
              <img src="<?= $f[0] ?>" alt="<?= $f[1] ?>" style="width: 100%; height: auto; display: block;">
            </div>
            <h3 class="feature-title" style="margin-bottom: 1rem;"><?= $f[1] ?></h3>
            <p class="feature-desc" style="margin: 0;"><?= $f[2] ?></p>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ====== HOW IT WORKS ====== -->
  <section class="steps-section" id="how-it-works" style="scroll-margin-top: 100px;">
    <div class="container">
      <div class="section-header animate-on-scroll">
        <div class="section-badge">Cara Kerja</div>
        <h2 class="section-title">Mulai Hanya Dalam <span class="gradient-text">4 Langkah</span></h2>
      </div>

      <div class="row g-4">
        <?php
        $steps = [
          ['01', 'Daftar Akun', 'Buat akun dalam 2 menit dengan email bisnis Anda.'],
          ['02', 'Pilih Buku Anda', 'Pilih buku yang sesuai dengan kebutuhan Anda.'],
          ['03', 'Pembayaran', 'Lakukan pembayaran dengan metode yang tersedia.'],
          ['04', 'Selamat Membaca', 'Buku siap dibaca setelah pembayaran berhasil.'],
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
  <section class="pricing-section" id="pricing" style="scroll-margin-top: 100px;">
    <div class="container">
      <div class="section-header animate-on-scroll">
        <div class="section-badge">Harga Transparan</div>
        <h2 class="section-title">Pilih Buku <span class="gradient-text">Terbaik Anda</span></h2>
      </div>

      <div class="row g-4 justify-content-center">
        <div class="col-md-4">
          <div class="pricing-card featured animate-on-scroll">
            <div class="pricing-badge">Populer</div>
            <div class="pricing-name">E - BOOk KAYA DENGAN PRIORITAS</div>
            <div class="pricing-price"><sup>Rp</sup>12K</div>
            <ul class="pricing-features">
              <li><i class="bi bi-check-circle-fill check"></i> E - Book Kaya Dengan Prioritas</li>
              <li><i class="bi bi-check-circle-fill check"></i> Membership Solusimu</li>
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
        <h2 class="cta-title">Kuasai Strategi Keuangan, Lipat Gandakan Keuntungan!</h2>
        <p class="cta-subtitle">Jangan biarkan bisnis stagnan karena salah kelola modal. Dapatkan panduan lengkap manajemen keuangan dari para ahli.</p>
        <div class="d-flex gap-3 justify-content-center flex-wrap">
          <a href="register.php" class="btn btn-primary-gradient px-5 py-3 fs-6">Daftar Sekarang</a>
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
            <span class="brand-text">SolusiMu</span>
          </a>
          <p class="footer-desc">Platform komunitas pembelajaran digital bagi Masyarakat Nusantara
“Membangun kesejahteraan melalui literasi, bukan spekulasi.”
</p>
        </div>
        <div class="col-6 col-lg-2">
          <div class="footer-heading">Produk</div>
          <ul class="footer-links">
            <li><a href="#">E - BOOK KAYA DENGAN PRIORITAS</a></li>
          </ul>
        </div>
        <div class="col-6 col-lg-2">
          <div class="footer-heading">Bantuan</div>
          <ul class="footer-links">
            <li><a href="#">Pusat Bantuan</a></li>
            <li><a href="#">Kontak</a></li>
          </ul>
        </div>
      </div>
      <div class="footer-bottom mt-5 py-4 border-top border-secondary border-opacity-10 text-center">
        <p class="footer-copyright mb-0">© <?= date('Y') ?> SolusiMu. Hak Cipta Dilindungi. Dibuat dengan di Jakarta.</p>
      </div>
    </div>
  </footer>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/main.js"></script>
<script>
// Typewriter Effect
const words = ["Belajar", "Bertumbuh", " Beretika"];
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
