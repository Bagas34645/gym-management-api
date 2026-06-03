# Eloquent Models — Gym Management API

All domain models live in `app/Models/`. UUID models use `HasUuids`; soft deletes on `User`, `MembershipRenewal`, `ProgressWeight`, `Feedback`.

## Core models

### User
- **Relations:** `trainer`, `faceRegistration`, `notificationPreference`, `memberships`, `activeMembership`, `attendanceRecords`, `trainerBookings`, `workoutPlans`, `progressWeights`, `notifications`, `chatConversations`, `feedback`, `refreshTokens`, `auditLogs`
- **Scopes:** `active()`, `admins()`, `members()`

### Role / Permission / RolePermission
- Many-to-many via `role_permissions`

### MembershipPackage → Membership → MembershipRenewal / PaymentRecord
- **Scopes:** `active()`, `expired()`, `expiringSoon($days)`

### Trainer → TrainerSchedule → TrainerBooking
- **Scope:** `active()` on Trainer

### WorkoutPlan ↔ Exercise (via WorkoutPlanExercise) → WorkoutLog

### ChatConversation → ChatMessage

### FaqCategory → Faq

### Notification / NotificationPreference / Feedback / AuditLog / FaceRegistration / AttendanceRecord / RefreshToken
- `RefreshToken::valid()` / `active()` for non-revoked tokens

## Factories (`database/factories/`)

`UserFactory`, `TrainerFactory`, `MembershipFactory`, `AttendanceRecordFactory`, `WorkoutPlanFactory`, `WorkoutLogFactory`, `TrainerBookingFactory`, `FeedbackFactory`, `ChatConversationFactory`

## Seeders (`database/seeders/`)

Run order in `DatabaseSeeder`: roles → permissions → packages → exercises → FAQ categories → users → memberships → trainer schedules → workout plans → FAQs.

**Default credentials:** `admin@gym.local` / `superadmin@gym.local` — password: `password`
