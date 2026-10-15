<?php

namespace App\Services\Forms;

use App\Domain\Forms\FormSnapshot;
use App\Domain\Forms\SnapshotField;
use App\Enums\DocumentKind;
use App\Enums\ErrorCode;
use App\Enums\FeatureFlag;
use App\Enums\IssuedDocumentStatus;
use App\Enums\SignatoryOffice;
use App\Exceptions\BmsException;
use App\Models\FormTemplate;
use App\Models\FormTemplateVersion;
use App\Models\FormTransaction;
use App\Models\Resident;
use App\Models\Signatory;
use App\Models\User;
use App\Services\Documents\DocumentLifecycle;
use App\Services\Documents\ResidentIssuanceGuard;
use App\Services\Features\FeatureFlags;
use App\Services\Numbering\DocumentNumberAllocator;
use ArrayObject;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class FormTransactionService
{
    private const array AUDITED_ATTRIBUTES = ['transaction_number', 'form_template_id', 'form_template_version_id', 'resident_id', ...FormTransaction::DETAIL_ATTRIBUTES, 'certified_by_name'];

    public function __construct(
        private DocumentLifecycle $lifecycle,
        private DocumentNumberAllocator $numbers,
        private ResidentIssuanceGuard $guard,
        private FeatureFlags $features,
        private FormTokenResolver $tokens,
        private FormValueResolver $values,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public function issue(int $templateId, int $residentId, array $values, ?string $orNumber, string $amount, User $actor): FormTransaction
    {
        return DB::transaction(function () use ($templateId, $residentId, $values, $orNumber, $amount, $actor): FormTransaction {
            $locked = FormTemplate::query()->lockForUpdate()->findOrFail($templateId);
            $version = $this->usableVersion($locked);

            $resident = Resident::query()->lockForUpdate()->findOrFail($residentId);
            $this->guard->ensureIssuable($resident, applyComplaintHold: true, requirePhoto: false);

            $now = CarbonImmutable::now();
            $fields = $this->values->resolve($version->fields, $values);
            $signatories = Signatory::forOffices(SignatoryOffice::PunongBarangay, SignatoryOffice::Secretary);

            $transaction = new FormTransaction(['or_number' => $orNumber, 'amount' => $amount]);
            $transaction->transaction_number = $this->numbers->next(DocumentKind::FormTransaction, $now->year);
            $transaction->form_template_id = $locked->id;
            $transaction->form_template_version_id = $version->id;
            $transaction->resident_id = $resident->id;
            $transaction->snapshot = (new FormSnapshot($fields, $this->tokens->snapshot($resident, $now)))->toArray();
            $transaction->snapshotSignatories(
                $signatories->get(SignatoryOffice::Secretary->value),
                $signatories->get(SignatoryOffice::PunongBarangay->value),
            );

            $issued = $this->lifecycle->issue($transaction, $actor, $now, self::AUDITED_ATTRIBUTES, ['field_keys' => $this->keys($fields)]);
            $issued->setRelation('template', $locked);

            return $issued;
        });
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function update(FormTransaction $transaction, array $values, ?string $orNumber, string $amount, User $actor): FormTransaction
    {
        $changedKeys = new ArrayObject;

        return $this->lifecycle->update(
            $transaction,
            $actor,
            self::AUDITED_ATTRIBUTES,
            function (FormTransaction $locked) use ($values, $orNumber, $amount, $changedKeys): void {
                $version = FormTemplateVersion::query()->with('fields')->findOrFail($locked->form_template_version_id);
                $snapshot = FormSnapshot::fromArray($locked->snapshot);
                $fields = $this->values->resolve($version->fields, $values);
                $previous = $snapshot->rawValues();

                foreach ($fields as $field) {
                    if (($previous[$field->key] ?? null) !== $field->value) {
                        $changedKeys->append($field->key);
                    }
                }

                $locked->snapshot = (new FormSnapshot($fields, $snapshot->tokens))->toArray();
                $locked->or_number = $orNumber;
                $locked->amount = $amount;
            },
            fn (): array => $changedKeys->count() === 0 ? [] : ['changed_field_keys' => $changedKeys->getArrayCopy()],
        );
    }

    /**
     * @param  list<int>  $ids
     */
    public function release(array $ids, User $actor): int
    {
        return $this->lifecycle->release(FormTransaction::class, $ids, $actor);
    }

    /**
     * @param  list<int>  $ids
     */
    public function cancel(array $ids, string $reason, User $actor): int
    {
        return $this->lifecycle->cancel(FormTransaction::class, $ids, $reason, $actor);
    }

    public function recordPrint(FormTransaction $transaction, User $actor): FormTransaction
    {
        return $this->lifecycle->recordPrint($transaction, $actor);
    }

    /**
     * @param  callable(FormTransaction): string  $render
     */
    public function printPdf(FormTransaction $transaction, User $actor, callable $render): string
    {
        if ($transaction->status === IssuedDocumentStatus::Cancelled) {
            throw BmsException::of(ErrorCode::DocumentCancelled, ['number' => $transaction->transaction_number]);
        }

        $contents = $render($transaction);
        $this->lifecycle->recordPrint($transaction, $actor);

        return $contents;
    }

    public function autoPrint(): bool
    {
        return $this->features->enabled(FeatureFlag::FormsAutoPrintOnSave);
    }

    public function usableVersion(FormTemplate $template): FormTemplateVersion
    {
        $version = $template->current_version_id === null ? null : FormTemplateVersion::query()->with('fields')->find($template->current_version_id);

        if (! $template->is_active || $version === null || ! $this->features->enabled($template->kind->flag())) {
            throw BmsException::of(ErrorCode::FormTemplateUnavailable, ['name' => $template->name]);
        }

        return $version;
    }

    /**
     * @param  list<SnapshotField>  $fields
     * @return list<string>
     */
    private function keys(array $fields): array
    {
        return array_map(fn (SnapshotField $field): string => $field->key, $fields);
    }
}
