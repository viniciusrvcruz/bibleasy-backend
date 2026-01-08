<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chapter extends Model
{
    use HasFactory;

    protected $fillable = ['number', 'book_id'];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function verses(): HasMany
    {
        return $this->hasMany(Verse::class);
    }
}
