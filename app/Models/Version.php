<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Version extends Model
{
    protected $fillable = ['name', 'language', 'copyright'];

    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class);
    }

    public function verses(): HasManyThrough
    {
        return $this->hasManyThrough(Verse::class, Chapter::class);
    }
}
