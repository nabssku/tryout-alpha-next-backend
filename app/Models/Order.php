<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class Order extends Model
{
    protected $fillable = [
        'user_id','product_id','merchant_order_id','amount','status',
        'duitku_reference','payment_url','payment_method',
        'promo_code','discount','paid_at','raw_callback'
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'raw_callback' => 'array',
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
