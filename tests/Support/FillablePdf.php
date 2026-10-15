<?php

namespace Tests\Support;

final class FillablePdf
{
    private const string HEADER = "%PDF-1.7\n%\xE2\xE3\xCF\xD3\n";

    private const int PAGE_WIDTH = 612;

    private const int PAGE_HEIGHT = 792;

    private const int FIELD_LEFT = 72;

    private const int FIELD_TOP = 700;

    private const int FIELD_WIDTH = 300;

    private const int FIELD_HEIGHT = 20;

    private const int FIELD_GAP = 40;

    private const int BOX_SIZE = 18;

    /**
     * @param  list<string>  $textFields
     * @param  list<string>  $checkboxes
     */
    public static function make(array $textFields, array $checkboxes = []): string
    {
        $objects = [];
        $fieldRefs = [];
        $next = 7;
        $widgets = [];
        $row = 0;

        foreach ($textFields as $name) {
            $id = $next++;
            $y = self::FIELD_TOP - $row++ * self::FIELD_GAP;
            $widgets[$id] = sprintf(
                '<< /Type /Annot /Subtype /Widget /FT /Tx /T (%s) /Rect [%d %d %d %d] /P 3 0 R /F 4 /DA (/Helv 12 Tf 0 g) >>',
                $name, self::FIELD_LEFT, $y, self::FIELD_LEFT + self::FIELD_WIDTH, $y + self::FIELD_HEIGHT,
            );
            $fieldRefs[] = "{$id} 0 R";
        }

        foreach ($checkboxes as $name) {
            $id = $next++;
            $on = $next++;
            $off = $next++;
            $y = self::FIELD_TOP - $row++ * self::FIELD_GAP;
            $widgets[$id] = sprintf(
                '<< /Type /Annot /Subtype /Widget /FT /Btn /T (%s) /Rect [%d %d %d %d] /P 3 0 R /F 4 /V /Off /AS /Off /AP << /N << /Yes %d 0 R /Off %d 0 R >> >> >>',
                $name, self::FIELD_LEFT, $y, self::FIELD_LEFT + self::BOX_SIZE, $y + self::BOX_SIZE, $on, $off,
            );
            $widgets[$on] = self::stream('<< /Type /XObject /Subtype /Form /BBox [0 0 18 18] /Length %d >>', '0 g 3 3 12 12 re f');
            $widgets[$off] = self::stream('<< /Type /XObject /Subtype /Form /BBox [0 0 18 18] /Length %d >>', '0 G 1 1 16 16 re S');
            $fieldRefs[] = "{$id} 0 R";
        }

        $refs = implode(' ', $fieldRefs);
        $objects[1] = "<< /Type /Catalog /Pages 2 0 R /AcroForm << /Fields [{$refs}] /DA (/Helv 0 Tf 0 g) /DR << /Font << /Helv 4 0 R >> >> >> >>";
        $objects[2] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $objects[3] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %d %d] /Contents 5 0 R /Resources << /Font << /Helv 4 0 R >> >> /Annots [%s] >>', self::PAGE_WIDTH, self::PAGE_HEIGHT, $refs);
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[5] = self::stream('<< /Length %d >>', 'BT /Helv 14 Tf 72 740 Td (Sample request form) Tj ET');
        $objects[6] = '<< /Producer (test fixture) >>';
        $objects += $widgets;
        ksort($objects);

        $body = self::HEADER;
        $offsets = [];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($body);
            $body .= "{$id} 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($body);
        $size = max(array_keys($objects)) + 1;
        $body .= "xref\n0 {$size}\n0000000000 65535 f \n";
        for ($id = 1; $id < $size; $id++) {
            $body .= sprintf("%010d 00000 n \n", $offsets[$id]);
        }

        return $body."trailer\n<< /Size {$size} /Root 1 0 R /Info 6 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }

    private static function stream(string $dictionary, string $content): string
    {
        return sprintf($dictionary, strlen($content))."\nstream\n{$content}\nendstream";
    }
}
