<?php

namespace App\Enums;

enum FormInputType: string
{
    case Text = 'text';
    case Resident = 'resident';
    case Table = 'table';
    case Check = 'check';
    case Date = 'date';
    case Amount = 'amount';

    public function labelKey(): string
    {
        return "forms.input_types.{$this->value}";
    }
}
