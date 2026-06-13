<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('external_id')->nullable();
            $table->string('fingerprint', 64);
            $table->string('author_name')->nullable();
            $table->date('review_date')->nullable();
            $table->text('text')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index('organization_id');
            $table->index(['organization_id', 'review_date']);
            $table->index(['organization_id', 'external_id']);
            $table->unique(['organization_id', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
