<?php

namespace App\Models;

//use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Post extends Model
{
    
    use UsesLegacyId;
use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'content',
        'image',
        'status',
        'likes',
        'shares',
        'status',
        'media',
        'type'
    ];

    // public function user()
    // {
    //     return $this->belongsTo(User::class, 'user_id');
    // }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function reports()
    {
        return $this->hasMany(Report::class, 'reported_post_id', 'id');
    }

   

    public function background(){
        return $this->hasMany(BackgroundFeed::class  , 'id' , 'background_id');
    }
 
    public function user(){
        return $this->belongsTo(User::class );
    }
    public function collections()
    {
        return $this->belongsToMany(Collection::class, 'collection_feeds', 'feed_id', 'collection_id');
    }
    public function reactions(){

        return $this->hasMany(Reaction::class , 'feed_id');
    }
 
    public function users()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function gallery(){
        return $this->hasMany(PostGallery::class);
    }
 
}
