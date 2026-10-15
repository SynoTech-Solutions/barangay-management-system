<?php

namespace Database\Seeders;

use App\Enums\LookupType;
use App\Models\LookupValue;
use Illuminate\Database\Seeder;

class LookupSeeder extends Seeder
{
    private const string DATA_FILE = __DIR__.'/data/lookups.php';

    public function run(): void
    {
        /** @var array<string, list<string>> $data */
        $data = require self::DATA_FILE;

        foreach ($data as $type => $labels) {
            foreach ($labels as $position => $label) {
                $exists = LookupValue::query()
                    ->whereNull('barangay_id')
                    ->where('type', $type)
                    ->whereRaw('lower(label) = lower(?)', [$label])
                    ->exists();

                if (! $exists) {
                    LookupValue::query()->create([
                        'barangay_id' => null,
                        'type' => LookupType::from($type),
                        'label' => $label,
                        'sort_order' => $position,
                    ]);
                }
            }
        }
    }
}
