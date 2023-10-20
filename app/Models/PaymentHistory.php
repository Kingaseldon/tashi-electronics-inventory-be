<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\CreatedByTrait;

class PaymentHistory extends Model implements Auditable
{
    use HasFactory, CreatedByTrait, \OwenIt\Auditing\Auditable;

    public function getPaidAtAttribute($value)
    {
        return $value ? date('d-m-Y', strtotime($value)) : null;
    }

    public function setPaidAtAttribute($value)
    {

        $this->attributes['paid_at'] = date('Y-m-d', strtotime($value));
    }

    public function saleVoucher()
    {
        return $this->belongsTo(SaleVoucher::class, 'sale_voucher_id');
    }
}
