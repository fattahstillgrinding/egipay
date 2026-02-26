/* ===========================
   EgiPay - Main JavaScript
   =========================== */

/* Page Loader */
window.addEventListener('load', function () {
  const loader = document.getElementById('pageLoader');
  if (loader) {
    setTimeout(() => {
      loader.classList.add('hidden');
    }, 1200);
  }
});

/* ===========================
   Particle Canvas
   =========================== */
function initParticles() {
  const canvas = document.getElementById('particles-canvas');
  if (!canvas) return;

  const ctx = canvas.getContext('2d');
  canvas.width = window.innerWidth;
  canvas.height = window.innerHeight;

  const particles = [];
  const colors = ['rgba(108,99,255,', 'rgba(0,212,255,', 'rgba(167,139,250,'];

  class Particle {
    constructor() {
      this.reset();
    }
    reset() {
      this.x = Math.random() * canvas.width;
      this.y = Math.random() * canvas.height;
      this.size = Math.random() * 2 + 0.5;
      this.speedX = (Math.random() - 0.5) * 0.4;
      this.speedY = (Math.random() - 0.5) * 0.4;
      this.color = colors[Math.floor(Math.random() * colors.length)];
      this.opacity = Math.random() * 0.5 + 0.1;
      this.fadeDir = Math.random() > 0.5 ? 1 : -1;
    }
    update() {
      this.x += this.speedX;
      this.y += this.speedY;
      this.opacity += this.fadeDir * 0.002;
      if (this.opacity >= 0.6 || this.opacity <= 0.05) {
        this.fadeDir *= -1;
      }
      if (this.x < 0 || this.x > canvas.width || this.y < 0 || this.y > canvas.height) {
        this.reset();
      }
    }
    draw() {
      ctx.beginPath();
      ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
      ctx.fillStyle = this.color + this.opacity + ')';
      ctx.fill();
    }
  }

  // Generate lines between nearby particles
  function drawConnections() {
    for (let i = 0; i < particles.length; i++) {
      for (let j = i + 1; j < particles.length; j++) {
        const dx = particles[i].x - particles[j].x;
        const dy = particles[i].y - particles[j].y;
        const dist = Math.sqrt(dx * dx + dy * dy);
        if (dist < 120) {
          ctx.beginPath();
          ctx.moveTo(particles[i].x, particles[i].y);
          ctx.lineTo(particles[j].x, particles[j].y);
          const opac = (1 - dist / 120) * 0.08;
          ctx.strokeStyle = `rgba(108,99,255,${opac})`;
          ctx.lineWidth = 0.5;
          ctx.stroke();
        }
      }
    }
  }

  const count = Math.min(60, Math.floor(window.innerWidth / 20));
  for (let i = 0; i < count; i++) {
    particles.push(new Particle());
  }

  function animate() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    particles.forEach(p => {
      p.update();
      p.draw();
    });
    drawConnections();
    requestAnimationFrame(animate);
  }
  animate();

  window.addEventListener('resize', () => {
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
  });
}

/* ===========================
   Navbar Scroll Effect
   =========================== */
function initNavbarScroll() {
  const navbar = document.querySelector('.navbar');
  if (!navbar) return;
  window.addEventListener('scroll', () => {
    if (window.scrollY > 50) {
      navbar.classList.add('scrolled');
    } else {
      navbar.classList.remove('scrolled');
    }
  });
}

/* ===========================
   Scroll Animations
   =========================== */
function initScrollAnimations() {
  const els = document.querySelectorAll('.animate-on-scroll');
  if (!els.length) return;

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('visible');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1 });

  els.forEach(el => observer.observe(el));
}

/* ===========================
   Counter Animation
   =========================== */
function animateCounter(el, target, duration = 2000, prefix = '', suffix = '') {
  const start = 0;
  const startTime = performance.now();

  function update(currentTime) {
    const elapsed = currentTime - startTime;
    const progress = Math.min(elapsed / duration, 1);
    const eased = 1 - Math.pow(1 - progress, 3); // cubic easing
    const value = Math.floor(eased * target);
    el.textContent = prefix + value.toLocaleString() + suffix;
    if (progress < 1) {
      requestAnimationFrame(update);
    }
  }
  requestAnimationFrame(update);
}

function initCounters() {
  const counters = document.querySelectorAll('[data-counter]');
  if (!counters.length) return;

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const el = entry.target;
        const target = parseInt(el.getAttribute('data-counter'));
        const prefix = el.getAttribute('data-prefix') || '';
        const suffix = el.getAttribute('data-suffix') || '';
        animateCounter(el, target, 2000, prefix, suffix);
        observer.unobserve(el);
      }
    });
  }, { threshold: 0.5 });

  counters.forEach(c => observer.observe(c));
}

/* ===========================
   Payment Method Selection
   =========================== */
function initPaymentMethods() {
  const cards = document.querySelectorAll('.payment-method-card');
  cards.forEach(card => {
    card.addEventListener('click', function () {
      cards.forEach(c => c.classList.remove('selected'));
      this.classList.add('selected');
      const method = this.getAttribute('data-method');
      const hiddenInput = document.getElementById('selected_method');
      if (hiddenInput) hiddenInput.value = method;
    });
  });
}

/* ===========================
   Toast Notifications
   =========================== */
function showToast(title, message, type = 'info') {
  const container = document.getElementById('toastContainer');
  if (!container) return;

  const icons = {
    success: '✓',
    error: '✕',
    info: 'ℹ'
  };

  const toast = document.createElement('div');
  toast.className = `custom-toast toast-${type}`;
  toast.innerHTML = `
    <div class="toast-icon">${icons[type]}</div>
    <div class="toast-text">
      <div class="toast-title">${title}</div>
      <div class="toast-msg">${message}</div>
    </div>
  `;

  container.appendChild(toast);
  setTimeout(() => {
    toast.style.animation = 'slideOutRight 0.3s ease forwards';
    setTimeout(() => toast.remove(), 300);
  }, 3500);
}

/* ===========================
   Sidebar Toggle (Mobile)
   =========================== */
function initSidebar() {
  const toggleBtn = document.getElementById('sidebarToggle');
  const sidebar = document.querySelector('.sidebar');
  const overlay = document.getElementById('sidebarOverlay');

  if (!toggleBtn || !sidebar) return;

  toggleBtn.addEventListener('click', () => {
    sidebar.classList.toggle('open');
    if (overlay) overlay.classList.toggle('show');
  });

  if (overlay) {
    overlay.addEventListener('click', () => {
      sidebar.classList.remove('open');
      overlay.classList.remove('show');
    });
  }
}

/* ===========================
   Payment Form Validation
   =========================== */
function initPaymentForm() {
  const form = document.getElementById('paymentForm');
  if (!form) return;

  form.addEventListener('submit', function (e) {
    const amount = document.getElementById('amount');
    if (amount && parseFloat(amount.value) <= 0) {
      e.preventDefault();
      showToast('Error', 'Jumlah pembayaran tidak valid', 'error');
      return;
    }
    const method = document.getElementById('selected_method');
    if (method && !method.value) {
      e.preventDefault();
      showToast('Error', 'Pilih metode pembayaran terlebih dahulu', 'error');
      return;
    }
  });

  // Format amount input
  const amountInput = document.getElementById('amount');
  if (amountInput) {
    amountInput.addEventListener('input', function () {
      this.value = this.value.replace(/[^0-9.]/g, '');
    });
  }
}

/* ===========================
   Floating Card Mouse Effect
   =========================== */
function initCardMouseEffect() {
  const heroVisual = document.querySelector('.hero-visual');
  if (!heroVisual) return;

  heroVisual.addEventListener('mousemove', (e) => {
    const rect = heroVisual.getBoundingClientRect();
    const x = (e.clientX - rect.left - rect.width / 2) / rect.width;
    const y = (e.clientY - rect.top - rect.height / 2) / rect.height;

    const cards = heroVisual.querySelectorAll('.float-card');
    cards.forEach((card, i) => {
      const factor = (i + 1) * 5;
      card.style.transform = `translate(${x * factor}px, ${y * factor}px)`;
    });
  });

  heroVisual.addEventListener('mouseleave', () => {
    const cards = heroVisual.querySelectorAll('.float-card');
    cards.forEach(card => {
      card.style.transform = '';
    });
  });
}

/* ===========================
   Typewriter Effect
   =========================== */
function typeWriter(element, texts, speed = 80, pause = 2000) {
  if (!element) return;
  let textIndex = 0;
  let charIndex = 0;
  let isDeleting = false;

  function type() {
    const currentText = texts[textIndex];
    if (isDeleting) {
      element.textContent = currentText.substring(0, charIndex - 1);
      charIndex--;
    } else {
      element.textContent = currentText.substring(0, charIndex + 1);
      charIndex++;
    }

    if (!isDeleting && charIndex === currentText.length) {
      setTimeout(() => { isDeleting = true; type(); }, pause);
      return;
    }
    if (isDeleting && charIndex === 0) {
      isDeleting = false;
      textIndex = (textIndex + 1) % texts.length;
    }

    const nextDelay = isDeleting ? speed / 2 : speed;
    setTimeout(type, nextDelay);
  }

  type();
}

/* ===========================
   Ripple Effect on Buttons
   =========================== */
function initRippleEffect() {
  document.querySelectorAll('.btn-primary-gradient').forEach(btn => {
    btn.addEventListener('click', function (e) {
      const rect = this.getBoundingClientRect();
      const ripple = document.createElement('span');
      const size = Math.max(rect.width, rect.height);
      ripple.style.cssText = `
        position: absolute;
        width: ${size}px;
        height: ${size}px;
        border-radius: 50%;
        background: rgba(255,255,255,0.3);
        transform: scale(0);
        animation: rippleAnim 0.6s linear;
        left: ${e.clientX - rect.left - size / 2}px;
        top: ${e.clientY - rect.top - size / 2}px;
        pointer-events: none;
      `;
      this.appendChild(ripple);
      ripple.addEventListener('animationend', () => ripple.remove());
    });
  });

  // Add ripple animation to CSS
  const style = document.createElement('style');
  style.textContent = '@keyframes rippleAnim { to { transform: scale(3); opacity: 0; } }';
  document.head.appendChild(style);
}

/* ===========================
   Init All
   =========================== */
document.addEventListener('DOMContentLoaded', () => {
  initParticles();
  initNavbarScroll();
  initScrollAnimations();
  initCounters();
  initPaymentMethods();
  initSidebar();
  initPaymentForm();
  initCardMouseEffect();
  initRippleEffect();

  // Typewriter on hero
  const typeEl = document.getElementById('typewriter');
  if (typeEl) {
    typeWriter(typeEl, [
      'Transfer Instan',
      'Pembayaran Aman',
      'Transaksi Mudah',
      'Kirim Uang Cepat'
    ]);
  }

  // Show toast on page load if there's a message
  const flashMsg = document.getElementById('flashMessage');
  if (flashMsg) {
    showToast(
      flashMsg.getAttribute('data-title') || 'Info',
      flashMsg.getAttribute('data-message'),
      flashMsg.getAttribute('data-type') || 'info'
    );
  }
});
