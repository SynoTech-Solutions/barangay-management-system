<?php

namespace App\Enums;

enum BusinessDimension: string
{
    case Category = 'category';
    case Nature = 'nature';
    case OwnershipType = 'ownership-type';
    case Purok = 'purok';
    case Status = 'status';

    public function labelKey(): string
    {
        return "businesses.dimensions.{$this->value}";
    }

    public function lookupType(): ?LookupType
    {
        return match ($this) {
            self::Category => LookupType::BusinessCategory,
            self::Nature => LookupType::BusinessNature,
            self::OwnershipType => LookupType::BusinessOwnershipType,
            self::Purok, self::Status => null,
        };
    }

    public function column(): string
    {
        return match ($this) {
            self::Category => 'category_id',
            self::Nature => 'nature_id',
            self::OwnershipType => 'ownership_type_id',
            self::Purok => 'purok_id',
            self::Status => 'status',
        };
    }
}
