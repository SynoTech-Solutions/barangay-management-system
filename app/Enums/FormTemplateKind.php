<?php

namespace App\Enums;

enum FormTemplateKind: string
{
    case RichText = 'rich-text';
    case Pdf = 'pdf';

    public function labelKey(): string
    {
        return "forms.kinds.{$this->value}";
    }

    public function flag(): FeatureFlag
    {
        return match ($this) {
            self::RichText => FeatureFlag::FormsRichTextTemplates,
            self::Pdf => FeatureFlag::PdfFormTemplates,
        };
    }
}
