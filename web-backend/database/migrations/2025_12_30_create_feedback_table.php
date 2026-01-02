<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('email');
            $table->text('message');
            $table->integer('rating')->default(5)->comment('Star rating 1-5');
            $table->boolean('is_testimonial')->default(false)->comment('Whether this feedback is marked as testimonial');
            $table->timestamps();
            
            // Indexes for efficient querying
            $table->index(['user_id']);
            $table->index(['is_testimonial']);
            $table->index(['created_at']);
            $table->index(['email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
