<?php
// Header component for main content area
// Usage: include __DIR__ . '/includes/header.php';
// Variables required: $user, $pageTitle, $pageSubtitle (optional)

// Get notification count
$notifCount = getUnreadNotifCount((int)$_SESSION['user_id']);
$notifications = dbFetchAll(
    'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5',
    [(int)$_SESSION['user_id']]
);
?>

<div style="padding:0 0 2rem 0;">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:1.5rem;">
    <div>
      <div style="display:flex;align-items:center;gap:1rem;margin-bottom:0.5rem;">
        <button class="btn btn-link d-lg-none p-0" id="menuToggle"
          style="font-size:1.5rem;color:var(--dark-accent);">
          <i class="bi bi-list"></i>
        </button>
        <h1 style="margin:0;font-size:2rem;font-weight:800;color:var(--text-primary);">
          <?= $pageTitle ?? 'Dashboard' ?>
        </h1>
      </div>
      <?php if (isset($pageSubtitle) && $pageSubtitle): ?>
      <p style="margin:0;color:var(--text-muted);font-size:0.95rem;"><?= $pageSubtitle ?> 👋</p>
      <?php endif; ?>
    </div>
    
    <div class="d-flex align-items-center gap-2">
      <!-- Notification Bell -->
      <div class="dropdown">
        <button class="btn dropdown-toggle" id="notifBtn"
          data-bs-toggle="dropdown" aria-expanded="false"
          style="background:var(--bg-card);border:1px solid var(--border-glass);border-radius:12px;color:var(--text-primary);padding:8px 14px;position:relative;">
          <i class="bi bi-bell"></i>
          <?php if ($notifCount > 0): ?>
          <span style="position:absolute;top:5px;right:8px;width:18px;height:18px;background:#f72585;border-radius:50%;border:2px solid var(--bg-dark);font-size:0.6rem;font-weight:700;display:flex;align-items:center;justify-content:center;">
            <?= $notifCount ?>
          </span>
          <?php endif; ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end" style="background:rgba(15,15,30,0.97);border:1px solid var(--border-glass);border-radius:16px;padding:0.5rem;min-width:300px;backdrop-filter:blur(20px);">
          <li style="padding:0.75rem 1rem 0.5rem;border-bottom:1px solid var(--border-glass);margin-bottom:0.25rem;">
            <span style="font-weight:700;font-size:0.875rem;">Notifikasi</span>
          </li>
          <?php if ($notifications): ?>
            <?php foreach ($notifications as $n): ?>
            <li style="padding:0.5rem 0.75rem;border-radius:10px;cursor:default;">
              <div style="display:flex;gap:10px;align-items:flex-start;">
                <div style="width:32px;height:32px;border-radius:10px;background:rgba(108,99,255,0.15);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:0.85rem;color:var(--primary-light);">
                  <i class="bi bi-<?= $n['type']==='success'?'check-circle':'info-circle' ?>"></i>
                </div>
                <div>
                  <div style="font-size:0.78rem;font-weight:600;"><?= htmlspecialchars($n['title']) ?></div>
                  <div style="font-size:0.72rem;color:var(--text-muted);"><?= htmlspecialchars(substr($n['message'],0,60)).'...' ?></div>
                </div>
              </div>
            </li>
            <?php endforeach; ?>
          <?php else: ?>
            <li style="padding:1rem;text-align:center;color:var(--text-muted);font-size:0.8rem;">Tidak ada notifikasi</li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </div>
</div>
