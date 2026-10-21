<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PriceAppKey
{
    public function handle(Request $request, Closure $next)
    {
        $configured = config('services.price_app_key');

        // ادعم الهيدر X-App-Key أو ?app_key= ... (للـ SSE بالمتصفح)
        $given = $request->header('X-App-Key') ?: $request->query('app_key');

        // لو عايز تخلّيها فعلاً عامّة من غير مفتاح، علّق الشرطين دول واسمح بالدخول.
        if ($configured && $given !== $configured) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden',
                'result'  => null,
            ], 403);
        }

        return $next($request);
    }
}
