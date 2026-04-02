<?php

namespace App\Console\Commands;

use App\Models\KnowledgeBase;
use App\Services\VectorEmbeddingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Re-embed all knowledge base records using the current embedding model.
 *
 * Useful after switching embedding providers/models (e.g., from
 * LocalEmbeddingService to Ollama all-minilm) to ensure every record
 * uses the same vector space for accurate cosine similarity comparisons.
 *
 * Usage:
 *   php artisan knowledge:reembed             # Re-embed all records
 *   php artisan knowledge:reembed --dry-run   # Preview without changes
 */
class ReembedKnowledgeBase extends Command
{
    protected $signature = 'knowledge:reembed
                            {--dry-run : Show what would be re-embedded without making changes}';

    protected $description = 'Regenerate embeddings for all knowledge base records using the current embedding model';

    private VectorEmbeddingService $embeddingService;

    public function __construct(VectorEmbeddingService $embeddingService)
    {
        parent::__construct();
        $this->embeddingService = $embeddingService;
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('Knowledge Base Re-embedding');
        $this->line('');

        if ($dryRun) {
            $this->warn('[DRY RUN] No changes will be made.');
            $this->line('');
        }

        // Check embedding service availability
        $this->info('Checking embedding service availability...');
        if (!$this->embeddingService->isAvailable()) {
            $this->error('Embedding service is not available.');
            $this->error('Make sure Ollama is running, or an OpenAI/Voyage API key is configured.');
            return self::FAILURE;
        }
        $this->info('Embedding service is available.');
        $this->line('');

        // Fetch all knowledge base records
        $records = KnowledgeBase::all();
        $total = $records->count();

        if ($total === 0) {
            $this->warn('No records found in the knowledge_base table. Nothing to re-embed.');
            return self::SUCCESS;
        }

        $this->info("Found {$total} record(s) to re-embed.");
        $this->line('');

        if ($dryRun) {
            $this->table(
                ['ID', 'Title', 'Category', 'Content (truncated)'],
                $records->map(function (KnowledgeBase $record) {
                    return [
                        $record->id,
                        $record->title ?? '(no title)',
                        $record->category ?? '(none)',
                        mb_substr($record->content_chunk ?? '', 0, 60) . '...',
                    ];
                })->toArray()
            );
            $this->line('');
            $this->info("[DRY RUN] {$total} record(s) would be re-embedded. No changes were made.");
            return self::SUCCESS;
        }

        // Process records with a progress bar
        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% -- %message%');
        $bar->setMessage('Starting...');
        $bar->start();

        $succeeded = 0;
        $failed = 0;

        foreach ($records as $record) {
            $bar->setMessage("ID {$record->id}: " . mb_substr($record->title ?? 'untitled', 0, 40));

            try {
                $text = $record->content_chunk ?? '';
                if (empty(trim($text))) {
                    $bar->setMessage("ID {$record->id}: skipped (empty content)");
                    $failed++;
                    $bar->advance();
                    continue;
                }

                $embedding = $this->embeddingService->generateEmbedding($text);

                if ($embedding === null || empty($embedding)) {
                    Log::warning("ReembedKnowledgeBase: Failed to generate embedding for record ID {$record->id}");
                    $failed++;
                    $bar->advance();
                    continue;
                }

                $record->embedding = json_encode($embedding);
                $record->save();

                $succeeded++;
            } catch (\Exception $e) {
                Log::error("ReembedKnowledgeBase: Error processing record ID {$record->id}: " . $e->getMessage());
                $failed++;
            }

            $bar->advance();
        }

        $bar->setMessage('Done!');
        $bar->finish();

        $this->line('');
        $this->line('');
        $this->info('Re-embedding complete.');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total records', $total],
                ['Succeeded', $succeeded],
                ['Failed', $failed],
            ]
        );

        if ($failed > 0) {
            $this->warn("{$failed} record(s) failed. Check the logs for details.");
        }

        return $failed === $total ? self::FAILURE : self::SUCCESS;
    }
}
