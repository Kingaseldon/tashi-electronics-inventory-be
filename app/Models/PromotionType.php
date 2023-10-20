<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\CreatedByTrait;

class PromotionType extends Model implements Auditable
{
    use HasFactory, CreatedByTrait, \OwenIt\Auditing\Auditable;

    public function getStartDateAttribute($value)
    {
        return $value ? date('d-m-Y', strtotime($value)) : null;
    }

    public function setStartDateAttribute($value)
    {

        $this->attributes['start_date'] = date('Y-m-d', strtotime($value));
    }

    public function getEndDateAttribute($value)
    {
        return $value ? date('d-m-Y', strtotime($value)) : null;
    }

    public function setEndDateAttribute($value)
    {

        $this->attributes['end_date'] = date('Y-m-d', strtotime($value));
    }
}
