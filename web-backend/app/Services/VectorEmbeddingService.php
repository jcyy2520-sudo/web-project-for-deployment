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
 * Key Features:
 * - Pre-computed embeddings for knowledge base (stored in DB)
 * - Real-time embedding generation for queries
 * - Cosine similarity for accurate semantic matching
 * - Caching for performance
 * - Fallback to keyword search if embeddings unavailable
 */
class VectorEmbeddingService
{
    // Embedding service configuration
    private const OLLAMA_EMBEDDINGS_URL = 'http://localhost:11434/api/embeddings';
    private const OPENAI_EMBEDDINGS_URL = 'https://api.openai.com/v1/embeddings';
    private const HUGGINGFACE_EMBEDDINGS_URL = 'https://router.huggingface.co/hf-inference/models/BAAI/bge-small-en-v1.5';
    
    private const OLLAMA_MODEL = 'nomic-embed-text'; // Good for semantic search
    private const OPENAI_MODEL = 'text-embedding-3-small';
    
    private const REQUEST_TIMEOUT = 30;
    private const CACHE_TTL = 86400; // 24 hours for embeddings
    private const SIMILARITY_THRESHOLD = 0.5;
    private const MAX_CHUNK_SIZE = 500;
    private const CHUNK_OVERLAP = 50;
    
    private ?string $openaiApiKey;
    private ?string $huggingfaceApiKey;
    private bool $useOllama;
    
    public function __construct()
    {
        $this->openaiApiKey = env('OPENAI_API_KEY');
        $this->huggingfaceApiKey = env('HUGGINGFACE_API_KEY');
        $this->useOllama = env('USE_OLLAMA_EMBEDDINGS', true);
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
        
        // Try Ollama first (local, free)
        if ($this->useOllama) {
            $embedding = $this->generateViaOllama($text);
        }
        
        // Try HuggingFace second (free API)
        if (!$embedding && $this->huggingfaceApiKey) {
            $embedding = $this->generateViaHuggingFace($text);
        }
        
        // Fallback to OpenAI if others fail and API key exists
        if (!$embedding && $this->openaiApiKey) {
            $embedding = $this->generateViaOpenAI($text);
        }
        
        // Cache successful embeddings
        if ($embedding) {
            Cache::put($cacheKey, $embedding, self::CACHE_TTL);
        }
        
        return $embedding;
    }
    
    /**
     * Generate embedding via Ollama (local)
     */
    private function generateViaOllama(string $text): ?array
    {
        try {
            $response = Http::timeout(self::REQUEST_TIMEOUT)
                ->post(self::OLLAMA_EMBEDDINGS_URL, [
                    'model' => self::OLLAMA_MODEL,
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
     * Generate embedding via OpenAI
     */
    private function generateViaOpenAI(string $text): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->openaiApiKey,
            ])
            ->timeout(self::REQUEST_TIMEOUT)
            ->post(self::OPENAI_EMBEDDINGS_URL, [
                'model' => self::OPENAI_MODEL,
                'input' => $text,
            ]);
            
            if (!$response->successful()) {
                Log::debug('OpenAI embedding failed: ' . $response->status());
                return null;
            }
            
            $data = $response->json();
            return $data['data'][0]['embedding'] ?? null;
            
        } catch (\Exception $e) {
            Log::debug('OpenAI embedding error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Generate embedding via HuggingFace (free!)
     * Uses sentence-transformers/all-MiniLM-L6-v2 - a great free model
     */
    private function generateViaHuggingFace(string $text): ?array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->huggingfaceApiKey,
            ])
            ->timeout(self::REQUEST_TIMEOUT)
            ->post(self::HUGGINGFACE_EMBEDDINGS_URL, [
                'inputs' => $text,
                'options' => ['wait_for_model' => true],
            ]);
            
            if (!$response->successful()) {
                Log::debug('HuggingFace embedding failed: ' . $response->status() . ' - ' . $response->body());
                return null;
            }
            
            $data = $response->json();
            
            // HuggingFace returns the embedding directly as an array
            if (is_array($data) && !empty($data)) {
                // If it's a 2D array (batch), get the first one
                if (is_array($data[0])) {
                    return $data[0];
                }
                return $data;
            }
            
            return null;
            
        } catch (\Exception $e) {
            Log::debug('HuggingFace embedding error: ' . $e->getMessage());
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
        try {
            // Generate embedding for query
            $queryEmbedding = $this->generateEmbedding($query);
            
            // If embedding generation fails, fall back to keyword search
            if (!$queryEmbedding) {
                Log::info('Falling back to keyword search - embedding generation failed');
                return $this->keywordFallbackSearch($query, $category, $limit);
            }
            
            // Query knowledge base
            $query_builder = KnowledgeBase::where('is_active', true);
            
            if ($category) {
                $query_builder->where('category', $category);
            }
            
            $documents = $query_builder->get();
            
            if ($documents->isEmpty()) {
                Log::debug('No documents in knowledge base for semantic search');
                return [];
            }
            
            // Calculate similarity for each document
            $results = [];
            foreach ($documents as $doc) {
                $docEmbedding = json_decode($doc->embedding, true);
                
                if (!$docEmbedding || !is_array($docEmbedding)) {
                    continue;
                }
                
                $similarity = $this->cosineSimilarity($queryEmbedding, $docEmbedding);
                
                if ($similarity >= self::SIMILARITY_THRESHOLD) {
                    $results[] = [
                        'id' => $doc->id,
                        'title' => $doc->title,
                        'content' => $doc->content_chunk,
                        'category' => $doc->category,
                        'type' => $doc->document_type,
                        'similarity' => $similarity,
                        'metadata' => json_decode($doc->metadata, true) ?? [],
                    ];
                }
            }
            
            // Sort by similarity (descending) and limit results
            usort($results, fn($a, $b) => $b['similarity'] <=> $a['similarity']);
            return array_slice($results, 0, $limit);
            
        } catch (\Exception $e) {
            Log::error('Semantic search failed: ' . $e->getMessage());
            return $this->keywordFallbackSearch($query, $category, $limit);
        }
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
        
        if ($length <= self::MAX_CHUNK_SIZE) {
            return [$text];
        }
        
        $position = 0;
        while ($position < $length) {
            $chunk = substr($text, $position, self::MAX_CHUNK_SIZE);
            
            // Try to break at sentence boundary
            if ($position + self::MAX_CHUNK_SIZE < $length) {
                $lastPeriod = strrpos($chunk, '.');
                $lastQuestion = strrpos($chunk, '?');
                $lastExclaim = strrpos($chunk, '!');
                
                $breakPoint = max($lastPeriod, $lastQuestion, $lastExclaim);
                
                if ($breakPoint !== false && $breakPoint > self::MAX_CHUNK_SIZE / 2) {
                    $chunk = substr($chunk, 0, $breakPoint + 1);
                }
            }
            
            $chunks[] = trim($chunk);
            $position += strlen($chunk) - self::CHUNK_OVERLAP;
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
        // Try to generate a test embedding
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
