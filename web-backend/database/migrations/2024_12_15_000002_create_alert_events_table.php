<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('alert_rule_id');
            $table->string('severity'); // critical, warning, info
            $table->text('message');
            $table->text('context')->nullable(); // JSON context data
            $table->string('channel'); // slack, email, sms
            $table->boolean('sent')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->string('external_id')->nullable(); // Slack message ID, etc
            $table->boolean('acknowledged')->default(false);
            $table->unsignedBigInteger('acknowledged_by')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->text('acknowledgment_note')->nullable();
            $table->timestamps();
            
            $table->foreign('alert_rule_id')->references('id')->on('alert_rules')->onDelete('cascade');
            $table->index('severity');
            $table->index('sent');
            $table->index('acknowledged');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_events');
    }
};
