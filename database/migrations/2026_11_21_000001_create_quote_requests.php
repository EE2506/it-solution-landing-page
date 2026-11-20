<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->boolean('is_owner')->default(false));

        Schema::create('quote_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->uuid('request_key')->unique();
            $table->char('email_lookup', 64)->index();
            $table->text('contact');
            $table->longText('snapshot');
            $table->string('status', 16)->default('new');
            $table->text('internal_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('quote_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quote_request_id')->constrained()->cascadeOnDelete();
            $table->string('recipient', 10);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('available_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->unique(['quote_request_id', 'recipient']);
            $table->index(['sent_at', 'failed_at', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_deliveries');
        Schema::dropIfExists('quote_requests');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_owner'));
    }
};
