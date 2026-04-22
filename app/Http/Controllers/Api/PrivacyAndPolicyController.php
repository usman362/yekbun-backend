<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PolicyAndTerm;
use Illuminate\Http\Request;
use App\Models\Setting;

class PrivacyAndPolicyController extends Controller
{
    public function privacy(){
        $privacy  =PolicyAndTerm::get();
        return response()->json(['success' => true, 'data' => $privacy]);
    }

    public function single_privacy($name){
        $single_privacy = PolicyAndTerm::where('name', $name)->first();
        return response()->json(['success' => true, 'data' => $single_privacy]);
    }
    
}



