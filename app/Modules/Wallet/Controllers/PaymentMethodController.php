<?php

namespace Wallet\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Wallet\Models\PaymentMethods;

class PaymentMethodController extends Controller
{
    protected string $path;

    public function __construct()
    {
        $this->middleware('auth');
        $this->path = 'Wallet::';
    }

    public function index()
    {
        $title = transWord('Payment Methods');
        $pages = [
            [transWord('Payment Methods'),'payment_methods.index']
        ];

        $methods = PaymentMethods::orderBy('sort_order')->orderByDesc('id')->get();

        return view($this->path.'payment_methods.index', compact('title','pages','methods'));
    }

    public function store(Request $r)
    {
        $v = Validator::make($r->all(), [
            'name'       => 'required|string|max:190',
            'value'      => 'required|string',
            'is_active'  => 'required|in:0,1',   // بدلاً من boolean
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($v->fails()) {
            return back()->withErrors($v)->withInput();
        }

        PaymentMethods::create([
            'name'       => $r->name,
            'value'      => $r->value,
            'is_active'  => (int)$r->input('is_active', 1),   // تضمن 0/1
            'sort_order' => (int)$r->input('sort_order', 0),
            'created_by' => Auth::id(),
        ]);

        return back()->with('success', transWord('Added successfully'));
    }

    public function update(Request $r, $id)
    {
        $method = PaymentMethods::findOrFail($id);

        $v = Validator::make($r->all(), [
            'name'       => 'required|string|max:190',
            'value'      => 'required|string',
            'is_active'  => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($v->fails()) {
            return back()->withErrors($v)->withInput();
        }

        $method->update([
            'name'       => $r->name,
            'value'      => $r->value,
            'is_active'  => (int)$r->input('is_active', 1),
            'sort_order' => (int)$r->input('sort_order', 0),
            'updated_by' => Auth::id(),
        ]);

        return back()->with('success', transWord('Updated successfully'));
    }

    public function toggle($id)
    {
        $method = PaymentMethods::findOrFail($id);
        $method->is_active = ! $method->is_active;
        $method->updated_by = Auth::id();
        $method->save();

        return back()->with('success', transWord('Status updated'));
    }

    public function destroy($id)
    {
        $method = PaymentMethods::findOrFail($id);
        $method->delete();

        return back()->with('success', transWord('Deleted successfully'));
    }

    /**
     * (اختياري) API/JSON لعرضها في التطبيق للمستخدمين كقائمة نصوص
     */
    public function listJson()
    {
        $methods = PaymentMethods::active()->get(['id','name','value']);
        return response()->json([
            'success' => true,
            'data' => $methods,
        ]);
    }
}
