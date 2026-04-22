<?php

namespace App\Http\Controllers\Api;

use App\Models\Setting;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class UserSettingController extends Controller
{
    
    public function index($user_id , Request $request){
        if(is_array($request->name)){
                $data = Setting::where('user_id' , $user_id)->whereIn('name' , $request->name)->get();
        }else{
            $data = Setting::where('user_id' , $user_id)->where('name' , $request->name)->first();
        }
        return response()->json(['success' => true, 'data'=> $data]);
    }

    public function save(Request $request){

        $setting =  new Setting();
        $setting->name = $request->name;
        $setting->value = $request->value;
        $setting ->user_id = $request->user()->id;
        if($setting->save()){
            return response()->json(['success' => true , 'data'=> $setting]);
        }else{
            return response()->json(['success' => false, 'data'=>$setting]);
        }
    }
    
    
}
