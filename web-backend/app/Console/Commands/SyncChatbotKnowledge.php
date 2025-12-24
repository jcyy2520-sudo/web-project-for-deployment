<?php

namespace App\Console\Commands;

use App\Services\SemanticEmbeddingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncChatbotKnowledge extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chatbot:sync-knowledge';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regenerate chatbot knowledge bundle and rebuild semantic embeddings';

    /**
     * Execute the console command.
     */
    public function handle(SemanticEmbeddingsService $embeddingsService)
    {
        $this->info('Starting chatbot knowledge synchronization...');

        // 1. Run the bundle generator
        $bundleGenerator = base_path('../chatbot_knowledge/generate_bundle.php');
        if (file_exists($bundleGenerator)) {
            $this->info('Generating knowledge bundle...');
            exec("php \"$bundleGenerator\"", $output, $returnVar);
            
            if ($returnVar !== 0) {
                $this->error('Failed to generate knowledge bundle.');
                return 1;
            }
            $this->info('Knowledge bundle generated successfully.');
        } else {
            $this->warn('Knowledge bundle generator not found at ' . $bundleGenerator);
        }

        // 2. Rebuild knowledge base
        $this->info('Rebuilding semantic knowledge base (this may take a while)...');
        try {
            $success = $embeddingsService->rebuildKnowledgeBase();
            
            if ($success) {
                $this->info('Knowledge base rebuilt successfully!');
            } else {
                $this->error('Failed to rebuild knowledge base.');
                return 1;
            }
        } catch (\Exception $e) {
            $this->error('Error rebuilding knowledge base: ' . $e->getMessage());
            Log::error('SyncChatbotKnowledge error: ' . $e->getMessage());
            return 1;
        }

        $this->info('Synchronization complete!');
        return 0;
    }
}
