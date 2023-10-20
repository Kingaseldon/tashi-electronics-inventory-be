<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\CreatedByTrait;

class Dzongkhag extends Model implements Auditable
{
    use HasFactory, CreatedByTrait, \OwenIt\Auditing\Auditable;

    public function gewogs()
    {
        return $this->hasMany(Gewog::class)->orderBy('name');
    }

    public function villages()
    {
        return $this->hasMany(Village::class)->orderBy('name');
    }
}
