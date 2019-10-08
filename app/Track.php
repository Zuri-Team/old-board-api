<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Track extends Model
{
    protected $fillable = ['track_name', 'track_description'];

    public function users(){
        return $this->belongsToMany('App\User');
    }
}
