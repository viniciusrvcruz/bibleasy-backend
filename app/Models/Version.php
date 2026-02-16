<?php

namespace App\Models;

use App\Enums\VersionTextSourceEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Version extends Model
{
    use HasFactory;

    protected $fillable = [
        'abbreviation',
        'name',
        'language',
        'copyright',
        'external_version_id',
        'text_source',
        'cache_ttl',
    ];

    protected function casts(): array
    {
        return [
            'text_source' => VersionTextSourceEnum::class,
            'cache_ttl' => 'integer',
        ];
    }

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    public function chapters(): HasManyThrough
    {
        return $this->hasManyThrough(Chapter::class, Book::class);
    }
}
