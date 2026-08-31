<?php
/**
 * build.php — Static lesson page generator
 *
 * Generates 95 standalone HTML files (one per lesson) in lessons/
 * Run once: php build.php
 * Re-run any time lesson.php content changes.
 */

$outDir = __DIR__ . '/lessons';
if (!is_dir($outDir)) {
    mkdir($outDir, 0755, true);
    echo "Created lessons/ directory\n";
}

// Load the roadmap for iteration (lesson.php also defines this, but we need
// it here before any include so we know which params to pass).
require_once __DIR__ . '/roadmap_data.php';

$count   = 0;
$errors  = 0;

echo "Generating 95 lesson pages…\n\n";

foreach ($roadmap as $chapterPos => $chapter) {
    foreach ($chapter['items'] as $lessonIdx => $lesson) {

        // Point lesson.php at this lesson via superglobal
        $_GET = ['c' => (string)$chapter['id'], 'l' => (string)$lessonIdx];

        // Capture lesson.php output
        ob_start();
        include __DIR__ . '/lesson.php';
        $html = ob_get_clean();

        if (empty($html)) {
            echo "  ⚠ EMPTY: c{$chapter['id']}-l{$lessonIdx} ({$lesson['title']})\n";
            $errors++;
            continue;
        }

        // ── Fix relative URLs for the lessons/ subdirectory ──────────────────
        // lesson.php links to lesson.php?c=X&l=Y  →  cX-lY.html (same dir)
        $html = preg_replace(
            '/href="lesson\.php\?c=(\d+)&(?:amp;)?l=(\d+)"/',
            'href="c$1-l$2.html"',
            $html
        );
        // lesson.php links to index.php  →  ../index.php
        $html = preg_replace('/href="index\.php(#[^"]*)?"/i', 'href="../index.php$1"', $html);

        // Save
        $filename = $outDir . "/c{$chapter['id']}-l{$lessonIdx}.html";
        file_put_contents($filename, $html);
        $count++;

        $padded = str_pad("lessons/c{$chapter['id']}-l{$lessonIdx}.html", 28);
        echo "  ✓ {$padded} {$lesson['title']}\n";
    }
}

echo "\n";
if ($errors) {
    echo "⚠  Done with {$errors} error(s). Generated {$count}/95 pages.\n";
} else {
    echo "✅ All {$count} lesson pages generated in lessons/\n";
    echo "   Open index.php in a browser — all lesson links are now static HTML.\n";
    echo "   Dynamic fallback still available at lesson.php?c=X&l=Y\n";
}
