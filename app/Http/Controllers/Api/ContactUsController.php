<?php

namespace App\Http\Controllers\Api;

use App\Models\ContactUs;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ContactUsController extends Controller
{
    public function contact_us(Request $request){
        $request->validate([
            'interest' => 'required',
            'title' => 'required',
            'description' => 'required'
        ]);

        $contact = new ContactUs();
        $contact->interest = $request->interest;
        $contact->title = $request->title;
        $contact->description = $request->description;

        if($contact->save()){
            return response()->json(['success' => true, 'message' => 'Your issue has been sent successfully.']);
        }else{
            return response()->json(['success' => false, 'message' => 'Issue has not  sent successfully.']);
        }
    }
    
}
