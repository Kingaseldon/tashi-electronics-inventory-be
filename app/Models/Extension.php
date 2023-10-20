<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\CreatedByTrait;

class Extension extends Model implements Auditable
{
    use HasFactory, CreatedByTrait, \OwenIt\Auditing\Auditable;

    protected $table ='extensions';
    protected $fillable = ['name', 'regional_id', 'code', 'description', 'created_by'];

    
    public function region()
    {
        return $this->belongsTo(Region::class, 'regional_id');
    }
}
