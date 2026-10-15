<?php

namespace App\Enums;

enum ReportColumnSensitivity: string
{
    case None = 'none';
    case PoliticalTag = 'political-tag';
    case GovernmentId = 'government-id';
}
