<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_page_sections', function (Blueprint $table) {
            $table->id();
            $table->string('section_key')->unique(); // e.g. 'hero', 'services_header', 'how_it_works', etc.
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable();
            $table->string('badge_text')->nullable();
            $table->string('button_primary_text')->nullable();
            $table->string('button_primary_link')->nullable();
            $table->string('button_secondary_text')->nullable();
            $table->string('button_secondary_link')->nullable();
            $table->string('image_url')->nullable();
            $table->string('image_alt')->nullable();
            $table->json('metadata')->nullable(); // extra flexible JSON for misc data
            $table->integer('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });

        Schema::create('landing_page_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('landing_page_sections')->onDelete('cascade');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('image_url')->nullable();
            $table->string('step_number')->nullable();
            $table->string('link')->nullable();
            $table->json('metadata')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });

        Schema::create('landing_page_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('text'); // text, json, boolean, number
            $table->string('group')->default('general'); // general, branding, footer, seo
            $table->string('label')->nullable(); // human-readable label
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_page_items');
        Schema::dropIfExists('landing_page_sections');
        Schema::dropIfExists('landing_page_settings');
    }
};
