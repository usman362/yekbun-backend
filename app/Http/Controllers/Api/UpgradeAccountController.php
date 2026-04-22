<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Nette\Utils\Json;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\GenerateInvoice;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class UpgradeAccountController extends Controller
{

    public function price_upgrade(){
        $settings = Setting::whereIn('name', ['premium_price', 'vip_price'])
    ->pluck('value', 'name')
    ->toArray();
    return response()->json(['success' => true , 'data' => $settings]);
    }

    public function account_upgrade(Request $request){
        $user = User::where('id' ,Auth::id())->first();
        $user->level = $request->level;
        $user->save();

        $invoice = new GenerateInvoice();
        $invoice->invoice_no = time();
        $invoice->date =date('Y-m-d');
        $invoice->due_date = Carbon::now()->addDays(4)->format('Y-m-d');
        $invoice->user_id = Auth::id();
        $invoice->total = $request->payment;
        $invoice->item = Json_encode(["name" => "User Account Upgrade"]);
        $invoice->status = "paid";
        $invoice->save();
        return response()->json(['success' => 'true' , 'message' => 'Account upgrade successfully']);
    }
}
