<?php

namespace App\Enums;

enum VoucherStatus: string
{
    case Prepared = 'prepared';
    case Verified = 'verified';
    case Approved = 'approved';
    case Released = 'released';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function labelKey(): string
    {
        return "finance.voucher_statuses.{$this->value}";
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Prepared, self::Verified], true);
    }

    public function commitsFunds(): bool
    {
        return in_array($this, [self::Approved, self::Released, self::Closed], true);
    }
}
