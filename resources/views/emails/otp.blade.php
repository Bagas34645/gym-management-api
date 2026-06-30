<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Kode OTP Verifikasi</title>
  <style>
    body {
      margin: 0;
      padding: 0;
      background-color: #f4f4f5;
      font-family: Arial, sans-serif;
      color: #18181b;
    }
    .wrapper {
      max-width: 480px;
      margin: 40px auto;
      background-color: #ffffff;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    .header {
      background-color: #18181b;
      padding: 32px 40px;
      text-align: center;
    }
    .header h1 {
      margin: 0;
      color: #ffffff;
      font-size: 20px;
      letter-spacing: 1px;
    }
    .body {
      padding: 40px;
    }
    .body p {
      margin: 0 0 16px;
      font-size: 15px;
      line-height: 1.6;
      color: #3f3f46;
    }
    .otp-box {
      margin: 32px 0;
      text-align: center;
    }
    .otp-code {
      display: inline-block;
      background-color: #f4f4f5;
      border: 2px dashed #d4d4d8;
      border-radius: 10px;
      padding: 18px 40px;
      font-size: 36px;
      font-weight: bold;
      letter-spacing: 10px;
      color: #18181b;
    }
    .expiry {
      text-align: center;
      font-size: 13px;
      color: #a1a1aa;
      margin-top: -16px;
      margin-bottom: 32px;
    }
    .warning {
      background-color: #fef9c3;
      border-left: 4px solid #eab308;
      border-radius: 6px;
      padding: 14px 18px;
      font-size: 13px;
      color: #854d0e;
      line-height: 1.5;
    }
    .footer {
      background-color: #f4f4f5;
      padding: 24px 40px;
      text-align: center;
      font-size: 12px;
      color: #a1a1aa;
    }
  </style>
</head>
<body>
  <div class="wrapper">

    <div class="header">
      <h1>GYM APP</h1>
    </div>

    <div class="body">
      <p>Halo,</p>
      <p>
        Gunakan kode OTP berikut untuk memverifikasi akun Anda.
        Jangan bagikan kode ini kepada siapapun.
      </p>

      <div class="otp-box">
        <span class="otp-code">{{ $code }}</span>
      </div>

      <p class="expiry">
        Kode ini berlaku selama
        <strong>{{ (int) ($ttl / 60) }} menit</strong>.
      </p>

      <div class="warning">
        ⚠️ Jika Anda tidak merasa mendaftar atau meminta kode ini,
        abaikan email ini. Akun Anda tetap aman.
      </div>
    </div>

    <div class="footer">
      &copy; {{ date('Y') }} Gym App. Semua hak dilindungi.<br/>
      Email ini dikirim otomatis, mohon tidak membalas.
    </div>

  </div>
</body>
</html>