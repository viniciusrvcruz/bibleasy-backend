<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verse_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verse_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->text('reference_text');
            $table->string('target_book_abbreviation')->nullable();
            $table->integer('target_chapter')->nullable();
            $table->integer('target_verse')->nullable();
            $table->timestamps();

            $table->unique(['verse_id', 'slug']);
            $table->index('verse_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verse_references');
    }
};

