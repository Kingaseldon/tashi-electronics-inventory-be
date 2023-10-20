<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use App\Traits\CreatedByTrait;

class AssignRegionExtension extends Model implements Auditable
{
    use HasFactory, CreatedByTrait, \OwenIt\Auditing\Auditable;

    public function region() 
    {
        return $this->belongsTo(Region::class, 'regional_id');
    }

    public function user() 
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function extension() 
    {
        return $this->belongsTo(Extension::class, 'extension_id');
    }
}
