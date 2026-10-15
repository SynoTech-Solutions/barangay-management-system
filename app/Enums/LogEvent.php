<?php

namespace App\Enums;

enum LogEvent: string
{
    case LoginSucceeded = 'auth.login.succeeded';
    case LoginFailed = 'auth.login.failed';
    case LoginRejectedInactive = 'auth.login.rejected-inactive';
    case Logout = 'auth.logout';
    case FeatureOverrideSet = 'features.override.set';
    case FeatureOverrideCleared = 'features.override.cleared';
    case BarangaySwitched = 'tenancy.barangay.switched';
    case BarangayOnboarded = 'tenancy.barangay.onboarded';
    case RolesSynchronized = 'access.roles.synchronized';
    case DocumentNumberAllocated = 'numbering.allocated';
    case ReadinessCheckFailed = 'health.readiness.failed';
    case DomainErrorRaised = 'errors.domain';
    case PdfFillFailed = 'forms.pdf.fill-failed';
    case SmsQueued = 'sms.queued';
    case SmsLogDriverDelivered = 'sms.log-driver.delivered';
    case SmsSent = 'sms.sent';
    case SmsRetryScheduled = 'sms.retry-scheduled';
    case SmsFailed = 'sms.failed';
    case SmsSkipped = 'sms.skipped';
    case SmsRateLimited = 'sms.rate-limited';
    case SmsStuckRequeued = 'sms.stuck.requeued';
    case SmsPruned = 'sms.pruned';
    case DataImportStarted = 'migration.import.started';
    case DataImportFinished = 'migration.import.finished';
    case DataImportRecordFailed = 'migration.import.record-failed';
}
