<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = ['track_id', 'title', 'body', 'deadline', 'is_active'];


    public function users(){
        return $this->belongsToMany('App\User');
    }

    public function track(){
        return $this->belongsTo('App\Tracks, 'track_id');
    }
}
