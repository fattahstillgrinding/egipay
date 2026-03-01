<?php
require_once __DIR__ . '/includes/config.php';
requireLogin();

$user   = getCurrentUser();
$userId = (int)$_SESSION['user_id'];
$flash  = getFlash();

// ── Handle POST: beli ebook ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $buyId = (int)($_POST['ebook_id'] ?? 0);

    // Daftar ebook valid (sama seperti di bawah)
    $ebookMap = [
        1 => ['title' => 'Kaya Dengan Prioritas',                             'price' => 12000],
        2 => ['title' => 'Kaya dalam 12 Bulan dengan Strategi Prioritas',     'price' => 15000],
    ];

    if (!isset($ebookMap[$buyId])) {
        setFlash('error', 'E-Book Tidak Ditemukan', 'Pilihan e-book tidak valid.');
        redirect(BASE_URL . '/ebooks.php');
    }

    $ebookInfo = $ebookMap[$buyId];

    // Buat token & invoice number
    $token      = bin2hex(random_bytes(32));
    $invNo      = 'INV-EB-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    $uniqueCode = 7; // kode unik tetap, total selalu berakhiran 7

    dbExecute(
        'INSERT INTO ebook_orders (inv_no, token, user_id, ebook_id, ebook_title, amount, unique_code, status, expires_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, "pending", DATE_ADD(NOW(), INTERVAL 15 MINUTE))',
        [$invNo, $token, $userId, $buyId, $ebookInfo['title'], $ebookInfo['price'], $uniqueCode]
    );

    auditLog($userId, 'ebook_order_created', 'Order e-book: ' . $ebookInfo['title'] . ' — ' . $invNo);

    redirect(BASE_URL . '/invoice_ebook.php?token=' . urlencode($token));
}

// Data E-book yang tersedia
$ebooks = [
    [
        'id' => 1,
        'title' => 'Kaya Dengan Prioritas',
        'description' => 'Panduan praktis mengelola uang dari nol hingga mampu membangun kestabilan finansial secara bertahap.',
        'price' => 12000,
        'image' => 'media/Screenshot 2026-02-28 142422.png',
        'benefits' => [
            'Mengatur keuangan dengan benar',
            'Menghindari kesalahan finansial umum',
            'Menentukan prioritas keuangan',
            'Membangun kebiasaan finansial sehat',
            'Memulai perjalanan menuju kebebasan finansial'
        ]
    ],
    [
        'id' => 2,
        'title' => 'Kaya dalam 12 Bulan dengan Strategi Prioritas',
        'description' => 'Panduan Praktis Membangun Kekayaan Nyata dengan Fokus pada yang Paling 
Berdampak',
        'price' => 15000,
        'image' => 'media/image.png',
        'benefits' => [
            'Memahami berbagai instrumen investasi',
            'Mengelola risiko investasi',
            'Strategi diversifikasi portofolio',
            'Menganalisis peluang investasi',
            'Memulai investasi dengan modal kecil'
        ]
    ],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>E-Book – SolusiMu</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link href="css/style.css" rel="stylesheet"/>
  <style>
    .ebook-card {
      background: var(--bg-card);
      border: 1px solid var(--border-glass);
      border-radius: 20px;
      padding: 1.5rem;
      transition: all 0.3s ease;
      height: 100%;
      display: flex;
      flex-direction: column;
    }
    .ebook-card:hover {
      transform: translateY(-5px);
      border-color: rgba(108, 99, 255, 0.5);
      box-shadow: 0 10px 40px rgba(108, 99, 255, 0.15);
    }
    .ebook-image {
      width: 100%;
      height: 250px;
      object-fit: cover;
      border-radius: 12px;
      margin-bottom: 1rem;
    }
    .ebook-price {
      font-size: 1.5rem;
      font-weight: 700;
      background: linear-gradient(135deg, #6c63ff 0%, #00d4ff 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    .benefit-item {
      display: flex;
      align-items: start;
      gap: 0.5rem;
      margin-bottom: 0.5rem;
      font-size: 0.9rem;
      color: var(--text-secondary);
    }
    .benefit-item i {
      color: #10b981;
      margin-top: 0.2rem;
    }
  </style>
</head>
<body>

<div class="content-wrapper d-flex">

<?php
$pageTitle = 'E-Book';
$pageSubtitle = 'Pilih e-book yang sesuai dengan kebutuhan Anda';
include __DIR__ . '/includes/sidebar.php';
?>

<!-- ====== MAIN CONTENT ====== -->
<main class="main-content">

  <?php include __DIR__ . '/includes/header.php'; ?>

  <?php if ($flash): ?>
  <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
    <strong><?= htmlspecialchars($flash['title']) ?></strong> <?= htmlspecialchars($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
  <?php endif; ?>

  <!-- E-books Grid -->
  <div class="row g-4">
    <?php foreach ($ebooks as $ebook): ?>
    <div class="col-md-6 col-lg-4">
      <div class="ebook-card">
        <img src="<?= htmlspecialchars($ebook['image']) ?>" alt="<?= htmlspecialchars($ebook['title']) ?>" class="ebook-image">
        
        <h3 class="h5 mb-2" style="color: var(--text-primary);"><?= htmlspecialchars($ebook['title']) ?></h3>
        <p class="text-secondary mb-3" style="font-size: 0.9rem;"><?= htmlspecialchars($ebook['description']) ?></p>
        
        <div class="mb-3">
          <div class="ebook-price">Rp <?= number_format($ebook['price'], 0, ',', '.') ?></div>
        </div>

        <div class="mb-3" style="flex-grow: 1;">
          <div class="fw-semibold mb-2" style="color: var(--text-primary); font-size: 0.9rem;">Yang Anda Dapatkan:</div>
          <?php foreach ($ebook['benefits'] as $benefit): ?>
          <div class="benefit-item">
            <i class="bi bi-check-circle-fill"></i>
            <span><?= htmlspecialchars($benefit) ?></span>
          </div>
          <?php endforeach; ?>
        </div>

        <form method="POST" action="ebooks.php">
          <?= csrfField() ?>
          <input type="hidden" name="ebook_id" value="<?= $ebook['id'] ?>">
          <button type="submit" class="btn btn-primary-gradient w-100 py-2">
            <i class="bi bi-cart-plus me-2"></i>Beli Sekarang
          </button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

</main>

</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/main.js"></script>

</body>
</html>
