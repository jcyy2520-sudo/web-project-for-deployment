<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_appeals', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->unsignedBigInteger('user_id');
            $table->string('user_email');
            $table->string('user_name');
            $table->enum('action_type', ['deleted', 'blocked', 'deactivated']);
            $table->text('action_reason')->nullable();
            $table->string('appeal_category')->nullable();
            $table->text('appeal_message')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('admin_response')->nullable();
            $table->unsignedBigInteger('acted_by')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('appeal_submitted_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('acted_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('resolved_by')->references('id')->on('users')->onDelete('set null');
            $table->index('token');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_appeals');
    }
};
