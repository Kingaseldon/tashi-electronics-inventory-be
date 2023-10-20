<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\CreatedByTrait;

class SubCategory extends Model implements Auditable
{
    use HasFactory, CreatedByTrait, \OwenIt\Auditing\Auditable;
    
    protected $table ='sub_categories';
    protected $fillable = ['name', 'category_id', 'code', 'description', 'created_by'];

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
}
