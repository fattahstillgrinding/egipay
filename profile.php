<?php
require_once __DIR__ . '/includes/config.php';
requireLogin();

$user   = getCurrentUser();
$userId = (int)$_SESSION['user_id'];
$flash  = getFlash();

// ── Handle profile update ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Nama tidak boleh kosong.';
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email tidak valid.';
    } else {
        // Check if email already taken by another user
        $emailCheck = dbFetchOne(
            'SELECT id FROM users WHERE email = ? AND id != ?',
            [$email, $userId]
        );
        if ($emailCheck) {
            $errors[] = 'Email sudah digunakan oleh user lain.';
        }
    }
    
    if (empty($errors)) {
        dbExecute(
            'UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?',
            [$name, $email, $phone, $userId]
        );
        
        setFlash('success', 'Berhasil', 'Profil berhasil diperbarui.');
        header('Location: profile.php');
        exit;
    } else {
        setFlash('error', 'Gagal', implode('<br>', $errors));
    }
}

// ── Handle password change ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword     = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    $errors = [];
    
    // Verify current password
    if (!password_verify($currentPassword, $user['password'])) {
        $errors[] = 'Password lama tidak sesuai.';
    }
    
    if (strlen($newPassword) < 6) {
        $errors[] = 'Password baru minimal 6 karakter.';
    }
    
    if ($newPassword !== $confirmPassword) {
        $errors[] = 'Konfirmasi password tidak cocok.';
    }
    
    if (empty($errors)) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        dbExecute('UPDATE users SET password = ? WHERE id = ?', [$hashedPassword, $userId]);
        
        setFlash('success', 'Berhasil', 'Password berhasil diubah.');
        header('Location: profile.php');
        exit;
    } else {
        setFlash('error', 'Gagal', implode('<br>', $errors));
    }
}

// ── Handle avatar upload ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_avatar'])) {
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $fileName = $_FILES['avatar']['name'];
        $fileTmp  = $_FILES['avatar']['tmp_name'];
        $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        if (in_array($fileExt, $allowed)) {
            $newFileName = 'avatar_' . $userId . '_' . time() . '.' . $fileExt;
            $uploadPath  = __DIR__ . '/media/avatars/' . $newFileName;
            
            // Create avatars directory if not exists
            if (!is_dir(__DIR__ . '/media/avatars')) {
                mkdir(__DIR__ . '/media/avatars', 0755, true);
            }
            
            if (move_uploaded_file($fileTmp, $uploadPath)) {
                // Delete old avatar if exists and is not default
                if (!empty($user['avatar']) && file_exists(__DIR__ . '/media/avatars/' . $user['avatar'])) {
                    @unlink(__DIR__ . '/media/avatars/' . $user['avatar']);
                }
                
                dbExecute('UPDATE users SET avatar = ? WHERE id = ?', [$newFileName, $userId]);
                setFlash('success', 'Berhasil', 'Avatar berhasil diperbarui.');
                header('Location: profile.php');
                exit;
            } else {
                setFlash('error', 'Gagal', 'Gagal mengupload file.');
            }
        } else {
            setFlash('error', 'Gagal', 'Format file tidak didukung. Gunakan JPG, JPEG, PNG, atau GIF.');
        }
    } else {
        setFlash('error', 'Gagal', 'Tidak ada file yang diupload.');
    }
}

// ── Reload user data after updates ────────────────────────────
$user = getCurrentUser();

// ── Get user statistics ───────────────────────────────────────
$userStats = [
    'total_transactions' => (int)(dbFetchOne(
        'SELECT COUNT(*) AS cnt FROM transactions WHERE user_id = ? AND status = "success"',
        [$userId]
    )['cnt'] ?? 0),
    'total_amount' => (float)(dbFetchOne(
        'SELECT COALESCE(SUM(amount), 0) AS total FROM transactions WHERE user_id = ? AND status = "success"',
        [$userId]
    )['total'] ?? 0),
    'total_referrals' => (int)(dbFetchOne(
        'SELECT COUNT(*) AS cnt FROM referrals WHERE referrer_id = ?',
        [$userId]
    )['cnt'] ?? 0),
    'member_since' => $user['created_at'] ?? date('Y-m-d')
];

// ── Get wallet info ───────────────────────────────────────────
$wallet = getUserWallet($userId);
$incentiveWallet = dbFetchOne(
    'SELECT balance, locked, total_received FROM incentive_wallets WHERE user_id = ?',
    [$userId]
);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Profil Saya – SolusiMu</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <link href="css/style.css" rel="stylesheet"/>
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
// Set page info for header
$pageTitle = 'Profil Saya';
$pageSubtitle = 'Kelola informasi profil dan keamanan akun Anda';

// Include sidebar
include __DIR__ . '/includes/sidebar.php'; 
?>

<!-- ====== MAIN CONTENT ====== -->
<main class="main-content">
  <?php include __DIR__ . '/includes/header.php'; ?>

  <!-- Main content area -->
  <div class="content-body">
    <!-- Profile Header Card -->
    <div class="animate-on-scroll" style="background:linear-gradient(135deg,rgba(108,99,255,0.2),rgba(0,212,255,0.1));border:1px solid rgba(108,99,255,0.3);border-radius:20px;padding:2rem;margin-bottom:1.5rem;">
      <div style="display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap;">
        <div style="position:relative;">
          <?php if (!empty($user['avatar']) && file_exists(__DIR__ . '/media/avatars/' . $user['avatar'])): ?>
            <img src="media/avatars/<?= htmlspecialchars($user['avatar']) ?>" alt="<?= htmlspecialchars($user['name']) ?>" style="width:100px;height:100px;border-radius:50%;object-fit:cover;border:4px solid rgba(255,255,255,0.2);">
          <?php else: ?>
            <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['name']) ?>&background=4f46e5&color=fff&size=100" alt="<?= htmlspecialchars($user['name']) ?>" style="width:100px;height:100px;border-radius:50%;border:4px solid rgba(255,255,255,0.2);">
          <?php endif; ?>
          <button onclick="document.getElementById('avatarModalBtn').click()" style="position:absolute;bottom:0;right:0;width:32px;height:32px;border-radius:50%;background:#fff;border:none;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,0.2);">
            <i class="bi bi-camera-fill" style="color:#6c63ff;font-size:0.85rem;"></i>
          </button>
        </div>
        <div style="flex:1;">
          <h3 style="font-size:1.5rem;font-weight:800;margin-bottom:0.5rem;color:#fff;"><?= htmlspecialchars($user['name']) ?></h3>
          <p style="margin-bottom:0.75rem;color:rgba(255,255,255,0.7);">
            <i class="bi bi-envelope me-2"></i><?= htmlspecialchars($user['email']) ?>
          </p>
          <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
            <span style="background:rgba(255,255,255,0.2);padding:4px 12px;border-radius:20px;font-size:0.75rem;font-weight:600;color:#fff;">
              <i class="bi bi-shield-check me-1"></i><?= ucfirst($user['role']) ?>
            </span>
            <?php if (!empty($user['member_code'])): ?>
            <span style="background:rgba(255,255,255,0.2);padding:4px 12px;border-radius:20px;font-size:0.75rem;font-weight:600;color:#fff;">
              <i class="bi bi-person-badge me-1"></i><?= htmlspecialchars($user['member_code']) ?>
            </span>
            <?php endif; ?>
            <span style="background:rgba(255,255,255,0.2);padding:4px 12px;border-radius:20px;font-size:0.75rem;font-weight:600;color:#fff;">
              <i class="bi bi-calendar-check me-1"></i>Bergabung <?= date('M Y', strtotime($userStats['member_since'])) ?>
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
      <div class="col-sm-6 col-xl-3">
        <div class="stat-card animate-on-scroll" style="background:linear-gradient(135deg,rgba(108,99,255,0.2),rgba(108,99,255,0.04));border-color:rgba(108,99,255,0.3);">
          <div class="stat-card-icon" style="background:rgba(108,99,255,0.15);">
            <i class="bi bi-wallet2" style="background:linear-gradient(135deg,#6c63ff,#a78bfa);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;"></i>
          </div>
          <div class="stat-card-label">Saldo Utama</div>
          <div class="stat-card-value">Rp <?= number_format($wallet['balance'], 0, ',', '.') ?></div>
        </div>
      </div>
      <div class="col-sm-6 col-xl-3">
        <div class="stat-card animate-on-scroll animate-delay-1">
          <div class="stat-card-icon" style="background:rgba(16,185,129,0.12);">
            <i class="bi bi-gift" style="background:linear-gradient(135deg,#10b981,#00d4ff);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;"></i>
          </div>
          <div class="stat-card-label">Insentif</div>
          <div class="stat-card-value">Rp <?= number_format($incentiveWallet['balance'] ?? 0, 0, ',', '.') ?></div>
        </div>
      </div>
      <div class="col-sm-6 col-xl-3">
        <div class="stat-card animate-on-scroll animate-delay-2" style="background:linear-gradient(135deg,rgba(245,158,11,0.15),rgba(245,158,11,0.03));border-color:rgba(245,158,11,0.3);">
          <div class="stat-card-icon" style="background:rgba(245,158,11,0.12);">
            <i class="bi bi-arrow-repeat" style="background:linear-gradient(135deg,#f59e0b,#fbbf24);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;"></i>
          </div>
          <div class="stat-card-label">Total Transaksi</div>
          <div class="stat-card-value"><?= number_format($userStats['total_transactions']) ?></div>
        </div>
      </div>
      <div class="col-sm-6 col-xl-3">
        <div class="stat-card animate-on-scroll animate-delay-3" style="background:linear-gradient(135deg,rgba(0,212,255,0.15),rgba(0,212,255,0.03));border-color:rgba(0,212,255,0.3);">
          <div class="stat-card-icon" style="background:rgba(0,212,255,0.12);">
            <i class="bi bi-people" style="background:linear-gradient(135deg,#00d4ff,#06b6d4);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;"></i>
          </div>
          <div class="stat-card-label">Total Referral</div>
          <div class="stat-card-value"><?= number_format($userStats['total_referrals']) ?></div>
        </div>
      </div>
    </div>

    <!-- Profile Content Tabs -->
    <div style="background:var(--bg-card);border:1px solid var(--border-glass);border-radius:20px;overflow:hidden;margin-bottom:1.5rem;">
      <ul class="nav nav-tabs" style="border-bottom:1px solid var(--border-glass);padding:0.75rem 1.5rem 0;background:transparent;">
        <li class="nav-item">
          <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button" style="border:none;background:transparent;color:var(--text-muted);font-weight:600;font-size:0.85rem;padding:0.6rem 1rem;border-radius:10px 10px 0 0;">
            <i class="bi bi-person me-2"></i>Informasi Profil
          </button>
        </li>
        <li class="nav-item">
          <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" style="border:none;background:transparent;color:var(--text-muted);font-weight:600;font-size:0.85rem;padding:0.6rem 1rem;border-radius:10px 10px 0 0;">
            <i class="bi bi-shield-lock me-2"></i>Keamanan
          </button>
        </li>
        <li class="nav-item">
          <button class="nav-link" id="referral-tab" data-bs-toggle="tab" data-bs-target="#referral" type="button" style="border:none;background:transparent;color:var(--text-muted);font-weight:600;font-size:0.85rem;padding:0.6rem 1rem;border-radius:10px 10px 0 0;">
            <i class="bi bi-link-45deg me-2"></i>Referral
          </button>
        </li>
      </ul>

      <div class="tab-content" style="padding:2rem;">
        <!-- Info Tab -->
        <div class="tab-pane fade show active" id="info">
          <div style="margin-bottom:1.5rem;">
            <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1.5rem;">
              <div style="width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,#6c63ff,#a78bfa);display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-person-lines-fill" style="color:#fff;font-size:1.1rem;"></i>
              </div>
              <div>
                <h5 style="font-size:1rem;font-weight:700;margin:0;color:var(--text-primary);">Edit Informasi Profil</h5>
                <p style="font-size:0.8rem;color:var(--text-muted);margin:0;">Perbarui data profil Anda</p>
              </div>
            </div>
            <form method="POST" action="">
              <div class="row g-3">
                <div class="col-md-6">
                  <label style="font-size:0.8rem;font-weight:600;color:var(--text-secondary);margin-bottom:0.5rem;display:block;">Nama Lengkap <span style="color:var(--danger);">*</span></label>
                  <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($user['name']) ?>" required style="background:rgba(255,255,255,0.05);border:1px solid var(--border-glass);color:var(--text-primary);border-radius:10px;padding:0.65rem 1rem;">
                </div>
                <div class="col-md-6">
                  <label style="font-size:0.8rem;font-weight:600;color:var(--text-secondary);margin-bottom:0.5rem;display:block;">Email <span style="color:var(--danger);">*</span></label>
                  <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email']) ?>" required style="background:rgba(255,255,255,0.05);border:1px solid var(--border-glass);color:var(--text-primary);border-radius:10px;padding:0.65rem 1rem;">
                </div>
                <div class="col-md-6">
                  <label style="font-size:0.8rem;font-weight:600;color:var(--text-secondary);margin-bottom:0.5rem;display:block;">Nomor Telepon</label>
                  <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="081234567890" style="background:rgba(255,255,255,0.05);border:1px solid var(--border-glass);color:var(--text-primary);border-radius:10px;padding:0.65rem 1rem;">
                </div>
                <div class="col-md-6">
                  <label style="font-size:0.8rem;font-weight:600;color:var(--text-secondary);margin-bottom:0.5rem;display:block;">Kode Member</label>
                  <input type="text" class="form-control" value="<?= htmlspecialchars($user['member_code'] ?? '-') ?>" disabled readonly style="background:rgba(255,255,255,0.03);border:1px solid var(--border-glass);color:var(--text-muted);border-radius:10px;padding:0.65rem 1rem;">
                </div>
                <div class="col-md-6">
                  <label style="font-size:0.8rem;font-weight:600;color:var(--text-secondary);margin-bottom:0.5rem;display:block;">Kode Referral</label>
                  <input type="text" class="form-control" value="<?= htmlspecialchars($user['referral_code'] ?? '-') ?>" disabled readonly style="background:rgba(255,255,255,0.03);border:1px solid var(--border-glass);color:var(--text-muted);border-radius:10px;padding:0.65rem 1rem;">
                  <small style="color:var(--text-muted);font-size:0.75rem;margin-top:0.25rem;display:block;">Gunakan kode ini untuk mengajak teman bergabung</small>
                </div>
                <div class="col-12">
                  <button type="submit" name="update_profile" class="btn btn-primary" style="background:linear-gradient(135deg,#6c63ff,#00d4ff);border:none;padding:0.65rem 1.5rem;border-radius:10px;font-weight:700;font-size:0.85rem;">
                    <i class="bi bi-check-circle me-2"></i>Simpan Perubahan
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>

        <!-- Security Tab -->
        <div class="tab-pane fade" id="security">
          <div style="margin-bottom:1.5rem;">
            <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1.5rem;">
              <div style="width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,#f59e0b,#ef4444);display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-key-fill" style="color:#fff;font-size:1.1rem;"></i>
              </div>
              <div>
                <h5 style="font-size:1rem;font-weight:700;margin:0;color:var(--text-primary);">Ubah Password</h5>
                <p style="font-size:0.8rem;color:var(--text-muted);margin:0;">Pastikan password Anda aman</p>
              </div>
            </div>
            <form method="POST" action="">
              <div class="row g-3">
                <div class="col-12">
                  <label style="font-size:0.8rem;font-weight:600;color:var(--text-secondary);margin-bottom:0.5rem;display:block;">Password Lama <span style="color:var(--danger);">*</span></label>
                  <input type="password" class="form-control" name="current_password" required style="background:rgba(255,255,255,0.05);border:1px solid var(--border-glass);color:var(--text-primary);border-radius:10px;padding:0.65rem 1rem;">
                </div>
                <div class="col-md-6">
                  <label style="font-size:0.8rem;font-weight:600;color:var(--text-secondary);margin-bottom:0.5rem;display:block;">Password Baru <span style="color:var(--danger);">*</span></label>
                  <input type="password" class="form-control" name="new_password" required minlength="6" style="background:rgba(255,255,255,0.05);border:1px solid var(--border-glass);color:var(--text-primary);border-radius:10px;padding:0.65rem 1rem;">
                  <small style="color:var(--text-muted);font-size:0.75rem;margin-top:0.25rem;display:block;">Minimal 6 karakter</small>
                </div>
                <div class="col-md-6">
                  <label style="font-size:0.8rem;font-weight:600;color:var(--text-secondary);margin-bottom:0.5rem;display:block;">Konfirmasi Password Baru <span style="color:var(--danger);">*</span></label>
                  <input type="password" class="form-control" name="confirm_password" required minlength="6" style="background:rgba(255,255,255,0.05);border:1px solid var(--border-glass);color:var(--text-primary);border-radius:10px;padding:0.65rem 1rem;">
                </div>
                <div class="col-12">
                  <button type="submit" name="change_password" class="btn btn-primary" style="background:linear-gradient(135deg,#f59e0b,#ef4444);border:none;padding:0.65rem 1.5rem;border-radius:10px;font-weight:700;font-size:0.85rem;">
                    <i class="bi bi-shield-check me-2"></i>Ubah Password
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>

        <!-- Referral Tab -->
        <div class="tab-pane fade" id="referral">
          <?php if (!empty($user['referral_code'])): ?>
          <div style="margin-bottom:1.5rem;">
            <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1.5rem;">
              <div style="width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,#10b981,#00d4ff);display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-share-fill" style="color:#fff;font-size:1.1rem;"></i>
              </div>
              <div>
                <h5 style="font-size:1rem;font-weight:700;margin:0;color:var(--text-primary);">Link Referral Anda</h5>
                <p style="font-size:0.8rem;color:var(--text-muted);margin:0;">Bagikan dan dapatkan insentif</p>
              </div>
            </div>
            <div style="background:rgba(108,99,255,0.1);border:1px solid rgba(108,99,255,0.3);border-radius:12px;padding:1rem;margin-bottom:1rem;">
              <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
                <code style="color:#a78bfa;font-size:0.85rem;flex:1;word-break:break-all;" id="referralLink"><?= BASE_URL ?>/register.php?ref=<?= htmlspecialchars($user['referral_code']) ?></code>
                <button onclick="copyReferralLink()" style="background:linear-gradient(135deg,#6c63ff,#00d4ff);border:none;color:#fff;padding:0.5rem 1rem;border-radius:8px;font-weight:700;font-size:0.8rem;cursor:pointer;white-space:nowrap;">
                  <i class="bi bi-clipboard"></i> Salin
                </button>
              </div>
            </div>
            <div style="text-align:center;margin-bottom:1.5rem;">
              <p style="color:var(--text-muted);margin-bottom:1rem;font-size:0.85rem;">Bagikan link referral Anda dan dapatkan insentif!</p>
              <div style="display:flex;gap:0.5rem;justify-content:center;flex-wrap:wrap;">
                <a href="https://wa.me/?text=<?= urlencode('Daftar di SolusiMu: ' . BASE_URL . '/register.php?ref=' . $user['referral_code']) ?>" target="_blank" style="background:#25d366;color:#fff;padding:0.5rem 1rem;border-radius:8px;text-decoration:none;font-weight:600;font-size:0.8rem;">
                  <i class="bi bi-whatsapp"></i> WhatsApp
                </a>
                <a href="https://t.me/share/url?url=<?= urlencode(BASE_URL . '/register.php?ref=' . $user['referral_code']) ?>&text=<?= urlencode('Daftar di SolusiMu') ?>" target="_blank" style="background:#0088cc;color:#fff;padding:0.5rem 1rem;border-radius:8px;text-decoration:none;font-weight:600;font-size:0.8rem;">
                  <i class="bi bi-telegram"></i> Telegram
                </a>
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode(BASE_URL . '/register.php?ref=' . $user['referral_code']) ?>" target="_blank" style="background:#1877f2;color:#fff;padding:0.5rem 1rem;border-radius:8px;text-decoration:none;font-weight:600;font-size:0.8rem;">
                  <i class="bi bi-facebook"></i> Facebook
                </a>
              </div>
            </div>
          </div>

          <div style="background:linear-gradient(135deg,rgba(168,85,247,0.12),rgba(108,99,255,0.06));border:1px solid rgba(168,85,247,0.25);border-radius:14px;padding:1.5rem;">
            <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:1.25rem;">
              <div style="width:32px;height:32px;border-radius:9px;background:linear-gradient(135deg,#a855f7,#6c63ff);display:flex;align-items:center;justify-content:center;">
                <i class="bi bi-people-fill" style="color:#fff;font-size:0.8rem;"></i>
              </div>
              <h5 style="font-size:0.9rem;font-weight:700;margin:0;color:var(--text-primary);">Statistik Referral</h5>
            </div>
            <div class="row g-3">
              <div class="col-md-4">
                <div style="background:rgba(108,99,255,0.12);border:1px solid rgba(108,99,255,0.25);border-radius:12px;padding:1rem;text-align:center;">
                  <h3 style="font-family:'Space Grotesk';font-size:1.8rem;font-weight:800;margin-bottom:0.25rem;background:linear-gradient(135deg,#6c63ff,#a78bfa);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;"><?= $userStats['total_referrals'] ?></h3>
                  <small style="color:var(--text-muted);font-size:0.75rem;font-weight:600;">Total Referral</small>
                </div>
              </div>
              <div class="col-md-4">
                <div style="background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.25);border-radius:12px;padding:1rem;text-align:center;">
                  <h3 style="font-family:'Space Grotesk';font-size:1.8rem;font-weight:800;margin-bottom:0.25rem;background:linear-gradient(135deg,#10b981,#00d4ff);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">Rp <?= number_format($incentiveWallet['total_received'] ?? 0, 0, ',', '.') ?></h3>
                  <small style="color:var(--text-muted);font-size:0.75rem;font-weight:600;">Total Insentif</small>
                </div>
              </div>
              <div class="col-md-4">
                <div style="background:rgba(0,212,255,0.12);border:1px solid rgba(0,212,255,0.25);border-radius:12px;padding:1rem;text-align:center;">
                  <h3 style="font-family:'Space Grotesk';font-size:1.8rem;font-weight:800;margin-bottom:0.25rem;background:linear-gradient(135deg,#00d4ff,#06b6d4);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">Rp <?= number_format($incentiveWallet['balance'] ?? 0, 0, ',', '.') ?></h3>
                  <small style="color:var(--text-muted);font-size:0.75rem;font-weight:600;">Saldo Insentif</small>
                </div>
              </div>
            </div>
          </div>
          <?php else: ?>
          <div style="text-align:center;padding:3rem 1rem;">
            <div style="width:80px;height:80px;border-radius:50%;background:rgba(108,99,255,0.1);display:inline-flex;align-items:center;justify-content:center;margin-bottom:1rem;">
              <i class="bi bi-info-circle" style="font-size:2.5rem;color:var(--text-muted);"></i>
            </div>
            <h5 style="font-weight:700;margin-bottom:0.5rem;color:var(--text-primary);">Kode Referral Tidak Tersedia</h5>
            <p style="color:var(--text-muted);font-size:0.85rem;">Hubungi administrator untuk mendapatkan kode referral.</p>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</main>

<!-- Avatar Upload Modal -->
<div class="modal fade" id="avatarModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="background:var(--bg-card);border:1px solid var(--border-glass);border-radius:20px;">
      <div class="modal-header" style="border-bottom:1px solid var(--border-glass);padding:1.25rem 1.5rem;">
        <h5 class="modal-title" style="font-weight:700;font-size:1rem;color:var(--text-primary);">
          <i class="bi bi-camera me-2" style="color:var(--primary-light);"></i>Ubah Avatar
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter:invert(1);opacity:0.5;"></button>
      </div>
      <form method="POST" action="" enctype="multipart/form-data">
        <div class="modal-body" style="padding:1.5rem;">
          <div style="text-align:center;margin-bottom:1.5rem;">
            <?php if (!empty($user['avatar']) && file_exists(__DIR__ . '/media/avatars/' . $user['avatar'])): ?>
              <img src="media/avatars/<?= htmlspecialchars($user['avatar']) ?>" alt="Avatar" id="avatarPreview" style="width:150px;height:150px;border-radius:50%;object-fit:cover;border:4px solid rgba(108,99,255,0.3);">
            <?php else: ?>
              <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['name']) ?>&background=4f46e5&color=fff&size=150" alt="Avatar" id="avatarPreview" style="width:150px;height:150px;border-radius:50%;border:4px solid rgba(108,99,255,0.3);">
            <?php endif; ?>
          </div>
          <div>
            <label style="font-size:0.8rem;font-weight:600;color:var(--text-secondary);margin-bottom:0.5rem;display:block;">Pilih foto baru</label>
            <input class="form-control" type="file" id="avatarFile" name="avatar" accept="image/*" onchange="previewAvatar(this)" style="background:rgba(255,255,255,0.05);border:1px solid var(--border-glass);color:var(--text-primary);border-radius:10px;">
            <small style="color:var(--text-muted);font-size:0.75rem;margin-top:0.5rem;display:block;">Format: JPG, JPEG, PNG, GIF. Maks: 2MB</small>
          </div>
        </div>
        <div class="modal-footer" style="border-top:1px solid var(--border-glass);padding:1rem 1.5rem;">
          <button type="button" class="btn" data-bs-dismiss="modal" style="background:rgba(255,255,255,0.05);border:1px solid var(--border-glass);color:var(--text-primary);padding:0.5rem 1rem;border-radius:8px;font-weight:600;font-size:0.85rem;">Batal</button>
          <button type="submit" name="update_avatar" style="background:linear-gradient(135deg,#6c63ff,#00d4ff);border:none;color:#fff;padding:0.5rem 1.25rem;border-radius:8px;font-weight:700;font-size:0.85rem;cursor:pointer;">
            <i class="bi bi-upload me-2"></i>Upload Avatar
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Hidden button to trigger modal -->
<button id="avatarModalBtn" data-bs-toggle="modal" data-bs-target="#avatarModal" style="display:none;"></button>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/main.js"></script>
<style>
.nav-tabs .nav-link.active {
  background: rgba(108,99,255,0.15) !important;
  color: var(--primary-light) !important;
  border-bottom: 2px solid var(--primary) !important;
}
.nav-tabs .nav-link:hover {
  background: rgba(108,99,255,0.08);
  color: var(--text-primary);
}
</style>
<script>
function copyReferralLink() {
  const link = document.getElementById('referralLink').textContent;
  navigator.clipboard.writeText(link).then(() => {
    showToast('success', 'Berhasil', 'Link referral disalin ke clipboard!');
  });
}

function previewAvatar(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = function(e) {
      document.getElementById('avatarPreview').src = e.target.result;
    };
    reader.readAsDataURL(input.files[0]);
  }
}
</script>
</body>
</html>
