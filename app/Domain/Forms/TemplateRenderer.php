<?php

namespace App\Domain\Forms;

final class TemplateRenderer
{
    private const int ESCAPE_FLAGS = ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5;

    private const string LINE_BREAK = '<br>';

    private const string NEWLINE_PATTERN = '/\R/u';

    /**
     * @param  array<string, string>  $values
     */
    public static function render(string $template, array $values): RenderedTemplate
    {
        $missing = [];

        $html = (string) preg_replace_callback(Placeholder::PATTERN, function (array $match) use ($values, &$missing): string {
            $token = $match[1];
            if (! array_key_exists($token, $values)) {
                $missing[$token] = true;

                return self::escape($match[0]);
            }

            return (string) preg_replace(self::NEWLINE_PATTERN, self::LINE_BREAK, self::escape($values[$token]));
        }, $template);

        return new RenderedTemplate($html, array_keys($missing));
    }

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, self::ESCAPE_FLAGS, 'UTF-8');
    }
}
