<?php

namespace App\Enums;

enum BlotterPartyRole: string
{
    case Complainant = 'complainant';
    case Respondent = 'respondent';

    public function labelKey(): string
    {
        return "blotters.roles.{$this->value}";
    }
}
