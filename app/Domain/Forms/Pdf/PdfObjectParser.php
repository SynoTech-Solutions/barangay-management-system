<?php

namespace App\Domain\Forms\Pdf;

final class PdfObjectParser
{
    private const string WHITESPACE = "\x00\t\n\x0c\r ";

    private const string DELIMITERS = '()<>[]{}/%';

    private const int MAX_DEPTH = 100;

    private const array ESCAPES = ['n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\x0c", '(' => '(', ')' => ')', '\\' => '\\'];

    private int $position;

    private readonly int $length;

    public function __construct(private readonly string $data, int $offset = 0)
    {
        $this->position = $offset;
        $this->length = strlen($data);
    }

    public function position(): int
    {
        return $this->position;
    }

    public function value(int $depth = 0): mixed
    {
        if ($depth > self::MAX_DEPTH) {
            throw new UnreadablePdf('Nesting too deep.');
        }

        $this->skipWhitespace();
        $char = $this->data[$this->position] ?? '';

        return match (true) {
            $char === '' => throw new UnreadablePdf('Unexpected end of data.'),
            str_starts_with(substr($this->data, $this->position, 2), '<<') => $this->dictionary($depth),
            $char === '<' => $this->hexString(),
            $char === '[' => $this->array($depth),
            $char === '(' => $this->literalString(),
            $char === '/' => $this->name(),
            $char === '+' || $char === '-' || $char === '.' || ctype_digit($char) => $this->numberOrReference(),
            default => $this->keyword(),
        };
    }

    public function skipWhitespace(): void
    {
        while ($this->position < $this->length) {
            $char = $this->data[$this->position];
            if ($char === '%') {
                while ($this->position < $this->length && $this->data[$this->position] !== "\n" && $this->data[$this->position] !== "\r") {
                    $this->position++;
                }
            } elseif (str_contains(self::WHITESPACE, $char)) {
                $this->position++;
            } else {
                return;
            }
        }
    }

    public function token(): string
    {
        $this->skipWhitespace();
        $start = $this->position;
        while ($this->position < $this->length && ! str_contains(self::WHITESPACE.self::DELIMITERS, $this->data[$this->position])) {
            $this->position++;
        }

        return substr($this->data, $start, $this->position - $start);
    }

    private function dictionary(int $depth): PdfDictionary
    {
        $this->position += 2;
        $entries = [];

        while (true) {
            $this->skipWhitespace();
            if (substr($this->data, $this->position, 2) === '>>') {
                $this->position += 2;

                return new PdfDictionary($entries);
            }

            $key = $this->value($depth + 1);
            if (! $key instanceof PdfName) {
                throw new UnreadablePdf('Dictionary key is not a name.');
            }
            $entries[$key->value] = $this->value($depth + 1);
        }
    }

    /**
     * @return list<mixed>
     */
    private function array(int $depth): array
    {
        $this->position++;
        $items = [];

        while (true) {
            $this->skipWhitespace();
            if (($this->data[$this->position] ?? '') === ']') {
                $this->position++;

                return $items;
            }
            $items[] = $this->value($depth + 1);
        }
    }

    private function name(): PdfName
    {
        $this->position++;
        $start = $this->position;
        while ($this->position < $this->length && ! str_contains(self::WHITESPACE.self::DELIMITERS, $this->data[$this->position])) {
            $this->position++;
        }

        $raw = substr($this->data, $start, $this->position - $start);

        return new PdfName((string) preg_replace_callback('/#([0-9A-Fa-f]{2})/', fn (array $match): string => chr((int) hexdec($match[1]) & 0xFF), $raw));
    }

    private function hexString(): string
    {
        $end = strpos($this->data, '>', $this->position);
        if ($end === false) {
            throw new UnreadablePdf('Unterminated hex string.');
        }

        $hex = (string) preg_replace('/[^0-9A-Fa-f]/', '', substr($this->data, $this->position + 1, $end - $this->position - 1));
        $this->position = $end + 1;

        return (string) hex2bin(strlen($hex) % 2 === 1 ? $hex.'0' : $hex);
    }

    private function literalString(): string
    {
        $this->position++;
        $depth = 1;
        $output = '';

        while ($this->position < $this->length) {
            $char = $this->data[$this->position++];

            if ($char === '\\') {
                $next = $this->data[$this->position] ?? '';
                if (isset(self::ESCAPES[$next])) {
                    $output .= self::ESCAPES[$next];
                    $this->position++;
                } elseif ($next !== '' && $next >= '0' && $next <= '7') {
                    $octal = '';
                    while (strlen($octal) < 3 && ($this->data[$this->position] ?? '') >= '0' && ($this->data[$this->position] ?? '') <= '7') {
                        $octal .= $this->data[$this->position++];
                    }
                    $output .= chr(octdec($octal) & 0xFF);
                } elseif ($next === "\r" || $next === "\n") {
                    $this->position++;
                    if ($next === "\r" && ($this->data[$this->position] ?? '') === "\n") {
                        $this->position++;
                    }
                }

                continue;
            }

            if ($char === '(') {
                $depth++;
            } elseif ($char === ')' && --$depth === 0) {
                return $output;
            }

            $output .= $char;
        }

        throw new UnreadablePdf('Unterminated string.');
    }

    private function numberOrReference(): int|float|PdfRef
    {
        $token = $this->token();
        if (! is_numeric($token)) {
            throw new UnreadablePdf('Invalid number.');
        }

        if (! ctype_digit($token)) {
            return str_contains($token, '.') ? (float) $token : (int) $token;
        }

        $saved = $this->position;
        $generation = $this->token();
        $marker = $this->token();
        if (ctype_digit($generation) && $marker === 'R') {
            return new PdfRef((int) $token);
        }

        $this->position = $saved;

        return (int) $token;
    }

    private function keyword(): ?bool
    {
        $token = $this->token();

        return match ($token) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => throw new UnreadablePdf('Unexpected token.'),
        };
    }
}
