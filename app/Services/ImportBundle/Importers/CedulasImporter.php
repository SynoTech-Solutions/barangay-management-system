<?php

namespace App\Services\ImportBundle\Importers;

use App\Domain\Money\Centavos;
use App\Domain\Residents\AddressComposer;
use App\Enums\BundleEntity;
use App\Enums\DocumentKind;
use App\Enums\ImportProblem;
use App\Enums\IssuedDocumentStatus;
use App\Services\ImportBundle\ImportContext;
use App\Services\ImportBundle\Record;
use App\Services\ImportBundle\TableWriter;

final class CedulasImporter extends BaseImporter
{
    public const string TABLE = 'cedulas';

    public const string BREAKDOWN_RULE = 'imported';

    private const int BREAKDOWN_VERSION = 1;

    private const array MONEY = [
        'business_gross_receipts', 'salaries', 'real_property_income', 'basic_tax', 'business_tax',
        'salary_tax', 'real_property_tax', 'additional_tax', 'interest', 'amount',
    ];

    public function entity(): BundleEntity
    {
        return BundleEntity::Cedulas;
    }

    public function importChunk(array $records, ImportContext $context): void
    {
        $ids = TableWriter::reserveIds(self::TABLE, count($records));
        $rows = [];
        $barangay = $context->barangay->loadMissing('municipality.province');
        $place = AddressComposer::compose([$barangay->name, $barangay->municipality->name, $barangay->municipality->province?->name]);

        foreach ($records as $index => $record) {
            $issuedYear = $context->year($record->raw('issued_at'));
            if ($this->alreadyImported($context, $record)) {
                $this->observe($context, $record, 'source_number', DocumentKind::Cedula, $issuedYear);

                continue;
            }

            $residentId = $this->required($context, $record, 'resident_key', BundleEntity::Residents);
            if ($residentId === null) {
                continue;
            }

            $id = $ids[$index];
            $lifecycle = $this->lifecycle($context, $record);
            $taxYear = $record->int('tax_year') ?? $issuedYear;
            [$number, $sourceNumber] = $this->number($context, $record, 'source_number', self::TABLE, 'cedula_number', DocumentKind::Cedula, $issuedYear);

            $formNumber = $record->text('form_number');
            $conflict = null;
            if ($formNumber !== null && $lifecycle['status'] !== IssuedDocumentStatus::Cancelled->value
                && ! $context->unique->claim(self::TABLE, 'form_number', $formNumber, scope: [['status', '<>', IssuedDocumentStatus::Cancelled->value]])) {
                $context->warn($record->key(), ImportProblem::FormNumberConflict);
                $conflict = (string) __('import.form_number_conflict', ['number' => $formNumber]);
                $formNumber = null;
            }

            $money = [];
            foreach (self::MONEY as $field) {
                $money[$field] = $record->money($field);
            }

            $rows[] = [
                ...$lifecycle,
                ...$this->signatories($record),
                ...$money,
                'id' => $id,
                'barangay_id' => $context->barangayId(),
                'resident_id' => $residentId,
                'cedula_number' => $number,
                'source_number' => $sourceNumber,
                'import_note' => $this->note($record, $conflict),
                'form_number' => $formNumber,
                'tax_year' => $taxYear,
                'place_of_issue' => $record->text('place_of_issue') ?? $place,
                'is_exempt' => $record->bool('is_exempt'),
                'breakdown' => json_encode($this->breakdown($money, $taxYear, $record->bool('is_exempt')), JSON_THROW_ON_ERROR),
            ];
            $this->remember($context, $record, self::TABLE, $id);
        }

        TableWriter::insert(self::TABLE, $rows);
    }

    /**
     * @param  array<string, string>  $money
     * @return array<string, mixed>
     */
    private function breakdown(array $money, int $year, bool $exempt): array
    {
        return [
            'v' => self::BREAKDOWN_VERSION,
            'rule' => self::BREAKDOWN_RULE,
            'tax_year' => $year,
            'exempt' => $exempt,
            'inputs' => [
                'business_gross_receipts' => $money['business_gross_receipts'],
                'salaries' => $money['salaries'],
                'real_property_income' => $money['real_property_income'],
            ],
            'basic' => $money['basic_tax'],
            'business' => $money['business_tax'],
            'salary' => $money['salary_tax'],
            'real_property' => $money['real_property_tax'],
            'additional' => $money['additional_tax'],
            'additional_capped' => false,
            'subtotal' => Centavos::sum($money['basic_tax'], $money['business_tax'], $money['salary_tax'], $money['real_property_tax'], $money['additional_tax']),
            'interest_applied' => $money['interest'] !== Record::ZERO,
            'interest_months' => 0,
            'interest_basis_points' => 0,
            'interest' => $money['interest'],
            'total' => $money['amount'],
        ];
    }
}
