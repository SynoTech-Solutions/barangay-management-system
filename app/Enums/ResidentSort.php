<?php

namespace App\Enums;

enum ResidentSort: string
{
    case Name = 'name';
    case Number = 'number';
    case Birthdate = 'birthdate';
    case Registered = 'registered';

    public function labelKey(): string
    {
        return "residents.sorts.{$this->value}";
    }
}
