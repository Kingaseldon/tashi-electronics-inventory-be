<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\CreatedByTrait;

class Warranty extends Model implements Auditable
{
    use HasFactory, CreatedByTrait, \OwenIt\Auditing\Auditable;
    protected $table = 'warranty';
    protected $fillable = ['no_of_years'];

    public function saleType()
    {
        return $this->belongsTo(SaleType::class);
    }

}
