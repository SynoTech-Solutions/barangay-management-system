<?php

namespace App\Services\Forms;

use App\Domain\Forms\CheckLabels;
use App\Domain\Forms\FieldValueFormatter;
use App\Domain\Forms\SnapshotField;
use App\Domain\Money\Centavos;
use App\Enums\FormInputType;
use App\Models\FormPicklistItem;
use App\Models\FormTemplateField;
use App\Models\Resident;

final readonly class FormValueResolver
{
    private const string NAME_SEPARATOR = ' ';

    /**
     * @param  iterable<FormTemplateField>  $fields
     * @param  array<string, mixed>  $input
     * @return list<SnapshotField>
     */
    public function resolve(iterable $fields, array $input): array
    {
        $labels = $this->checkLabels();
        $resolved = [];

        foreach ($fields as $field) {
            $raw = $input[$field->key] ?? null;
            [$value, $display] = match ($field->input_type) {
                FormInputType::Resident => $this->resident($raw),
                FormInputType::Table => $this->item($raw, $field->form_picklist_id),
                FormInputType::Check => [$raw === true, FieldValueFormatter::display(FormInputType::Check, $raw === true, $labels)],
                FormInputType::Amount => $this->amount($raw),
                FormInputType::Date => is_string($raw) && $raw !== '' ? [$raw, FieldValueFormatter::display(FormInputType::Date, $raw, $labels)] : [null, ''],
                FormInputType::Text => is_string($raw) ? [trim($raw), trim($raw)] : [null, ''],
            };

            $resolved[] = new SnapshotField($field->key, $field->label, $field->input_type, $value, $display);
        }

        return $resolved;
    }

    public function checkLabels(): CheckLabels
    {
        return new CheckLabels((string) __('forms.check.checked'), (string) __('forms.check.unchecked'));
    }

    /**
     * @return array{0: int|null, 1: string}
     */
    private function resident(mixed $raw): array
    {
        $resident = is_int($raw) ? Resident::query()->find($raw) : null;

        return $resident === null
            ? [null, '']
            : [$resident->id, implode(self::NAME_SEPARATOR, array_filter([$resident->first_name, $resident->middle_name, $resident->last_name, $resident->name_extension]))];
    }

    /**
     * @return array{0: int|null, 1: string}
     */
    private function item(mixed $raw, ?int $picklistId): array
    {
        $item = is_int($raw) && $picklistId !== null
            ? FormPicklistItem::query()->where('form_picklist_id', $picklistId)->find($raw)
            : null;

        return $item === null ? [null, ''] : [$item->id, $item->label];
    }

    /**
     * @return array{0: string|null, 1: string}
     */
    private function amount(mixed $raw): array
    {
        if (! is_string($raw) || $raw === '') {
            return [null, ''];
        }

        $normalized = Centavos::toDecimal(Centavos::fromDecimal($raw));

        return [$normalized, FieldValueFormatter::display(FormInputType::Amount, $normalized, $this->checkLabels())];
    }
}
