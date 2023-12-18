<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\CreatedByTrait;


class ReturnProduct extends Model implements Auditable
{
    use HasFactory, CreatedByTrait, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'item_number',
        'category_id',
        'sale_type_id',
        'sub_category_id',
        'serial_no',
        'store_id',
        'color_id',
        'total_quantity',
        'price',
        'created_date',
        'description',
        'sub_inventory',
        'locator',
        'iccid',
        'status',
        'invoice_number',
        'sale_status',  
        'created_by'  

    ];
}
