<?php

namespace App\Enums;

enum IssuedDocumentStatus: string
{
    case Issued = 'issued';
    case Released = 'released';
    case Cancelled = 'cancelled';

    public function labelKey(): string
    {
        return "documents.statuses.{$this->value}";
    }
}
