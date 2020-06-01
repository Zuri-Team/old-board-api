<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    //

    protected $fillable = [
        'bio', 'url', 'profile_img', 'phone'
    ];

    public function profileImg()
    {
        // $imagePath = ($this->profile_img) ? $this->profile_img : 'https://res.cloudinary.com/hngojet/image/upload/v1573196562/hngojet/profile_img/no-photo_mpbetk.png';
        $imagePath = 'https://res.cloudinary.com/hngojet/image/upload/v1573196562/hngojet/profile_img/no-photo_mpbetk.png';
        return $imagePath;
    }

    public function user()
    {
        //
        return $this->belongsTo('App\User');
    }
}
