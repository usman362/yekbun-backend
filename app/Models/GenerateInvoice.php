<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
//use Illuminate\Database\Eloquent\Model;
use Jenssegers\Mongodb\Eloquent\Model;

class GenerateInvoice extends Model
{
    use HasFactory;

    public function user(){
        return $this->belongsTo(User::class);
    }
}
