<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\CreatedByTrait;

class SaleVoucher extends Model implements Auditable
{
    use HasFactory, CreatedByTrait, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'status',
    ];
    
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function user(){
        return $this->belongsTo(User::class,'created_by');    
    }


    public function customerEMI()
    {
        return $this->belongsTo(CustomerEmi::class, 'regional_id');
    }
    
    public function extension()
    {
        return $this->belongsTo(Extension::class, 'region_extension_id');
    }
    
    //for particular region assign
    public function scopeLoggedInAssignRegion($query)
    {
        return $query->where('regional_id', auth()->user()->assignAndEmployee->regional_id);
    }

    //for particular extension assign
    public function scopeLoggedInAssignExtension($query)
    {
        return $query->where('region_extension_id', auth()->user()->assignAndEmployee->extension_id);
    }

    public function getInvoiceDateAttribute($value)
    {
        return $value ? date('d-m-Y', strtotime($value)) : null;
    }

    public function setInvoiceDateAttribute($value)
    {

        $this->attributes['invoice_date'] = date('Y-m-d', strtotime($value));
    }

    public function saleVoucherDetails()
    {
        return $this->hasMany(SaleVoucherDetail::class, 'sale_voucher_id');
    }
}
