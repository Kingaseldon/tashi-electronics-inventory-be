<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\CreatedByTrait;

class SaleVoucherDetail extends Model implements Auditable
{
    use HasFactory, CreatedByTrait, \OwenIt\Auditing\Auditable;

    protected $table = 'sale_voucher_details';
    protected $fillable = ['sale_voucher_id', 'product_id', 'product_transaction_id', 'quantity', 'gst', 'price', 'total', 'discount_type_id'];

    public function saleVoucher()
    {
        return $this->belongsTo(SaleVoucher::class, 'sale_voucher_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
    public function discount()
    {
        return $this->belongsTo(DiscountType::class, 'discount_type_id');
    }
}
