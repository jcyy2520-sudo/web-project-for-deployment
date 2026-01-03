<?php

namespace App\Console\Commands;

use App\Services\VectorEmbeddingService;
use Illuminate\Console\Command;

/**
 * Command to index knowledge base for the chatbot
 * 
 * Usage:
 *   php artisan chatbot:index-knowledge         # Index from bundle file
 *   php artisan chatbot:index-knowledge --rebuild  # Clear and rebuild all
 *   php artisan chatbot:index-knowledge --services # Index only DB services
 */
class IndexChatbotKnowledge extends Command
{
    protected $signature = 'chatbot:index-knowledge 
                            {--rebuild : Clear and rebuild the entire index}
                            {--services : Index services from database only}
                            {--path= : Custom path to knowledge bundle file}';
    
    protected $description = 'Index knowledge base content for chatbot semantic search';
    
    private VectorEmbeddingService $embeddingService;
    
    public function __construct(VectorEmbeddingService $embeddingService)
    {
        parent::__construct();
        $this->embeddingService = $embeddingService;
    }
    
    public function handle(): int
    {
        $this->info('🤖 Chatbot Knowledge Indexer');
        $this->line('');
        
        // Check if embedding service is available
        $this->info('Checking embedding service availability...');
        if (!$this->embeddingService->isAvailable()) {
            $this->warn('⚠️  Embedding service (Ollama/OpenAI) is not available.');
            $this->warn('   Make sure Ollama is running or OPENAI_API_KEY is set.');
            $this->warn('   Continuing with keyword-based fallback...');
            $this->line('');
        } else {
            $this->info('✅ Embedding service is available');
            $this->line('');
        }
        
        // Rebuild option
        if ($this->option('rebuild')) {
            $this->info('🔄 Rebuilding entire knowledge index...');
            $result = $this->embeddingService->rebuildIndex();
            
            if ($result['success'] ?? false) {
                $this->info("✅ Index rebuilt successfully!");
                $this->line("   Indexed: {$result['indexed']} documents");
                $this->line("   Failed:  {$result['failed']} documents");
            } else {
                $this->error("❌ Index rebuild failed: " . ($result['error'] ?? 'Unknown error'));
                return self::FAILURE;
            }
            
            return self::SUCCESS;
        }
        
        // Services only option
        if ($this->option('services')) {
            $this->info('📦 Indexing services from database...');
            $indexed = $this->embeddingService->indexServicesFromDatabase();
            $this->info("✅ Indexed {$indexed} services");
            
            return self::SUCCESS;
        }
        
        // Default: Load from bundle
        $bundlePath = $this->option('path');
        
        $this->info('📚 Loading knowledge bundle...');
        $this->line('');
        
        $result = $this->embeddingService->loadKnowledgeBundle($bundlePath);
        
        if ($result['success'] ?? false) {
            $this->info('✅ Knowledge bundle loaded successfully!');
            $this->line('');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Documents Indexed', $result['indexed']],
                    ['Documents Failed', $result['failed']],
                    ['Total Processed', $result['total']],
                ]
            );
        } else {
            $this->error('❌ Failed to load knowledge bundle');
            $this->error("   Error: " . ($result['error'] ?? 'Unknown error'));
            return self::FAILURE;
        }
        
        // Show total indexed documents
        $this->line('');
        $total = $this->embeddingService->getIndexedDocumentCount();
        $this->info("📊 Total documents in knowledge base: {$total}");
        
        return self::SUCCESS;
    }
}
