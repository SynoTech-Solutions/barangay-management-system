<?php

namespace App\Enums;

enum PdfFieldType: string
{
    case Text = 'text';
    case Checkbox = 'checkbox';
    case Radio = 'radio';
    case Choice = 'choice';
    case PushButton = 'push-button';
    case Signature = 'signature';

    public function labelKey(): string
    {
        return "forms.pdf_field_types.{$this->value}";
    }

    public function isFillable(): bool
    {
        return in_array($this, [self::Text, self::Checkbox, self::Choice], true);
    }
}
