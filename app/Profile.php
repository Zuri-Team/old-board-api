<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    //

    protected $fillable = [
        'bio', 'url', 'profile_img', 'phone'
    ];

    public function user()
    {
        //
        return $this->belongsTo('App\User');
    }
}
