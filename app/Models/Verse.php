<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Verse extends Model
{
    protected $fillable = ['chapter_id', 'number', 'text'];

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }
}
