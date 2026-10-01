<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $connection = 'catalog';
    protected $table = 'products';

    protected $fillable = [
        'sku',
        'designation',
        'brand_id',
        'category_id',
        'description',
        'com',
        'unit',
        'gross_price',
        'spec',
        'cost_price',
        'min_stock',
        'status',
    ];

    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
}
