# Database Schema — Gym Management API

**Database:** `gym_management_api` (PostgreSQL 17)  
**Domain tables:** 27 (+ Laravel support: `cache`, `jobs`, `sessions`, `migrations`)

## Authentication (5)

| Table | PK | Notes |
|-------|-----|-------|
| `users` | UUID | Soft deletes; role/status enums |
| `roles` | BIGINT | member, admin, super_admin |
| `permissions` | BIGINT | Granular API permissions |
| `role_permissions` | BIGINT | Junction |
| `refresh_tokens` | BIGINT | JWT refresh storage |

## Membership (4)

| Table | PK | Notes |
|-------|-----|-------|
| `membership_packages` | UUID | daily/weekly/monthly/yearly |
| `memberships` | UUID | One active per user (partial unique index) |
| `membership_renewals` | UUID | Soft deletes |
| `payment_records` | UUID | Unique `reference_number` |

## Attendance (2)

| Table | PK | Notes |
|-------|-----|-------|
| `face_registrations` | UUID | `face_embedding` bytea, 1:1 user |
| `attendance_records` | UUID | Face verification status |

## Trainer (3)

| Table | PK | Notes |
|-------|-----|-------|
| `trainers` | UUID | 1:1 with user |
| `trainer_schedules` | UUID | day_of_week 0–6 |
| `trainer_bookings` | UUID | Unique per trainer/schedule/date |

## Programs (4)

| Table | PK | Notes |
|-------|-----|-------|
| `workout_plans` | UUID | Optional trainer |
| `workout_plan_exercises` | BIGINT | Junction with sets/reps |
| `exercises` | UUID | Muscle group + difficulty |
| `workout_logs` | UUID | JSON exercise payload |

## Progress (1)

| Table | PK | Notes |
|-------|-----|-------|
| `progress_weight` | UUID | Unique (user_id, recorded_at); soft deletes |

## Notification (2)

| Table | PK | Notes |
|-------|-----|-------|
| `notifications` | UUID | Read tracking |
| `notification_preferences` | UUID | 1:1 user |

## Communication (5)

| Table | PK | Notes |
|-------|-----|-------|
| `chat_conversations` | UUID | member + optional admin |
| `chat_messages` | UUID | Per conversation |
| `faq_categories` | BIGINT | Ordered categories |
| `faqs` | BIGINT | Linked to category |
| `feedback` | UUID | Soft deletes; 1–5 rating |

## Audit (1)

| Table | PK | Notes |
|-------|-----|-------|
| `audit_logs` | BIGINT | JSONB `changes` |

## PostgreSQL extensions

- `uuid-ossp`, `pgcrypto`, `pg_trgm`
- 18 native ENUM types in `database/sql/enums.sql`

## Key constraints

- `memberships`: `end_date > start_date`; partial unique on `user_id` WHERE `status = 'active'`
- Check constraints on amounts, ratings, schedule times, capacity
- FK cascade/restrict per business rules in migrations
