<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\CreatedByTrait;

class ProductRequisition extends Model implements Auditable
{
    use HasFactory, CreatedByTrait, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'status', 'transfer_quantity', 'transfer_date'
    ];

    public function saleType() 
    {
        return $this->belongsTo(SaleType::class, 'sale_type_id');
    }

    public function region() 
    {
        return $this->belongsTo(Region::class, 'regional_id');
    }
     
    public function extension() 
    {
        return $this->belongsTo(Extension::class,  'region_extension_id');
    }

    public function createdBy() 
    {
        return $this->belongsTo(User::class,  'created_by');
    }

    public function getRequestDateAttribute($value)
    {
        return $value ? date('d-m-Y', strtotime($value)) : null;
    }

    public function setRequestDateAttribute($value)
    {

        $this->attributes['request_date'] = date('Y-m-d', strtotime($value));
    }

    public function getTransferDateAttribute($value)
    {
        return $value ? date('d-m-Y', strtotime($value)) : null;
    }

    public function setTransferDateAttribute($value)
    {

        $this->attributes['transfer_date'] = date('Y-m-d', strtotime($value));
    }
    
}
