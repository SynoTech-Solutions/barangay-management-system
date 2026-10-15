<?php

namespace App\Services\Sms;

use App\Domain\Residents\NameNormalizer;
use App\Domain\Sms\SmsText;
use App\Enums\AuditAction;
use App\Models\SmsTemplate;
use App\Models\User;
use App\Services\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;

final readonly class SmsTemplateService
{
    public function __construct(private AuditTrail $audit) {}

    public function save(?SmsTemplate $template, string $title, string $body, User $actor): SmsTemplate
    {
        return DB::transaction(function () use ($template, $title, $body, $actor): SmsTemplate {
            $template ??= new SmsTemplate;
            $before = $template->exists ? ['title' => $template->title] : null;
            if (! $template->exists) {
                $template->created_by = $actor->id;
            }

            $template->title = NameNormalizer::normalize($title) ?? '';
            $template->body = SmsText::clean($body);
            $template->updated_by = $actor->id;
            $template->save();

            $this->audit->record(AuditAction::SmsTemplateSaved, $template, $before, ['title' => $template->title], $template->barangay_id, $actor->id);

            return $template;
        });
    }

    public function delete(SmsTemplate $template, User $actor): void
    {
        DB::transaction(function () use ($template, $actor): void {
            $template->delete();

            $this->audit->record(AuditAction::SmsTemplateDeleted, $template, ['title' => $template->title], null, $template->barangay_id, $actor->id);
        });
    }
}
