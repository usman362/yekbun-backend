<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Ad;
use App\Models\MarketCategory;
use App\Models\MarketGallery;
use App\Models\MarketService;
use App\Models\MarketSubCategory;
use App\Models\MarketView;

class MarketServiceContorller extends Controller
{
    // About Service Market
    public function market_services(Request $request){

        if($request->has('upload')){

            $request->validate([
                'title' => 'required',
                'description' => 'required',
                'gallery' => 'required'
            ]);
    
            // for about service
            $ads = new Ad();
            $ads->title = $request->title;
            $ads->user_id = $request->user_id;
            $ads->description  = $request->description;
            $ads->country = $request->country;
            $ads->city = $request->city;
            $ads->address = $request->address;
            $ads->code = $request->code;
            $ads->phone_no = $request->phone_no;
            $ads->save();

            // for categories
            $categoryData = $request->input('category');
            $subcategoryData =  $request->input('sub_category');
            $servicesData = $request->input('services');
     
            MarketCategory::create([
                 'mail_contact' => $categoryData['mail_contact'] ?? null,
                 'message' => $categoryData['message'] ?? null
                 ]);
             MarketSubCategory::create([
                 'mail_contact' => $subcategoryData['mail_contact'] ?? null,
                 'message' => $subcategoryData['message'] ?? null
             ]);
             MarketService::create([
                 'mail_contact' => $servicesData['mail_contact'] ?? null,
                 'message' => $servicesData['message'] ?? null
             ]);

             // for our Gallery
             $market_gallery = new MarketGallery();
             $img = $request->input('gallery');
             $image = collect([]);
     
             foreach($img  as $gallery){
                 $image->push($gallery);     
             }
             $market_gallery->gallery = json_encode($image);
             $market_gallery->save();



             // for view option
             $market_view = new MarketView();
             $market_view->mail_contact = $request->mail_contact;
             $market_view->message = $request->message;
             $market_view->phone = $request->phone;
             $market_view->address = $request->address;


            return response()->json(['success' => true , 'message' => 'Categories saved successfully']);

        }
    }

}
