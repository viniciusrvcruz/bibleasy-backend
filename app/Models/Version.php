<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Version extends Model
{
    protected $fillable = ['name', 'copyright'];

    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class);
    }
}
