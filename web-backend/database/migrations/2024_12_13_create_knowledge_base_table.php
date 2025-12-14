<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_base', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('content_chunk'); // Store document chunks
            $table->integer('chunk_index')->default(0); // Track chunks from same document
            $table->string('category'); // services, faq, documentation, etc.
            $table->string('document_type'); // service, faq, guide, etc.
            $table->longText('embedding'); // JSON array of vector values
            $table->json('metadata')->nullable(); // Additional metadata
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes for fast searching
            $table->index('category');
            $table->index('document_type');
            $table->index('is_active');
            $table->fullText(['title', 'content_chunk']); // For full-text search as fallback
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_base');
    }
};
