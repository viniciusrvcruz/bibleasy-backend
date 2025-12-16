<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'order'];

    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class);
    }
}
