<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TrackUser extends Model
{
    protected $table = 'track_users';

    protected $fillable = ['track_id', 'user_id'];

    public function tracks()
    {
        return $this->belongsToMany('App\Track', 'track_id')->withTimestamps();
    }

    public function users()
    {
        return $this->belongsToMany('App\User', 'user_id')->withTimestamps();
    }
}