-- ====================================================
-- PostgreSQL ENUM Types for Gym Management System
-- ====================================================

-- User roles and status
CREATE TYPE user_role AS ENUM ('member', 'admin', 'super_admin');
CREATE TYPE user_status AS ENUM ('active', 'inactive', 'suspended');

-- Membership related
CREATE TYPE membership_type AS ENUM ('daily', 'weekly', 'monthly', 'yearly');
CREATE TYPE membership_status AS ENUM ('active', 'inactive', 'expired', 'pending_verification');
CREATE TYPE payment_method AS ENUM ('transfer', 'cash', 'qris', 'midtrans');
CREATE TYPE payment_status AS ENUM ('pending', 'completed', 'failed');

-- Attendance
CREATE TYPE verification_status AS ENUM ('verified', 'manual_verified', 'failed');

-- Trainer
CREATE TYPE trainer_status AS ENUM ('active', 'inactive');

-- Workout
CREATE TYPE difficulty_level AS ENUM ('beginner', 'intermediate', 'advanced');
CREATE TYPE workout_status AS ENUM ('active', 'completed', 'archived');
CREATE TYPE booking_status AS ENUM ('confirmed', 'cancelled', 'completed', 'no_show');

-- Notification
CREATE TYPE notification_type AS ENUM ('membership_reminder', 'promo', 'workout_reminder', 'system');

-- Chat & Communication
CREATE TYPE conversation_status AS ENUM ('open', 'in_progress', 'resolved', 'closed');
CREATE TYPE feedback_category AS ENUM ('facility', 'trainer', 'service', 'cleanliness', 'other');
CREATE TYPE feedback_status AS ENUM ('new', 'reviewed', 'resolved');
CREATE TYPE faq_status AS ENUM ('active', 'inactive');
CREATE TYPE role_status AS ENUM ('active', 'inactive');

-- Renewal
CREATE TYPE renewal_status AS ENUM ('pending_verification', 'pending_payment', 'approved', 'rejected');
