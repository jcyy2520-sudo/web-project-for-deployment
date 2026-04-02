<?php

namespace App\Services;

use App\Models\KnowledgeBase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/**
 * VectorEmbeddingService - Semantic Search via Vector Embeddings
 * 
 * This service replaces keyword/pattern matching with true semantic understanding.
 * 
 * How it works:
 * 1. TEXT → EMBEDDING: Convert text to high-dimensional vector (using Ollama or OpenAI)
 * 2. SIMILARITY SEARCH: Find documents with similar vectors (cosine similarity)
 * 3. RANKED RESULTS: Return most relevant documents by similarity score
 *
 * Fallback chain: Ollama all-minilm → Voyage Voyage-3
 * 
 * Key Features:
 * - Pre-computed embeddings for knowledge base (stored in DB)
 * - Real-time embedding generation for queries
 * - Cosine similarity for accurate semantic matching
 * - Caching for performance
 * - Fallback to keyword search if embeddings unavailable
 */
class VectorEmbeddingService
{
    // Instance properties loaded from config
    private string $voyageEmbeddingsUrl;
    private string $ollamaModel;
    private string $voyageModel;
    private int $requestTimeout;
    private int $cacheTtl;
    private float $similarityThreshold;
    private float $bm25Weight;
    private float $vectorWeight;
    private int $maxChunkSize;
    private int $chunkOverlap;

    private ?string $voyageApiKey;
    private bool $useOllama;

    public function __construct()
    {
        $this->voyageApiKey = config('services.voyage.api_key');
        $this->useOllama = config('services.ollama.embeddings_enabled', true);

        $embConf = 'chatbot_unified.embeddings.';
        $this->ollamaEmbeddingsUrl = config($embConf . 'ollama_url', 'http://localhost:11434/api/embeddings');
        $this->voyageEmbeddingsUrl = config($embConf . 'voyage_url', 'https://api.voyageai.com/v1/embeddings');
        $this->ollamaModel = config($embConf . 'ollama_model', 'all-minilm');
        $this->voyageModel = config($embConf . 'voyage_model', 'voyage-3');
        $this->requestTimeout = (int) config($embConf . 'request_timeout', 30);
        $this->cacheTtl = (int) config($embConf . 'cache_ttl', 86400);
        $this->similarityThreshold = (float) config($embConf . 'similarity_threshold', 0.3);
        $this->bm25Weight = (float) config($embConf . 'bm25_weight', 0.4);
        $this->vectorWeight = (float) config($embConf . 'vector_weight', 0.6);
        $this->maxChunkSize = (int) config($embConf . 'max_chunk_size', 500);
        $this->chunkOverlap = (int) config($embConf . 'chunk_overlap', 50);
    }
    
    /**
     * Generate embedding for text
     * 
     * @param string $text Text to embed
     * @return array|null Embedding vector or null on failure
     */
    public function generateEmbedding(string $text): ?array
    {
        // Check cache first
        $cacheKey = 'embedding_' . md5($text);
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        $embedding = null;

        // PRIMARY: Try Ollama (local ML model, free)
        if ($this->useOllama) {
            $embedding = $this->generateViaOllama($text);
        }

        // FALLBACK 1: Voyage AI (highly accurate, fast)
        if (!$embedding && $this->voyageApiKey) {
            $embedding = $this->generateViaVoyage($text);
        }

        // Cache successful embeddings
        if ($embedding) {
            Cache::put($cacheKey, $embedding, $this->cacheTtl);
        }

        return $embedding;
    }

    /**
     * Generate embedding via Voyage AI
     */
    private function generateViaVoyage(string $text): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->voyageApiKey,
            ])
            ->timeout($this->requestTimeout)
            ->post($this->voyageEmbeddingsUrl, [
                'model' => $this->voyageModel,
                'input' => $text,
            ]);
            
            if (!$response->successful()) {
                Log::debug('Voyage embedding failed: ' . $response->status());
                return null;
            }
            
            $data = $response->json();
            return $data['data'][0]['embedding'] ?? null;
            
        } catch (\Exception $e) {
            Log::debug('Voyage embedding error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Generate embedding via Ollama (local)
     */
    private function generateViaOllama(string $text): ?array
    {
        try {
            $response = Http::timeout($this->requestTimeout)
                ->post($this->ollamaEmbeddingsUrl, [
                    'model' => $this->ollamaModel,
                    'prompt' => $text,
                ]);
            
            if (!$response->successful()) {
                Log::debug('Ollama embedding failed: ' . $response->status());
                return null;
            }
            
            $data = $response->json();
            return $data['embedding'] ?? null;
            
        } catch (\Exception $e) {
            Log::debug('Ollama embedding error: ' . $e->getMessage());
            return null;
        }
    }
    

    
    /**
     * Perform semantic search on knowledge base
     * 
     * @param string $query User's question/query
     * @param string|null $category Optional category filter
     * @param int $limit Max results to return
     * @return array Relevant documents sorted by similarity
     */
    public function semanticSearch(string $query, ?string $category = null, int $limit = 5): array
    {
        // ── CACHING: Check for cached search results first (Speed optimization) ──
        $queryHash = md5(mb_strtolower(trim($query)));
        $categoryKey = $category ?? 'all';
        $searchCacheKey = "chatbot_search_{$categoryKey}_{$queryHash}_{$limit}";
        
        $cachedResults = Cache::get($searchCacheKey);
        if ($cachedResults !== null) {
            Log::debug('Returning cached semantic search results', ['query' => $query]);
            return $cachedResults;
        }

        try {
            // Check if there are any documents at all before spending time/money on embeddings
            $query_builder = KnowledgeBase::where('is_active', true);

            if ($category) {
                $query_builder->where('category', $category);
            }

            $documents = $query_builder->get();

            if ($documents->isEmpty()) {
                Log::debug('No documents in knowledge base for semantic search - skipping embedding generation');
                return [];
            }

            // Generate embedding for query
            $queryEmbedding = $this->generateEmbedding($query);

            // If embedding generation fails, fall back to keyword search
            if (!$queryEmbedding) {
                Log::info('Falling back to keyword search - embedding generation failed');
                return $this->keywordFallbackSearch($query, $category, $limit);
            }

            $fetchLimit = max($limit * 2, 10); // Fetch more for hybrid scoring

            // ── HYBRID SEARCH: Vector similarity + BM25 keyword scoring ──
            $queryKeywords = $this->extractKeywords($query);

            // Get document boosts from learning service
            $documentBoosts = [];
            try {
                $learningService = app(ChatbotLearningService::class);
                $documentBoosts = $learningService->getDocumentBoosts();
            } catch (\Exception $e) {
                // Learning service not available, no boosts
            }

            $results = [];
            foreach ($documents as $doc) {
                $docEmbedding = json_decode($doc->embedding, true);

                // Vector similarity score
                $vectorScore = 0.0;
                if ($docEmbedding && is_array($docEmbedding)) {
                    $vectorScore = $this->cosineSimilarity($queryEmbedding, $docEmbedding);
                }

                // BM25-style keyword score
                $bm25Score = $this->calculateBM25Score(
                    $queryKeywords,
                    ($doc->title ?? '') . ' ' . ($doc->content_chunk ?? ''),
                    $documents->count()
                );

                // Hybrid score: weighted combination
                $hybridScore = ($this->vectorWeight * max(0, $vectorScore))
                             + ($this->bm25Weight * $bm25Score);

                // Apply learning boost if available
                $boost = $documentBoosts[$doc->id] ?? 1.0;
                $hybridScore *= $boost;

                if ($hybridScore >= $this->similarityThreshold) {
                    $results[] = [
                        'id' => $doc->id,
                        'title' => $doc->title,
                        'content' => $doc->content_chunk,
                        'category' => $doc->category,
                        'type' => $doc->document_type,
                        'similarity' => $hybridScore,
                        'vector_score' => $vectorScore,
                        'bm25_score' => $bm25Score,
                        'boost' => $boost,
                        'metadata' => json_decode($doc->metadata, true) ?? [],
                    ];
                }
            }

            // Sort by hybrid similarity (descending) and limit to candidate pool
            usort($results, fn($a, $b) => $b['similarity'] <=> $a['similarity']);
            $results = array_slice($results, 0, $fetchLimit);



            $finalResults = array_slice($results, 0, $limit);
            
            // Cache successful search results for 15 minutes
            Cache::put($searchCacheKey, $finalResults, 900);
            
            return $finalResults;

        } catch (\Exception $e) {
            Log::error('Semantic search failed: ' . $e->getMessage());
            return $this->keywordFallbackSearch($query, $category, $limit);
        }
    }

    /**
     * Calculate BM25-style keyword relevance score
     *
     * @param array $queryTerms Extracted query keywords
     * @param string $document Document text
     * @param int $totalDocs Total documents in collection
     * @return float Score between 0.0 and 1.0
     */
    private function calculateBM25Score(array $queryTerms, string $document, int $totalDocs): float
    {
        if (empty($queryTerms) || empty($document)) {
            return 0.0;
        }

        $k1 = 1.2;
        $b = 0.75;
        $avgDocLen = 200; // approximate average document length in words

        $docLower = mb_strtolower($document);
        $docWords = preg_split('/\s+/', $docLower);
        $docLen = count($docWords);
        $docTermFreq = array_count_values($docWords);

        $score = 0.0;
        foreach ($queryTerms as $term) {
            $tf = $docTermFreq[$term] ?? 0;
            if ($tf === 0) {
                // Also check for partial match (substring)
                $partialMatches = 0;
                foreach ($docWords as $word) {
                    if (str_contains($word, $term) || str_contains($term, $word)) {
                        $partialMatches++;
                    }
                }
                $tf = $partialMatches * 0.5; // Partial matches count as half
            }

            if ($tf > 0) {
                // Simplified IDF: assume term appears in ~10% of docs
                $idf = log(($totalDocs + 1) / (max($totalDocs * 0.1, 1)));
                // BM25 TF normalization
                $tfNorm = ($tf * ($k1 + 1)) / ($tf + $k1 * (1 - $b + $b * ($docLen / $avgDocLen)));
                $score += $idf * $tfNorm;
            }
        }

        // Normalize to 0-1 range (sigmoid-like)
        return min(1.0, $score / (count($queryTerms) * 3));
    }
    
    /**
     * Keyword-based fallback search when embeddings unavailable
     */
    private function keywordFallbackSearch(string $query, ?string $category, int $limit): array
    {
        try {
            $keywords = $this->extractKeywords($query);
            
            $query_builder = KnowledgeBase::where('is_active', true);
            
            if ($category) {
                $query_builder->where('category', $category);
            }
            
            // Search for any keyword match
            $query_builder->where(function($q) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $q->orWhere('content_chunk', 'LIKE', "%{$keyword}%")
                      ->orWhere('title', 'LIKE', "%{$keyword}%");
                }
            });
            
            return $query_builder->limit($limit)
                ->get()
                ->map(function($doc) {
                    return [
                        'id' => $doc->id,
                        'title' => $doc->title,
                        'content' => $doc->content_chunk,
                        'category' => $doc->category,
                        'type' => $doc->document_type,
                        'similarity' => 0.5, // Default score for keyword matches
                        'metadata' => json_decode($doc->metadata, true) ?? [],
                    ];
                })
                ->toArray();
                
        } catch (\Exception $e) {
            Log::error('Keyword fallback search failed: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Extract keywords from text
     */
    private function extractKeywords(string $text): array
    {
        // Remove common stop words and extract meaningful terms
        $stopWords = ['a', 'an', 'the', 'is', 'are', 'was', 'were', 'be', 'been', 
                      'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 
                      'would', 'could', 'should', 'may', 'might', 'must', 'shall',
                      'can', 'need', 'dare', 'ought', 'used', 'to', 'of', 'in', 
                      'for', 'on', 'with', 'at', 'by', 'from', 'as', 'into', 'through',
                      'during', 'before', 'after', 'above', 'below', 'between',
                      'under', 'again', 'further', 'then', 'once', 'here', 'there',
                      'when', 'where', 'why', 'how', 'all', 'each', 'few', 'more',
                      'most', 'other', 'some', 'such', 'no', 'nor', 'not', 'only',
                      'own', 'same', 'so', 'than', 'too', 'very', 'just', 'and', 
                      'but', 'if', 'or', 'because', 'until', 'while', 'what', 'which',
                      'who', 'whom', 'this', 'that', 'these', 'those', 'i', 'me', 
                      'my', 'we', 'our', 'you', 'your', 'he', 'she', 'it', 'they',
                      'ang', 'ng', 'sa', 'na', 'ay', 'mga', 'ko', 'mo', 'niya', 'siya',
                      'kami', 'tayo', 'sila', 'ito', 'iyon', 'ano', 'sino', 'paano'];
        
        $words = preg_split('/\s+/', strtolower($text));
        $keywords = array_filter($words, function($word) use ($stopWords) {
            return strlen($word) > 2 && !in_array($word, $stopWords);
        });
        
        return array_values(array_unique($keywords));
    }
    
    /**
     * Calculate cosine similarity between two vectors
     */
    public function cosineSimilarity(array $vec1, array $vec2): float
    {
        if (empty($vec1) || empty($vec2)) {
            return 0.0;
        }
        
        $dotProduct = 0.0;
        $norm1 = 0.0;
        $norm2 = 0.0;
        
        $len = min(count($vec1), count($vec2));
        
        for ($i = 0; $i < $len; $i++) {
            $dotProduct += $vec1[$i] * $vec2[$i];
            $norm1 += $vec1[$i] * $vec1[$i];
            $norm2 += $vec2[$i] * $vec2[$i];
        }
        
        $magnitude = sqrt($norm1) * sqrt($norm2);
        
        if ($magnitude == 0) {
            return 0.0;
        }
        
        return $dotProduct / $magnitude;
    }
    
    /**
     * Index a document into the knowledge base
     */
    public function indexDocument(
        string $title,
        string $content,
        string $category,
        string $documentType = 'general',
        array $metadata = []
    ): bool {
        try {
            // Limit content size to avoid memory issues
            $content = substr($content, 0, 3000);
            
            // Generate embedding for the content (no chunking for simplicity)
            $embedding = $this->generateEmbedding($content);
            
            if (!$embedding) {
                Log::warning("Failed to embed document: {$title}");
                return false;
            }
            
            KnowledgeBase::updateOrCreate(
                [
                    'title' => $title,
                    'chunk_index' => 0,
                ],
                [
                    'content_chunk' => $content,
                    'category' => $category,
                    'document_type' => $documentType,
                    'embedding' => json_encode($embedding),
                    'metadata' => json_encode($metadata),
                    'is_active' => true,
                ]
            );
            
            return true;
            
        } catch (\Exception $e) {
            Log::error("Document indexing failed for {$title}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Chunk text into smaller pieces with overlap
     */
    private function chunkText(string $text): array
    {
        $chunks = [];
        $text = trim($text);
        $length = strlen($text);
        
        if ($length <= $this->maxChunkSize) {
            return [$text];
        }
        
        $position = 0;
        while ($position < $length) {
            $chunk = substr($text, $position, $this->maxChunkSize);
            
            // Try to break at sentence boundary
            if ($position + $this->maxChunkSize < $length) {
                $lastPeriod = strrpos($chunk, '.');
                $lastQuestion = strrpos($chunk, '?');
                $lastExclaim = strrpos($chunk, '!');
                
                $breakPoint = max($lastPeriod, $lastQuestion, $lastExclaim);
                
                if ($breakPoint !== false && $breakPoint > $this->maxChunkSize / 2) {
                    $chunk = substr($chunk, 0, $breakPoint + 1);
                }
            }
            
            $chunks[] = trim($chunk);
            $position += strlen($chunk) - $this->chunkOverlap;
        }
        
        return $chunks;
    }
    
    /**
     * Load and index knowledge from the JSON bundle
     */
    public function loadKnowledgeBundle(string $bundlePath = null): array
    {
        $bundlePath = $bundlePath ?? base_path('../chatbot_knowledge/chatbot_knowledge_bundle_with_summaries.json');
        
        if (!File::exists($bundlePath)) {
            // Try alternate path
            $bundlePath = base_path('../chatbot_knowledge/chatbot_knowledge_bundle.json');
        }
        
        if (!File::exists($bundlePath)) {
            Log::warning('Knowledge bundle not found at: ' . $bundlePath);
            return ['success' => false, 'error' => 'Bundle file not found', 'indexed' => 0];
        }
        
        try {
            $content = File::get($bundlePath);
            $bundle = json_decode($content, true);
            
            if (!$bundle) {
                return ['success' => false, 'error' => 'Failed to parse bundle JSON', 'indexed' => 0];
            }
            
            $indexed = 0;
            $failed = 0;
            
            // Index files from the bundle (new structure)
            if (!empty($bundle['files'])) {
                foreach ($bundle['files'] as $file) {
                    $path = $file['path'] ?? '';
                    $kind = $file['kind'] ?? 'doc';
                    $summary = $file['summary'] ?? '';
                    $fileContent = $file['content'] ?? '';
                    
                    // Create a meaningful title from the path
                    $title = basename($path);
                    if (empty($title)) {
                        $title = $kind . '_' . substr(md5($path), 0, 8);
                    }
                    
                    // Use summary first, fall back to truncated content
                    // Limit content size to avoid memory issues
                    $fullContent = '';
                    if (!empty($summary)) {
                        $fullContent = $summary;
                    }
                    if (!empty($fileContent)) {
                        // Truncate large content to first 2000 chars
                        $truncatedContent = substr($fileContent, 0, 2000);
                        if (strlen($fileContent) > 2000) {
                            $truncatedContent .= '...';
                        }
                        $fullContent = trim($fullContent . "\n\n" . $truncatedContent);
                    }
                    
                    if (empty($fullContent)) {
                        $failed++;
                        continue;
                    }
                    
                    $success = $this->indexDocument(
                        $title,
                        $fullContent,
                        $kind,
                        $kind,
                        ['path' => $path, 'sha1' => $file['sha1'] ?? null]
                    );
                    $success ? $indexed++ : $failed++;
                }
            }
            
            // Index services (legacy structure support)
            if (!empty($bundle['services'])) {
                foreach ($bundle['services'] as $service) {
                    $success = $this->indexDocument(
                        "Service: " . ($service['name'] ?? 'Unknown'),
                        ($service['description'] ?? '') . "\n\nPrice: " . ($service['price'] ?? 'Contact for pricing'),
                        'services',
                        'service',
                        ['service_id' => $service['id'] ?? null, 'price' => $service['price'] ?? null]
                    );
                    $success ? $indexed++ : $failed++;
                }
            }
            
            // Index FAQs (legacy structure support)
            if (!empty($bundle['faqs'])) {
                foreach ($bundle['faqs'] as $faq) {
                    $success = $this->indexDocument(
                        "FAQ: " . ($faq['question'] ?? $faq['title'] ?? 'Question'),
                        $faq['answer'] ?? $faq['content'] ?? '',
                        'faq',
                        'faq',
                        ['faq_id' => $faq['id'] ?? null]
                    );
                    $success ? $indexed++ : $failed++;
                }
            }
            
            // Index general knowledge (legacy structure support)
            if (!empty($bundle['knowledge'])) {
                foreach ($bundle['knowledge'] as $item) {
                    $success = $this->indexDocument(
                        $item['title'] ?? 'Knowledge Item',
                        $item['content'] ?? '',
                        $item['category'] ?? 'general',
                        'knowledge',
                        ['source' => $item['source'] ?? null]
                    );
                    $success ? $indexed++ : $failed++;
                }
            }
            
            // Index summaries (legacy structure support)
            if (!empty($bundle['summaries'])) {
                foreach ($bundle['summaries'] as $summary) {
                    $success = $this->indexDocument(
                        $summary['title'] ?? 'Summary',
                        $summary['content'] ?? $summary['summary'] ?? '',
                        'summary',
                        'summary',
                        ['file' => $summary['file'] ?? null]
                    );
                    $success ? $indexed++ : $failed++;
                }
            }
            
            Log::info("Knowledge bundle loaded: {$indexed} indexed, {$failed} failed");
            
            return [
                'success' => true,
                'indexed' => $indexed,
                'failed' => $failed,
                'total' => $indexed + $failed,
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to load knowledge bundle: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage(), 'indexed' => 0];
        }
    }
    
    /**
     * Rebuild the entire knowledge base index
     */
    public function rebuildIndex(): array
    {
        try {
            // Clear existing index
            KnowledgeBase::query()->delete();
            
            // Reload from bundle
            $result = $this->loadKnowledgeBundle();
            
            // Also index active services from database
            $this->indexServicesFromDatabase();
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error('Failed to rebuild index: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Index services from the database
     */
    public function indexServicesFromDatabase(): int
    {
        try {
            $services = \App\Models\Service::where('is_active', true)->get();
            $indexed = 0;
            
            foreach ($services as $service) {
                $content = $service->description ?? '';
                if ($service->price) {
                    $content .= "\n\nPrice: ₱" . number_format($service->price, 2);
                }
                if ($service->duration) {
                    $content .= "\nDuration: {$service->duration} minutes";
                }
                
                $success = $this->indexDocument(
                    "Service: {$service->name}",
                    $content,
                    'services',
                    'service',
                    [
                        'service_id' => $service->id,
                        'price' => $service->price,
                        'duration' => $service->duration,
                    ]
                );
                
                if ($success) $indexed++;
            }
            
            return $indexed;
            
        } catch (\Exception $e) {
            Log::error('Failed to index services: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Check if embedding service is available
     */
    public function isAvailable(): bool
    {
        // Try to generate a test embedding via API providers
        $testEmbedding = $this->generateEmbedding('test');
        return $testEmbedding !== null;
    }
    
    /**
     * Get count of indexed documents
     */
    public function getIndexedDocumentCount(): int
    {
        try {
            return KnowledgeBase::where('is_active', true)->count();
        } catch (\Exception $e) {
            return 0;
        }
    }
    
    /**
     * Get RAG context string for LLM
     */
    public function getRAGContext(string $query, ?string $category = null, int $limit = 3): string
    {
        $results = $this->semanticSearch($query, $category, $limit);
        
        if (empty($results)) {
            return '';
        }
        
        $context = "## Relevant Information:\n\n";
        
        foreach ($results as $doc) {
            $score = round($doc['similarity'] * 100, 1);
            $context .= "**{$doc['title']}** (Relevance: {$score}%)\n";
            $context .= "{$doc['content']}\n\n";
        }
        
        return $context;
    }

}
