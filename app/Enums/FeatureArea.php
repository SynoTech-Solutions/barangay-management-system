<?php

namespace App\Enums;

enum FeatureArea: string
{
    case Platform = 'platform';
    case Residents = 'residents';
    case Sms = 'sms';
    case Certificates = 'certificates';
    case Forms = 'forms';
    case Finance = 'finance';
    case Migration = 'migration';
}
