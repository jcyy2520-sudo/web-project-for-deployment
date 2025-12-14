<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\KnowledgeBase;

/**
 * SemanticEmbeddingsService - Provides semantic understanding and RAG
 * 
 * Features:
 * - Document embeddings for semantic search
 * - Retrieval-Augmented Generation (RAG)
 * - Vector similarity search
 * - Knowledge base indexing
 * 
 * Uses: Ollama embeddings or sentence-transformers
 */
class SemanticEmbeddingsService
{
    private const OLLAMA_EMBEDDINGS_URL = 'http://localhost:11434/api/embeddings';
    private const SENTENCE_TRANSFORMERS_URL = 'http://localhost:8000/embeddings'; // Optional local service
    private const EMBEDDING_MODEL = 'nomic-embed-text'; // Ollama embedding model
    private const MAX_SEARCH_RESULTS = 5;

    /**
     * Generate embedding for text
     * 
     * Uses Ollama or external service to create vector representation
     */
    public function generateEmbedding(string $text): ?array
    {
        try {
            $response = Http::timeout(30)->post(self::OLLAMA_EMBEDDINGS_URL, [
                'model' => self::EMBEDDING_MODEL,
                'prompt' => $text,
            ]);

            if (!$response->successful()) {
                Log::warning('Failed to generate embedding', ['status' => $response->status()]);
                return null;
            }

            $data = $response->json();
            return [
                'embedding' => $data['embedding'] ?? [],
                'model' => self::EMBEDDING_MODEL,
                'success' => true,
            ];
        } catch (\Exception $e) {
            Log::error('Embedding generation failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Index a document or FAQ into knowledge base
     * 
     * Breaks document into chunks and embeds each
     */
    public function indexDocument(
        string $title,
        string $content,
        string $category,
        string $documentType = 'faq',
        array $metadata = []
    ): bool {
        try {
            // Split content into chunks (e.g., 500 character chunks)
            $chunks = $this->chunkText($content, 500, 100); // 500 chars, 100 overlap

            foreach ($chunks as $index => $chunk) {
                $embedding = $this->generateEmbedding($chunk);

                if (!$embedding) {
                    Log::warning("Failed to embed chunk $index for $title");
                    continue;
                }

                // Store in knowledge base
                KnowledgeBase::create([
                    'title' => $title,
                    'content_chunk' => $chunk,
                    'chunk_index' => $index,
                    'category' => $category,
                    'document_type' => $documentType,
                    'embedding' => json_encode($embedding['embedding']),
                    'metadata' => json_encode($metadata),
                    'is_active' => true,
                ]);
            }

            return true;
        } catch (\Exception $e) {
            Log::error("Document indexing failed for $title: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Perform semantic search on knowledge base
     * 
     * Returns most relevant documents based on semantic similarity
     */
    public function semanticSearch(
        string $query,
        string $category = null,
        int $limit = self::MAX_SEARCH_RESULTS
    ): array {
        try {
            $queryEmbedding = $this->generateEmbedding($query);

            if (!$queryEmbedding) {
                return [];
            }

            $queryVector = $queryEmbedding['embedding'];

            // Query knowledge base and calculate similarity
            $results = KnowledgeBase::where('is_active', true)
                ->when($category, fn($q) => $q->where('category', $category))
                ->get()
                ->map(function ($doc) use ($queryVector) {
                    $docVector = json_decode($doc->embedding, true);
                    $similarity = $this->cosineSimilarity($queryVector, $docVector);

                    return [
                        'id' => $doc->id,
                        'title' => $doc->title,
                        'content' => $doc->content_chunk,
                        'category' => $doc->category,
                        'type' => $doc->document_type,
                        'similarity' => $similarity,
                        'metadata' => json_decode($doc->metadata, true) ?? [],
                    ];
                })
                ->sortByDesc('similarity')
                ->take($limit)
                ->values()
                ->toArray();

            return $results;
        } catch (\Exception $e) {
            Log::error('Semantic search failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get RAG context for LLM
     * 
     * Retrieves relevant documents to augment LLM knowledge
     */
    public function getRAGContext(
        string $query,
        string $category = null,
        int $limit = 3
    ): string {
        try {
            $relevantDocs = $this->semanticSearch($query, $category, $limit);

            if (empty($relevantDocs)) {
                return '';
            }

            $context = "## Relevant Information:\n\n";

            foreach ($relevantDocs as $doc) {
                $score = round($doc['similarity'] * 100, 1);
                $context .= "**{$doc['title']}** (Relevance: {$score}%)\n";
                $context .= "{$doc['content']}\n\n";
            }

            return $context;
        } catch (\Exception $e) {
            Log::error('RAG context retrieval failed: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Index business services into knowledge base
     * 
     * Called during setup to populate with business data
     */
    public function indexBusinessServices(): bool
    {
        try {
            $services = \App\Models\Service::where('is_active', true)->get();

            foreach ($services as $service) {
                $this->indexDocument(
                    "Service: {$service->name}",
                    $service->description . "\n\nPrice: " . $service->price,
                    'services',
                    'service',
                    [
                        'service_id' => $service->id,
                        'price' => $service->price,
                    ]
                );
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Service indexing failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Index FAQs into knowledge base
     * 
     * Create custom FAQs or import from file
     */
    public function indexFAQs(array $faqs): bool
    {
        try {
            foreach ($faqs as $faq) {
                $this->indexDocument(
                    "FAQ: {$faq['question']}",
                    $faq['answer'],
                    'faq',
                    'faq',
                    [
                        'question' => $faq['question'],
                    ]
                );
            }

            return true;
        } catch (\Exception $e) {
            Log::error('FAQ indexing failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Split text into overlapping chunks
     */
    private function chunkText(string $text, int $chunkSize = 500, int $overlap = 100): array
    {
        $chunks = [];
        $length = strlen($text);

        for ($i = 0; $i < $length; $i += ($chunkSize - $overlap)) {
            $chunk = substr($text, $i, $chunkSize);
            if (!empty(trim($chunk))) {
                $chunks[] = trim($chunk);
            }
        }

        return !empty($chunks) ? $chunks : [$text];
    }

    /**
     * Calculate cosine similarity between two vectors
     */
    private function cosineSimilarity(array $vec1, array $vec2): float
    {
        if (empty($vec1) || empty($vec2)) {
            return 0.0;
        }

        $dotProduct = 0;
        $norm1 = 0;
        $norm2 = 0;

        for ($i = 0; $i < count($vec1) && $i < count($vec2); $i++) {
            $dotProduct += $vec1[$i] * $vec2[$i];
            $norm1 += $vec1[$i] * $vec1[$i];
            $norm2 += $vec2[$i] * $vec2[$i];
        }

        $magnitude = sqrt($norm1) * sqrt($norm2);

        if ($magnitude == 0) {
            return 0.0;
        }

        return (float) ($dotProduct / $magnitude);
    }

    /**
     * Clear and rebuild knowledge base
     * 
     * Useful for re-indexing after data changes
     */
    public function rebuildKnowledgeBase(): bool
    {
        try {
            KnowledgeBase::query()->delete();

            $this->indexBusinessServices();
            $this->indexDefaultFAQs();

            return true;
        } catch (\Exception $e) {
            Log::error('Knowledge base rebuild failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Index default FAQs
     */
    private function indexDefaultFAQs(): void
    {
        $defaultFAQs = [
            [
                'question' => 'How do I book an appointment?',
                'answer' => 'You can book an appointment through our online booking system. Select the service you need, choose a convenient time slot, and provide your details. You\'ll receive a confirmation email with your appointment details.',
            ],
            [
                'question' => 'What is your refund policy?',
                'answer' => 'We offer full refunds for cancellations made at least 24 hours before your appointment. Cancellations within 24 hours may be subject to a cancellation fee. Please contact us for details about your specific appointment.',
            ],
            [
                'question' => 'Can I reschedule my appointment?',
                'answer' => 'Yes, you can reschedule your appointment up to 24 hours before the scheduled time. Log into your account, go to your appointments, and select the option to reschedule.',
            ],
            [
                'question' => 'What payment methods do you accept?',
                'answer' => 'We accept credit cards, debit cards, and online payment methods. You can also pay at the time of your appointment if available.',
            ],
            [
                'question' => 'Who is Peejayy De Guzman?',
                'answer' => 'Peejayy De Guzman is a professional notary public and legal services provider offering comprehensive legal support including notarization, document authentication, and legal consultation services.',
            ],
        ];

        $this->indexFAQs($defaultFAQs);
    }
}
