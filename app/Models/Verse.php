<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Verse extends Model
{
    use HasFactory;

    protected $fillable = ['chapter_id', 'number', 'text'];

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    public function references(): HasMany
    {
        return $this->hasMany(VerseReference::class);
    }
}
