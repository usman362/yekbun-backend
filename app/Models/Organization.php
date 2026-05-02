<?php

namespace App\Models;

//use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Organization extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        "logo",
        "showCreateFormModal",
        "organization_name",
        "gender",
        "first_name",
        "last_name",
        "country",
        "city",
        "address",
        "phone_no",
        "email",
        "image",
    ];

    public function donations()
    {
        return $this->hasMany(Donation::class);
    }
}
