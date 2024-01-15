<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\CreatedByTrait;

class Category extends Model implements Auditable
{
    use HasFactory, CreatedByTrait, \OwenIt\Auditing\Auditable;
    protected $fillable = ['sale_type_id'];


    public function saleType()
    {
        return $this->belongsTo(SaleType::class);
    }

    public function sub_categories()
    {
        return $this->hasMany(SubCategory::class, 'category_id');
    }
}
