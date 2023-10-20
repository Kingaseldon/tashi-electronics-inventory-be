<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\CreatedByTrait;

class Bank extends Model implements Auditable
{
    use HasFactory, CreatedByTrait, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'name',
        'code',
        'account_number',
        'region_id',
        'extension_id',
        'description',
    ];

    public function region()
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    public function extension()
    {
        return $this->belongsTo(Extension::class, 'extension_id');
    }

   
}
