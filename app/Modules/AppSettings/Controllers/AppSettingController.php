<?php

namespace AppSettings\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use AppSettings\Models\AppSettings;

class AppSettingController extends Controller
{
    public $path;


    public function __construct()
    {
        $this->middleware('auth');
        $this->path = 'AppSettings::';
    }

    public function index()
    {
        hasPermissions('show_settings');
        $title = transWord('Main Settings');
        $pages = [
            [transWord('App Settings'),'appsettings']
        ];
        $settings = AppSettings::whereIn('key', [
            'initial_demo_balance',
            'sell_commission_rate',
            'minimum_withdraw',
            'exchange_rate_usd_to_egp'
        ])->pluck('value', 'key')->toArray();

        return view($this->path.'index',compact('settings','pages','title'));
    }

    public function update(AppSettings $appSetting ,Request $request){
        $request->validate([
            'initial_demo_balance' => 'required|numeric|min:0',
            'sell_commission_rate' => 'required|numeric|min:0|max:100',
            'minimum_withdraw' => 'required|numeric|min:0',
            'exchange_rate_usd_to_egp' => 'required|numeric|min:1|max:1000',
        ]);

        // تحديث القيم في الجدول
        $keys = ['initial_demo_balance', 'sell_commission_rate', 'minimum_withdraw','exchange_rate_usd_to_egp' ];

        foreach ($keys as $key) {
            AppSettings::updateOrCreate(
                ['key' => $key],
                ['value' => $request->$key]
            );
        }
        \Cache::forget('trading:quotes:xau_xag');
        \Cache::forget('settings:exchange_rate_usd_to_egp');
        
        return back()->with('success', transWord('Settings updated successfully'));
    } 



}
