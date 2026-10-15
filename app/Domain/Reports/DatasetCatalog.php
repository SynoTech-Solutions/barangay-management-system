<?php

namespace App\Domain\Reports;

use App\Enums\CivilStatus;
use App\Enums\Gender;
use App\Enums\IssuedDocumentStatus;
use App\Enums\PaymentType;
use App\Enums\ReportColumnSensitivity as Sensitivity;
use App\Enums\ReportColumnType as Type;
use App\Enums\ReportDataset;
use App\Enums\VoucherStatus;

final class DatasetCatalog
{
    /**
     * @return list<ColumnDefinition>
     */
    public static function columns(ReportDataset $dataset): array
    {
        return match ($dataset) {
            ReportDataset::Residents => [
                new ColumnDefinition('resident_number', Type::Text),
                new ColumnDefinition('full_name', Type::Text),
                new ColumnDefinition('last_name', Type::Text, groupable: true),
                new ColumnDefinition('first_name', Type::Text),
                new ColumnDefinition('birthdate', Type::Date),
                new ColumnDefinition('age', Type::Number, groupable: true),
                new ColumnDefinition('gender', Type::Choice, groupable: true, choices: Gender::class),
                new ColumnDefinition('civil_status', Type::Choice, groupable: true, choices: CivilStatus::class),
                new ColumnDefinition('purok', Type::Text, groupable: true),
                new ColumnDefinition('street_address', Type::Text),
                new ColumnDefinition('mobile_number', Type::Text),
                new ColumnDefinition('email', Type::Text),
                new ColumnDefinition('is_registered_voter', Type::Boolean, groupable: true),
                new ColumnDefinition('precinct_number', Type::Text, groupable: true),
                new ColumnDefinition('is_senior_citizen_member', Type::Boolean, groupable: true),
                new ColumnDefinition('is_pwd', Type::Boolean, groupable: true),
                new ColumnDefinition('is_single_parent', Type::Boolean, groupable: true),
                new ColumnDefinition('is_indigent', Type::Boolean, groupable: true),
                new ColumnDefinition('is_deceased', Type::Boolean, groupable: true),
                new ColumnDefinition('registered_on', Type::Date),
                new ColumnDefinition('political_color', Type::Text, groupable: true, sensitivity: Sensitivity::PoliticalTag),
                new ColumnDefinition('national_id', Type::Text, sensitivity: Sensitivity::GovernmentId),
                new ColumnDefinition('drivers_license', Type::Text, sensitivity: Sensitivity::GovernmentId),
                new ColumnDefinition('tin', Type::Text, sensitivity: Sensitivity::GovernmentId),
                new ColumnDefinition('sss_gsis', Type::Text, sensitivity: Sensitivity::GovernmentId),
                new ColumnDefinition('pagibig', Type::Text, sensitivity: Sensitivity::GovernmentId),
                new ColumnDefinition('philhealth', Type::Text, sensitivity: Sensitivity::GovernmentId),
                new ColumnDefinition('voter_id', Type::Text, sensitivity: Sensitivity::GovernmentId),
            ],
            ReportDataset::BarangayClearances => [
                new ColumnDefinition('clearance_number', Type::Text),
                new ColumnDefinition('control_number', Type::Text),
                new ColumnDefinition('resident_name', Type::Text),
                new ColumnDefinition('status', Type::Choice, groupable: true, choices: IssuedDocumentStatus::class),
                new ColumnDefinition('clearance_type', Type::Text, groupable: true),
                new ColumnDefinition('purpose', Type::Text, groupable: true),
                new ColumnDefinition('or_number', Type::Text),
                new ColumnDefinition('amount', Type::Money),
                new ColumnDefinition('doc_stamp', Type::Money),
                new ColumnDefinition('issued_on', Type::Date, groupable: true),
                new ColumnDefinition('expires_on', Type::Date),
            ],
            ReportDataset::BusinessClearances => [
                new ColumnDefinition('clearance_number', Type::Text),
                new ColumnDefinition('control_number', Type::Text),
                new ColumnDefinition('business_name', Type::Text),
                new ColumnDefinition('owner_name', Type::Text),
                new ColumnDefinition('status', Type::Choice, groupable: true, choices: IssuedDocumentStatus::class),
                new ColumnDefinition('or_number', Type::Text),
                new ColumnDefinition('amount', Type::Money),
                new ColumnDefinition('doc_stamp', Type::Money),
                new ColumnDefinition('issued_on', Type::Date, groupable: true),
                new ColumnDefinition('expires_on', Type::Date),
            ],
            ReportDataset::Cedulas => [
                new ColumnDefinition('cedula_number', Type::Text),
                new ColumnDefinition('form_number', Type::Text),
                new ColumnDefinition('resident_name', Type::Text),
                new ColumnDefinition('tax_year', Type::Number, groupable: true),
                new ColumnDefinition('status', Type::Choice, groupable: true, choices: IssuedDocumentStatus::class),
                new ColumnDefinition('is_exempt', Type::Boolean, groupable: true),
                new ColumnDefinition('basic_tax', Type::Money),
                new ColumnDefinition('additional_tax', Type::Money),
                new ColumnDefinition('interest', Type::Money),
                new ColumnDefinition('amount', Type::Money),
                new ColumnDefinition('issued_on', Type::Date, groupable: true),
            ],
            ReportDataset::Collections => [
                new ColumnDefinition('collection_number', Type::Text),
                new ColumnDefinition('collection_type', Type::Text, groupable: true),
                new ColumnDefinition('payer_name', Type::Text),
                new ColumnDefinition('or_number', Type::Text),
                new ColumnDefinition('amount', Type::Money),
                new ColumnDefinition('paid_on', Type::Date, groupable: true),
                new ColumnDefinition('is_cancelled', Type::Boolean, groupable: true),
            ],
            ReportDataset::Vouchers => [
                new ColumnDefinition('voucher_number', Type::Text),
                new ColumnDefinition('voucher_date', Type::Date, groupable: true),
                new ColumnDefinition('payee_name', Type::Text, groupable: true),
                new ColumnDefinition('payment_type', Type::Choice, groupable: true, choices: PaymentType::class),
                new ColumnDefinition('status', Type::Choice, groupable: true, choices: VoucherStatus::class),
                new ColumnDefinition('particulars', Type::Text),
                new ColumnDefinition('amount', Type::Money),
            ],
            ReportDataset::FormTransactions => [
                new ColumnDefinition('transaction_number', Type::Text),
                new ColumnDefinition('template_name', Type::Text, groupable: true),
                new ColumnDefinition('template_type', Type::Text, groupable: true),
                new ColumnDefinition('resident_name', Type::Text),
                new ColumnDefinition('status', Type::Choice, groupable: true, choices: IssuedDocumentStatus::class),
                new ColumnDefinition('or_number', Type::Text),
                new ColumnDefinition('amount', Type::Money),
                new ColumnDefinition('issued_on', Type::Date, groupable: true),
                new ColumnDefinition('released_on', Type::Date),
            ],
        };
    }

    public static function column(ReportDataset $dataset, string $key): ?ColumnDefinition
    {
        foreach (self::columns($dataset) as $column) {
            if ($column->key === $key) {
                return $column;
            }
        }

        return null;
    }
}
