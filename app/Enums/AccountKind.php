<?php

namespace App\Enums;

enum AccountKind: string
{
    case Group = 'group';
    case Title = 'title';
    case Subsidiary = 'subsidiary';

    public function labelKey(): string
    {
        return "finance.account_kinds.{$this->value}";
    }
}
