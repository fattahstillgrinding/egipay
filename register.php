<?php
require_once __DIR__ . '/includes/config.php';
requireGuest();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name          = trim($_POST['name']     ?? '');
    $email         = trim($_POST['email']    ?? '');
    $phone         = trim($_POST['phone']    ?? '');
    $password      = $_POST['password']      ?? '';
    $confirm       = $_POST['password_confirm'] ?? '';
    $referralCode  = trim($_POST['referral_code'] ?? '');
    $plan          = 'membership'; // Default plan gratis

    // ── Validasi ───────────────────────────────────────────────
    if (strlen($name) < 3)
        $errors['name']    = 'Nama minimal 3 karakter.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors['email']   = 'Format email tidak valid.';
    if (strlen($password) < 8)
        $errors['password']= 'Password minimal 8 karakter.';
    if ($password !== $confirm)
        $errors['confirm'] = 'Konfirmasi password tidak cocok.';

    // ── Cek email yang sudah menjadi member ────────────────────
    if (empty($errors['email'])) {
        $member = dbFetchOne('SELECT id FROM users WHERE email = ?', [$email]);
        if ($member) {
            $errors['email'] = 'Email sudah terdaftar sebagai member. Silakan masuk.';
        }
    }

    if (empty($errors)) {
        // ── Ambil kode referral dari input form atau URL ───────
        $refCode   = $referralCode ?: trim($_GET['ref'] ?? '');
        $referrer  = $refCode ? dbFetchOne('SELECT id FROM users WHERE referral_code = ?', [$refCode]) : null;
        $referrerId = $referrer ? (int)$referrer['id'] : null;

        // ── Buat akun member langsung (gratis) ──────────────────
        $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        
        $initials = strtoupper(substr($name, 0, 1));
        $parts    = explode(' ', $name);
        if (count($parts) > 1) $initials .= strtoupper(substr($parts[1], 0, 1));

        try {
            $pdo = getDB();
            $pdo->beginTransaction();

            // Insert user
            dbExecute(
                'INSERT INTO users (name, email, password, phone, role, plan, status, avatar, email_verified_at)
                 VALUES (?, ?, ?, ?, "merchant", ?, "active", ?, NOW())',
                [$name, $email, $hashed, $phone ?: null, $plan, $initials]
            );
            $userId = (int)dbLastId();
            
            // Generate member code & referral code
            $memberCode = 'MU-' . sprintf('%09d', $userId);
            $newRefCode = generateReferralCode($name);
            dbExecute(
                'UPDATE users SET member_code=?, referral_code=?, referred_by=? WHERE id=?',
                [$memberCode, $newRefCode, $referrerId, $userId]
            );

            // Create wallet
            dbExecute('INSERT INTO wallets (user_id, balance) VALUES (?, 0.00)', [$userId]);

            // Create sandbox API key
            dbExecute(
                'INSERT INTO api_keys (user_id, name, key_type, client_key, server_key) VALUES (?, ?, ?, ?, ?)',
                [$userId, 'Sandbox Key', 'sandbox', generateApiKey('sandbox'), generateApiKey('sandbox')]
            );

            // Welcome notification
            dbExecute(
                'INSERT INTO notifications (user_id, type, title, message) VALUES (?, "success", ?, ?)',
                [
                    $userId,
                    'Selamat Datang di SolusiMu!',
                    'Akun Anda berhasil dibuat. Mulai terima pembayaran sekarang!',
                ]
            );

            // ── Catat referral jika ada ─────────────────────
            if ($referrerId) {
                dbExecute(
                    'INSERT IGNORE INTO referrals (referrer_id, referred_id, referral_code) VALUES (?, ?, ?)',
                    [$referrerId, $userId, $refCode]
                );
                // Notif untuk referrer
                $refCount = dbFetchOne('SELECT COUNT(*) AS c FROM referrals WHERE referrer_id = ?', [$referrerId])['c'] ?? 0;
                dbExecute(
                    'INSERT INTO notifications (user_id, type, title, message) VALUES (?, "success", ?, ?)',
                    [
                        $referrerId,
                        'Referral Berhasil! 🎉',
                        htmlspecialchars($name) . ' baru saja mendaftar menggunakan link referral Anda. Total referral Anda: ' . $refCount,
                    ]
                );
            }

            // Hapus ref_code dari session
            unset($_SESSION['ref_code']);

            $pdo->commit();

            auditLog($userId, 'register_success', 'Registrasi gratis berhasil: ' . $email);

            // Auto-login
            session_regenerate_id(true);
            $_SESSION['user_id'] = $userId;
            
            setFlash('success', 'Selamat Datang!', 'Akun Anda berhasil dibuat. Selamat datang di SolusiMu!');
            redirect(BASE_URL . '/dashboard.php');

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors['general'] = 'Gagal membuat akun. Silakan coba lagi.';
        }
    }
}

$selectedPlan = $_GET['plan'] ?? ($_POST['plan'] ?? 'starter');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Daftar Gratis – SolusiMu</title>
  <meta name="description" content="Buat akun SolusiMu gratis dan mulai menerima pembayaran hari ini."/>
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
        <span style="color:var(--text-muted);font-size:0.875rem;">Sudah punya akun?</span>
        <a href="login.php" class="btn btn-outline-glow btn-sm ms-2 px-3">Masuk</a>
      </div>
    </div>
  </nav>
  <div class="hero-bg-orb hero-bg-orb-1" style="opacity:0.1;width:400px;height:400px;top:-50px;right:-100px;"></div>
  <div class="hero-bg-orb hero-bg-orb-2" style="opacity:0.08;width:350px;height:350px;bottom:-50px;left:-100px;"></div>

  <section class="auth-section" style="padding-top:7rem;">
    <div class="auth-card" style="max-width:520px;">
      <!-- Back link inside card -->
      <a href="index.php"
         style="display:inline-flex;align-items:center;gap:6px;color:var(--text-muted);text-decoration:none;font-size:0.78rem;font-weight:600;margin-bottom:1.5rem;transition:color 0.2s;"
         onmouseover="this.style.color='var(--primary-light)'"
         onmouseout="this.style.color='var(--text-muted)'">
        <i class="bi bi-arrow-left-circle-fill" style="font-size:1rem;"></i>
        Kembali ke Beranda
      </a>

      <div class="text-center mb-4">
        <svg width="52" height="52" viewBox="0 0 42 42" fill="none" style="margin-bottom:0.75rem;">
          <defs><linearGradient id="authLg2" x1="0" y1="0" x2="42" y2="42"><stop stop-color="#6c63ff"/><stop offset="1" stop-color="#00d4ff"/></linearGradient></defs>
          <rect width="42" height="42" rx="12" fill="url(#authLg2)"/>
          <path d="M12 14h10a6 6 0 010 12H12V14zm0 6h8a2 2 0 000-6" fill="white" opacity="0.95"/>
          <circle cx="30" cy="28" r="3" fill="white" opacity="0.8"/>
        </svg>
        <h1 class="auth-title">Buat Akun Gratis</h1>
        <p class="auth-subtitle">Mulai menerima pembayaran dalam beberapa menit</p>
      </div>

      <?php if (!empty($errors['general'])): ?>
      <div style="background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.3);border-radius:12px;padding:0.75rem 1rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:8px;font-size:0.875rem;color:#ef4444;">
        <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($errors['general']) ?>
      </div>
      <?php endif; ?>

      <form method="POST" action="register.php">
        <?= csrfField() ?>

        <div class="row g-3">
          <div class="col-12">
            <label class="form-label-modern"><i class="bi bi-person me-1"></i>Nama Lengkap</label>
            <div class="input-icon-wrapper">
              <i class="bi bi-person input-icon"></i>
              <input type="text" name="name" class="form-control-modern input-with-icon <?= !empty($errors['name']) ? 'border-danger' : '' ?>"
                placeholder="Nama lengkap Anda" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"/>
            </div>
            <?php if (!empty($errors['name'])): ?><div style="color:#ef4444;font-size:0.75rem;margin-top:4px;"><?= $errors['name'] ?></div><?php endif; ?>
          </div>

          <div class="col-12">
            <label class="form-label-modern"><i class="bi bi-envelope me-1"></i>Email Bisnis</label>
            <div class="input-icon-wrapper">
              <i class="bi bi-envelope input-icon"></i>
              <input type="email" name="email" class="form-control-modern input-with-icon <?= !empty($errors['email']) ? 'border-danger' : '' ?>"
                placeholder="email@bisnis.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"/>
            </div>
            <?php if (!empty($errors['email'])): ?><div style="color:#ef4444;font-size:0.75rem;margin-top:4px;"><?= $errors['email'] ?></div><?php endif; ?>
          </div>

          <div class="col-12">
            <label class="form-label-modern"><i class="bi bi-phone me-1"></i>No. Telepon <span style="color:var(--text-muted);font-weight:400;">(opsional)</span></label>
            <div class="input-icon-wrapper">
              <i class="bi bi-phone input-icon"></i>
              <input type="tel" name="phone" class="form-control-modern input-with-icon"
                placeholder="+62 812 xxxx xxxx" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"/>
            </div>
          </div>

          <div class="col-12">
            <label class="form-label-modern"><i class="bi bi-people me-1"></i>Kode Referral <span style="color:var(--text-muted);font-weight:400;">(opsional)</span></label>
            <div class="input-icon-wrapper">
              <i class="bi bi-gift input-icon"></i>
              <input type="text" name="referral_code" class="form-control-modern input-with-icon"
                placeholder="Masukkan kode referral jika ada" value="<?= htmlspecialchars($_GET['ref'] ?? $_POST['referral_code'] ?? '') ?>"/>
            </div>
            <div style="font-size:0.7rem;color:var(--text-muted);margin-top:4px;">Dapatkan benefit tambahan dengan kode referral dari teman Anda</div>
          </div>

          <div class="col-md-6">
            <label class="form-label-modern"><i class="bi bi-lock me-1"></i>Password</label>
            <div class="input-icon-wrapper">
              <i class="bi bi-lock input-icon"></i>
              <input type="password" name="password" id="regPwd" class="form-control-modern input-with-icon <?= !empty($errors['password']) ? 'border-danger' : '' ?>"
                placeholder="Min. 8 karakter" required style="padding-right:3rem;"/>
              <button type="button" onclick="let i=document.getElementById('regPwd');i.type=i.type==='password'?'text':'password'"
                style="position:absolute;right:1rem;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;">
                <i class="bi bi-eye"></i>
              </button>
            </div>
            <?php if (!empty($errors['password'])): ?><div style="color:#ef4444;font-size:0.75rem;margin-top:4px;"><?= $errors['password'] ?></div><?php endif; ?>
          </div>

          <div class="col-md-6">
            <label class="form-label-modern"><i class="bi bi-lock-fill me-1"></i>Konfirmasi Password</label>
            <div class="input-icon-wrapper">
              <i class="bi bi-lock-fill input-icon"></i>
              <input type="password" name="password_confirm" class="form-control-modern input-with-icon <?= !empty($errors['confirm']) ? 'border-danger' : '' ?>"
                placeholder="Ulangi password" required/>
            </div>
            <?php if (!empty($errors['confirm'])): ?><div style="color:#ef4444;font-size:0.75rem;margin-top:4px;"><?= $errors['confirm'] ?></div><?php endif; ?>
          </div>
        </div>

        <!-- Password strength -->
        <div style="margin-top:0.75rem;margin-bottom:1.25rem;">
          <div style="display:flex;gap:4px;margin-bottom:4px;">
            <?php for ($i=1;$i<=4;$i++): ?>
            <div id="sb<?=$i?>" style="height:3px;flex:1;border-radius:3px;background:var(--border-glass);transition:background 0.3s;"></div>
            <?php endfor; ?>
          </div>
          <div style="font-size:0.7rem;color:var(--text-muted);" id="strengthLabel">Masukkan password untuk melihat kekuatan</div>
        </div>

        <label style="display:flex;align-items:flex-start;gap:10px;font-size:0.8rem;color:var(--text-muted);margin-bottom:1.5rem;cursor:pointer;">
          <input type="checkbox" required style="accent-color:var(--primary);margin-top:2px;flex-shrink:0;">
          Saya menyetujui <a href="#" style="color:var(--primary-light);margin:0 3px;">Syarat & Ketentuan</a> dan
          <a href="#" style="color:var(--primary-light);margin:0 3px;">Kebijakan Privasi</a> SolusiMu.
        </label>

        <button type="submit" class="btn btn-primary-gradient w-100 py-3 fs-6 mb-3">
          <i class="bi bi-rocket-takeoff me-2"></i>Buat Akun Gratis
        </button>

        <div style="text-align:center;font-size:0.875rem;color:var(--text-muted);">
          Sudah punya akun?
          <a href="login.php" style="color:var(--primary-light);text-decoration:none;font-weight:600;">Masuk di sini</a>
        </div>
      </form>

      <div style="margin-top:1.5rem;display:flex;align-items:center;justify-content:center;gap:1rem;flex-wrap:wrap;">
        <span style="display:flex;align-items:center;gap:4px;font-size:0.7rem;color:var(--text-muted);"><i class="bi bi-shield-check text-success"></i>SSL Aman</span>
        <span style="display:flex;align-items:center;gap:4px;font-size:0.7rem;color:var(--text-muted);"><i class="bi bi-lock text-success"></i>Data Terenkripsi</span>
        <span style="display:flex;align-items:center;gap:4px;font-size:0.7rem;color:var(--text-muted);"><i class="bi bi-check-circle text-success"></i>Gratis Selamanya</span>
      </div>
    </div>
  </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/main.js"></script>
<script>
// Password strength
const regPwd = document.getElementById('regPwd');
const bars   = [1,2,3,4].map(i => document.getElementById('sb' + i));
const strengthLabel = document.getElementById('strengthLabel');
const colors = ['#ef4444','#f59e0b','#10b981','#6c63ff'];
const labels = ['Lemah','Cukup','Kuat','Sangat Kuat'];
if (regPwd) {
  regPwd.addEventListener('input', function() {
    const v = this.value;
    let score = 0;
    if (v.length >= 8)          score++;
    if (/[A-Z]/.test(v))        score++;
    if (/[0-9]/.test(v))        score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    bars.forEach((b, i) => b.style.background = i < score ? colors[score-1] : 'var(--border-glass)');
    strengthLabel.textContent = v ? (labels[score-1]||'Lemah') : 'Masukkan password untuk melihat kekuatan';
    strengthLabel.style.color = v ? colors[score-1] : 'var(--text-muted)';
  });
}
</script>
</body>
</html>
