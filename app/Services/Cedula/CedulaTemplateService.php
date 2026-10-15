<?php

namespace App\Services\Cedula;

use App\Enums\AuditAction;
use App\Models\CedulaTemplate;
use App\Models\User;
use App\Services\Audit\AuditSnapshot;
use App\Services\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;

final readonly class CedulaTemplateService
{
    private const array AUDITED_ATTRIBUTES = [...CedulaTemplate::DETAIL_ATTRIBUTES, 'is_active'];

    public function __construct(private AuditTrail $audit) {}

    /**
     * @param  array<string, mixed>  $details
     */
    public function save(?CedulaTemplate $template, array $details, User $actor): CedulaTemplate
    {
        return DB::transaction(function () use ($template, $details, $actor): CedulaTemplate {
            $template ??= new CedulaTemplate;
            $before = $template->exists ? AuditSnapshot::of($template, self::AUDITED_ATTRIBUTES) : null;
            $template->fill($details);
            $template->updated_by = $actor->id;
            $template->save();

            $this->audit->record(AuditAction::CedulaTemplateSaved, $template, $before, AuditSnapshot::of($template, self::AUDITED_ATTRIBUTES), $template->barangay_id, $actor->id);

            return $template;
        });
    }

    public function setActive(CedulaTemplate $template, bool $active, User $actor): CedulaTemplate
    {
        return DB::transaction(function () use ($template, $active, $actor): CedulaTemplate {
            $template->is_active = $active;
            $template->updated_by = $actor->id;
            $template->save();

            $this->audit->record($active ? AuditAction::Reactivated : AuditAction::Deactivated, $template, ['is_active' => ! $active], ['is_active' => $active], $template->barangay_id, $actor->id);

            return $template;
        });
    }
}
