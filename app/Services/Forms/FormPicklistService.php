<?php

namespace App\Services\Forms;

use App\Enums\AuditAction;
use App\Enums\ErrorCode;
use App\Exceptions\BmsException;
use App\Models\FormPicklist;
use App\Models\FormPicklistItem;
use App\Models\User;
use App\Services\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;

final readonly class FormPicklistService
{
    public function __construct(private AuditTrail $audit) {}

    public function create(string $name, User $actor): FormPicklist
    {
        return DB::transaction(function () use ($name, $actor): FormPicklist {
            $this->ensureNameAvailable($name, null);
            $picklist = new FormPicklist(['name' => $name]);
            $picklist->is_active = true;
            $picklist->updated_by = $actor->id;
            $picklist->save();

            $this->audit->record(AuditAction::FormPicklistSaved, $picklist, null, ['name' => $name], $picklist->barangay_id, $actor->id);

            return $picklist;
        });
    }

    public function update(FormPicklist $picklist, string $name, bool $active, User $actor): void
    {
        DB::transaction(function () use ($picklist, $name, $active, $actor): void {
            $locked = FormPicklist::query()->lockForUpdate()->findOrFail($picklist->id);
            $this->ensureNameAvailable($name, $locked->id);
            $before = ['name' => $locked->name, 'is_active' => $locked->is_active];
            $locked->name = $name;
            $locked->is_active = $active;
            $locked->updated_by = $actor->id;
            $locked->save();

            $after = ['name' => $locked->name, 'is_active' => $locked->is_active];
            if ($before !== $after) {
                $this->audit->record(AuditAction::FormPicklistSaved, $locked, $before, $after, $locked->barangay_id, $actor->id);
            }
        });
    }

    public function addItem(FormPicklist $picklist, string $label, User $actor): FormPicklistItem
    {
        return DB::transaction(function () use ($picklist, $label, $actor): FormPicklistItem {
            $locked = FormPicklist::query()->lockForUpdate()->findOrFail($picklist->id);
            $this->ensureLabelAvailable($locked, $label, null);
            $item = new FormPicklistItem(['label' => $label]);
            $item->form_picklist_id = $locked->id;
            $item->is_active = true;
            $item->save();

            $this->audit->record(AuditAction::FormPicklistSaved, $locked, null, ['item_id' => $item->id, 'label' => $label], $locked->barangay_id, $actor->id);

            return $item;
        });
    }

    public function updateItem(FormPicklistItem $item, string $label, bool $active, User $actor): void
    {
        DB::transaction(function () use ($item, $label, $active, $actor): void {
            $picklist = FormPicklist::query()->lockForUpdate()->findOrFail($item->form_picklist_id);
            $locked = FormPicklistItem::query()->lockForUpdate()->findOrFail($item->id);
            $this->ensureLabelAvailable($picklist, $label, $locked->id);
            $before = ['item_id' => $locked->id, 'label' => $locked->label, 'is_active' => $locked->is_active];
            $locked->label = $label;
            $locked->is_active = $active;
            $locked->save();

            $after = ['item_id' => $locked->id, 'label' => $locked->label, 'is_active' => $locked->is_active];
            if ($before !== $after) {
                $this->audit->record(AuditAction::FormPicklistSaved, $picklist, $before, $after, $picklist->barangay_id, $actor->id);
            }
        });
    }

    private function ensureNameAvailable(string $name, ?int $exceptId): void
    {
        $taken = FormPicklist::query()
            ->whereRaw('lower(name) = lower(?)', [$name])
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();

        if ($taken) {
            throw BmsException::of(ErrorCode::FormPicklistNameTaken, ['name' => $name]);
        }
    }

    private function ensureLabelAvailable(FormPicklist $picklist, string $label, ?int $exceptId): void
    {
        $taken = FormPicklistItem::query()
            ->where('form_picklist_id', $picklist->id)
            ->whereRaw('lower(label) = lower(?)', [$label])
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();

        if ($taken) {
            throw BmsException::of(ErrorCode::FormPicklistItemTaken, ['label' => $label]);
        }
    }
}
