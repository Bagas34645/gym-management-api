# ER Diagram — Gym Management API

```
┌─────────────┐     ┌──────────────────┐     ┌─────────────┐
│    roles    │────<│ role_permissions │>────│ permissions │
└─────────────┘     └──────────────────┘     └─────────────┘

┌─────────────┐     ┌─────────────────────┐     ┌──────────────────────┐
│    users    │────<│    memberships      │>────│ membership_packages  │
│  (UUID PK)  │     └─────────────────────┘     └──────────────────────┘
└──────┬──────┘              │
       │                     ├──< membership_renewals
       │                     └──< payment_records
       ├──1:1── face_registrations
       ├──1:1── trainers ──< trainer_schedules ──< trainer_bookings
       ├──1:1── notification_preferences
       ├──1:N── attendance_records
       ├──1:N── workout_plans ──< workout_plan_exercises >── exercises
       ├──1:N── workout_logs
       ├──1:N── progress_weight
       ├──1:N── notifications
       ├──1:N── chat_conversations (member_id) ──< chat_messages
       ├──1:N── feedback
       ├──1:N── refresh_tokens
       └──1:N── audit_logs

┌──────────────┐     ┌──────┐
│ faq_categories│────<│ faqs │
└──────────────┘     └──────┘
```

## Cardinality summary

| Relationship | Type |
|--------------|------|
| User ↔ Trainer | 1:1 |
| User ↔ FaceRegistration | 1:1 |
| User ↔ NotificationPreference | 1:1 |
| User → Membership (active) | 1:1 enforced by DB index |
| Role ↔ Permission | N:M |
| WorkoutPlan ↔ Exercise | N:M via `workout_plan_exercises` |
| Trainer → TrainerSchedule | 1:N |
| ChatConversation → ChatMessage | 1:N |
