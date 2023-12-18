<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\CreatedByTrait;

class Store extends Model implements Auditable
{
    use HasFactory, CreatedByTrait, \OwenIt\Auditing\Auditable;

    public function dzongkhag()
    {
        return $this->belongsTo(Dzongkhag::class);
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }
    public function extension()
    {
        return $this->belongsTo(Extension::class);
    }
}
