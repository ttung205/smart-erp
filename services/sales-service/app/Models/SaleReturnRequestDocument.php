<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleReturnRequestDocument extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function request()
    {
        return $this->belongsTo(SaleReturnRequest::class, 'sale_return_request_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
