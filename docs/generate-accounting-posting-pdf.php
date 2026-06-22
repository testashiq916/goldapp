<?php

$source = __DIR__ . '/accounting-posting-logic.md';
$output = __DIR__ . '/accounting-posting-logic.pdf';

$markdown = file($source, FILE_IGNORE_NEW_LINES);
if ($markdown === false) {
    fwrite(STDERR, "Unable to read source markdown.\n");
    exit(1);
}

$pages = [];
$current = [];
$y = 790;
$left = 54;
$lineHeight = 12;
$maxChars = 92;

$flushPage = function () use (&$pages, &$current): void {
    if ($current !== []) {
        $pages[] = $current;
        $current = [];
    }
};

$addLine = function (string $text, int $size = 10, bool $bold = false, int $indent = 0) use (&$current, &$y, $left, $lineHeight, &$flushPage): void {
    if ($y < 54) {
        $flushPage();
        $y = 790;
    }
    $current[] = [
        'text' => $text,
        'x' => $left + $indent,
        'y' => $y,
        'size' => $size,
        'bold' => $bold,
    ];
    $y -= $lineHeight + max(0, $size - 10);
};

$wrap = function (string $text, int $max) {
    $text = trim($text);
    if ($text === '') return [''];
    $words = preg_split('/\s+/', $text) ?: [];
    $lines = [];
    $line = '';
    foreach ($words as $word) {
        $candidate = $line === '' ? $word : $line . ' ' . $word;
        if (strlen($candidate) > $max && $line !== '') {
            $lines[] = $line;
            $line = $word;
        } else {
            $line = $candidate;
        }
    }
    if ($line !== '') $lines[] = $line;
    return $lines;
};

foreach ($markdown as $raw) {
    $line = rtrim($raw);
    if ($line === '') {
        $y -= 5;
        continue;
    }

    if (preg_match('/^# (.+)$/', $line, $m)) {
        $y -= 6;
        foreach ($wrap($m[1], 48) as $part) {
            $addLine($part, 18, true);
        }
        $y -= 8;
        continue;
    }

    if (preg_match('/^## (.+)$/', $line, $m)) {
        $y -= 5;
        foreach ($wrap($m[1], 62) as $part) {
            $addLine($part, 14, true);
        }
        $y -= 4;
        continue;
    }

    if (preg_match('/^### (.+)$/', $line, $m)) {
        $y -= 3;
        foreach ($wrap($m[1], 72) as $part) {
            $addLine($part, 12, true);
        }
        $y -= 2;
        continue;
    }

    if (preg_match('/^- (.+)$/', $line, $m)) {
        $parts = $wrap($m[1], $maxChars - 4);
        foreach ($parts as $idx => $part) {
            $addLine(($idx === 0 ? '- ' : '  ') . $part, 10, false, 12);
        }
        continue;
    }

    foreach ($wrap($line, $maxChars) as $part) {
        $addLine($part, 10);
    }
}
$flushPage();

if ($pages === []) {
    $pages[] = [];
}

$objects = [];
$addObject = function (string $body) use (&$objects): int {
    $objects[] = $body;
    return count($objects);
};

$fontRegular = $addObject("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>");
$fontBold = $addObject("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>");

$pageRefs = [];
foreach ($pages as $pageIndex => $lines) {
    $stream = "BT\n";
    foreach ($lines as $line) {
        $font = $line['bold'] ? 'F2' : 'F1';
        $text = str_replace(["\\", "(", ")"], ["\\\\", "\\(", "\\)"], $line['text']);
        $stream .= sprintf("/%s %d Tf %.2f %.2f Td (%s) Tj\n", $font, $line['size'], $line['x'], $line['y'], $text);
    }
    $pageNumber = 'Page ' . ($pageIndex + 1) . ' of ' . count($pages);
    $stream .= sprintf("/F1 8 Tf 500.00 28.00 Td (%s) Tj\n", $pageNumber);
    $stream .= "ET";

    $contentRef = $addObject("<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream");
    $pageRefs[] = $addObject("<< /Type /Page /Parent 0 0 R /MediaBox [0 0 612 842] /Resources << /Font << /F1 {$fontRegular} 0 R /F2 {$fontBold} 0 R >> >> /Contents {$contentRef} 0 R >>");
}

$pagesRef = count($objects) + 1;
foreach ($pageRefs as $ref) {
    $objects[$ref - 1] = str_replace('/Parent 0 0 R', "/Parent {$pagesRef} 0 R", $objects[$ref - 1]);
}
$kids = implode(' ', array_map(fn ($ref) => "{$ref} 0 R", $pageRefs));
$addObject("<< /Type /Pages /Kids [{$kids}] /Count " . count($pageRefs) . " >>");
$catalogRef = $addObject("<< /Type /Catalog /Pages {$pagesRef} 0 R >>");

$pdf = "%PDF-1.4\n";
$offsets = [0];
foreach ($objects as $idx => $body) {
    $offsets[] = strlen($pdf);
    $num = $idx + 1;
    $pdf .= "{$num} 0 obj\n{$body}\nendobj\n";
}

$xref = strlen($pdf);
$pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
$pdf .= "0000000000 65535 f \n";
for ($i = 1; $i <= count($objects); $i++) {
    $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
}
$pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root {$catalogRef} 0 R >>\n";
$pdf .= "startxref\n{$xref}\n%%EOF\n";

file_put_contents($output, $pdf);
echo $output . PHP_EOL;
