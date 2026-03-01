<?php
require_once __DIR__ . '/includes/config.php';
requireGuest();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember']);

    // Validate
    if (!$email)    $errors['email']    = 'Email wajib diisi.';
    if (!$password) $errors['password'] = 'Password wajib diisi.';

    if (empty($errors)) {
        $user = dbFetchOne(
            'SELECT * FROM users WHERE email = ? AND status = "active" LIMIT 1',
            [$email]
        );

        if ($user && password_verify($password, $user['password'])) {
            // Login success
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];

            if ($remember) {
                session_set_cookie_params(['lifetime' => 60 * 60 * 24 * 30]);
            }

            auditLog($user['id'], 'login', 'Login dari IP: ' . ($_SERVER['REMOTE_ADDR'] ?? ''));
            setFlash('success', 'Selamat Datang!', 'Halo ' . $user['name'] . ', selamat datang kembali!');
            if ($user['role'] === 'superadmin') {
                $dest = BASE_URL . '/superadmin/index.php';
            } elseif ($user['role'] === 'admin') {
                $dest = BASE_URL . '/admin/index.php';
            } else {
                $dest = BASE_URL . '/dashboard.php';
            }
            redirect($dest);
        } else {
            $errors['general'] = 'Email atau password salah. Silakan coba lagi.';
            auditLog(null, 'login_failed', 'Percobaan login gagal untuk: ' . $email);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Masuk – SolusiMu</title>
  <meta name="description" content="Masuk ke akun SolusiMu Anda untuk mengelola pembayaran bisnis."/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link href="css/style.css" rel="stylesheet"/>
</head>
<body>
<div class="page-loader" id="pageLoader">
  <div class="loader-logo">
    <img src="media/logo/Screenshot_2026-02-28_133755-removebg-preview.png" alt="SolusiMu" style="width: 80px; height: auto;">
  </div>
  <div class="loader-bar"><div class="loader-bar-fill"></div></div>
</div>
<canvas id="particles-canvas"></canvas>
<div class="toast-container" id="toastContainer"></div>

<div class="content-wrapper">
  <nav class="navbar fixed-top">
    <div class="container justify-content-between">
      <a class="navbar-brand" href="index.php">
        <img src="media/logo/solusi-removebg-preview (3).png" alt="SolusiMu" style="height: 50px; width: auto; object-fit: contain;">
      </a>

      <div>
        <span style="color:var(--text-muted);font-size:0.875rem;">Belum punya akun?</span>
        <a href="register.php" class="btn btn-primary-gradient btn-sm ms-2 px-3">Daftar</a>
      </div>
    </div>
  </nav>

  <div class="hero-bg-orb hero-bg-orb-1" style="opacity:0.1;width:400px;height:400px;top:-50px;right:-100px;"></div>
  <div class="hero-bg-orb hero-bg-orb-2" style="opacity:0.08;width:350px;height:350px;bottom:-50px;left:-100px;"></div>

  <section class="auth-section">
    <div class="auth-card">
      <!-- Back link inside card -->
      <a href="index.php"
         style="display:inline-flex;align-items:center;gap:6px;color:var(--text-muted);text-decoration:none;font-size:0.78rem;font-weight:600;margin-bottom:1.5rem;transition:color 0.2s;"
         onmouseover="this.style.color='var(--primary-light)'"
         onmouseout="this.style.color='var(--text-muted)'">
        <i class="bi bi-arrow-left-circle-fill" style="font-size:1rem;"></i>
        Kembali ke Beranda
      </a>

      <div class="text-center mb-4">
        <svg width="52" height="52" viewBox="0 0 42 42" fill="none" style="margin-bottom:1rem;">
          <defs><linearGradient id="authLg" x1="0" y1="0" x2="42" y2="42"><stop stop-color="#6c63ff"/><stop offset="1" stop-color="#00d4ff"/></linearGradient></defs>
          <rect width="42" height="42" rx="12" fill="url(#authLg)"/>
          <path d="M12 14h10a6 6 0 010 12H12V14zm0 6h8a2 2 0 000-6" fill="white" opacity="0.95"/>
          <circle cx="30" cy="28" r="3" fill="white" opacity="0.8"/>
        </svg>
        <h1 class="auth-title">Selamat Datang</h1>
        <p class="auth-subtitle">Masuk ke dashboard SolusiMu Anda</p>
      </div>

      <!-- General error -->
      <?php if (!empty($errors['general'])): ?>
      <div style="background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.3);border-radius:12px;padding:0.75rem 1rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:8px;font-size:0.875rem;color:#ef4444;">
        <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($errors['general']) ?>
      </div>
      <?php endif; ?>


      <form method="POST" action="login.php" id="loginForm">
        <?= csrfField() ?>

        <div class="form-floating-modern">
          <label class="form-label-modern" for="email"><i class="bi bi-envelope me-1"></i>Email</label>
          <div class="input-icon-wrapper">
            <i class="bi bi-envelope input-icon"></i>
            <input type="email" id="email" name="email" class="form-control-modern input-with-icon <?= !empty($errors['email']) ? 'border-danger' : '' ?>"
              placeholder="email@example.com"
              value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
              required autocomplete="email"/>
          </div>
          <?php if (!empty($errors['email'])): ?>
          <div style="color:#ef4444;font-size:0.75rem;margin-top:4px;"><?= $errors['email'] ?></div>
          <?php endif; ?>
        </div>

        <div class="form-floating-modern">
          <label class="form-label-modern" for="password"><i class="bi bi-lock me-1"></i>Password</label>
          <div class="input-icon-wrapper" style="position:relative;">
            <i class="bi bi-lock input-icon"></i>
            <input type="password" id="password" name="password" class="form-control-modern input-with-icon <?= !empty($errors['password']) ? 'border-danger' : '' ?>"
              placeholder="Masukkan password" style="padding-right:3rem;"
              required autocomplete="current-password"/>
            <button type="button" id="togglePwd" style="position:absolute;right:1rem;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;padding:0;">
              <i class="bi bi-eye" id="togglePwdIcon"></i>
            </button>
          </div>
          <?php if (!empty($errors['password'])): ?>
          <div style="color:#ef4444;font-size:0.75rem;margin-top:4px;"><?= $errors['password'] ?></div>
          <?php endif; ?>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3" style="font-size:0.8rem;">
          <label style="display:flex;align-items:center;gap:8px;color:var(--text-muted);cursor:pointer;">
            <input type="checkbox" name="remember" style="accent-color:var(--primary);"> Ingat saya
          </label>
          <a href="#" style="color:var(--primary-light);text-decoration:none;">Lupa password?</a>
        </div>

        <button type="submit" class="btn btn-primary-gradient w-100 py-3 fs-6 mb-3">
          <i class="bi bi-box-arrow-in-right me-2"></i>Masuk ke Dashboard
        </button>

        <div style="text-align:center;font-size:0.8rem;color:var(--text-muted);margin-bottom:1.5rem;">— atau masuk dengan —</div>

        <div class="row g-2 mb-3">
          <div class="col-6">
            <button type="button" class="btn btn-outline-glow w-100 py-2" style="font-size:0.8rem;" onclick="showToast('Info','Fitur segera hadir!','info')">
              <i class="bi bi-google me-2"></i>Google
            </button>
          </div>
          <div class="col-6">
            <button type="button" class="btn btn-outline-glow w-100 py-2" style="font-size:0.8rem;" onclick="showToast('Info','Fitur segera hadir!','info')">
              <i class="bi bi-facebook me-2"></i>Facebook
            </button>
          </div>
        </div>

        <div style="text-align:center;font-size:0.875rem;color:var(--text-muted);">
          Belum punya akun?
          <a href="register.php" style="color:var(--primary-light);text-decoration:none;font-weight:600;">Daftar gratis</a>
        </div>
      </form>
    </div>
  </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/main.js"></script>
<script>
const toggleBtn = document.getElementById('togglePwd');
const pwdInput  = document.getElementById('password');
const pwdIcon   = document.getElementById('togglePwdIcon');
if (toggleBtn) {
  toggleBtn.addEventListener('click', () => {
    pwdInput.type = pwdInput.type === 'password' ? 'text' : 'password';
    pwdIcon.className = pwdInput.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
  });
}
</script>
</body>
</html>
