<?php

namespace Tests\Feature\Platform;

use App\Domain\Forms\PlaceholderCatalog;
use App\Domain\Reports\ColumnDefinition;
use App\Domain\Reports\DatasetCatalog;
use App\Enums\AccountGroupType;
use App\Enums\AccountKind;
use App\Enums\AuditAction;
use App\Enums\BudgetStatus;
use App\Enums\BusinessDimension;
use App\Enums\BusinessStatus;
use App\Enums\CheckStatus;
use App\Enums\CivilStatus;
use App\Enums\CollectionSource;
use App\Enums\DashboardCounter;
use App\Enums\ErrorCode;
use App\Enums\FeatureFlag;
use App\Enums\FormInputType;
use App\Enums\FormTemplateKind;
use App\Enums\Gender;
use App\Enums\IssuedDocumentStatus;
use App\Enums\LiquidationStatus;
use App\Enums\LookupType;
use App\Enums\PaymentType;
use App\Enums\PdfFieldType;
use App\Enums\Permission;
use App\Enums\ReportDataset;
use App\Enums\ReportOperator;
use App\Enums\ResidentFlag;
use App\Enums\ResidentSort;
use App\Enums\SignatoryOffice;
use App\Enums\SmsSource;
use App\Enums\SmsStatus;
use App\Enums\SortDirection;
use App\Enums\SystemRole;
use App\Enums\VoucherStatus;
use App\Services\Finance\CollectionCsvExporter;
use App\Services\Forms\FormTransactionCsvExporter;
use App\Services\Residents\ResidentCsvExporter;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

class TranslationCatalogTest extends TestCase
{
    public function test_every_enum_value_has_user_facing_text(): void
    {
        $keys = [
            ...array_map(fn (FeatureFlag $flag): string => $flag->labelKey(), FeatureFlag::cases()),
            ...array_map(fn (FeatureFlag $flag): string => $flag->descriptionKey(), FeatureFlag::cases()),
            ...array_map(fn (Permission $permission): string => $permission->labelKey(), Permission::cases()),
            ...array_map(fn (SystemRole $role): string => $role->labelKey(), SystemRole::cases()),
            ...array_map(fn (ErrorCode $code): string => $code->messageKey(), ErrorCode::cases()),
            ...array_map(fn (LookupType $type): string => $type->labelKey(), LookupType::cases()),
            ...array_map(fn (SignatoryOffice $office): string => $office->labelKey(), SignatoryOffice::cases()),
            ...array_map(fn (AuditAction $action): string => "audit.{$action->value}", AuditAction::cases()),
            ...array_map(fn (Gender $gender): string => $gender->labelKey(), Gender::cases()),
            ...array_map(fn (CivilStatus $status): string => $status->labelKey(), CivilStatus::cases()),
            ...array_map(fn (ResidentFlag $flag): string => $flag->labelKey(), ResidentFlag::cases()),
            ...array_map(fn (ResidentSort $sort): string => $sort->labelKey(), ResidentSort::cases()),
            ...array_map(fn (string $column): string => "residents.export.{$column}", ResidentCsvExporter::COLUMNS),
            ...array_map(fn (IssuedDocumentStatus $status): string => $status->labelKey(), IssuedDocumentStatus::cases()),
            ...array_map(fn (BusinessStatus $status): string => $status->labelKey(), BusinessStatus::cases()),
            ...array_map(fn (BusinessDimension $dimension): string => $dimension->labelKey(), BusinessDimension::cases()),
            ...array_map(fn (DashboardCounter $counter): string => $counter->labelKey(), DashboardCounter::cases()),
            ...array_map(fn (SmsStatus $status): string => $status->labelKey(), SmsStatus::cases()),
            ...array_map(fn (SmsSource $source): string => $source->labelKey(), SmsSource::cases()),
            ...array_map(fn (AccountKind $kind): string => $kind->labelKey(), AccountKind::cases()),
            ...array_map(fn (AccountGroupType $type): string => $type->labelKey(), AccountGroupType::cases()),
            ...array_map(fn (BudgetStatus $status): string => $status->labelKey(), BudgetStatus::cases()),
            ...array_map(fn (PaymentType $type): string => $type->labelKey(), PaymentType::cases()),
            ...array_map(fn (VoucherStatus $status): string => $status->labelKey(), VoucherStatus::cases()),
            ...array_map(fn (CheckStatus $status): string => $status->labelKey(), CheckStatus::cases()),
            ...array_map(fn (LiquidationStatus $status): string => $status->labelKey(), LiquidationStatus::cases()),
            ...array_map(fn (CollectionSource $source): string => $source->labelKey(), CollectionSource::cases()),
            ...array_map(fn (string $column): string => "finance.export.{$column}", CollectionCsvExporter::COLUMNS),
            ...array_map(fn (FormTemplateKind $kind): string => $kind->labelKey(), FormTemplateKind::cases()),
            ...array_map(fn (FormInputType $type): string => $type->labelKey(), FormInputType::cases()),
            ...array_map(fn (PdfFieldType $type): string => $type->labelKey(), PdfFieldType::cases()),
            ...array_map(fn (ReportDataset $dataset): string => $dataset->labelKey(), ReportDataset::cases()),
            ...array_map(fn (ReportOperator $operator): string => $operator->labelKey(), ReportOperator::cases()),
            ...array_map(fn (SortDirection $direction): string => "reports.saved.directions.{$direction->value}", SortDirection::cases()),
            ...array_map(fn (string $column): string => "forms.export.{$column}", FormTransactionCsvExporter::COLUMNS),
            ...array_map(fn (string $token): string => 'forms.tokens.'.$token, PlaceholderCatalog::tokens()),
            ...array_map(fn (string $token): string => 'forms.samples.'.$token, PlaceholderCatalog::tokens()),
            ...array_map(
                fn (ColumnDefinition $column): string => "reports.saved.columns.{$column->key}",
                array_merge(...array_map(DatasetCatalog::columns(...), ReportDataset::cases())),
            ),
        ];

        $missing = array_values(array_filter($keys, fn (string $key): bool => ! Lang::has($key)));

        $this->assertSame([], $missing);
    }
}
