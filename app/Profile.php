<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    //

    protected $fillable = [
        'bio', 'url', 'profile_img'
    ];

    public function user()
    {
        //
        return $this->belongsTo(User::class);
    }
}
