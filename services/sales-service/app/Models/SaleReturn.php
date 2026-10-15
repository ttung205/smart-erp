<?php

namespace App\Models;

use App\Support\IdentityUserResolver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleReturn extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = ['return_date' => 'date', 'total_amount' => 'float'];

    public function request()
    {
        return $this->belongsTo(SaleReturnRequest::class, 'sale_return_request_id');
    }

    public function items()
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    public function vatInvoiceAdjustments()
    {
        return $this->hasMany(VatInvoiceAdjustment::class);
    }

    public function getCreatedByNameAttribute(?string $value): string
    {
        return IdentityUserResolver::name($this->created_by_id, $value);
    }
}
