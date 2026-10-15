<?php

namespace App\Domain\Forms\Pdf;

use App\Enums\PdfFieldType;

final class AcroFormReader
{
    public const int MAX_FIELDS = 500;

    private const string SIGNATURE = '%PDF-';

    private const int SIGNATURE_WINDOW = 1024;

    private const string OBJECT_PATTERN = '/(?<![0-9])(\d+)\s+(\d+)\s+obj\b/';

    private const string TRAILER_PATTERN = '/trailer\s*(?=<<)/';

    private const string STREAM_KEYWORD = 'stream';

    private const string END_STREAM = 'endstream';

    private const int END_STREAM_WINDOW = 32;

    private const string FLATE = 'FlateDecode';

    private const string OFF_STATE = 'Off';

    private const int FLAG_RADIO = 1 << 15;

    private const int FLAG_PUSH_BUTTON = 1 << 16;

    private const int MAX_DEPTH = 32;

    private const string NAME_SEPARATOR = '.';

    /** @var array<int, mixed> */
    private array $objects = [];

    /** @var array<int, string> */
    private array $streams = [];

    /** @var list<PdfDictionary> */
    private array $trailers = [];

    /** @var array<int, true> */
    private array $visited = [];

    /** @var array<string, PdfFormField> */
    private array $fields = [];

    private function __construct(private readonly string $pdf) {}

    /**
     * @return list<PdfFormField>
     */
    public static function read(string $pdf): array
    {
        $start = strpos(substr($pdf, 0, self::SIGNATURE_WINDOW), self::SIGNATURE);
        if ($start === false) {
            throw new UnreadablePdf('Not a PDF file.');
        }

        $reader = new self($pdf);
        $reader->collectObjects();
        $reader->collectTrailers();
        $reader->expandObjectStreams();

        if ($reader->isEncrypted()) {
            throw new UnreadablePdf('Encrypted PDF files are not supported.');
        }

        $reader->walkForm();

        return array_values($reader->fields);
    }

    private function collectObjects(): void
    {
        preg_match_all(self::OBJECT_PATTERN, $this->pdf, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $skipUntil = 0;

        foreach ($matches as $match) {
            $offset = $match[0][1];
            if ($offset < $skipUntil) {
                continue;
            }

            $parser = new PdfObjectParser($this->pdf, $offset + strlen($match[0][0]));
            try {
                $value = $parser->value();
            } catch (UnreadablePdf) {
                continue;
            }

            $id = (int) $match[1][0];
            $this->objects[$id] = $value;
            unset($this->streams[$id]);

            $after = $parser->position();
            if ($value instanceof PdfDictionary && $parser->token() === self::STREAM_KEYWORD) {
                $streamStart = $parser->position();
                if (($this->pdf[$streamStart] ?? '') === "\r") {
                    $streamStart++;
                }
                if (($this->pdf[$streamStart] ?? '') === "\n") {
                    $streamStart++;
                }

                $length = $value->integer('Length');
                $end = $length !== null && str_contains(substr($this->pdf, $streamStart + $length, self::END_STREAM_WINDOW), self::END_STREAM)
                    ? $streamStart + $length
                    : strpos($this->pdf, self::END_STREAM, $streamStart);

                if ($end === false) {
                    continue;
                }

                $this->streams[$id] = substr($this->pdf, $streamStart, $end - $streamStart);
                $skipUntil = $end;
            } else {
                $skipUntil = $after;
            }
        }
    }

    private function collectTrailers(): void
    {
        preg_match_all(self::TRAILER_PATTERN, $this->pdf, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[0] as [$text, $offset]) {
            try {
                $value = (new PdfObjectParser($this->pdf, $offset + strlen($text)))->value();
            } catch (UnreadablePdf) {
                continue;
            }
            if ($value instanceof PdfDictionary) {
                $this->trailers[] = $value;
            }
        }

        foreach ($this->objects as $object) {
            if ($object instanceof PdfDictionary && $object->name('Type') === 'XRef') {
                $this->trailers[] = $object;
            }
        }
    }

    private function expandObjectStreams(): void
    {
        foreach ($this->objects as $id => $object) {
            if (! $object instanceof PdfDictionary || $object->name('Type') !== 'ObjStm' || ! isset($this->streams[$id])) {
                continue;
            }

            $data = $this->decode($object, $this->streams[$id]);
            $count = $object->integer('N');
            $first = $object->integer('First');
            if ($data === null || $count === null || $first === null) {
                continue;
            }

            $header = new PdfObjectParser($data);
            for ($index = 0; $index < $count; $index++) {
                $objectId = $header->token();
                $relative = $header->token();
                if (! ctype_digit($objectId) || ! ctype_digit($relative)) {
                    break;
                }

                if (isset($this->objects[(int) $objectId])) {
                    continue;
                }

                try {
                    $this->objects[(int) $objectId] = (new PdfObjectParser($data, $first + (int) $relative))->value();
                } catch (UnreadablePdf) {
                    continue;
                }
            }
        }
    }

    private function decode(PdfDictionary $dictionary, string $data): ?string
    {
        $filter = $dictionary->get('Filter');
        $filters = is_array($filter) ? $filter : ($filter === null ? [] : [$filter]);

        foreach ($filters as $name) {
            if (! $name instanceof PdfName || $name->value !== self::FLATE || $dictionary->get('DecodeParms') !== null) {
                return null;
            }

            $decoded = @gzuncompress($data);
            if ($decoded === false) {
                return null;
            }
            $data = $decoded;
        }

        return $data;
    }

    private function isEncrypted(): bool
    {
        foreach ($this->trailers as $trailer) {
            if ($trailer->get('Encrypt') !== null) {
                return true;
            }
        }

        return false;
    }

    private function walkForm(): void
    {
        $catalog = null;
        foreach (array_reverse($this->trailers) as $trailer) {
            $root = $this->resolve($trailer->get('Root'));
            if ($root instanceof PdfDictionary) {
                $catalog = $root;
                break;
            }
        }

        if ($catalog === null) {
            foreach ($this->objects as $object) {
                if ($object instanceof PdfDictionary && $object->name('Type') === 'Catalog') {
                    $catalog = $object;
                }
            }
        }

        $form = $catalog === null ? null : $this->resolve($catalog->get('AcroForm'));
        $fields = $form instanceof PdfDictionary ? $this->resolve($form->get('Fields')) : null;
        if (! is_array($fields) || $fields === []) {
            throw new UnreadablePdf('The PDF has no form fields.');
        }

        foreach ($fields as $field) {
            $this->walkField($field, null, null, 0, 0);
        }

        if ($this->fields === []) {
            throw new UnreadablePdf('The PDF has no form fields.');
        }
    }

    private function walkField(mixed $reference, ?string $parentName, ?string $inheritedType, int $inheritedFlags, int $depth): void
    {
        if ($depth > self::MAX_DEPTH || count($this->fields) >= self::MAX_FIELDS) {
            return;
        }

        if ($reference instanceof PdfRef) {
            if (isset($this->visited[$reference->id])) {
                return;
            }
            $this->visited[$reference->id] = true;
        }

        $field = $this->resolve($reference);
        if (! $field instanceof PdfDictionary) {
            return;
        }

        $partial = $this->resolve($field->get('T'));
        $name = is_string($partial) ? self::text($partial) : null;
        $fullName = $name === null ? $parentName : ($parentName === null ? $name : $parentName.self::NAME_SEPARATOR.$name);
        $type = $field->name('FT') ?? $inheritedType;
        $flags = $field->integer('Ff') ?? $inheritedFlags;

        $kids = $this->resolve($field->get('Kids'));
        $kidFields = [];
        $widgets = [$field];
        if (is_array($kids)) {
            foreach ($kids as $kid) {
                $kidDictionary = $this->resolve($kid);
                if ($kidDictionary instanceof PdfDictionary && $kidDictionary->get('T') !== null) {
                    $kidFields[] = $kid;
                } elseif ($kidDictionary instanceof PdfDictionary) {
                    $widgets[] = $kidDictionary;
                }
            }
        }

        foreach ($kidFields as $kid) {
            $this->walkField($kid, $fullName, $type, $flags, $depth + 1);
        }

        if ($kidFields !== [] || $fullName === null || $type === null || isset($this->fields[$fullName])) {
            return;
        }

        $fieldType = self::fieldType($type, $flags);
        if ($fieldType !== null) {
            $this->fields[$fullName] = new PdfFormField($fullName, $fieldType, $fieldType === PdfFieldType::Checkbox ? $this->onState($widgets) : null);
        }
    }

    /**
     * @param  list<PdfDictionary>  $widgets
     */
    private function onState(array $widgets): ?string
    {
        foreach ($widgets as $widget) {
            $appearance = $this->resolve($widget->get('AP'));
            $normal = $appearance instanceof PdfDictionary ? $this->resolve($appearance->get('N')) : null;
            if ($normal instanceof PdfDictionary) {
                foreach (array_keys($normal->entries) as $state) {
                    if ($state !== self::OFF_STATE) {
                        return $state;
                    }
                }
            }
        }

        return null;
    }

    private static function fieldType(string $type, int $flags): ?PdfFieldType
    {
        return match ($type) {
            'Tx' => PdfFieldType::Text,
            'Ch' => PdfFieldType::Choice,
            'Sig' => PdfFieldType::Signature,
            'Btn' => match (true) {
                ($flags & self::FLAG_PUSH_BUTTON) !== 0 => PdfFieldType::PushButton,
                ($flags & self::FLAG_RADIO) !== 0 => PdfFieldType::Radio,
                default => PdfFieldType::Checkbox,
            },
            default => null,
        };
    }

    private function resolve(mixed $value, int $hops = 0): mixed
    {
        while ($value instanceof PdfRef && $hops++ < self::MAX_DEPTH) {
            $value = $this->objects[$value->id] ?? null;
        }

        return $value;
    }

    public static function text(string $raw): string
    {
        if (str_starts_with($raw, "\xFE\xFF")) {
            return (string) mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16BE');
        }

        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            return substr($raw, 3);
        }

        return mb_check_encoding($raw, 'UTF-8') ? $raw : (string) mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1');
    }
}
