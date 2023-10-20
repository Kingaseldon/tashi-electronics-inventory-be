<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\CreatedByTrait;

class Region extends Model implements Auditable
{
    use HasFactory, CreatedByTrait, \OwenIt\Auditing\Auditable;

    public function dzongkhag()
    {
        return $this->belongsTo(Dzongkhag::class);
    }

    public function extensions()
    {
        return $this->hasMany(Extension::class, 'regional_id');
    }
    
}
