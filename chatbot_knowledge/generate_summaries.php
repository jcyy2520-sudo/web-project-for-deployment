<?php

$root = __DIR__;
$bundlePath = $root . '/chatbot_knowledge_bundle.json';
$outputSummaries = $root . '/file_summaries.json';
$outputBundle = $root . '/chatbot_knowledge_bundle_with_summaries.json';

if (!file_exists($bundlePath)) {
    echo "Bundle not found: $bundlePath\n";
    exit(1);
}

$data = json_decode(file_get_contents($bundlePath), true);
if ($data === null) {
    echo "Failed to parse bundle JSON\n";
    exit(1);
}

$summaries = [];
foreach ($data['files'] as $file) {
    $path = $file['path'] ?? ($file['file'] ?? 'unknown');
    $raw = '';
    if (!empty($file['summary'])) {
        $raw = $file['summary'];
    } elseif (!empty($file['content'])) {
        $raw = $file['content'];
    }

    // Strip markdown/code fences and delimiters
    $clean = preg_replace('/```+.*?```/s', ' ', $raw);
    $clean = preg_replace('/````markdown|````/i', ' ', $clean);
    $clean = strip_tags($clean);
    $clean = preg_replace('/\s+/', ' ', $clean);
    $clean = trim($clean);

    // Take first paragraph or first 400 chars
    $paragraphs = preg_split('/\n\s*\n/', $clean);
    $candidate = $paragraphs[0] ?? $clean;
    $candidate = trim($candidate);
    if (mb_strlen($candidate) > 400) {
        $candidate = mb_substr($candidate, 0, 397) . '...';
    }

    // Fallback if still empty
    if (empty($candidate)) {
        $candidate = mb_substr($clean, 0, 200);
    }

    $summaries[] = [
        'path' => $path,
        'concise_summary' => $candidate,
        'sha1' => $file['sha1'] ?? null,
        'kind' => $file['kind'] ?? null,
    ];
}

file_put_contents($outputSummaries, json_encode($summaries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

// Add summaries into a copy of the bundle
$data['summaries'] = $summaries;
file_put_contents($outputBundle, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "Summaries generated: " . count($summaries) . "\n";
echo "Wrote: $outputSummaries\n";
echo "Wrote: $outputBundle\n";

return 0;
