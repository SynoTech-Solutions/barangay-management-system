<?php

namespace App\Http\Requests\Forms;

use App\Domain\Residents\NameNormalizer;
use App\Enums\FormInputType;
use App\Models\FormTemplate;
use App\Models\FormTemplateField;
use App\Models\FormTemplateVersion;
use App\Models\FormTransaction;
use App\Rules\ActiveResident;
use App\Rules\AvailableOrNumber;
use App\Tenancy\CurrentBarangay;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FormTransactionRequest extends FormRequest
{
    public const int TEXT_MAX = 2000;

    public const string AMOUNT_MAX = '9999999999.99';

    private const int OR_NUMBER_MAX = 32;

    private const string VALUES = 'values';

    /** @var Collection<int, FormTemplateField>|null */
    private ?Collection $fields = null;

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('or_number'))) {
            $this->merge(['or_number' => NameNormalizer::normalize($this->input('or_number'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(CurrentBarangay $currentBarangay): array
    {
        $transaction = $this->transaction();
        $barangayId = $currentBarangay->require()->id;

        $rules = [
            'form_template_id' => $transaction === null
                ? ['required', 'integer', Rule::exists('form_templates', 'id')->where('barangay_id', $barangayId)->where('is_active', true)]
                : ['prohibited'],
            'resident_id' => $transaction === null ? ['required', new ActiveResident] : ['prohibited'],
            'or_number' => ['nullable', 'string', 'max:'.self::OR_NUMBER_MAX, new AvailableOrNumber($transaction)],
            'amount' => ['nullable', 'decimal:0,2', 'min:0', 'max:'.self::AMOUNT_MAX],
            self::VALUES => ['array'],
        ];

        foreach ($this->fields() as $field) {
            $rules[self::VALUES.'.'.$field->key] = $this->fieldRules($field, $barangayId);
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];
        foreach ($this->fields() as $field) {
            $attributes[self::VALUES.'.'.$field->key] = $field->label;
        }

        return $attributes;
    }

    public function templateId(): int
    {
        return $this->integer('form_template_id');
    }

    public function residentId(): int
    {
        return $this->integer('resident_id');
    }

    public function orNumber(): ?string
    {
        return $this->filled('or_number') ? $this->string('or_number')->toString() : null;
    }

    public function amount(): string
    {
        return $this->filled('amount') ? $this->string('amount')->toString() : '0';
    }

    /**
     * @return array<string, string|int|bool|null>
     */
    public function values(): array
    {
        $input = (array) $this->input(self::VALUES, []);
        $values = [];

        foreach ($this->fields() as $field) {
            $raw = $input[$field->key] ?? null;
            $values[$field->key] = match ($field->input_type) {
                FormInputType::Check => filter_var($raw, FILTER_VALIDATE_BOOL),
                FormInputType::Resident, FormInputType::Table => is_numeric($raw) ? (int) $raw : null,
                FormInputType::Text, FormInputType::Date, FormInputType::Amount => is_scalar($raw) && (string) $raw !== '' ? (string) $raw : null,
            };
        }

        return $values;
    }

    /**
     * @return list<mixed>
     */
    private function fieldRules(FormTemplateField $field, int $barangayId): array
    {
        $presence = $field->is_required ? 'required' : 'nullable';

        return match ($field->input_type) {
            FormInputType::Text => [$presence, 'string', 'max:'.self::TEXT_MAX],
            FormInputType::Resident => [$presence, 'integer', new ActiveResident],
            FormInputType::Table => [$presence, 'integer', Rule::exists('form_picklist_items', 'id')
                ->where('barangay_id', $barangayId)
                ->where('form_picklist_id', $field->form_picklist_id ?? 0)
                ->where('is_active', true)],
            FormInputType::Check => $field->is_required ? ['accepted'] : ['nullable', 'boolean'],
            FormInputType::Date => [$presence, 'date_format:Y-m-d'],
            FormInputType::Amount => [$presence, 'decimal:0,2', 'min:0', 'max:'.self::AMOUNT_MAX],
        };
    }

    /**
     * @return Collection<int, FormTemplateField>
     */
    private function fields(): Collection
    {
        if ($this->fields !== null) {
            return $this->fields;
        }

        $transaction = $this->transaction();
        $versionId = $transaction?->form_template_version_id;
        if ($versionId === null && is_numeric($this->input('form_template_id'))) {
            $versionId = FormTemplate::query()->whereKey((int) $this->input('form_template_id'))->value('current_version_id');
        }

        $version = is_int($versionId) ? FormTemplateVersion::query()->with('fields')->find($versionId) : null;

        return $this->fields = $version === null ? new Collection : $version->fields;
    }

    private function transaction(): ?FormTransaction
    {
        $transaction = $this->route('transaction');

        return $transaction instanceof FormTransaction ? $transaction : null;
    }
}
