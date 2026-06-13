<?php

use App\Enums\ParsingStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('yandex_url');
            $table->string('normalized_yandex_url', 512);
            $table->string('yandex_business_id')->nullable()->index();
            $table->string('name')->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->unsignedInteger('ratings_count')->nullable();
            $table->unsignedInteger('reviews_count')->nullable();
            $table->string('parsing_status')->default(ParsingStatus::Pending->value)->index();
            $table->text('parsing_error')->nullable();
            $table->timestamp('last_parsed_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->unique(['user_id', 'normalized_yandex_url'], 'organizations_user_normalized_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
