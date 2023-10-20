<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\CreatedByTrait;

class ProductTransaction extends Model implements Auditable
{
    use HasFactory, CreatedByTrait, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'receive',
        'store_quantity',
        'sold_quantity',
        'give_back',
        'sale_status',
        'region_store_quantity',
        'region_transfer_quantity',
        'updated_by',
        
    ];
  
    public function product() 
    {
        return $this->belongsTo(Product::class);
    } 
    
    public function region()
    {
        return $this->belongsTo(Region::class, 'regional_id');
    }

    public function extension()
    {
        return $this->belongsTo(Extension::class, 'region_extension_id');
    }
    
    //for particular region assign
    public function scopeLoggedInAssignRegion($query)
    {
        return $query->where('product_transactions.regional_id', auth()->user()->assignAndEmployee->regional_id);
    }

    //for particular extension assign
    public function scopeLoggedInAssignExtension($query)
    {
        return $query->where('region_extension_id', auth()->user()->assignAndEmployee->extension_id);
    }

    public function getReceivedDateAttribute($value)
    {
        return $value ? date('d-m-Y', strtotime($value)) : null;
    }

    public function setReceivedDateAttribute($value)
    {

        $this->attributes['received_date'] = date('Y-m-d', strtotime($value));
    }

    public function getMovementDateAttribute($value)
    {
        return $value ? date('d-m-Y', strtotime($value)) : null;
    }

    public function setMovementDateAttribute($value)
    {

        $this->attributes['movement_date'] = date('Y-m-d', strtotime($value));
    }
    public function productMovement()
    {
        return $this->hasOne(productMovement::class, 'id');
    }

  
 
}
