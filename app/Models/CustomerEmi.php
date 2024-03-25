<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\CreatedByTrait;

class CustomerEmi extends Model implements Auditable
{
    use HasFactory, CreatedByTrait, \OwenIt\Auditing\Auditable;
    protected $fillable = [
        'user_id',
        'emi_no',
        'sale_voucher_id',
        'product_id',
        'created_by',
        'quantity',
        'request_date',
        'emi_duration',
        'product_id',
        'monthly_emi',
        'total',
        'deduction_from',
        'status',
        'updated_by'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function creator()
    {
        return $this->belongsTo(User::class,'updated_by');
    }
    public function saleVoucher()
    {
        return $this->belongsTo(SaleVoucher::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
