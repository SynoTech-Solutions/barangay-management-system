<?php

namespace App\Domain\Forms;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Dom\Text;

final class RichTextSanitizer
{
    private const array ALLOWED_TAGS = [
        'p', 'br', 'hr', 'h1', 'h2', 'h3', 'h4', 'strong', 'b', 'em', 'i', 'u', 's', 'sub', 'sup',
        'ul', 'ol', 'li', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'blockquote', 'div', 'span',
    ];

    private const array VOID_TAGS = ['br', 'hr'];

    private const array DROPPED_WITH_CONTENT = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'template', 'noscript', 'svg', 'math',
        'head', 'title', 'textarea', 'select', 'option', 'button', 'form', 'input', 'link', 'meta', 'base', 'canvas',
        'audio', 'video', 'source', 'track', 'applet', 'xml',
    ];

    private const array SPAN_TAGS = ['td', 'th'];

    private const array SPAN_ATTRIBUTES = ['colspan', 'rowspan'];

    private const int MAX_SPAN = 20;

    private const array ALIGNMENTS = ['left', 'right', 'center', 'justify'];

    private const string ALIGN_PATTERN = '/^\s*text-align\s*:\s*([a-z]+)\s*;?\s*$/i';

    private const string ROOT_WRAPPER = '<!DOCTYPE html><html><body>%s</body></html>';

    private const int ESCAPE_FLAGS = ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5;

    private const int MAX_DEPTH = 64;

    public static function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = HTMLDocument::createFromString(sprintf(self::ROOT_WRAPPER, $html), LIBXML_NOERROR);
        $body = $document->body;

        return $body === null ? '' : trim(self::children($body, 0));
    }

    private static function children(Node $parent, int $depth): string
    {
        if ($depth > self::MAX_DEPTH) {
            return self::escape((string) $parent->textContent);
        }

        $output = '';
        foreach ($parent->childNodes as $child) {
            if ($child instanceof Text) {
                $output .= self::escape($child->data);
            } elseif ($child instanceof Element) {
                $output .= self::element($child, $depth + 1);
            }
        }

        return $output;
    }

    private static function element(Element $element, int $depth): string
    {
        $tag = strtolower($element->localName);

        if (in_array($tag, self::DROPPED_WITH_CONTENT, true)) {
            return '';
        }

        if (! in_array($tag, self::ALLOWED_TAGS, true)) {
            return self::children($element, $depth);
        }

        $attributes = self::attributes($element, $tag);

        if (in_array($tag, self::VOID_TAGS, true)) {
            return "<{$tag}>";
        }

        return "<{$tag}{$attributes}>".self::children($element, $depth)."</{$tag}>";
    }

    private static function attributes(Element $element, string $tag): string
    {
        $output = '';

        $style = $element->getAttribute('style');
        if ($style !== null && preg_match(self::ALIGN_PATTERN, $style, $match) === 1 && in_array(strtolower($match[1]), self::ALIGNMENTS, true)) {
            $output .= ' style="text-align: '.strtolower($match[1]).'"';
        }

        $align = $element->getAttribute('align');
        if ($output === '' && $align !== null && in_array(strtolower($align), self::ALIGNMENTS, true)) {
            $output .= ' style="text-align: '.strtolower($align).'"';
        }

        if (in_array($tag, self::SPAN_TAGS, true)) {
            foreach (self::SPAN_ATTRIBUTES as $name) {
                $value = $element->getAttribute($name);
                if ($value !== null && ctype_digit($value) && (int) $value >= 1 && (int) $value <= self::MAX_SPAN) {
                    $output .= " {$name}=\"".(int) $value.'"';
                }
            }
        }

        return $output;
    }

    private static function escape(string $text): string
    {
        return htmlspecialchars($text, self::ESCAPE_FLAGS, 'UTF-8');
    }
}
