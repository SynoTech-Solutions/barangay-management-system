<?php

namespace App\Console\Commands;

use App\Domain\Geography\PsgcCode;
use App\Domain\Geography\PsgcLevel;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use SplFileObject;

class ImportGeographyCommand extends Command
{
    private const string COLUMN_CODE = 'psgc_code';

    private const string COLUMN_NAME = 'name';

    private const string COLUMN_LEVEL = 'level';

    protected $signature = 'bms:geography:import {path : CSV with columns psgc_code,name,level}';

    protected $description = 'Import regions, provinces, and cities/municipalities from a PSGC CSV extract';

    public function handle(): int
    {
        $path = $this->stringArgument('path');
        if (! is_readable($path)) {
            $this->components->error("Cannot read {$path}.");

            return self::FAILURE;
        }

        $rows = $this->rows($path);
        $counts = ['regions' => 0, 'provinces' => 0, 'municipalities' => 0, 'skipped' => 0];

        DB::transaction(function () use ($rows, &$counts): void {
            foreach ([PsgcLevel::Region, PsgcLevel::Province, PsgcLevel::City, PsgcLevel::Municipality, PsgcLevel::SubMunicipality] as $level) {
                foreach ($rows[$level->value] ?? [] as [$code, $name]) {
                    match ($level) {
                        PsgcLevel::Region => $this->importRegion($code, $name, $counts),
                        PsgcLevel::Province => $this->importProvince($code, $name, $counts),
                        default => $this->importMunicipality($code, $name, $level, $counts),
                    };
                }
            }
        });

        $this->components->info(sprintf(
            'Imported %d regions, %d provinces, %d cities/municipalities. Skipped %d rows without a parent.',
            $counts['regions'], $counts['provinces'], $counts['municipalities'], $counts['skipped'],
        ));

        return self::SUCCESS;
    }

    private function stringArgument(string $name): string
    {
        $value = $this->argument($name);

        return is_string($value) ? $value : '';
    }

    /**
     * @return array<string, list<array{0: PsgcCode, 1: string}>>
     */
    private function rows(string $path): array
    {
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD);

        $header = null;
        $rows = [];

        foreach ($file as $line) {
            if (! is_array($line) || $line === [null]) {
                continue;
            }

            if ($header === null) {
                $header = array_flip(array_map(fn ($value): string => strtolower(trim((string) $value)), $line));

                continue;
            }

            $code = PsgcCode::tryFrom((string) ($line[$header[self::COLUMN_CODE] ?? -1] ?? ''));
            $level = PsgcLevel::tryFrom(trim((string) ($line[$header[self::COLUMN_LEVEL] ?? -1] ?? '')));
            $name = trim((string) ($line[$header[self::COLUMN_NAME] ?? -1] ?? ''));

            if ($code === null || $level === null || $name === '') {
                continue;
            }

            $rows[$level->value][] = [$code, $name];
        }

        return $rows;
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function importRegion(PsgcCode $code, string $name, array &$counts): void
    {
        Region::query()->updateOrCreate(['psgc_code' => $code->value], ['name' => $name]);
        $counts['regions']++;
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function importProvince(PsgcCode $code, string $name, array &$counts): void
    {
        $region = Region::query()->where('psgc_code', $code->regionCode())->first();
        if ($region === null) {
            $counts['skipped']++;

            return;
        }

        Province::query()->updateOrCreate(['psgc_code' => $code->value], ['name' => $name, 'region_id' => $region->id]);
        $counts['provinces']++;
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function importMunicipality(PsgcCode $code, string $name, PsgcLevel $level, array &$counts): void
    {
        $region = Region::query()->where('psgc_code', $code->regionCode())->first();
        if ($region === null) {
            $counts['skipped']++;

            return;
        }

        $province = Province::query()->where('psgc_code', $code->provinceCode())->first();

        Municipality::query()->updateOrCreate(['psgc_code' => $code->value], [
            'name' => $name,
            'region_id' => $region->id,
            'province_id' => $province?->id,
            'is_city' => $level === PsgcLevel::City,
        ]);
        $counts['municipalities']++;
    }
}
