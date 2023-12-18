<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\CreatedByTrait;

class Product extends Model implements Auditable
{
    use HasFactory, CreatedByTrait, \OwenIt\Auditing\Auditable;
    protected $fillable = [
        'item_number',
        'description',
        'total_quantity',
        'main_store_distributed_qty',
        'main_store_qty',
        'main_store_sold_qty',
        'region_store_distributed_qty',
        'region_store_qty',
        'region_store_sold_qty',
        'extension_store_sold_qty',
        'extension_store_qty',
        'extension_distributed_qty',
        'serial_no',
        'sale_status',
        'price',
        'created_by',
        'status',
        'sale_type_id',
        'category_id',
        'store_id',
        'sub_category_id',
        'color_id',
        'iccid',
        'sub_inventory',
        'locator',
        'batch_no',
        'created_date',        
      
    ];

    public function unit() 
    {
        return $this->belongsTo(Unit::class);
    }

    public function brand() 
    {
        return $this->belongsTo(Brand::class);
    }

    public function store() 
    {
        return $this->belongsTo(Store::class);
    }

    public function category() 
    {
        return $this->belongsTo(Category::class);
    }

    public function color() 
    {
        return $this->belongsTo(Color::class);
    }

    public function saleType() 
    {
        return $this->belongsTo(SaleType::class);
    }

    public function subCategory() 
    {
        return $this->belongsTo(SubCategory::class);
    }

    public function transaction()
    {
        return $this->hasMany(ProductTransaction::class);
    }

    
}
