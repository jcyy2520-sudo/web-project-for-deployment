<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * EmbeddingService - Vector Embeddings for Semantic Search & RAG
 * 
 * Features:
 * - Generate embeddings via OpenAI, Voyage, or local models
 * - Store embeddings in database (SQLite with json, or pgvector)
 * - Semantic similarity search
 * - Knowledge base indexing
 * - Conversation embedding for long-term memory
 */
class EmbeddingService
{
    private const OPENAI_EMBEDDING_URL = 'https://api.openai.com/v1/embeddings';
    private const VOYAGE_EMBEDDING_URL = 'https://api.voyageai.com/v1/embeddings';
    private const OLLAMA_EMBEDDING_URL = 'http://localhost:11434/api/embeddings';

    // Embedding dimensions by model
    private const DIMENSIONS = [
        'text-embedding-3-small' => 1536,
        'text-embedding-3-large' => 3072,
        'voyage-2' => 1024,
        'nomic-embed-text' => 768,
        'mxbai-embed-large' => 1024,
    ];

    private array $config;
    private string $provider;

    public function __construct()
    {
        $this->config = [
            'openai_key' => config('services.openai.api_key'),
            'voyage_key' => config('services.voyage.api_key'),
            'use_ollama' => config('services.ollama.embeddings_enabled', false),
            'ollama_model' => config('services.ollama.embedding_model', 'nomic-embed-text'),
            'default_model' => config('services.embedding.model', 'text-embedding-3-small'),
        ];

        $this->provider = $this->determineProvider();
    }

    /**
     * Generate embedding for text
     */
    public function generateEmbedding(string $text): ?array
    {
        $text = $this->preprocessText($text);

        try {
            switch ($this->provider) {
                case 'openai':
                    return $this->generateViaOpenAI($text);
                case 'voyage':
                    return $this->generateViaVoyage($text);
                case 'ollama':
                    return $this->generateViaOllama($text);
                default:
                    Log::warning('No embedding provider available');
                    return null;
            }
        } catch (\Exception $e) {
            Log::error('Embedding generation failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate embedding via OpenAI
     */
    private function generateViaOpenAI(string $text): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->config['openai_key'],
        ])
        ->timeout(30)
        ->post(self::OPENAI_EMBEDDING_URL, [
            'input' => $text,
            'model' => $this->config['default_model'],
        ]);

        if (!$response->successful()) {
            throw new \Exception('OpenAI embedding error: ' . $response->status());
        }

        return $response->json()['data'][0]['embedding'];
    }

    /**
     * Generate embedding via Voyage AI
     */
    private function generateViaVoyage(string $text): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->config['voyage_key'],
        ])
        ->timeout(30)
        ->post(self::VOYAGE_EMBEDDING_URL, [
            'input' => $text,
            'model' => 'voyage-2',
        ]);

        if (!$response->successful()) {
            throw new \Exception('Voyage embedding error: ' . $response->status());
        }

        return $response->json()['data'][0]['embedding'];
    }

    /**
     * Generate embedding via Ollama (local)
     */
    private function generateViaOllama(string $text): array
    {
        $response = Http::timeout(60)
            ->post(self::OLLAMA_EMBEDDING_URL, [
                'model' => $this->config['ollama_model'],
                'prompt' => $text,
            ]);

        if (!$response->successful()) {
            throw new \Exception('Ollama embedding error: ' . $response->status());
        }

        return $response->json()['embedding'];
    }

    /**
     * Store knowledge base entry with embedding
     */
    public function storeKnowledgeEntry(
        string $category,
        string $title,
        string $content,
        array $metadata = []
    ): bool {
        try {
            $embedding = $this->generateEmbedding($title . ' ' . $content);
            
            if (!$embedding) {
                Log::warning('Failed to generate embedding for knowledge entry');
                return false;
            }

            DB::table('chatbot_knowledge_base')->insert([
                'category' => $category,
                'title' => $title,
                'content' => $content,
                'embedding' => json_encode($embedding),
                'metadata' => json_encode($metadata),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to store knowledge entry: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Store conversation embedding for long-term memory
     */
    public function storeConversationEmbedding(string $conversationId, string $summary): bool
    {
        try {
            $embedding = $this->generateEmbedding($summary);
            
            if (!$embedding) {
                return false;
            }

            DB::table('chatbot_conversation_embeddings')->updateOrInsert(
                ['conversation_id' => $conversationId],
                [
                    'summary' => $summary,
                    'embedding' => json_encode($embedding),
                    'updated_at' => now(),
                ]
            );

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to store conversation embedding: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Search knowledge base semantically
     */
    public function searchKnowledge(string $query, int $limit = 5, ?string $category = null): array
    {
        try {
            $queryEmbedding = $this->generateEmbedding($query);
            
            if (!$queryEmbedding) {
                // Fall back to keyword search
                return $this->keywordSearch($query, $limit, $category);
            }

            // Get all entries (for SQLite - in production use pgvector)
            $query = DB::table('chatbot_knowledge_base');
            
            if ($category) {
                $query->where('category', $category);
            }

            $entries = $query->get();

            // Calculate similarities
            $results = [];
            foreach ($entries as $entry) {
                $entryEmbedding = json_decode($entry->embedding, true);
                $similarity = $this->cosineSimilarity($queryEmbedding, $entryEmbedding);
                
                if ($similarity > 0.5) { // Threshold
                    $results[] = [
                        'id' => $entry->id,
                        'category' => $entry->category,
                        'title' => $entry->title,
                        'content' => $entry->content,
                        'similarity' => $similarity,
                        'metadata' => json_decode($entry->metadata, true),
                    ];
                }
            }

            // Sort by similarity
            usort($results, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

            return array_slice($results, 0, $limit);

        } catch (\Exception $e) {
            Log::error('Knowledge search failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Search for similar conversations
     */
    public function searchSimilarConversations(int $userId, string $query, int $limit = 3): array
    {
        try {
            $queryEmbedding = $this->generateEmbedding($query);
            
            if (!$queryEmbedding) {
                return [];
            }

            // Get user's conversation embeddings
            $conversations = DB::table('chatbot_conversation_embeddings as ce')
                ->join('chatbot_conversations as c', 'ce.conversation_id', '=', 'c.conversation_id')
                ->where('c.user_id', $userId)
                ->select('ce.*', 'c.primary_intent', 'c.last_activity_at')
                ->get();

            $results = [];
            foreach ($conversations as $conv) {
                $convEmbedding = json_decode($conv->embedding, true);
                $similarity = $this->cosineSimilarity($queryEmbedding, $convEmbedding);
                
                if ($similarity > 0.6) {
                    $results[] = [
                        'conversation_id' => $conv->conversation_id,
                        'summary' => $conv->summary,
                        'topic' => $conv->primary_intent,
                        'similarity' => $similarity,
                        'date' => $conv->last_activity_at,
                    ];
                }
            }

            usort($results, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

            return array_slice($results, 0, $limit);

        } catch (\Exception $e) {
            Log::error('Similar conversation search failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get relevant context for RAG (Retrieval-Augmented Generation)
     */
    public function getRAGContext(string $query, ?int $userId = null): string
    {
        $context = "";

        // Search knowledge base
        $knowledgeResults = $this->searchKnowledge($query, 3);
        if (!empty($knowledgeResults)) {
            $context .= "## Relevant Knowledge Base Information:\n";
            foreach ($knowledgeResults as $result) {
                $context .= "**{$result['title']}**: {$result['content']}\n\n";
            }
        }

        // Search user's conversation history (if authenticated)
        if ($userId) {
            $conversationResults = $this->searchSimilarConversations($userId, $query, 2);
            if (!empty($conversationResults)) {
                $context .= "\n## Related Past Conversations:\n";
                foreach ($conversationResults as $conv) {
                    $context .= "- {$conv['summary']}\n";
                }
            }
        }

        return $context;
    }

    /**
     * Index all services for knowledge base
     */
    public function indexServices(): int
    {
        $count = 0;

        try {
            $services = DB::table('services')->where('is_active', true)->get();

            foreach ($services as $service) {
                $content = "Service: {$service->name}. ";
                $content .= "Description: " . ($service->description ?? 'No description') . ". ";
                $content .= "Price: " . ($service->price ?? 'Contact for pricing') . ". ";
                $content .= "Duration: " . ($service->duration_minutes ?? 'Varies') . " minutes.";

                if ($this->storeKnowledgeEntry('services', $service->name, $content, [
                    'service_id' => $service->id,
                    'price' => $service->price,
                    'duration' => $service->duration_minutes,
                ])) {
                    $count++;
                }
            }

        } catch (\Exception $e) {
            Log::error('Service indexing failed: ' . $e->getMessage());
        }

        return $count;
    }

    /**
     * Index FAQs for knowledge base
     */
    public function indexFAQs(array $faqs): int
    {
        $count = 0;

        foreach ($faqs as $faq) {
            if ($this->storeKnowledgeEntry('faq', $faq['question'], $faq['answer'], [
                'category' => $faq['category'] ?? 'general',
            ])) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Calculate cosine similarity between two vectors
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            return 0.0;
        }

        $dotProduct = 0;
        $normA = 0;
        $normB = 0;

        for ($i = 0; $i < count($a); $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $normA = sqrt($normA);
        $normB = sqrt($normB);

        if ($normA == 0 || $normB == 0) {
            return 0.0;
        }

        return $dotProduct / ($normA * $normB);
    }

    /**
     * Fallback keyword search
     */
    private function keywordSearch(string $query, int $limit, ?string $category = null): array
    {
        $queryBuilder = DB::table('chatbot_knowledge_base');
        
        if ($category) {
            $queryBuilder->where('category', $category);
        }

        // Simple keyword matching
        $words = explode(' ', strtolower($query));
        $queryBuilder->where(function($q) use ($words) {
            foreach ($words as $word) {
                if (strlen($word) > 2) {
                    $q->orWhere('title', 'LIKE', "%{$word}%")
                      ->orWhere('content', 'LIKE', "%{$word}%");
                }
            }
        });

        return $queryBuilder->limit($limit)->get()->map(fn($entry) => [
            'id' => $entry->id,
            'category' => $entry->category,
            'title' => $entry->title,
            'content' => $entry->content,
            'similarity' => 0.5, // Default score for keyword match
            'metadata' => json_decode($entry->metadata, true),
        ])->toArray();
    }

    /**
     * Preprocess text for embedding
     */
    private function preprocessText(string $text): string
    {
        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Truncate to ~8000 chars (most models have token limits)
        if (strlen($text) > 8000) {
            $text = substr($text, 0, 8000);
        }

        return trim($text);
    }

    /**
     * Determine best available provider
     */
    private function determineProvider(): string
    {
        if ($this->config['use_ollama']) {
            return 'ollama';
        }

        if (!empty($this->config['openai_key'])) {
            return 'openai';
        }

        if (!empty($this->config['voyage_key'])) {
            return 'voyage';
        }

        // Check if Ollama is available
        try {
            $response = Http::timeout(2)->get(self::OLLAMA_EMBEDDING_URL . '/../tags');
            if ($response->successful()) {
                return 'ollama';
            }
        } catch (\Exception $e) {
            // Not available
        }

        return 'none';
    }

    /**
     * Check if embedding service is available
     */
    public function isAvailable(): bool
    {
        return $this->provider !== 'none';
    }

    /**
     * Get current provider info
     */
    public function getProviderInfo(): array
    {
        return [
            'provider' => $this->provider,
            'model' => $this->getModelName(),
            'dimensions' => self::DIMENSIONS[$this->getModelName()] ?? 'unknown',
        ];
    }

    /**
     * Get current model name
     */
    private function getModelName(): string
    {
        switch ($this->provider) {
            case 'openai':
                return $this->config['default_model'];
            case 'voyage':
                return 'voyage-2';
            case 'ollama':
                return $this->config['ollama_model'];
            default:
                return 'none';
        }
    }
}
