<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TrackRequest extends Model
{

    protected $fillable = ['user_id', 'track_id', 'reason', 'action'];
    public function user()
    {
        return $this->belongsTo('App\User', 'user_id');
    }

    public function track()
    {
        return $this->belongsTo('App\Track', 'track_id');
    }
}
