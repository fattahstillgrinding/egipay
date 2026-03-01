<?php
// Sidebar component - included in all dashboard pages
// Usage: include __DIR__ . '/includes/sidebar.php';
?>

<!-- Sidebar Overlay (mobile) -->
<div id="sidebarOverlay"
  style="position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:999;display:none;backdrop-filter:blur(4px);"
  class="d-lg-none"></div>

<!-- ====== SIDEBAR ====== -->
<aside class="sidebar" id="mainSidebar">
  <div class="sidebar-logo">
    <img src="<?= BASE_URL ?>/media/logo/solusi-removebg-preview (3).png" alt="SolusiMu" style="height: 50px; width: auto; object-fit: contain;">
  </div>

  <ul class="sidebar-menu">
    <li class="sidebar-section-title">Utama</li>
    <li><a href="<?= BASE_URL ?>/dashboard.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>"><span class="icon"><i class="bi bi-grid-1x2-fill"></i></span>Dashboard</a></li>
    <li><a href="<?= BASE_URL ?>/incentive_wallet.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'incentive_wallet.php' ? 'active' : '' ?>"><span class="icon"><i class="bi bi-wallet2"></i></span>Dompet</a></li>
    <li class="sidebar-section-title">E-Book</li>
    <li><a href="<?= BASE_URL ?>/ebooks.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'ebooks.php' ? 'active' : '' ?>"><span class="icon"><i class="bi bi-book-fill"></i></span>Daftar E-book</a></li>
    <li class="sidebar-section-title">Akun</li>
    <li><a href="<?= BASE_URL ?>/profile.php" class="sidebar-link <?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>"><span class="icon"><i class="bi bi-person-circle"></i></span>Profil</a></li>
    <li><a href="#" class="sidebar-link"><span class="icon"><i class="bi bi-gear"></i></span>Pengaturan</a></li>
    <li><a href="#" class="sidebar-link"><span class="icon"><i class="bi bi-headset"></i></span>Support</a></li>
    <?php if (isAdmin()): ?>
    <li class="sidebar-section-title">Administrasi</li>
    <li><a href="<?= BASE_URL ?>/admin/index.php" class="sidebar-link" style="color:#f72585;"><span class="icon"><i class="bi bi-shield-lock-fill"></i></span>Admin Panel</a></li>
    <?php endif; ?>
    <?php if (isSuperAdmin()): ?>
    <li><a href="<?= BASE_URL ?>/superadmin/index.php" class="sidebar-link" style="color:#c084fc;"><span class="icon"><i class="bi bi-shield-shaded"></i></span>Super Admin Panel</a></li>
    <?php endif; ?>
  </ul>

  <div class="sidebar-footer">
    <a href="<?= BASE_URL ?>/logout.php" style="display:flex;align-items:center;justify-content:center;gap:0.5rem;background:linear-gradient(135deg,#ef4444,#dc2626);border:none;color:#fff;padding:0.75rem 1.5rem;border-radius:12px;font-weight:700;font-size:0.85rem;text-decoration:none;transition:all 0.3s ease;width:100%;">
      <i class="bi bi-box-arrow-right"></i> Logout
    </a>
  </div>
</aside>
