<?php

namespace App\Console\Commands;

use App\Models\Barangay;
use App\Services\PoliticalTags\PoliticalTagService;
use Illuminate\Console\Command;

class PurgePoliticalTagsCommand extends Command
{
    protected $signature = 'bms:political-tags:purge
        {--barangay= : PSGC code of the barangay whose color tags and categories are deleted}';

    protected $description = 'Permanently delete every political color tag and color category of one barangay';

    public function handle(PoliticalTagService $tags): int
    {
        $code = $this->option('barangay');
        $barangay = is_string($code) && $code !== '' ? Barangay::query()->where('psgc_code', $code)->first() : null;

        if ($barangay === null) {
            $this->components->error('Pass --barangay with the PSGC code of an existing barangay.');

            return self::INVALID;
        }

        if (! $this->confirm("Permanently delete all political color tags and categories of {$barangay->name}?")) {
            $this->components->warn('Nothing deleted.');

            return self::FAILURE;
        }

        $result = $tags->purge($barangay, null);
        $this->components->info("Deleted {$result['tags']} tags and {$result['categories']} categories from {$barangay->name}.");

        return self::SUCCESS;
    }
}
