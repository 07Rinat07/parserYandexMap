<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->unsignedTinyInteger('parser_confidence')->nullable()->after('parsing_error');
            $table->json('parser_metadata')->nullable()->after('parser_confidence');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn(['parser_confidence', 'parser_metadata']);
        });
    }
};
