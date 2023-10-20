<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\CreatedByTrait;


class Gewog extends Model  implements Auditable
{
    use HasFactory, CreatedByTrait, \OwenIt\Auditing\Auditable;

    
    public function villages()
    {
        return $this->hasMany(Village::class);
    }

    public function dzongkhag()
    {
        return $this->belongsTo(Dzongkhag::class);
    }
}
