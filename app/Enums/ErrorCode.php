<?php

namespace App\Enums;

enum ErrorCode: string
{
    case AuthInvalidToken = 'AUTH_001';
    case AuthInvalidRefresh = 'AUTH_002';
    case AuthTooManyAttempts = 'AUTH_003';
    case MemberNotFound = 'MEMBER_001';
    case MemberDuplicate = 'MEMBER_002';
    case MembershipInactive = 'MEMBERSHIP_001';
    case MembershipPackageInvalid = 'MEMBERSHIP_002';
    case FaceBadQuality = 'FACE_001';
    case FaceNotRegistered = 'FACE_002';
    case FaceMismatch = 'FACE_003';
    case BookingFull = 'BOOKING_001';
    case BookingCancelLimit = 'BOOKING_002';
    case ReportInvalidRange = 'REPORT_001';
    case FileUnsupported = 'FILE_001';
    case FileTooLarge = 'FILE_002';
}
