Chatbot Knowledge Bundle
=========================

This folder contains a generator to produce a structured JSON bundle for feeding your AI chatbot.

Files:
- `generate_bundle.php` - Scans selected docs, a routes copy, and backend services (from `services_index.json`) and writes `chatbot_knowledge_bundle.json`.
- `services_index.json` - List of service filenames to include (used by the generator).
- `docs/` - Copies of key Markdown docs.

How to run (Windows / PowerShell):

1. Generate bundle now:

```powershell
cd c:\laragon\www\web\chatbot_knowledge
php generate_bundle.php
```

2. Set up near-real-time regeneration (PowerShell watcher example):

```powershell
$folder = 'c:\laragon\www\web'
$watcher = New-Object System.IO.FileSystemWatcher $folder -Property @{IncludeSubdirectories = $true; NotifyFilter = [System.IO.NotifyFilters]'FileName, LastWrite'}
Register-ObjectEvent $watcher Changed -Action { 
    Write-Host "Change detected. Regenerating chatbot bundle..."; 
    php "c:\laragon\www\web\chatbot_knowledge\generate_bundle.php" 
}

# Keep the PowerShell session open to keep watching
```

Notes & recommendations:
- The generator includes full content for docs and any service files listed in `services_index.json` that exist in `web-backend/app/Services/`.
- Summaries are extracted from the top docblock if present; otherwise the first 400 characters are used.
- The bundle is safe-for-indexing but does not alter any source files.
- For production RAG ingestion, consider: tokenization, chunking, embedding (SemanticEmbeddingsService), and secret redaction (strip `.env` values).

If you want, I can:
- Expand `services_index.json` to include controllers, repositories, models and frontend files.
- Add an automated Git hook or CI job to regenerate the bundle on push.
- Produce an embeddings pipeline (node/php) that creates vector embeddings for fast retrieval.
