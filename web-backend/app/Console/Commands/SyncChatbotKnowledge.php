<?php

namespace App\Console\Commands;

use App\Services\SemanticEmbeddingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * SyncChatbotKnowledge Command
 * 
 * Regenerates the chatbot knowledge bundle and rebuilds semantic embeddings.
 * This command is automatically triggered when:
 * - Service configurations change
 * - AppointmentSettings are updated
 * - Manually run via: php artisan chatbot:sync-knowledge
 * 
 * The knowledge sync ensures the chatbot always has accurate, up-to-date
 * information about services, pricing, business rules, and system capabilities.
 */
class SyncChatbotKnowledge extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chatbot:sync-knowledge 
                            {--force : Force regeneration even if no changes detected}
                            {--skip-embeddings : Skip embedding regeneration (faster)}';

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
        $startTime = microtime(true);
        $this->info('Starting chatbot knowledge synchronization...');

        // 1. Check if sync is needed (unless forced)
        if (!$this->option('force')) {
            $lastSyncHash = Cache::get('chatbot_knowledge_hash');
            $currentHash = $this->calculateKnowledgeHash();
            
            if ($lastSyncHash === $currentHash) {
                $this->info('No changes detected. Use --force to regenerate anyway.');
                return 0;
            }
        }

        // 2. Run the bundle generator
        $bundleGenerator = base_path('../chatbot_knowledge/generate_bundle.php');
        if (file_exists($bundleGenerator)) {
            $this->info('Generating knowledge bundle...');
            exec("php \"$bundleGenerator\" 2>&1", $output, $returnVar);
            
            if ($returnVar !== 0) {
                $this->error('Failed to generate knowledge bundle.');
                $this->line(implode("\n", $output));
                Log::error('Chatbot knowledge bundle generation failed', ['output' => $output]);
                return 1;
            }
            $this->info('Knowledge bundle generated successfully.');
        } else {
            $this->warn('Knowledge bundle generator not found at ' . $bundleGenerator);
        }

        // 3. Rebuild knowledge base (unless skipped)
        if (!$this->option('skip-embeddings')) {
            $this->info('Rebuilding semantic knowledge base (this may take a while)...');
            try {
                $success = $embeddingsService->rebuildKnowledgeBase();
                
                if ($success) {
                    $this->info('Knowledge base rebuilt successfully!');
                } else {
                    $this->warn('Knowledge base rebuild completed with warnings.');
                }
            } catch (\Exception $e) {
                $this->error('Error rebuilding knowledge base: ' . $e->getMessage());
                Log::error('SyncChatbotKnowledge error: ' . $e->getMessage());
                // Continue even if embeddings fail - the bundle is still useful
            }
        } else {
            $this->info('Skipping embeddings regeneration (--skip-embeddings flag used).');
        }

        // 4. Clear all chatbot caches to ensure fresh data
        $this->info('Clearing chatbot caches...');
        $this->clearChatbotCaches();

        // 5. Update sync hash
        $newHash = $this->calculateKnowledgeHash();
        Cache::forever('chatbot_knowledge_hash', $newHash);
        Cache::put('chatbot_last_sync', now()->toIso8601String(), 86400);

        $elapsed = round(microtime(true) - $startTime, 2);
        $this->info("Synchronization complete in {$elapsed}s!");
        
        Log::info('Chatbot knowledge synchronized', [
            'elapsed_seconds' => $elapsed,
            'hash' => $newHash,
        ]);
        
        return 0;
    }

    /**
     * Calculate a hash of the knowledge sources for change detection
     */
    private function calculateKnowledgeHash(): string
    {
        $hashes = [];
        
        // Hash services
        $services = \App\Models\Service::where('is_active', true)
            ->select('id', 'name', 'price', 'description', 'updated_at')
            ->get()
            ->toArray();
        $hashes[] = md5(json_encode($services));
        
        // Hash appointment settings
        $settings = \App\Models\AppointmentSettings::first();
        if ($settings) {
            $hashes[] = md5(json_encode($settings->toArray()));
        }
        
        // Hash bundle file modification time
        $bundlePath = base_path('../chatbot_knowledge/chatbot_knowledge_bundle.json');
        if (file_exists($bundlePath)) {
            $hashes[] = md5(filemtime($bundlePath) . filesize($bundlePath));
        }
        
        return md5(implode('|', $hashes));
    }

    /**
     * Clear all chatbot-related caches
     */
    private function clearChatbotCaches(): void
    {
        $keys = [
            'chatbot_system_stats',
            'chatbot_todays_summary',
            'chatbot_available_services',
            'chatbot_business_hours',
            'chatbot_all_appointments',
            'chatbot_pending_appointments',
            'chatbot_pending_payments',
            'chatbot_pending_refunds',
            'chatbot_system_health',
            'chatbot_analytics_summary',
            'chatbot_staff_list',
            'chatbot_services_list',
            'chatbot_embeddings_version',
            'chatbot_services_pricing',
            'chatbot_appointment_settings',
            'chatbot_booking_rules',
        ];
        
        foreach ($keys as $key) {
            Cache::forget($key);
        }
        
        $this->info('Cleared ' . count($keys) . ' cache keys.');
    }
}
