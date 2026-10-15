<?php

namespace App\Enums;

enum ResidentFlag: string
{
    case Senior = 'senior';
    case SeniorMember = 'senior-member';
    case Pwd = 'pwd';
    case Indigent = 'indigent';
    case SingleParent = 'single-parent';
    case LivingWithParents = 'living-with-parents';
    case PermanentResident = 'permanent-resident';
    case Deceased = 'deceased';
    case Living = 'living';
    case Incomplete = 'incomplete';

    public function labelKey(): string
    {
        return "residents.flags.{$this->value}";
    }
}
