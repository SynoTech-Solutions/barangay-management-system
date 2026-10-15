<?php

namespace App\Services\Reports;

use App\Domain\Reports\ReportDefinition;
use App\Domain\Reports\ReportFilter;
use App\Enums\AuditAction;
use App\Enums\ErrorCode;
use App\Exceptions\BmsException;
use App\Models\SavedReport;
use App\Models\User;
use App\Services\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;

final readonly class SavedReportService
{
    public function __construct(private AuditTrail $audit) {}

    public function save(?SavedReport $report, string $name, ReportDefinition $definition, User $actor): SavedReport
    {
        return DB::transaction(function () use ($report, $name, $definition, $actor): SavedReport {
            $locked = $report === null ? null : SavedReport::query()->lockForUpdate()->findOrFail($report->id);
            $this->ensureNameAvailable($name, $locked?->id);

            $before = $locked === null ? null : $this->summary($locked->name, ReportDefinition::fromArray($locked->definition));
            $saved = $locked ?? new SavedReport;
            $saved->name = $name;
            $saved->dataset = $definition->dataset;
            $saved->definition = $definition->toArray();
            $saved->is_active = true;
            $saved->updated_by = $actor->id;
            if ($locked === null) {
                $saved->created_by = $actor->id;
            }
            $saved->save();

            $this->audit->record(AuditAction::SavedReportSaved, $saved, $before, $this->summary($name, $definition), $saved->barangay_id, $actor->id);

            return $saved;
        });
    }

    public function deactivate(SavedReport $report, User $actor): void
    {
        DB::transaction(function () use ($report, $actor): void {
            $locked = SavedReport::query()->lockForUpdate()->findOrFail($report->id);
            $locked->is_active = false;
            $locked->updated_by = $actor->id;
            $locked->save();

            $this->audit->record(AuditAction::SavedReportDeactivated, $locked, ['is_active' => true], ['is_active' => false], $locked->barangay_id, $actor->id);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(string $name, ReportDefinition $definition): array
    {
        return [
            'name' => $name,
            'dataset' => $definition->dataset->value,
            'columns' => $definition->columns,
            'filters' => array_map(fn (ReportFilter $filter): string => $filter->column.' '.$filter->operator->value, $definition->filters),
            'group_by' => $definition->groupBy,
        ];
    }

    private function ensureNameAvailable(string $name, ?int $exceptId): void
    {
        $taken = SavedReport::query()
            ->whereRaw('lower(name) = lower(?)', [$name])
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();

        if ($taken) {
            throw BmsException::of(ErrorCode::SavedReportNameTaken, ['name' => $name]);
        }
    }
}
