<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TrackUser extends Model
{
    protected $table = "track_user";
    protected $fillable = ['track_id', 'user_id'];
}
