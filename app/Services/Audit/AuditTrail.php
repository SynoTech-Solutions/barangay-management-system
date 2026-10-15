<?php

namespace App\Services\Audit;

use App\Domain\Audit\AuditRedactor;
use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Tenancy\CurrentBarangay;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;

final readonly class AuditTrail
{
    public const string REQUEST_ID_CONTEXT_KEY = 'request_id';

    public function __construct(
        private CurrentBarangay $currentBarangay,
        private AuthFactory $auth,
        private Request $request,
    ) {}

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function record(
        AuditAction $action,
        ?Model $subject = null,
        ?array $before = null,
        ?array $after = null,
        ?int $barangayId = null,
        ?int $actorId = null,
    ): AuditEvent {
        $requestId = Context::get(self::REQUEST_ID_CONTEXT_KEY);
        $userId = $this->auth->guard()->id();

        return AuditEvent::query()->create([
            'barangay_id' => $barangayId ?? $this->currentBarangay->id(),
            'actor_id' => $actorId ?? (is_int($userId) ? $userId : null),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject === null ? null : (string) $subject->getKey(),
            'changes' => ($before === null && $after === null) ? null : AuditRedactor::payload($before, $after),
            'request_id' => is_string($requestId) ? $requestId : null,
            'ip_address' => $this->request->ip(),
        ]);
    }
}
