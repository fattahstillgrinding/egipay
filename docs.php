<?php
require_once __DIR__ . '/includes/config.php';

// Docs are always in Indonesian + bilingual terms; no need to load lang here
// but start session so navbar can show login state
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>API Documentation – SolusiMu</title>
  <meta name="description" content="Dokumentasi lengkap SolusiMu Payment API. Integrasi gateway pembayaran dalam hitungan menit menggunakan REST API kami."/>
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

<!-- ====== NAVBAR ====== -->
<nav class="navbar navbar-expand-lg fixed-top">
  <div class="container-fluid px-4">
    <a class="navbar-brand" href="index.php">
      <svg class="brand-logo" width="38" height="38" viewBox="0 0 42 42" fill="none">
        <defs><linearGradient id="nLg" x1="0" y1="0" x2="42" y2="42"><stop stop-color="#6c63ff"/><stop offset="1" stop-color="#00d4ff"/></linearGradient></defs>
        <rect width="42" height="42" rx="12" fill="url(#nLg)"/>
        <path d="M12 14h10a6 6 0 010 12H12V14zm0 6h8a2 2 0 000-6" fill="white" opacity="0.95"/>
        <circle cx="30" cy="28" r="3" fill="white" opacity="0.8"/>
      </svg>
      <span class="brand-text">SolusiMu</span>
    </a>

    <div class="d-flex align-items-center gap-2 ms-auto">
      <span style="background:rgba(16,185,129,0.12);border:1px solid rgba(16,185,129,0.25);color:#10b981;font-size:0.7rem;font-weight:700;padding:3px 10px;border-radius:50px;letter-spacing:1px;">
        <i class="bi bi-circle-fill me-1" style="font-size:0.5rem;"></i>API v2.1.0
      </span>
      <?php if (isLoggedIn()): ?>
      <a href="dashboard.php" class="btn btn-outline-glow btn-sm px-3">Dashboard</a>
      <?php else: ?>
      <a href="login.php"    class="btn btn-outline-glow btn-sm px-3">Masuk</a>
      <a href="register.php" class="btn btn-primary-gradient btn-sm px-3">Daftar</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<div class="content-wrapper" style="padding-top:5rem;">
  <div class="container-fluid px-4">
    <div class="row" style="min-height:100vh;">

      <!-- ====== LEFT SIDEBAR (Nav) ====== -->
      <div class="col-lg-2 d-none d-lg-block">
        <div class="docs-sidebar">
          <div class="docs-nav-title">Memulai</div>
          <a href="#getting-started" class="docs-nav-link active">Pengantar</a>
          <a href="#authentication"  class="docs-nav-link">Autentikasi</a>
          <a href="#base-url"        class="docs-nav-link">Base URL</a>
          <a href="#errors"          class="docs-nav-link">Error Handling</a>

          <div class="docs-nav-title">Pembayaran</div>
          <a href="#create-charge"   class="docs-nav-link">Buat Transaksi</a>
          <a href="#get-status"      class="docs-nav-link">Cek Status</a>
          <a href="#cancel"          class="docs-nav-link">Batalkan</a>
          <a href="#refund"          class="docs-nav-link">Refund</a>

          <div class="docs-nav-title">Metode</div>
          <a href="#qris"            class="docs-nav-link">QRIS</a>
          <a href="#ewallet"         class="docs-nav-link">E-Wallet</a>
          <a href="#bank-transfer"   class="docs-nav-link">Transfer Bank</a>
          <a href="#credit-card"     class="docs-nav-link">Kartu Kredit</a>

          <div class="docs-nav-title">Lainnya</div>
          <a href="#webhook"         class="docs-nav-link">Webhook</a>
          <a href="#sandbox"         class="docs-nav-link">Sandbox</a>
          <a href="#sdk"             class="docs-nav-link">SDK & Library</a>
          <a href="#changelog"       class="docs-nav-link">Changelog</a>
        </div>
      </div>

      <!-- ====== MAIN DOCS CONTENT ====== -->
      <div class="col-lg-7 docs-content py-4">

        <!-- ── GETTING STARTED ──────────────────────── -->
        <section id="getting-started">
          <div class="section-badge mb-3"><i class="bi bi-book me-1"></i>Pengantar</div>
          <h2>SolusiMu Payment API</h2>
          <p>Selamat datang di dokumentasi <strong>SolusiMu REST API</strong>. API ini memungkinkan Anda mengintegrasikan gateway pembayaran SolusiMu ke dalam platform Anda dengan cepat dan aman.</p>
          <p>API kami menggunakan format <strong>JSON</strong> untuk request dan response, serta menggunakan metode HTTP standar (<code>GET</code>, <code>POST</code>, <code>PUT</code>, <code>DELETE</code>).</p>

          <h3>Prasyarat</h3>
          <ul>
            <li>Daftar akun SolusiMu (gratis) di <a href="register.php" style="color:var(--primary-light);">register.php</a></li>
            <li>Aktifkan akun dan dapatkan API Key dari dashboard</li>
            <li>Gunakan HTTPS untuk semua request di mode production</li>
          </ul>
        </section>

        <!-- ── AUTHENTICATION ────────────────────────── -->
        <section id="authentication">
          <h2>Autentikasi</h2>
          <p>Semua request API harus disertai <strong>Server Key</strong> menggunakan metode <strong>HTTP Basic Auth</strong>. Server Key dikirim sebagai <em>username</em>, password biarkan kosong.</p>

          <div class="code-block">
            <div class="code-block-header">
              <span class="code-lang">HTTP Header</span>
              <button class="copy-btn" onclick="copyCode(this)"><i class="bi bi-clipboard me-1"></i>Copy</button>
            </div>
            <pre><code>Authorization: Basic <span class="tok-s">Base64(ServerKey + ":")</span></code></pre>
          </div>

          <div class="code-block">
            <div class="code-block-header">
              <span class="code-lang">cURL</span>
              <button class="copy-btn" onclick="copyCode(this)"><i class="bi bi-clipboard me-1"></i>Copy</button>
            </div>
            <pre><code><span class="tok-f">curl</span> -u <span class="tok-s">"SK-Live-xxxx:"</span> \
  -X POST <span class="tok-s">"https://api.solusimu.id/v2/charge"</span> \
  -H <span class="tok-s">"Content-Type: application/json"</span> \
  -d <span class="tok-s">'{"amount": 50000, "method": "qris"}'</span></code></pre>
          </div>

          <div class="code-block">
            <div class="code-block-header">
              <span class="code-lang">PHP</span>
              <button class="copy-btn" onclick="copyCode(this)"><i class="bi bi-clipboard me-1"></i>Copy</button>
            </div>
            <pre><code><span class="tok-k">$serverKey</span> = <span class="tok-s">'SK-Live-xxxx'</span>;
<span class="tok-k">$response</span>  = <span class="tok-f">file_get_contents</span>(<span class="tok-s">'https://api.solusimu.id/v2/charge'</span>, <span class="tok-n">false</span>, stream_context_create([
    <span class="tok-s">'http'</span> => [
        <span class="tok-s">'method'</span>  => <span class="tok-s">'POST'</span>,
        <span class="tok-s">'header'</span>  => <span class="tok-s">"Authorization: Basic "</span> . <span class="tok-f">base64_encode</span>(<span class="tok-k">$serverKey</span> . <span class="tok-s">':'</span>),
        <span class="tok-s">'content'</span> => <span class="tok-f">json_encode</span>([<span class="tok-s">'amount'</span> => <span class="tok-n">50000</span>, <span class="tok-s">'method'</span> => <span class="tok-s">'qris'</span>]),
    ],
]));</code></pre>
          </div>

          <div style="background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.2);border-radius:12px;padding:1rem;font-size:0.82rem;color:#f59e0b;margin-top:1rem;">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Penting:</strong> Jangan pernah membagikan <strong>Server Key</strong> di frontend atau repository publik. Gunakan <strong>Client Key</strong> untuk integrasi JavaScript di sisi klien.
          </div>
        </section>

        <!-- ── BASE URL ───────────────────────────────── -->
        <section id="base-url">
          <h2>Base URL</h2>
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <div style="background:rgba(16,185,129,0.06);border:1px solid rgba(16,185,129,0.2);border-radius:14px;padding:1.25rem;">
                <div style="font-size:0.7rem;font-weight:700;color:#10b981;text-transform:uppercase;letter-spacing:1px;margin-bottom:0.5rem;">🟢 Production</div>
                <code style="color:#e2e8f0;font-size:0.82rem;">https://api.solusimu.id/v2</code>
              </div>
            </div>
            <div class="col-md-6">
              <div style="background:rgba(245,158,11,0.06);border:1px solid rgba(245,158,11,0.2);border-radius:14px;padding:1.25rem;">
                <div style="font-size:0.7rem;font-weight:700;color:#f59e0b;text-transform:uppercase;letter-spacing:1px;margin-bottom:0.5rem;">🟡 Sandbox</div>
                <code style="color:#e2e8f0;font-size:0.82rem;">https://sandbox.solusimu.id/v2</code>
              </div>
            </div>
          </div>
          <p>Semua endpoint menggunakan HTTPS. Request HTTP biasa akan di-redirect ke HTTPS secara otomatis.</p>
        </section>

        <!-- ── ERRORS ─────────────────────────────────── -->
        <section id="errors">
          <h2>Error Handling</h2>
          <p>API menggunakan HTTP status code standar. Setiap error response menyertakan <code>error_code</code> dan <code>message</code> yang menjelaskan penyebab error.</p>

          <div class="code-block">
            <div class="code-block-header"><span class="code-lang">Response Error</span></div>
            <pre><code>{
  <span class="tok-k">"status"</span>     : <span class="tok-s">"error"</span>,
  <span class="tok-k">"error_code"</span> : <span class="tok-s">"INSUFFICIENT_BALANCE"</span>,
  <span class="tok-k">"message"</span>    : <span class="tok-s">"Saldo tidak mencukupi untuk transaksi ini"</span>,
  <span class="tok-k">"request_id"</span> : <span class="tok-s">"req_abc123xyz"</span>
}</code></pre>
          </div>

          <table class="param-table">
            <thead><tr><th>HTTP Code</th><th>Keterangan</th></tr></thead>
            <tbody>
              <tr><td><code>200 OK</code></td><td>Request berhasil</td></tr>
              <tr><td><code>201 Created</code></td><td>Transaksi berhasil dibuat</td></tr>
              <tr><td><code>400 Bad Request</code></td><td>Parameter tidak valid atau tidak lengkap</td></tr>
              <tr><td><code>401 Unauthorized</code></td><td>API key tidak valid atau tidak disertakan</td></tr>
              <tr><td><code>403 Forbidden</code></td><td>Tidak punya akses ke resource ini</td></tr>
              <tr><td><code>404 Not Found</code></td><td>Transaksi atau resource tidak ditemukan</td></tr>
              <tr><td><code>429 Too Many Requests</code></td><td>Rate limit terlampaui (100 req/menit)</td></tr>
              <tr><td><code>500 Server Error</code></td><td>Kesalahan internal server</td></tr>
            </tbody>
          </table>
        </section>

        <!-- ── CREATE CHARGE ─────────────────────────── -->
        <section id="create-charge">
          <h2>Buat Transaksi</h2>
          <div class="endpoint-row">
            <span class="http-badge http-post">POST</span>
            <span class="endpoint-path">/v2/charge</span>
            <span class="endpoint-desc">Membuat transaksi pembayaran baru</span>
          </div>

          <h3>Request Body</h3>
          <table class="param-table">
            <thead><tr><th>Parameter</th><th>Tipe</th><th>Wajib</th><th>Keterangan</th></tr></thead>
            <tbody>
              <tr><td><code>amount</code></td><td>integer</td><td><span class="req-badge">required</span></td><td>Nominal pembayaran dalam Rupiah (min: 1000)</td></tr>
              <tr><td><code>method</code></td><td>string</td><td><span class="req-badge">required</span></td><td>Kode metode: <code>qris</code>, <code>gopay</code>, <code>ovo</code>, <code>bca</code>, <code>bni</code>, dll.</td></tr>
              <tr><td><code>order_id</code></td><td>string</td><td><span class="req-badge">required</span></td><td>ID order unik dari sistem Anda (maks. 36 karakter)</td></tr>
              <tr><td><code>customer_name</code></td><td>string</td><td><span class="opt-badge">optional</span></td><td>Nama pelanggan</td></tr>
              <tr><td><code>customer_email</code></td><td>string</td><td><span class="opt-badge">optional</span></td><td>Email pelanggan</td></tr>
              <tr><td><code>description</code></td><td>string</td><td><span class="opt-badge">optional</span></td><td>Keterangan transaksi</td></tr>
              <tr><td><code>expired_at</code></td><td>integer</td><td><span class="opt-badge">optional</span></td><td>Batas waktu pembayaran (Unix timestamp, default: +1 jam)</td></tr>
              <tr><td><code>metadata</code></td><td>object</td><td><span class="opt-badge">optional</span></td><td>Data tambahan bebas (akan dikembalikan di callback)</td></tr>
            </tbody>
          </table>

          <div class="code-block">
            <div class="code-block-header">
              <span class="code-lang">Request</span>
              <button class="copy-btn" onclick="copyCode(this)"><i class="bi bi-clipboard me-1"></i>Copy</button>
            </div>
            <pre><code>{
  <span class="tok-k">"amount"</span>         : <span class="tok-n">150000</span>,
  <span class="tok-k">"method"</span>         : <span class="tok-s">"qris"</span>,
  <span class="tok-k">"order_id"</span>       : <span class="tok-s">"ORD-20240101-001"</span>,
  <span class="tok-k">"customer_name"</span>  : <span class="tok-s">"Budi Santoso"</span>,
  <span class="tok-k">"customer_email"</span> : <span class="tok-s">"budi@example.com"</span>,
  <span class="tok-k">"description"</span>    : <span class="tok-s">"Pembelian Paket Premium"</span>,
  <span class="tok-k">"metadata"</span>       : { <span class="tok-k">"product_id"</span>: <span class="tok-s">"PRD-007"</span> }
}</code></pre>
          </div>

          <div class="code-block">
            <div class="code-block-header">
              <span class="code-lang">Response 201</span>
            </div>
            <pre><code>{
  <span class="tok-k">"status"</span>       : <span class="tok-s">"pending"</span>,
  <span class="tok-k">"transaction_id"</span>: <span class="tok-s">"TXN-20240101-XK7P2Q"</span>,
  <span class="tok-k">"order_id"</span>     : <span class="tok-s">"ORD-20240101-001"</span>,
  <span class="tok-k">"amount"</span>       : <span class="tok-n">150000</span>,
  <span class="tok-k">"fee"</span>          : <span class="tok-n">0</span>,
  <span class="tok-k">"total"</span>        : <span class="tok-n">150000</span>,
  <span class="tok-k">"method"</span>       : <span class="tok-s">"qris"</span>,
  <span class="tok-k">"qr_url"</span>       : <span class="tok-s">"https://api.solusimu.id/qr/TXN-20240101-XK7P2Q.png"</span>,
  <span class="tok-k">"expired_at"</span>   : <span class="tok-n">1704067200</span>,
  <span class="tok-k">"created_at"</span>   : <span class="tok-s">"2024-01-01T10:00:00Z"</span>
}</code></pre>
          </div>
        </section>

        <!-- ── GET STATUS ─────────────────────────────── -->
        <section id="get-status">
          <h2>Cek Status Transaksi</h2>
          <div class="endpoint-row">
            <span class="http-badge http-get">GET</span>
            <span class="endpoint-path">/v2/transaction/{transaction_id}</span>
            <span class="endpoint-desc">Mengambil detail & status transaksi</span>
          </div>

          <div class="code-block">
            <div class="code-block-header">
              <span class="code-lang">cURL</span>
              <button class="copy-btn" onclick="copyCode(this)"><i class="bi bi-clipboard me-1"></i>Copy</button>
            </div>
            <pre><code><span class="tok-f">curl</span> -u <span class="tok-s">"SK-Live-xxxx:"</span> \
  <span class="tok-s">"https://api.solusimu.id/v2/transaction/TXN-20240101-XK7P2Q"</span></code></pre>
          </div>

          <h3>Status Transaksi</h3>
          <table class="param-table">
            <thead><tr><th>Status</th><th>Keterangan</th></tr></thead>
            <tbody>
              <tr><td><span class="tx-badge tx-pending">pending</span></td><td>Menunggu pembayaran dari pelanggan</td></tr>
              <tr><td><span class="tx-badge tx-success">success</span></td><td>Pembayaran berhasil dikonfirmasi</td></tr>
              <tr><td><span class="tx-badge tx-failed">failed</span></td><td>Pembayaran gagal atau ditolak</td></tr>
              <tr><td><span class="tx-badge tx-cancelled">cancelled</span></td><td>Transaksi dibatalkan oleh merchant/pelanggan</td></tr>
              <tr><td><span class="tx-badge tx-refunded">refunded</span></td><td>Dana telah dikembalikan ke pelanggan</td></tr>
            </tbody>
          </table>
        </section>

        <!-- ── CANCEL ────────────────────────────────── -->
        <section id="cancel">
          <h2>Batalkan Transaksi</h2>
          <div class="endpoint-row">
            <span class="http-badge http-post">POST</span>
            <span class="endpoint-path">/v2/transaction/{transaction_id}/cancel</span>
            <span class="endpoint-desc">Membatalkan transaksi yang masih pending</span>
          </div>
          <p>Transaksi hanya bisa dibatalkan jika statusnya masih <span class="tx-badge tx-pending" style="font-size:0.7rem;">pending</span>. Setelah berhasil dibayar, gunakan endpoint <a href="#refund" style="color:var(--primary-light);">Refund</a>.</p>
        </section>

        <!-- ── REFUND ─────────────────────────────────── -->
        <section id="refund">
          <h2>Refund</h2>
          <div class="endpoint-row">
            <span class="http-badge http-post">POST</span>
            <span class="endpoint-path">/v2/refund</span>
            <span class="endpoint-desc">Mengembalikan dana transaksi yang sudah sukses</span>
          </div>

          <table class="param-table">
            <thead><tr><th>Parameter</th><th>Tipe</th><th>Wajib</th><th>Keterangan</th></tr></thead>
            <tbody>
              <tr><td><code>transaction_id</code></td><td>string</td><td><span class="req-badge">required</span></td><td>ID transaksi yang akan di-refund</td></tr>
              <tr><td><code>amount</code></td><td>integer</td><td><span class="opt-badge">optional</span></td><td>Nominal refund (bisa parsial, default: full refund)</td></tr>
              <tr><td><code>reason</code></td><td>string</td><td><span class="opt-badge">optional</span></td><td>Alasan refund</td></tr>
            </tbody>
          </table>
        </section>

        <!-- ── QRIS ──────────────────────────────────── -->
        <section id="qris">
          <h2><i class="bi bi-phone me-2" style="color:#00d4ff;"></i>QRIS</h2>
          <p>QRIS (Quick Response Code Indonesian Standard) memungkinkan pelanggan membayar menggunakan aplikasi e-wallet apapun. SolusiMu menggunakan QRIS berstandar Bank Indonesia.</p>
          <ul>
            <li>Fee: <strong>0% (gratis)</strong></li>
            <li>Waktu expire: <strong>30 menit</strong></li>
            <li>Limit: <strong>Rp 500.000 per transaksi</strong></li>
            <li>Metode: <code>qris</code></li>
          </ul>
          <div class="code-block">
            <div class="code-block-header"><span class="code-lang">Response QR</span></div>
            <pre><code>{
  <span class="tok-k">"qr_url"</span>    : <span class="tok-s">"https://api.solusimu.id/qr/TXN-xxxxx.png"</span>,
  <span class="tok-k">"qr_string"</span> : <span class="tok-s">"00020101021126660014ID.CO.SOLUSIMU.WWW..."</span>,
  <span class="tok-k">"expired_at"</span>: <span class="tok-n">1704069000</span>
}</code></pre>
          </div>
        </section>

        <!-- ── E-WALLET ───────────────────────────────── -->
        <section id="ewallet">
          <h2><i class="bi bi-wallet2 me-2" style="color:#f72585;"></i>E-Wallet</h2>
          <p>Dukung semua e-wallet populer di Indonesia dengan redirect flow atau deeplink ke aplikasi masing-masing.</p>
          <table class="param-table">
            <thead><tr><th>Metode</th><th>Code</th><th>Fee</th><th>Limit</th></tr></thead>
            <tbody>
              <tr><td><i class="bi bi-phone me-1" style="color:#00aab5;"></i> GoPay</td><td><code>gopay</code></td><td>2%</td><td>Rp 20.000.000</td></tr>
              <tr><td><i class="bi bi-phone me-1" style="color:#4c2a96;"></i> OVO</td><td><code>ovo</code></td><td>2%</td><td>Rp 20.000.000</td></tr>
              <tr><td><i class="bi bi-phone me-1" style="color:#0077b5;"></i> DANA</td><td><code>dana</code></td><td>1.5%</td><td>Rp 10.000.000</td></tr>
              <tr><td><i class="bi bi-phone me-1" style="color:#ff7300;"></i> ShopeePay</td><td><code>shopeepay</code></td><td>2%</td><td>Rp 20.000.000</td></tr>
              <tr><td><i class="bi bi-phone me-1" style="color:#ef4444;"></i> LinkAja</td><td><code>linkaja</code></td><td>1.5%</td><td>Rp 10.000.000</td></tr>
            </tbody>
          </table>
        </section>

        <!-- ── BANK TRANSFER ──────────────────────────── -->
        <section id="bank-transfer">
          <h2><i class="bi bi-bank me-2" style="color:#10b981;"></i>Transfer Bank (VA)</h2>
          <p>Virtual Account (VA) memungkinkan pelanggan melakukan pembayaran melalui ATM, internet banking, atau mobile banking ke nomor VA unik yang di-generate untuk setiap transaksi.</p>
          <table class="param-table">
            <thead><tr><th>Bank</th><th>Code</th><th>Fee</th></tr></thead>
            <tbody>
              <tr><td>BCA Virtual Account</td><td><code>bca</code></td><td>Rp 4.000</td></tr>
              <tr><td>BNI Virtual Account</td><td><code>bni</code></td><td>Rp 4.000</td></tr>
              <tr><td>Mandiri Bill</td><td><code>mandiri</code></td><td>Rp 4.000</td></tr>
              <tr><td>BRI Virtual Account</td><td><code>bri</code></td><td>Rp 4.000</td></tr>
              <tr><td>Permata Virtual Account</td><td><code>permata</code></td><td>Rp 4.000</td></tr>
            </tbody>
          </table>
        </section>

        <!-- ── CREDIT CARD ────────────────────────────── -->
        <section id="credit-card">
          <h2><i class="bi bi-credit-card me-2" style="color:#6c63ff;"></i>Kartu Kredit / Debit</h2>
          <p>Dukung pembayaran dengan Visa, Mastercard, dan JCB. Semua transaksi kartu dilindungi dengan 3D Secure (3DS).</p>
          <ul>
            <li>Fee: <strong>2.9% + Rp 2.000</strong></li>
            <li>Mendukung cicilan 3, 6, 12 bulan (pilihan bank tertentu)</li>
            <li>Metode: <code>credit_card</code></li>
            <li>3DS wajib diaktifkan di akun Anda</li>
          </ul>
        </section>

        <!-- ── WEBHOOK ────────────────────────────────── -->
        <section id="webhook">
          <h2>Webhook / Notifikasi</h2>
          <p>SolusiMu akan mengirim HTTP POST ke URL webhook Anda setiap kali status transaksi berubah. Konfigurasi URL webhook di <strong>Dashboard → Pengaturan → Webhook</strong>.</p>

          <h3>Verifikasi Signature</h3>
          <p>Setiap webhook menyertakan header <code>X-SolusiMu-Signature</code> untuk memvalidasi keaslian request:</p>

          <div class="code-block">
            <div class="code-block-header">
              <span class="code-lang">PHP – Verifikasi Webhook</span>
              <button class="copy-btn" onclick="copyCode(this)"><i class="bi bi-clipboard me-1"></i>Copy</button>
            </div>
            <pre><code><span class="tok-k">$serverKey</span> = <span class="tok-s">'SK-Live-xxxx'</span>;
<span class="tok-k">$payload</span>   = <span class="tok-f">file_get_contents</span>(<span class="tok-s">'php://input'</span>);
<span class="tok-k">$signature</span> = <span class="tok-f">hash_hmac</span>(<span class="tok-s">'sha256'</span>, <span class="tok-k">$payload</span>, <span class="tok-k">$serverKey</span>);

<span class="tok-k">if</span> (<span class="tok-k">$signature</span> !== <span class="tok-k">$_SERVER</span>[<span class="tok-s">'HTTP_X_SOLUSIMU_SIGNATURE'</span>]) {
    <span class="tok-f">http_response_code</span>(<span class="tok-n">401</span>);
    <span class="tok-f">exit</span>(<span class="tok-s">'Signature tidak valid'</span>);
}

<span class="tok-k">$data</span> = <span class="tok-f">json_decode</span>(<span class="tok-k">$payload</span>, <span class="tok-n">true</span>);
<span class="tok-c">// Proses status transaksi...</span></code></pre>
          </div>

          <div class="code-block">
            <div class="code-block-header"><span class="code-lang">Contoh Payload Webhook</span></div>
            <pre><code>{
  <span class="tok-k">"event"</span>          : <span class="tok-s">"transaction.success"</span>,
  <span class="tok-k">"transaction_id"</span> : <span class="tok-s">"TXN-20240101-XK7P2Q"</span>,
  <span class="tok-k">"order_id"</span>       : <span class="tok-s">"ORD-20240101-001"</span>,
  <span class="tok-k">"status"</span>         : <span class="tok-s">"success"</span>,
  <span class="tok-k">"amount"</span>         : <span class="tok-n">150000</span>,
  <span class="tok-k">"paid_at"</span>        : <span class="tok-s">"2024-01-01T10:05:23Z"</span>,
  <span class="tok-k">"metadata"</span>       : { <span class="tok-k">"product_id"</span>: <span class="tok-s">"PRD-007"</span> }
}</code></pre>
          </div>
        </section>

        <!-- ── SANDBOX ────────────────────────────────── -->
        <section id="sandbox">
          <h2>Sandbox Testing</h2>
          <p>Gunakan environment <strong>Sandbox</strong> untuk testing tanpa biaya nyata. Dapatkan Sandbox Key dari dashboard Anda.</p>

          <div class="row g-3">
            <div class="col-md-6">
              <div style="background:var(--bg-card);border:1px solid var(--border-glass);border-radius:14px;padding:1.25rem;">
                <div style="font-size:0.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:0.75rem;">Kartu Sukses</div>
                <code style="font-size:0.8rem;color:#e2e8f0;">4811 1111 1111 1114</code><br/>
                <small style="color:var(--text-muted);">CVV: 123 | Exp: 12/30</small>
              </div>
            </div>
            <div class="col-md-6">
              <div style="background:var(--bg-card);border:1px solid var(--border-glass);border-radius:14px;padding:1.25rem;">
                <div style="font-size:0.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:0.75rem;">Kartu Gagal</div>
                <code style="font-size:0.8rem;color:#e2e8f0;">4911 1111 1111 1113</code><br/>
                <small style="color:var(--text-muted);">CVV: 123 | Exp: 12/30</small>
              </div>
            </div>
          </div>

          <div style="background:rgba(108,99,255,0.08);border:1px solid rgba(108,99,255,0.2);border-radius:12px;padding:1rem;margin-top:1rem;font-size:0.82rem;color:var(--text-secondary);">
            <i class="bi bi-info-circle-fill me-2" style="color:var(--primary-light);"></i>
            Untuk QRIS sandbox, scan QR yang di-generate lalu pilih <strong>"Simulasi Pembayaran Berhasil"</strong> di halaman redirect.
          </div>
        </section>

        <!-- ── SDK ───────────────────────────────────── -->
        <section id="sdk">
          <h2>SDK & Library</h2>
          <div class="row g-3">
            <?php
            $sdks = [
              ['bi bi-filetype-php','PHP','Mendukung PHP 7.4+','composer require solusimu/php-sdk','#6c63ff'],
              ['bi bi-node-plus',   'Node.js','Mendukung Node 16+','npm install solusimu-sdk','#10b981'],
              ['bi bi-filetype-py', 'Python','Mendukung Python 3.8+','pip install solusimu','#f59e0b'],
              ['bi bi-cup-hot',     'Java','Mendukung Java 8+','Maven / Gradle tersedia','#ef4444'],
            ];
            foreach ($sdks as [$icon, $name, $desc, $install, $color]):
            ?>
            <div class="col-sm-6">
              <div style="background:var(--bg-card);border:1px solid var(--border-glass);border-radius:16px;padding:1.25rem;transition:all 0.25s ease;" onmouseover="this.style.borderColor='<?= $color ?>44'" onmouseout="this.style.borderColor='var(--border-glass)'">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:0.75rem;">
                  <div style="width:40px;height:40px;border-radius:10px;background:<?= $color ?>22;display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:<?= $color ?>;">
                    <i class="<?= $icon ?>"></i>
                  </div>
                  <div>
                    <div style="font-weight:700;font-size:0.9rem;"><?= $name ?></div>
                    <div style="font-size:0.72rem;color:var(--text-muted);"><?= $desc ?></div>
                  </div>
                </div>
                <code style="font-size:0.75rem;color:var(--text-muted);display:block;background:rgba(0,0,0,0.25);padding:6px 10px;border-radius:8px;"><?= $install ?></code>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </section>

        <!-- ── CHANGELOG ─────────────────────────────── -->
        <section id="changelog">
          <h2>Changelog</h2>
          <?php
          $changes = [
            ['v2.1.0', '2024-02-01', 'Tambahan dukungan ShopeePay & LinkAja. Perbaikan rate limit header. Auto Refund endpoint baru.'],
            ['v2.0.0', '2024-01-01', 'Major release: dukungan QRIS, multi-currency, webhook signature v2, SDK v2 untuk semua bahasa.'],
            ['v1.5.3', '2023-11-15', 'Perbaikan bug pada endpoint cancel. Tambahan field metadata di response.'],
            ['v1.5.0', '2023-10-01', 'Dukungan Paylater (Kredivo & Akulaku). Dashboard analytics API.'],
          ];
          foreach ($changes as [$ver, $date, $desc]):
          ?>
          <div style="display:flex;gap:1rem;padding:1rem 0;border-bottom:1px solid var(--border-glass);">
            <div style="min-width:80px;">
              <span style="background:rgba(108,99,255,0.12);color:var(--primary-light);font-size:0.72rem;font-weight:700;padding:3px 8px;border-radius:6px;"><?= $ver ?></span>
            </div>
            <div>
              <div style="font-size:0.72rem;color:var(--text-muted);margin-bottom:4px;"><?= $date ?></div>
              <div style="font-size:0.85rem;color:var(--text-secondary);"><?= $desc ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </section>

      </div><!-- .docs-content -->

      <!-- ====== RIGHT: Quick Links ====== -->
      <div class="col-lg-3 d-none d-xl-block">
        <div style="position:sticky;top:5rem;padding-left:1.5rem;">
          <!-- Sandbox CTA -->
          <div style="background:linear-gradient(135deg,rgba(108,99,255,0.15),rgba(0,212,255,0.08));border:1px solid rgba(108,99,255,0.25);border-radius:20px;padding:1.5rem;margin-bottom:1.5rem;">
            <div style="font-size:0.7rem;font-weight:700;color:var(--primary-light);text-transform:uppercase;letter-spacing:1px;margin-bottom:0.75rem;">🚀 Mulai Sekarang</div>
            <p style="font-size:0.8rem;color:var(--text-secondary);margin-bottom:1rem;line-height:1.6;">Daftar gratis dan dapatkan API Key sandbox untuk mulai testing.</p>
            <a href="register.php" class="btn btn-primary-gradient w-100 py-2" style="font-size:0.82rem;">
              <i class="bi bi-rocket-takeoff me-1"></i>Daftar Gratis
            </a>
          </div>

          <!-- API Keys -->
          <div style="background:var(--bg-card);border:1px solid var(--border-glass);border-radius:16px;padding:1.25rem;margin-bottom:1.5rem;">
            <div style="font-size:0.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:1rem;">Format API Key</div>
            <div style="margin-bottom:0.75rem;">
              <div style="font-size:0.68rem;color:var(--text-muted);margin-bottom:3px;">Production</div>
              <code style="font-size:0.75rem;color:#10b981;">SK-Live-xxxxxxxxxxxx</code>
            </div>
            <div>
              <div style="font-size:0.68rem;color:var(--text-muted);margin-bottom:3px;">Sandbox</div>
              <code style="font-size:0.75rem;color:#f59e0b;">SK-Sandbox-xxxxxxxxxxxx</code>
            </div>
          </div>

          <!-- Support -->
          <div style="background:var(--bg-card);border:1px solid var(--border-glass);border-radius:16px;padding:1.25rem;">
            <div style="font-size:0.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:1rem;">Butuh Bantuan?</div>
            <div style="display:flex;flex-direction:column;gap:0.5rem;">
              <a href="#" style="display:flex;align-items:center;gap:8px;font-size:0.8rem;color:var(--text-secondary);text-decoration:none;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.04);" class="hover-primary">
                <i class="bi bi-chat-dots" style="color:var(--primary-light);"></i>Live Chat
              </a>
              <a href="#" style="display:flex;align-items:center;gap:8px;font-size:0.8rem;color:var(--text-secondary);text-decoration:none;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.04);">
                <i class="bi bi-envelope" style="color:var(--primary-light);"></i>dev@solusimu.id
              </a>
              <a href="#" style="display:flex;align-items:center;gap:8px;font-size:0.8rem;color:var(--text-secondary);text-decoration:none;padding:6px 0;">
                <i class="bi bi-github" style="color:var(--primary-light);"></i>github.com/solusimu
              </a>
            </div>
          </div>
        </div>
      </div>

    </div><!-- .row -->
  </div><!-- .container-fluid -->
</div><!-- .content-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/main.js"></script>
<script>
// ── Active nav link on scroll ─────────────────────────
const sections = document.querySelectorAll('.docs-content section[id]');
const navLinks  = document.querySelectorAll('.docs-nav-link');
const observer  = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      navLinks.forEach(l => l.classList.remove('active'));
      const active = document.querySelector(`.docs-nav-link[href="#${e.target.id}"]`);
      if (active) active.classList.add('active');
    }
  });
}, { rootMargin: '-70px 0px -70% 0px' });
sections.forEach(s => observer.observe(s));

// ── Copy code button ──────────────────────────────────
function copyCode(btn) {
  const pre = btn.closest('.code-block').querySelector('pre');
  navigator.clipboard.writeText(pre.innerText).then(() => {
    btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Disalin!';
    btn.style.color = '#10b981';
    setTimeout(() => {
      btn.innerHTML = '<i class="bi bi-clipboard me-1"></i>Copy';
      btn.style.color = '';
    }, 1800);
  });
}
</script>
</body>
</html>
