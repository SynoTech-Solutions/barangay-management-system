<?php

namespace App\Console\Commands;

use App\Enums\FeatureFlag;
use App\Enums\Permission;
use BackedEnum;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateContractsCommand extends Command
{
    private const string OUTPUT = 'resources/js/contracts/generated.ts';

    private const string INDENT = '    ';

    protected $signature = 'bms:contracts:generate {--check : Fail if the generated file is out of date}';

    protected $description = 'Write TypeScript constants for permissions and feature flags';

    public function handle(): int
    {
        $contents = implode("\n", [
            $this->constant('Permission', Permission::cases()),
            $this->constant('FeatureFlag', FeatureFlag::cases()),
        ]);

        $path = base_path(self::OUTPUT);

        if ($this->option('check')) {
            if (! File::exists($path) || File::get($path) !== $contents) {
                $this->components->error(self::OUTPUT.' is out of date. Run php artisan bms:contracts:generate.');

                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);
        $this->components->info('Wrote '.self::OUTPUT);

        return self::SUCCESS;
    }

    /**
     * @param  array<int, BackedEnum>  $cases
     */
    private function constant(string $name, array $cases): string
    {
        $lines = ["export const {$name} = {"];
        foreach ($cases as $case) {
            $lines[] = self::INDENT.$case->name.": '".$case->value."',";
        }
        $lines[] = '} as const;';
        $lines[] = '';
        $lines[] = "export type {$name} = (typeof {$name})[keyof typeof {$name}];";
        $lines[] = '';

        return implode("\n", $lines);
    }
}
