<?php

namespace App\Models;

use App\Enums\BookAbbreviationEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['version_id', 'name', 'abbreviation', 'order'];

    protected function casts(): array
    {
        return [
            'abbreviation' => BookAbbreviationEnum::class,
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(Version::class);
    }

    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class);
    }
}
