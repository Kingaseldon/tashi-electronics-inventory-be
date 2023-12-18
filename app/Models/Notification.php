<?php

namespace App\Models;

use App\Traits\CreatedByTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Notification extends Model implements Auditable
{
    use HasFactory, CreatedByTrait, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'extension_from',
        'extension_to',
        'requisition_number',     
        'created_date',
        'created_by',
        'status',
        'read',
        'user_id',
        'product_id',
        'quantity'
    ];
}
