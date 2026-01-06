<?php

namespace App\Models;

use App\Enums\BookAbbreviationEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerseReference extends Model
{
    use HasFactory;

    protected $fillable = [
        'verse_id',
        'slug',
        'reference_text',
        'target_book_abbreviation',
        'target_chapter',
        'target_verse',
    ];

    protected function casts(): array
    {
        return [
            'target_book_abbreviation' => BookAbbreviationEnum::class,
        ];
    }

    public function verse(): BelongsTo
    {
        return $this->belongsTo(Verse::class);
    }
}

