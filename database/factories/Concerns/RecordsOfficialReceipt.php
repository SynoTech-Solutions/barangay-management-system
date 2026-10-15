<?php

namespace Database\Factories\Concerns;

use App\Services\Receipts\OfficialReceiptRegistry;
use Illuminate\Database\Eloquent\Model;

trait RecordsOfficialReceipt
{
    public function configure(): static
    {
        return $this->afterCreating(function (Model $document): void {
            app(OfficialReceiptRegistry::class)->sync($document);
        });
    }
}
