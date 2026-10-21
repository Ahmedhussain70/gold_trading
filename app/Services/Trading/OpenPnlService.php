<?php

namespace App\Services\Trading;

use App\Modules\Orders\Models\Order;
use AppSettings\Models\AppSettings;
use Illuminate\Support\Facades\Cache;

class OpenPnlService
{
    public function calculateForOrder(Order $order): array
    {
        $exchangeRate = $this->getExchangeRate();

        $currency = strtoupper((string) $order->currency);

        $entryPrice = (float) ($order->entry_price ?: $order->executed_price ?: $order->price);
        $currentPrice = $this->getCurrentGramPrice($order, $currency, $exchangeRate);

        $qty = (float) $order->qty;

        if ($qty <= 0) {
            $qty = (float) $order->quantity * (float) $order->unit_gram_weight;
        }

        if ($entryPrice <= 0 || $currentPrice <= 0 || $qty <= 0) {
            return [
                'entry_price' => round($entryPrice, 4),
                'current_price' => round($currentPrice, 4),

                'entry_price_usd' => $currency === 'USD' ? round($entryPrice, 4) : round($entryPrice / $exchangeRate, 4),
                'current_price_usd' => $currency === 'USD' ? round($currentPrice, 4) : round($currentPrice / $exchangeRate, 4),

                'entry_price_egp' => $currency === 'EGP' ? round($entryPrice, 4) : round($entryPrice * $exchangeRate, 4),
                'current_price_egp' => $currency === 'EGP' ? round($currentPrice, 4) : round($currentPrice * $exchangeRate, 4),

                'floating_pnl' => 0,
                'floating_pnl_usd' => 0,
                'floating_pnl_egp' => 0,
                'floating_pnl_percent' => 0,

                'currency' => $currency,
                'exchange_rate_usd_to_egp' => round($exchangeRate, 4),
            ];
        }

        // النظام الحالي شراء فقط Long/Buy
        $pnl = ($currentPrice - $entryPrice) * $qty;

        if ($currency === 'EGP') {
            $pnlEgp = $pnl;
            $pnlUsd = $exchangeRate > 0 ? $pnl / $exchangeRate : 0;
        } else {
            $pnlUsd = $pnl;
            $pnlEgp = $pnl * $exchangeRate;
        }

        $positionValue = $entryPrice * $qty;

        $pnlPercent = $positionValue > 0
            ? ($pnl / $positionValue) * 100
            : 0;

        return [
            // السعر بنفس عملة الأوردر
            'entry_price' => round($entryPrice, 4),
            'current_price' => round($currentPrice, 4),

            // السعر بالدولار للعرض فقط
            'entry_price_usd' => $currency === 'USD'
                ? round($entryPrice, 4)
                : round($entryPrice / $exchangeRate, 4),

            'current_price_usd' => $currency === 'USD'
                ? round($currentPrice, 4)
                : round($currentPrice / $exchangeRate, 4),

            // السعر بالجنيه للعرض فقط
            'entry_price_egp' => $currency === 'EGP'
                ? round($entryPrice, 4)
                : round($entryPrice * $exchangeRate, 4),

            'current_price_egp' => $currency === 'EGP'
                ? round($currentPrice, 4)
                : round($currentPrice * $exchangeRate, 4),

            // P/L الأساسي بنفس عملة الأوردر
            'floating_pnl' => round($pnl, $currency === 'EGP' ? 2 : 6),

            // P/L مفصول حسب العملة
            'floating_pnl_usd' => round($pnlUsd, 6),
            'floating_pnl_egp' => round($pnlEgp, 2),

            'floating_pnl_percent' => round($pnlPercent, 4),

            'currency' => $currency,
            'exchange_rate_usd_to_egp' => round($exchangeRate, 4),
        ];
    }

    public function calculateSummaryForUser(int $userId, ?string $mode = null): array
    {
        $query = Order::query()
            ->where('user_id', $userId)
            ->where('type', 'trade')
            ->where('status', 'open');

        if ($mode) {
            $query->where('mode', $mode);
        }

        $orders = $query->get();

        $totalUsd = 0;
        $totalEgp = 0;

        foreach ($orders as $order) {
            $pnl = $this->calculateForOrder($order);

            $currency = strtoupper((string) $order->currency);

            // مهم: التجميع حسب عملة الأوردر الأصلية فقط
            if ($currency === 'EGP') {
                $totalEgp += $pnl['floating_pnl'];
            } else {
                $totalUsd += $pnl['floating_pnl'];
            }
        }

        return [
            'floating_pnl_usd' => round($totalUsd, 6),
            'floating_pnl_egp' => round($totalEgp, 2),
            'open_trades_count' => $orders->count(),
            'exchange_rate_usd_to_egp' => round($this->getExchangeRate(), 4),
        ];
    }

    private function getCurrentGramPrice(Order $order, string $currency, float $exchangeRate): float
    {
        $currentUsdGramPrice = $this->getCurrentGramPriceUsd($order);

        if ($currentUsdGramPrice <= 0) {
            return $order->price ? (float) $order->price : 0;
        }

        if ($currency === 'EGP') {
            return $currentUsdGramPrice * $exchangeRate;
        }

        return $currentUsdGramPrice;
    }

    private function getCurrentGramPriceUsd(Order $order): float
    {
        $symbol = $this->symbolForMetal($order->metal);

        $last = Cache::get("td:last:{$symbol}");

        if (is_array($last) && isset($last['price'])) {
            $ouncePriceUsd = (float) $last['price'];

            return $this->convertOunceToGram($ouncePriceUsd);
        }

        return 0;
    }

    private function symbolForMetal(?string $metal): string
    {
        return match (strtoupper((string) $metal)) {
            'XAG', 'SILVER' => 'XAG/USD',
            default => 'XAU/USD',
        };
    }

    private function convertOunceToGram(float $ouncePrice): float
    {
        return $ouncePrice / 31.1035;
    }

    private function getExchangeRate(): float
    {
        return (float) Cache::remember('settings:exchange_rate_usd_to_egp', 3600, function () {
            return AppSettings::where('key', 'exchange_rate_usd_to_egp')->value('value') ?: 51;
        });
    }
}