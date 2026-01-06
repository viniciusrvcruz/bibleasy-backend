<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Version extends Model
{
    use HasFactory;

    protected $fillable = ['abbreviation', 'name', 'language', 'copyright'];

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    public function chapters(): HasManyThrough
    {
        return $this->hasManyThrough(Chapter::class, Book::class);
    }
}
