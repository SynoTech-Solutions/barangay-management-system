<?php

namespace App\Enums;

enum SystemRole: string
{
    case CentralAdmin = 'central-admin';
    case MunicipalViewer = 'municipal-viewer';
    case BarangayAdmin = 'barangay-admin';
    case Secretary = 'secretary';
    case Treasurer = 'treasurer';
    case Bookkeeper = 'bookkeeper';
    case Clerk = 'clerk';
    case Viewer = 'viewer';

    public function scope(): RoleScope
    {
        return match ($this) {
            self::CentralAdmin, self::MunicipalViewer => RoleScope::Global,
            default => RoleScope::Barangay,
        };
    }

    public function labelKey(): string
    {
        return "roles.{$this->value}";
    }
}
