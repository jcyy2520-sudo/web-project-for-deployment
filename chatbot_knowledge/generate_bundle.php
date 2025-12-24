<?php
// generate_bundle.php
// Scans selected project files (docs, routes, services) and writes chatbot_knowledge_bundle.json

$root = realpath(__DIR__ . '/..');
$backendServicesDir = $root . '/web-backend/app/Services/';
$docsDir = __DIR__ . '/docs/';
$routesDoc = __DIR__ . '/routes/api.php';

$servicesIndexFile = __DIR__ . '/services_index.json';

function readFileSafe($path) {
    if (!file_exists($path)) return null;
    return file_get_contents($path);
}

$bundle = [
    'generated_at' => date('c'),
    'source_root' => $root,
    'files' => []
];

// Add docs
$docFiles = glob($docsDir . '*.md');
foreach ($docFiles as $doc) {
    $content = readFileSafe($doc);
    if ($content === null) continue;
    $bundle['files'][] = [
        'path' => str_replace($root . '/', '', $doc),
        'kind' => 'doc',
        'sha1' => sha1($content),
        'summary' => trim(substr(strip_tags($content), 0, 400)),
        'content' => $content,
    ];
}

// Add routes copy
$routesContent = readFileSafe($routesDoc);
if ($routesContent !== null) {
    $bundle['files'][] = [
        'path' => str_replace($root . '/', '', $routesDoc),
        'kind' => 'routes_doc',
        'sha1' => sha1($routesContent),
        'summary' => trim(substr(strip_tags($routesContent), 0, 400)),
        'content' => $routesContent,
    ];
}

// Add services listed in services_index.json
$servicesIndex = json_decode(readFileSafe($servicesIndexFile) ?: '[]', true);
foreach ($servicesIndex as $svcFile) {
    $fullPath = $backendServicesDir . $svcFile;
    $content = readFileSafe($fullPath);
    if ($content === null) {
        // try alternative case (some filenames are CamelCase)
        $candidates = glob($backendServicesDir . '*' . pathinfo($svcFile, PATHINFO_FILENAME) . '*.php');
        $found = null;
        foreach ($candidates as $c) { if (file_exists($c)) { $found = $c; break; } }
        if ($found) $content = readFileSafe($found);
        $fullPath = $found ?: $fullPath;
    }

    if ($content === null) {
        $bundle['files'][] = [
            'path' => str_replace($root . '/', '', $fullPath),
            'kind' => 'service',
            'sha1' => null,
            'summary' => null,
            'content' => null,
            'note' => 'file not found'
        ];
        continue;
    }

    // Extract top docblock as summary if present
    $summary = null;
    if (preg_match('/\/\*\*(.*?)\*\//s', $content, $m)) {
        $docblock = trim($m[1]);
        $docblock = preg_replace('/\s*\*\s?/',' ', $docblock);
        $summary = trim(substr($docblock, 0, 400));
    }
    if (empty($summary)) {
        $summary = trim(substr(strip_tags($content), 0, 400));
    }

    $bundle['files'][] = [
        'path' => str_replace($root . '/', '', $fullPath),
        'kind' => 'service',
        'sha1' => sha1($content),
        'summary' => $summary,
        'content' => $content,
    ];
}

$outFile = __DIR__ . '/chatbot_knowledge_bundle.json';
// --- Redaction: remove obvious secrets and webhook tokens from collected content ---
function redact_sensitive(array $bundle): array
{
    $sensitivePatterns = [
        '/SLACK[_-]?WEBHOOK[_-]?URL\s*=\s*[^\n\r]+/i',
        '/PERSPECTIVE[_-]?API[_-]?KEY\s*=\s*[^\n\r]+/i',
        '/API[_-]?KEY\s*=\s*[^\n\r]+/i',
        '/SECRET\s*=\s*[^\n\r]+/i',
        '/APP[_-]?KEY\s*=\s*[^\n\r]+/i',
        '/DB[_-]?PASSWORD\s*=\s*[^\n\r]+/i',
        '/MAIL[_-]?PASSWORD\s*=\s*[^\n\r]+/i',
        '/WEBHOOK[_-]?URL\s*=\s*[^\n\r]+/i',
        '/\b(?:[A-Za-z0-9_\-]{20,})\b/' // long tokens (best-effort)
    ];

    foreach ($bundle['files'] as &$file) {
        if (empty($file['content'])) continue;
        $content = $file['content'];

        // Remove lines containing env-style secrets (e.g., SLACK_WEBHOOK_URL=...)
        $content = preg_replace($sensitivePatterns, '[REDACTED]', $content);

        // Remove any literal .env file content if accidentally included
        $content = preg_replace_callback('/^\s*([A-Za-z0-9_]+)=.*$/m', function ($m) {
            $key = $m[1] ?? 'VAR';
            $safe = $key . '=[REDACTED]';
            return $safe;
        }, $content);

        // Further limit: strip long base64-like blobs
        $content = preg_replace('/[A-Za-z0-9_\-]{40,}/', '[REDACTED]', $content);

        $file['content'] = $content;
        // Also sanitize summaries
        if (!empty($file['summary'])) {
            $file['summary'] = preg_replace($sensitivePatterns, '[REDACTED]', $file['summary']);
        }
    }

    return $bundle;
}

$bundle = redact_sensitive($bundle);

file_put_contents($outFile, json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "Bundle generated: {$outFile}\n";
echo "Files included: " . count($bundle['files']) . "\n";

// Exit
return 0;
