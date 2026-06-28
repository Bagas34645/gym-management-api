# Email OTP Authentication Flow

Dokumentasi lengkap untuk sistem verifikasi pendaftaran dengan OTP email di Gym Management API.

## Daftar Isi

1. [Alur Sistem](#alur-sistem)
2. [Cara Kerja Teknis](#cara-kerja-teknis)
3. [Testing API](#testing-api)
4. [Troubleshooting](#troubleshooting)

---

## Alur Sistem

### Diagram Alur Registrasi & Verifikasi

```
┌─────────────────────────────────────────────────────────────┐
│                    USER FLOW - AUTHENTICATION               │
└─────────────────────────────────────────────────────────────┘

1. REGISTER
   ├─ User submit: POST /v1/auth/register
   │  (name, email, phone, password)
   ├─ Server: Create user dengan is_verified = false
   ├─ Server: Generate 6-digit OTP code
   ├─ Server: Hash OTP dan simpan di DB (email_otps table)
   ├─ Server: Queue email OTP ke user
   └─ Response: 201 Created (tanpa token)
       └─ { id, name, email, phone, expires_in }

2. RESEND OTP (Opsional)
   ├─ User submit: POST /v1/auth/resend-otp
   │  (email)
   ├─ Server: Check is_verified = false
   ├─ Server: Increment resend counter (limit 3/jam)
   ├─ Server: Generate kode OTP baru
   ├─ Server: Queue email OTP lagi
   └─ Response: 200 OK { expires_in }

3. VERIFY OTP
   ├─ User submit: POST /v1/auth/verify-otp
   │  (email, code)
   ├─ Server: Query email_otps table
   ├─ Server: Hash check code vs stored hash
   ├─ Server: Cek expires_at belum lewat
   ├─ Server: Update user is_verified = true
   ├─ Server: Delete/invalidate OTP record
   └─ Response: 200 OK "OTP terverifikasi"

4. LOGIN
   ├─ User submit: POST /v1/auth/login
   │  (identifier: email/phone, password)
   ├─ Server: Cari user by email/phone
   ├─ Server: Check password hash
   ├─ Server: Check is_verified = true ← (BARU)
   │  └─ Jika false → Error 403 "Akun belum terverifikasi"
   ├─ Server: Generate JWT access_token & refresh_token
   └─ Response: 200 OK { access_token, refresh_token, member }

5. AUTHENTICATED REQUESTS
   └─ Gunakan access_token di header: Authorization: Bearer <token>
```

---

### Sequence Diagram Lengkap

```
Client                                Server                    Database
  │                                      │                           │
  ├─ POST /v1/auth/register ────────────>│                           │
  │  { name, email, phone, password }   │                           │
  │                                      ├─ Validate input          │
  │                                      ├─ Hash password           │
  │                                      ├─ Create user             │
  │                                      ├─────────────────────────>│
  │                                      │   INSERT users           │
  │                                      │   (is_verified=false)    │
  │                                      │<─────────────────────────┤
  │                                      ├─ Generate 6-digit OTP   │
  │                                      ├─ Hash OTP               │
  │                                      ├─────────────────────────>│
  │                                      │   INSERT email_otps      │
  │                                      │   (code_hash, expires_at)│
  │                                      │<─────────────────────────┤
  │                                      ├─ Queue OtpMail          │
  │                                      ├─────────────────────────>│
  │                                      │   INSERT queue jobs      │
  │                                      │<─────────────────────────┤
  │<─ 201 { id, email, expires_in } ────┤                           │
  │                                      │                           │
  │    [Queue Worker Processing...]     │                           │
  │                                      ├─ Process job             │
  │                                      ├─ Send email OTP          │
  │                                      │  (atau log ke storage)   │
  │                                      ├─ DELETE queue jobs       │
  │                                      ├─────────────────────────>│
  │                                      │   DELETE queues          │
  │                                      │<─────────────────────────┤
  │                                      │                           │
  │<─────── EMAIL RECEIVED ──────────────│                           │
  │  [Kode: 123456]                     │                           │
  │                                      │                           │
  ├─ POST /v1/auth/verify-otp ─────────>│                           │
  │  { email, code: "123456" }          │                           │
  │                                      ├─ Query email_otps        │
  │                                      ├─────────────────────────>│
  │                                      │   SELECT * WHERE email   │
  │                                      │<─────────────────────────┤
  │                                      ├─ Hash::check code        │
  │                                      ├─ Check expires_at        │
  │                                      ├─ Update user.is_verified │
  │                                      ├─────────────────────────>│
  │                                      │   UPDATE users SET       │
  │                                      │   is_verified=true       │
  │                                      │<─────────────────────────┤
  │                                      ├─ DELETE email_otps       │
  │                                      ├─────────────────────────>│
  │                                      │<─────────────────────────┤
  │<─ 200 "OTP terverifikasi" ──────────┤                           │
  │                                      │                           │
  ├─ POST /v1/auth/login ──────────────>│                           │
  │  { identifier, password }           │                           │
  │                                      ├─ Query user              │
  │                                      ├─────────────────────────>│
  │                                      │   SELECT * WHERE email   │
  │                                      │<─────────────────────────┤
  │                                      ├─ Hash::check password    │
  │                                      ├─ Check is_verified=true  │
  │                                      ├─ Create JWT tokens       │
  │<─ 200 { access_token, ... } ───────┤                           │
  │                                      │                           │
```

---

## Cara Kerja Teknis

### 1. **Database Schema**

#### Tabel: `email_otps`

```sql
CREATE TABLE email_otps (
  id UUID PRIMARY KEY,
  identifier VARCHAR(255) INDEXED,  -- email atau phone
  code_hash VARCHAR(255),             -- hash dari 6-digit code
  method VARCHAR(50),                 -- 'email' atau 'sms'
  expires_at TIMESTAMP,               -- kapan OTP kadaluarsa
  created_at TIMESTAMP,
  updated_at TIMESTAMP
);
```

#### Tabel: `users` (Modified)

```sql
ALTER TABLE users ADD COLUMN is_verified BOOLEAN DEFAULT FALSE;
-- Ditambah setelah email_verified_at
```

### 2. **Model & Service**

#### Model: `EmailOtp`

- Menyimpan hash dari OTP code (bukan plain text)
- `expires_at` menentukan TTL OTP
- Auto-generates UUID untuk `id`

```php
class EmailOtp extends Model {
    protected $fillable = ['id', 'identifier', 'code_hash', 'method', 'expires_at'];
    protected $casts = ['expires_at' => 'datetime'];
}
```

#### Service: `OtpService`

**Method utama:**

- `send(string $identifier, string $method): int`
    - Generate 6-digit OTP
    - Hash dan simpan ke DB
    - Queue email OTP (via `OtpMail`)
    - Limit resend count per jam (3 resend max)
    - Return TTL (detik)

- `verify(string $identifier, string $code): bool`
    - Query latest OTP dari DB
    - Check expires_at
    - Hash::check code
    - Simpan temporary verified state di cache
    - Return true/false

- `clear(string $identifier): void`
    - Delete OTP dari DB
    - Clear cache
    - Reset resend counter

### 3. **Email Mailable**

#### Class: `OtpMail`

- Extend `Mailable implements ShouldQueue`
- Build view `emails.otp`
- Parameter: `code` (string), `ttl` (int detik)

```php
class OtpMail extends Mailable implements ShouldQueue {
    public function __construct(public string $code, public int $ttl) {}
    public function build() {
        return $this->subject('Kode OTP Verifikasi')
                    ->view('emails.otp')
                    ->with(['code' => $this->code, 'ttl' => $this->ttl]);
    }
}
```

#### View: `resources/views/emails/otp.blade.php`

```
Kode OTP Anda: {{ $code }}.

Berlaku selama {{ $ttl }} detik.

Jika Anda tidak meminta kode ini, abaikan email ini.
```

### 4. **Auth Controller Endpoints**

#### POST `/v1/auth/register`

**Request:**

```json
{
    "name": "string (required, max 255)",
    "email": "string (required, unique)",
    "phone": "string (required, regex: 08\\d{8,11}, unique)",
    "password": "string (required, min 8, confirmed)",
    "password_confirmation": "string (required)"
}
```

**Response: 201 Created**

```json
{
    "success": true,
    "message": "Registrasi berhasil. Kode OTP dikirim ke email",
    "data": {
        "id": "uuid",
        "name": "Test User",
        "email": "test@example.com",
        "phone": "081234567890"
    },
    "meta": {
        "expires_in": 300
    }
}
```

#### POST `/v1/auth/verify-otp`

**Request:**

```json
{
    "email": "string (required, email)",
    "code": "string (required, size:6)"
}
```

**Response: 200 OK**

```json
{
    "success": true,
    "message": "OTP terverifikasi",
    "data": null
}
```

**Error: 422 Unprocessable Entity**

```json
{
    "success": false,
    "message": "Kode OTP tidak valid",
    "data": null
}
```

#### POST `/v1/auth/resend-otp`

**Request:**

```json
{
    "email": "string (required, email)"
}
```

**Response: 200 OK**

```json
{
    "success": true,
    "message": "Kode OTP dikirim ulang ke email",
    "data": null,
    "meta": {
        "expires_in": 300
    }
}
```

**Error: 429 Too Many Requests** (limit resend)

```json
{
    "success": false,
    "message": "Batas pengiriman ulang OTP tercapai. Coba lagi nanti.",
    "data": null
}
```

#### POST `/v1/auth/login` (Modified)

**Request:**

```json
{
    "identifier": "string (email or phone)",
    "password": "string",
    "device_token": "string (optional)"
}
```

**Response: 200 OK** (jika verified)

```json
{
    "success": true,
    "message": "Login berhasil",
    "data": {
        "access_token": "eyJhbGc...",
        "refresh_token": "...",
        "token_type": "Bearer",
        "expires_in": 86400,
        "member": {
            "id": "uuid",
            "name": "Test User",
            "membership_status": "inactive"
        }
    }
}
```

**Error: 403 Forbidden** (not verified)

```json
{
    "success": false,
    "message": "Akun belum terverifikasi",
    "data": null
}
```

### 5. **Configuration**

**File: `.env`**

```env
OTP_TTL=300                          # TTL OTP dalam detik (default 5 menit)
LOGIN_MAX_ATTEMPTS=5                 # Max login attempts sebelum lockout
LOGIN_LOCKOUT_MINUTES=15             # Durasi lockout

QUEUE_CONNECTION=database            # Queue driver (gunakan database)
MAIL_MAILER=log                      # Mailer driver (log untuk testing)
MAIL_FROM_ADDRESS=hello@example.com  # Sender email
```

**File: `config/gym.php`**

```php
'otp_ttl' => (int) env('OTP_TTL', 300),  // 300 detik = 5 menit
```

### 6. **Queue Processing**

**Without Queue Worker:**

```php
// Mail langsung dikirim saat endpoint dipanggil
Mail::to($email)->queue(new OtpMail($code, $ttl));
```

**With Queue Worker:**

```bash
php artisan queue:work --tries=3
```

- Job disimpan ke DB
- Worker membaca dan process job
- Email dikirim/dilog
- Jika gagal, retry max 3 kali

---

## Testing API

### Persiapan

#### 1. Jalankan Database Migration

```bash
php artisan migrate
```

#### 2. Jalankan Server (Terminal 1)

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

#### 3. Jalankan Queue Worker (Terminal 2) - Opsional tapi Disarankan

```bash
php artisan queue:work --tries=3
```

Jika tidak menjalankan worker, ubah `QUEUE_CONNECTION=sync` di `.env` untuk immediate processing.

---

### Base URL & Headers

**Base URL:**

```
http://127.0.0.1:8000
```

**Headers (semua request):**

```
Content-Type: application/json
Accept: application/json
```

---

### Test Scenario 1: Register & Verify OTP

#### Step 1: Register User

**Method:** POST  
**URL:** `http://127.0.0.1:8000/v1/auth/register`

**Body:**

```json
{
    "name": "Test User",
    "email": "test@example.com",
    "phone": "081234567890",
    "password": "SecurePassword123",
    "password_confirmation": "SecurePassword123"
}
```

**Expected Response: 201 Created**

```json
{
    "success": true,
    "message": "Registrasi berhasil. Kode OTP dikirim ke email",
    "data": {
        "id": "550e8400-e29b-41d4-a716-446655440000",
        "name": "Test User",
        "email": "test@example.com",
        "phone": "081234567890"
    },
    "meta": {
        "expires_in": 300
    }
}
```

**Status:** ✅ User created, OTP queued/logged

---

#### Step 2: Dapatkan Kode OTP

**Opsi A: Dengan Queue Worker**

```bash
# Terminal 2: Monitor log real-time
Get-Content storage/logs/laravel.log -Tail 50 -Wait
```

Cari log berisi:

```
[2026-06-22 10:30:45] local.INFO: Mailing message [app/Mail/OtpMail]
...
Kode OTP Anda: 123456.
```

**Opsi B: Tanpa Queue Worker**

- Ubah `.env` ke `QUEUE_CONNECTION=sync`
- Ubah `.env` ke `MAIL_MAILER=array` atau buka `storage/logs/laravel.log`

**Opsi C: Cek Database Langsung**

```bash
php artisan tinker
# Dalam tinker shell:
>>> \App\Models\EmailOtp::where('identifier', 'test@example.com')->latest()->first()
```

Catatan: Kode di DB adalah hash, bukan plain text. Cek log untuk plain code.

---

#### Step 3: Verify OTP

**Method:** POST  
**URL:** `http://127.0.0.1:8000/v1/auth/verify-otp`

**Body:**

```json
{
    "email": "test@example.com",
    "code": "123456"
}
```

**Expected Response: 200 OK**

```json
{
    "success": true,
    "message": "OTP terverifikasi",
    "data": null
}
```

**Status:** ✅ User is_verified = true

---

#### Step 4: Login

**Method:** POST  
**URL:** `http://127.0.0.1:8000/v1/auth/login`

**Body:**

```json
{
    "identifier": "test@example.com",
    "password": "SecurePassword123"
}
```

**Expected Response: 200 OK**

```json
{
    "success": true,
    "message": "Login berhasil",
    "data": {
        "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
        "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
        "token_type": "Bearer",
        "expires_in": 86400,
        "member": {
            "id": "550e8400-e29b-41d4-a716-446655440000",
            "name": "Test User",
            "membership_status": "inactive"
        }
    }
}
```

**Status:** ✅ Login berhasil

---

### Test Scenario 2: Resend OTP (Limit Test)

#### Step 1: Resend OTP (1st time)

**Method:** POST  
**URL:** `http://127.0.0.1:8000/v1/auth/resend-otp`

**Body:**

```json
{
    "email": "test@example.com"
}
```

**Expected Response: 200 OK**

```json
{
    "success": true,
    "message": "Kode OTP dikirim ulang ke email",
    "data": null,
    "meta": {
        "expires_in": 300
    }
}
```

---

#### Step 2: Resend OTP (2nd & 3rd time)

Ulangi request di atas → sukses

---

#### Step 3: Resend OTP (4th time - Should Fail)

**Expected Response: 429 Too Many Requests**

```json
{
    "success": false,
    "message": "Batas pengiriman ulang OTP tercapai. Coba lagi nanti.",
    "data": null
}
```

**Status:** ✅ Rate limit works

---

### Test Scenario 3: Login Before Verification (Negative Test)

#### Daftar user baru tanpa verify

```bash
# Register ulang dengan email baru
POST /v1/auth/register
{
  "name": "Unverified User",
  "email": "unverified@example.com",
  "phone": "082345678901",
  "password": "SecurePassword123",
  "password_confirmation": "SecurePassword123"
}
```

#### Coba login tanpa verify OTP

**Method:** POST  
**URL:** `http://127.0.0.1:8000/v1/auth/login`

**Body:**

```json
{
    "identifier": "unverified@example.com",
    "password": "SecurePassword123"
}
```

**Expected Response: 403 Forbidden**

```json
{
    "success": false,
    "message": "Akun belum terverifikasi",
    "data": null
}
```

**Status:** ✅ Login protection works

---

### Test Scenario 4: Invalid OTP Code

#### Verify dengan kode salah

**Method:** POST  
**URL:** `http://127.0.0.1:8000/v1/auth/verify-otp`

**Body:**

```json
{
    "email": "test@example.com",
    "code": "000000"
}
```

**Expected Response: 422 Unprocessable Entity**

```json
{
    "success": false,
    "message": "Kode OTP tidak valid",
    "data": null
}
```

**Status:** ✅ Validation works

---

### Test Scenario 5: Expired OTP

#### Tunggu 300 detik, lalu verify

Setelah TTL terlampaui, verify akan return 422 (OTP tidak valid/expired).

---

### Postman Collection (JSON)

Save sebagai file `.postman_collection.json` lalu import di Postman:

```json
{
    "info": {
        "name": "Email OTP Authentication",
        "description": "API testing untuk Email OTP verification flow"
    },
    "item": [
        {
            "name": "1. Register User",
            "request": {
                "method": "POST",
                "header": [
                    { "key": "Content-Type", "value": "application/json" },
                    { "key": "Accept", "value": "application/json" }
                ],
                "url": {
                    "raw": "http://127.0.0.1:8000/v1/auth/register",
                    "protocol": "http",
                    "host": ["127", "0", "0", "1"],
                    "port": "8000",
                    "path": ["v1", "auth", "register"]
                },
                "body": {
                    "mode": "raw",
                    "raw": "{\"name\":\"Test User\",\"email\":\"test@example.com\",\"phone\":\"081234567890\",\"password\":\"SecurePassword123\",\"password_confirmation\":\"SecurePassword123\"}"
                }
            }
        },
        {
            "name": "2. Verify OTP",
            "request": {
                "method": "POST",
                "header": [
                    { "key": "Content-Type", "value": "application/json" },
                    { "key": "Accept", "value": "application/json" }
                ],
                "url": {
                    "raw": "http://127.0.0.1:8000/v1/auth/verify-otp",
                    "protocol": "http",
                    "host": ["127", "0", "0", "1"],
                    "port": "8000",
                    "path": ["v1", "auth", "verify-otp"]
                },
                "body": {
                    "mode": "raw",
                    "raw": "{\"email\":\"test@example.com\",\"code\":\"123456\"}"
                }
            }
        },
        {
            "name": "3. Resend OTP",
            "request": {
                "method": "POST",
                "header": [
                    { "key": "Content-Type", "value": "application/json" },
                    { "key": "Accept", "value": "application/json" }
                ],
                "url": {
                    "raw": "http://127.0.0.1:8000/v1/auth/resend-otp",
                    "protocol": "http",
                    "host": ["127", "0", "0", "1"],
                    "port": "8000",
                    "path": ["v1", "auth", "resend-otp"]
                },
                "body": {
                    "mode": "raw",
                    "raw": "{\"email\":\"test@example.com\"}"
                }
            }
        },
        {
            "name": "4. Login",
            "request": {
                "method": "POST",
                "header": [
                    { "key": "Content-Type", "value": "application/json" },
                    { "key": "Accept", "value": "application/json" }
                ],
                "url": {
                    "raw": "http://127.0.0.1:8000/v1/auth/login",
                    "protocol": "http",
                    "host": ["127", "0", "0", "1"],
                    "port": "8000",
                    "path": ["v1", "auth", "login"]
                },
                "body": {
                    "mode": "raw",
                    "raw": "{\"identifier\":\"test@example.com\",\"password\":\"SecurePassword123\"}"
                }
            }
        }
    ]
}
```

---

### cURL Commands

#### Register

```bash
curl -X POST http://127.0.0.1:8000/v1/auth/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name":"Test User",
    "email":"test@example.com",
    "phone":"081234567890",
    "password":"SecurePassword123",
    "password_confirmation":"SecurePassword123"
  }'
```

#### Verify OTP

```bash
curl -X POST http://127.0.0.1:8000/v1/auth/verify-otp \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email":"test@example.com",
    "code":"123456"
  }'
```

#### Resend OTP

```bash
curl -X POST http://127.0.0.1:8000/v1/auth/resend-otp \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email":"test@example.com"
  }'
```

#### Login

```bash
curl -X POST http://127.0.0.1:8000/v1/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "identifier":"test@example.com",
    "password":"SecurePassword123"
  }'
```

---

## Troubleshooting

### Problem: 404 pada endpoint

**Solusi:** Pastikan path prefix `/v1/` digunakan, bukan `/api/`

- ✅ Benar: `POST /v1/auth/register`
- ❌ Salah: `POST /api/auth/register`

---

### Problem: OTP tidak masuk ke email

**Penyebab & Solusi:**

1. **Queue tidak diproses**
    - Jalankan: `php artisan queue:work --tries=3`
    - Atau ubah `.env` ke `QUEUE_CONNECTION=sync`

2. **Mailer belum dikonfigurasi**
    - Default `.env`: `MAIL_MAILER=log`
    - Cek log: `storage/logs/laravel.log`

3. **Job gagal di queue**
    - Cek: `php artisan queue:failed`
    - Retry: `php artisan queue:retry all`

---

### Problem: OTP code selalu invalid

**Penyebab & Solusi:**

1. **Code sudah expired** (lewat TTL)
    - Resend OTP baru: `POST /v1/auth/resend-otp`

2. **Code tidak sesuai**
    - Cek log untuk plain code OTP
    - Code di DB hanya hash, buka log file

3. **Email identifier tidak cocok**
    - Verify gunakan email yang sama saat register

---

### Problem: Resend limit error 429

**Solusi:**

- Batas resend: 3 kali per jam
- Tunggu 1 jam atau reset manual:
    ```bash
    php artisan tinker
    >>> cache()->forget('otp_resend:'.hash('sha256', 'test@example.com'))
    >>> exit
    ```

---

### Problem: Migration tidak jalan

**Solusi:**

```bash
# Cek status migration
php artisan migrate:status

# Rollback jika ada error
php artisan migrate:rollback

# Jalankan ulang
php artisan migrate
```

---

### Problem: User tidak ditemukan setelah register

**Solusi:**

1. Cek database:

    ```bash
    php artisan tinker
    >>> \App\Models\User::where('email', 'test@example.com')->first()
    ```

2. Jika tidak ada, cek error di response register

---

### Problem: Access denied setelah login

**Solusi:**

1. Verifikasi `is_verified = true` di DB:

    ```bash
    php artisan tinker
    >>> \App\Models\User::where('email', 'test@example.com')->first()->is_verified
    ```

2. Jika false, verify OTP dulu

---

## Summary

| Tahap      | Endpoint              | Method | Status                 |
| ---------- | --------------------- | ------ | ---------------------- |
| Register   | `/v1/auth/register`   | POST   | 201 ✅                 |
| Resend OTP | `/v1/auth/resend-otp` | POST   | 200 ✅                 |
| Verify OTP | `/v1/auth/verify-otp` | POST   | 200 ✅                 |
| Login      | `/v1/auth/login`      | POST   | 200 ✅ (jika verified) |

---

**Last Updated:** 2026-06-22  
**Version:** 1.0
