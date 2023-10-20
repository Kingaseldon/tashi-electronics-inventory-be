<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\CreatedByTrait;

class DiscountType extends Model implements Auditable
{
    use HasFactory, CreatedByTrait, \OwenIt\Auditing\Auditable;


    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function subCategory()
    {
        return $this->belongsTo(SubCategory::class);
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function extension()
    {
        return $this->belongsTo(Extension::class);
    }

    public function getStartDateAttribute($value)
    {
        return $value ? date('d-m-Y', strtotime($value)) : null;
    }

    // public function setStartDateAttribute($value)
    // {

    //     $this->attributes['start_date'] = date('Y-m-d', strtotime($value));
    // }

    public function getEndDateAttribute($value)
    {
        return $value ? date('d-m-Y', strtotime($value)) : null;
    }

    public function saleVoucherDetails($value)
    {
        return $this->hasMany(SaleVoucherDetaill::class);
    }

    // public function setEndDateAttribute($value)
    // {

    //     $this->attributes['end_date'] = date('Y-m-d', strtotime($value));
    // }
}
