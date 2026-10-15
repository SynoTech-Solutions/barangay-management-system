<?php

namespace App\Enums;

enum Gender: string
{
    case Male = 'male';
    case Female = 'female';

    public function labelKey(): string
    {
        return "residents.genders.{$this->value}";
    }
}
