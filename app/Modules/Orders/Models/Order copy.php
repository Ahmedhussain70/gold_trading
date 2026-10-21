<?php

// namespace Orders\Models;
namespace App\Modules\Orders\Models;

use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Database\Eloquent\Model;
use Astrotomic\Translatable\Contracts\Translatable as TranslatableContract;
use Astrotomic\Translatable\Translatable;
use CarTypes\Models\CarType;
use Plans\Models\UserPlan;
use Services\Models\Service;

class Order extends Model
{
    protected $guarded = [];
    protected $table = 'orders';

    protected $fillable = [
    'user_id','mode','type','status',
    'metal','currency','qty',
    'stop_loss','sell_when_price','take_profit',
    'price','prev_close_price','open_price','low_price','high_price','open_time','ch','chp','ask','bid',
    'price_gram_24k','price_gram_22k','price_gram_21k','price_gram_20k','price_gram_18k','price_gram_16k','price_gram_14k','price_gram_10k',
    'entry_price','executed_price',       // حسب المتاح عندك
    'close_price','realized_pnl','unrealized_pnl','closed_at','close_kind',
    'address','delivery_status','delivery_requested_at','delivery_method','delivery_city','delivery_phone','delivery_fee','delivery_tracking_no',
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
