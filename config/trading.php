<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trading Engine - Polling & Quotes Caching
    |--------------------------------------------------------------------------
    | poll_seconds:
    |   - تكرار تشغيل الـ Job (Self-dispatch loop).
    |   - ده "معدل فحص" داخلي، مش شرط يكون معدل جلب الأسعار من الـ API.
    |
    | quotes_ttl_seconds:
    |   - مدة صلاحية الأسعار في الكاش.
    |   - الجوب هيعمل API call فقط عند انتهاء الـ TTL.
    |
    | Free Plan (Recommended):
    |   TRADING_POLL_SECONDS=2
    |   TRADING_QUOTES_TTL_SECONDS=300   (5 minutes)
    |
    | Paid Plan (Near real-time):
    |   TRADING_POLL_SECONDS=1
    |   TRADING_QUOTES_TTL_SECONDS=1-2
    */

    'poll_seconds' => (int) env('TRADING_POLL_SECONDS', 10),

    'quotes_ttl_seconds' => (int) env('TRADING_QUOTES_TTL_SECONDS', 180),


    /*
    |--------------------------------------------------------------------------
    | Lock to Prevent Overlap
    |--------------------------------------------------------------------------
    | يمنع تشغيل نسختين من الجوب في نفس الوقت.
    | لازم lock_seconds >= poll_seconds عادةً.
    */

    'lock_seconds' => (int) env('TRADING_LOCK_SECONDS', 20),


    /*
    |--------------------------------------------------------------------------
    | Spread Configuration
    |--------------------------------------------------------------------------
    | spread_fixed:
    |   سبريد ثابت يتم إضافته/خصمه من سعر السوق لاستخراج buy/sell.
    |   (نفس منطق calculateBuySell عندك)
    |
    | ملاحظة: لو لاحقاً عايز سبريد بالنِسَب بدل الثابت، نخليه config ونعدل الحساب.
    */

    'spread_fixed' => (float) env('TRADING_SPREAD_FIXED', 2.0),


    /*
    |--------------------------------------------------------------------------
    | TwelveData Symbols (Metals)
    |--------------------------------------------------------------------------
    | رموز TwelveData المستخدمة.
    */

    'symbols' => [
        'XAU' => env('TRADING_SYMBOL_XAU', 'XAU/USD'),
        'XAG' => env('TRADING_SYMBOL_XAG', 'XAG/USD'),
    ],


    /*
    |--------------------------------------------------------------------------
    | Safety Limits
    |--------------------------------------------------------------------------
    | max_chunk:
    |   عدد السجلات في كل chunkById لتقليل الضغط.
    */

    'max_chunk' => (int) env('TRADING_MAX_CHUNK', 300),


    /*
    |--------------------------------------------------------------------------
    | Quotes Cache Key
    |--------------------------------------------------------------------------
    | مفتاح الكاش الذي نخزن تحته أسعار المعادن.
    */

    'quotes_cache_key' => env('TRADING_QUOTES_CACHE_KEY', 'trading:quotes:xau_xag'),

];
