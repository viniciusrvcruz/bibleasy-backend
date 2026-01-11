<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerseReference extends Model
{
    use HasFactory;

    protected $fillable = [
        'verse_id',
        'slug',
        'text',
    ];

    public function verse(): BelongsTo
    {
        return $this->belongsTo(Verse::class);
    }
}

