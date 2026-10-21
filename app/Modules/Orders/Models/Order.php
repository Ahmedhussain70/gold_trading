<?php

namespace App\Modules\Orders\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Product\Models\Product;

class Order extends Model
{
    protected $guarded = [];
    protected $table = 'orders';

    protected $fillable = [
        'user_id',
        'product_id',        // ✅ NEW: ربط بالمنتج
        'quantity',          // ✅ NEW: عدد الوحدات/القطع
        'unit_gram_weight',  // ✅ NEW: وزن الوحدة وقت الشراء
        'unit_karat',        // ✅ NEW: العيار وقت الشراء
        'mode',
        'type',
        'status',
        'metal',
        'currency',
        'qty',               // إجمالي الجرامات (quantity × unit_gram_weight)
        'stop_loss',
        'sell_when_price',
        'take_profit',
        'price',
        'prev_close_price',
        'open_price',
        'low_price',
        'high_price',
        'open_time',
        'ch',
        'chp',
        'ask',
        'bid',
        'price_gram_24k',
        'price_gram_22k',
        'price_gram_21k',
        'price_gram_20k',
        'price_gram_18k',
        'price_gram_16k',
        'price_gram_14k',
        'price_gram_10k',
        'entry_price',
        'executed_price',
        'close_price',
        'realized_pnl',
        'unrealized_pnl',
        'closed_at',
        'close_kind',
        'address',
        'delivery_status',
        'delivery_requested_at',
        'delivery_method',
        'delivery_city',
        'delivery_phone',
        'delivery_fee',
        'delivery_tracking_no',

        'metal_cost_snapshot',
        'manufacturing_fee_snapshot',
        'shipping_cost_snapshot',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_gram_weight' => 'decimal:3',
        'unit_karat' => 'integer',
        'qty' => 'decimal:3',
        'open_price' => 'decimal:4',
        'price' => 'decimal:4',
        'entry_price' => 'decimal:4',
        'executed_price' => 'decimal:4',
        'close_price' => 'decimal:4',
        'realized_pnl' => 'decimal:6',
        'unrealized_pnl' => 'decimal:6',
        'stop_loss' => 'decimal:4',
        'take_profit' => 'decimal:4',
        'delivery_fee' => 'decimal:2',
        'closed_at' => 'datetime',
        'delivery_requested_at' => 'datetime',
    ];

    /**
     * Get the user that owns the order
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the product associated with this order
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Calculate total grams for this order
     * quantity × unit_gram_weight
     */
    public function getTotalGramsAttribute(): float
    {
        return (float)$this->quantity * (float)$this->unit_gram_weight;
    }

    /**
     * Scope: Filter by metal type
     */
    public function scopeByMetal($query, string $metal)
    {
        return $query->where('metal', $metal);
    }

    /**
     * Scope: Active trades (open)
     */
    public function scopeActiveTrades($query)
    {
        return $query->where('type', 'trade')->where('status', 'open');
    }

    /**
     * Scope: Pending orders
     */
    public function scopePendingOrders($query)
    {
        return $query->where('type', 'order')->where('status', 'pending');
    }
}
