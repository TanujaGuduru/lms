<?php

declare(strict_types=1);

namespace App\Core;

/**
 * A minimal, hand-rolled PDF writer — single-page-text-report capable, no
 * external library, no Composer dependency, matching this app's existing
 * no-Composer-at-runtime convention (the same reasoning that produced
 * hand-rolled Argon2 config and HMAC-signed file URLs elsewhere). Built for
 * Monthly Parent Reports (docs/student-module/04f) and reused for
 * Certificates (04g) — both originally pointed at S3/an HTML+CSS template
 * renderer respectively; with no cloud service and no headless-browser/
 * HTML-to-PDF engine available on shared hosting, something still has to
 * produce real PDF bytes for `parent_reports.pdf_url` /
 * `certificates.pdf_path`.
 *
 * Deliberately simple: one font (Helvetica, a standard PDF base-14 font —
 * no embedding needed), automatic word-wrap and pagination, basic centered-
 * text support (an approximate-width estimate, not real font metrics —
 * close enough to center a certificate title, not pixel-perfect). No
 * tables, images, or `certificate_templates.html_template`/`css_styles`
 * rendering — that template system stays unused; a real HTML+CSS-to-PDF
 * engine is exactly the kind of heavy dependency this build avoids.
 */
class SimplePdf
{
    private const PAGE_WIDTH = 612;  // US Letter, points
    private const PAGE_HEIGHT = 792;
    private const MARGIN = 50;
    private const DEFAULT_FONT_SIZE = 12;
    private const LEADING = 16;
    private const CHARS_PER_LINE = 80;
    private const AVG_CHAR_WIDTH_FACTOR = 0.5; // rough Helvetica approximation, good enough to center text

    /** @var list<array{text: string, size: int, align: string}> */
    private array $lines = [];

    public function addHeading(string $text): void
    {
        if ($this->lines) {
            $this->addLine('');
        }
        $this->addLine($text);
        $this->addLine('');
    }

    public function addLine(string $text = '', int $size = self::DEFAULT_FONT_SIZE, string $align = 'left'): void
    {
        $this->lines[] = ['text' => $text, 'size' => $size, 'align' => $align];
    }

    public function addCenteredLine(string $text, int $size = 18): void
    {
        $this->addLine($text, $size, 'center');
    }

    public function addParagraph(string $text): void
    {
        foreach ($this->wrap($text) as $line) {
            $this->addLine($line);
        }
        $this->addLine('');
    }

    private function wrap(string $text): array
    {
        $wrapped = wordwrap($text, self::CHARS_PER_LINE, "\n", true);
        return explode("\n", $wrapped);
    }

    public function toBytes(): string
    {
        $linesPerPage = (int) floor((self::PAGE_HEIGHT - 2 * self::MARGIN) / self::LEADING);
        $pages = array_chunk($this->lines ?: [['text' => '', 'size' => self::DEFAULT_FONT_SIZE, 'align' => 'left']], max(1, $linesPerPage));

        $objects = [];
        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";

        $nextObj = 4; // 1=Catalog, 2=Pages, 3=Font, then page/content pairs from 4 onward
        $objects[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";

        $pageRefs = [];
        foreach ($pages as $pageLines) {
            $pageObj = $nextObj++;
            $contentObj = $nextObj++;
            $pageRefs[] = "{$pageObj} 0 R";

            $objects[$pageObj] = "<< /Type /Page /Parent 2 0 R "
                . "/Resources << /Font << /F1 3 0 R >> >> "
                . "/MediaBox [0 0 " . self::PAGE_WIDTH . ' ' . self::PAGE_HEIGHT . "] "
                . "/Contents {$contentObj} 0 R >>";

            $stream = $this->buildContentStream($pageLines);
            $objects[$contentObj] = "<< /Length " . strlen($stream) . " >>\nstream\n{$stream}endstream";
        }

        $objects[2] = "<< /Type /Pages /Kids [" . implode(' ', $pageRefs) . "] /Count " . count($pageRefs) . " >>";

        return $this->assemble($objects);
    }

    private function buildContentStream(array $pageLines): string
    {
        $ops = ['BT'];
        $y = self::PAGE_HEIGHT - self::MARGIN;

        foreach ($pageLines as $line) {
            $text = $this->escape($line['text']);
            $x = $line['align'] === 'center'
                ? max(self::MARGIN, (self::PAGE_WIDTH - $this->estimateWidth($text, $line['size'])) / 2)
                : self::MARGIN;

            $ops[] = "/F1 {$line['size']} Tf";
            $ops[] = "1 0 0 1 {$x} {$y} Tm";
            $ops[] = "({$text}) Tj";
            $y -= self::LEADING;
        }

        $ops[] = 'ET';

        return implode("\n", $ops) . "\n";
    }

    private function estimateWidth(string $text, int $size): float
    {
        return strlen($text) * $size * self::AVG_CHAR_WIDTH_FACTOR;
    }

    /**
     * PDF text strings for a standard (non-embedded) font like Helvetica
     * are single-byte WinAnsi, not UTF-8 — writing raw UTF-8 bytes (e.g.
     * from AI-generated summary text, which routinely contains em-dashes
     * and curly quotes) produces mojibake, not an error, so this is easy to
     * ship broken without ever seeing an exception. Transliterate the
     * common offenders to ASCII and drop anything else outside WinAnsi's
     * single-byte range — full Unicode would need an embedded font, well
     * beyond what a "report card's worth of text" needs.
     */
    private function escape(string $text): string
    {
        $replacements = [
            "\u{2014}" => '-', "\u{2013}" => '-',
            "\u{2018}" => "'", "\u{2019}" => "'",
            "\u{201C}" => '"', "\u{201D}" => '"',
            "\u{2026}" => '...',
        ];
        $text = str_replace(array_keys($replacements), array_values($replacements), $text);
        $text = preg_replace('/[^\x20-\x7E]/', '', $text) ?? $text;

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function assemble(array $objects): string
    {
        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [0 => 0];

        foreach ($objects as $num => $body) {
            $offsets[$num] = strlen($pdf);
            $pdf .= "{$num} 0 obj\n{$body}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $count = max($objects ? array_key_last($objects) : 0, 0) + 1;

        $pdf .= "xref\n0 {$count}\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i < $count; $i++) {
            $offset = $offsets[$i] ?? 0;
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }
}
