# CoreGym Management API

Laravel 13 REST API for the CoreGym gym management system.

## Features

- JWT authentication (RS256) with refresh tokens
- Email OTP registration and password reset
- Firebase Google Sign-In
- Membership packages and Midtrans payments
- Face recognition attendance (via `gym-face-service`)
- Admin dashboard API (`/v1/admin/*`)
- Member mobile API (`/v1/members/*` style routes under `/v1`)

## Requirements

- PHP 8.3+
- PostgreSQL
- Composer, Node.js (for Vite assets)
- `gym-face-service` running on port 8001 (for face features)
- Firebase service account JSON at `storage/app/firebase/service-account.json` (for Google login)

## Quick start

See the full local setup guide: [`../panduan/PANDUAN_MENJALANKAN.md`](../panduan/PANDUAN_MENJALANKAN.md)

```bash
cp .env.example .env
composer install
php artisan key:generate

mkdir -p storage/app/jwt
openssl genrsa -out storage/app/jwt/private.pem 2048
openssl rsa -in storage/app/jwt/private.pem -pubout -out storage/app/jwt/public.pem
chmod 600 storage/app/jwt/private.pem

php artisan migrate
php artisan db:seed
php artisan storage:link
```

### Development (API + queue + logs + Vite)

OTP emails require a queue worker when `QUEUE_CONNECTION=database`:

```bash
composer dev
```

Or run separately:

```bash
php artisan serve --host=0.0.0.0 --port=8000
php artisan queue:work
```

## API documentation

- Swagger UI: `http://localhost:8000/docs`
- Auth flow details: [`AUTHENTICATION_OTP_FLOW.md`](AUTHENTICATION_OTP_FLOW.md)

## Production deployment

See [`../panduan/PANDUAN_SETUP_DEBIAN_SERVER.md`](../panduan/PANDUAN_SETUP_DEBIAN_SERVER.md)

## Tests

```bash
composer test
./vendor/bin/pint --test
```

## Related projects

| Project | Purpose |
|---------|---------|
| `gym-admin` | Next.js admin dashboard |
| `gym_mobile_flutter` | Flutter member app |
| `gym-face-service` | Face recognition microservice |
