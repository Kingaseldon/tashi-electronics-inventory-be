<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\CreatedByTrait;

class ProductMovement extends Model implements Auditable
{
    use HasFactory, CreatedByTrait, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'product_id',
        'description',
        'regional_transfer_id',
        'region_extension_id',
        'regional_id',
        'requisition_number',
        'movement_date',
        'status',
        'receive',
        'created_by',
        'status',
        'created_by',
        'product_movement_no'
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
    public function createdby()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function updatedby()
    {
        return $this->belongsTo(User::class, 'updated_by');
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

    public function productTransaction()
    {
        return $this->hasOne(productTransaction::class, 'product_movement_id');
    }


}
