<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_requests', function (Blueprint $table): void {
            $table->foreignId('revises_id')->nullable()->constrained('quote_requests')->restrictOnDelete();
            $table->foreignId('series_id')->nullable()->constrained('quote_requests')->restrictOnDelete();
        });

        Schema::create('quote_catalog_overrides', function (Blueprint $table): void {
            $table->string('item', 80)->primary();
            $table->json('settings');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_catalog_overrides');
        Schema::table('quote_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('revises_id');
            $table->dropConstrainedForeignId('series_id');
        });
    }
};
