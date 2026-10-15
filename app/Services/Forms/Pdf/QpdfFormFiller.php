<?php

namespace App\Services\Forms\Pdf;

use App\Enums\ErrorCode;
use App\Enums\LogEvent;
use App\Exceptions\BmsException;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use JsonException;

final readonly class QpdfFormFiller implements PdfFormFiller
{
    private const array SUCCESS_CODES = [0, 3];

    private const string INPUT = 'input.pdf';

    private const string PATCH = 'patch.json';

    private const string OUTPUT = 'output.pdf';

    private const string TEXT_PREFIX = 'u:';

    private const string OFF_STATE = '/Off';

    private const string OBJECT_KEY = 'obj:%s';

    private const string DIRECTORY_PREFIX = 'bms-pdf-';

    private const string VALUE_KEY = '/V';

    private const string STATE_KEY = '/AS';

    private const string APPEARANCE_KEY = '/AP';

    private const string NORMAL_APPEARANCE_KEY = '/N';

    private const string READ_FIELDS = '--json-key=acroform';

    private const string READ_OBJECTS = '--json-key=qpdf';

    private const string JSON_OUTPUT = '--json=2';

    private const string OBJECT_ARGUMENT = '--json-object=';

    private const string UPDATE_ARGUMENT = '--update-from-json=';

    private const array FLATTEN_ARGUMENTS = ['--generate-appearances', '--flatten-annotations=all'];

    public function __construct(
        private string $binary,
        private int $timeoutSeconds,
    ) {}

    public function fill(string $pdf, array $values): string
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.self::DIRECTORY_PREFIX.Str::lower((string) Str::ulid());
        File::ensureDirectoryExists($directory);
        $input = $directory.DIRECTORY_SEPARATOR.self::INPUT;
        $patch = $directory.DIRECTORY_SEPARATOR.self::PATCH;
        $output = $directory.DIRECTORY_SEPARATOR.self::OUTPUT;

        try {
            File::put($input, $pdf);
            $fields = $this->json([$this->binary, self::JSON_OUTPUT, self::READ_FIELDS, $input]);
            $acroFields = $fields['acroform']['fields'] ?? [];

            $targets = [];
            foreach (is_array($acroFields) ? $acroFields : [] as $field) {
                $name = is_array($field) ? ($field['fullname'] ?? null) : null;
                if (is_string($name) && array_key_exists($name, $values) && is_string($field['object'] ?? null)) {
                    $targets[] = $field;
                }
            }

            $command = [$this->binary, $input];
            if ($targets !== []) {
                File::put($patch, $this->patch($input, $targets, $values));
                $command[] = self::UPDATE_ARGUMENT.$patch;
            }

            $this->run([...$command, ...self::FLATTEN_ARGUMENTS, $output]);

            return File::get($output);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    /**
     * @param  list<array<mixed>>  $targets
     * @param  array<string, string|bool>  $values
     */
    private function patch(string $input, array $targets, array $values): string
    {
        $objectIds = [];
        foreach ($targets as $target) {
            $objectIds[] = $this->objectNumber((string) $target['object']);
            $annotation = $target['annotation']['object'] ?? null;
            if (is_string($annotation)) {
                $objectIds[] = $this->objectNumber($annotation);
            }
        }

        $arguments = array_map(fn (string $id): string => self::OBJECT_ARGUMENT.$id, array_values(array_unique($objectIds)));
        $document = $this->json([$this->binary, self::JSON_OUTPUT, self::READ_OBJECTS, ...$arguments, $input]);
        $header = $document['qpdf'][0] ?? [];
        $objects = $document['qpdf'][1] ?? [];
        if (! is_array($objects)) {
            throw BmsException::of(ErrorCode::PdfFillFailed);
        }

        foreach ($targets as $target) {
            $value = $values[(string) $target['fullname']];
            $fieldKey = sprintf(self::OBJECT_KEY, $target['object']);
            $isCheckbox = ($target['ischeckbox'] ?? false) === true;

            if (! $isCheckbox) {
                $objects[$fieldKey]['value'][self::VALUE_KEY] = self::TEXT_PREFIX.(is_bool($value) ? '' : $value);

                continue;
            }

            $annotationKey = sprintf(self::OBJECT_KEY, $target['annotation']['object'] ?? $target['object']);
            $state = $value === true ? $this->onState($objects[$annotationKey]['value'] ?? []) : self::OFF_STATE;
            $objects[$fieldKey]['value'][self::VALUE_KEY] = $state;
            $objects[$annotationKey]['value'][self::STATE_KEY] = $state;
        }

        try {
            return json_encode(['qpdf' => [$header, $objects]], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            throw BmsException::of(ErrorCode::PdfFillFailed);
        }
    }

    private function onState(mixed $annotation): string
    {
        $normal = is_array($annotation) ? ($annotation[self::APPEARANCE_KEY][self::NORMAL_APPEARANCE_KEY] ?? null) : null;
        foreach (is_array($normal) ? array_keys($normal) : [] as $state) {
            if (is_string($state) && $state !== self::OFF_STATE) {
                return $state;
            }
        }

        return self::OFF_STATE;
    }

    private function objectNumber(string $reference): string
    {
        return (string) (int) $reference;
    }

    /**
     * @param  list<string>  $command
     * @return array<mixed>
     */
    private function json(array $command): array
    {
        try {
            $decoded = json_decode($this->run($command), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw BmsException::of(ErrorCode::PdfFillFailed);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  list<string>  $command
     */
    private function run(array $command): string
    {
        try {
            $result = Process::timeout($this->timeoutSeconds)->run($command);
        } catch (ProcessTimedOutException) {
            Log::warning(LogEvent::PdfFillFailed->value, ['timeout_seconds' => $this->timeoutSeconds]);

            throw BmsException::of(ErrorCode::PdfFillFailed);
        }

        if (! in_array($result->exitCode(), self::SUCCESS_CODES, true)) {
            Log::warning(LogEvent::PdfFillFailed->value, ['exit_code' => $result->exitCode(), 'error' => Str::limit($result->errorOutput(), 500)]);

            throw BmsException::of(ErrorCode::PdfFillFailed);
        }

        return $result->output();
    }
}
