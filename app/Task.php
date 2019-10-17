<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = ['track_id', 'title', 'body', 'deadline', 'is_active'];


    public function users(){
        return $this->belongsToMany('App\User');
    }

    public function tracks(){
        return $this->hasMany('App\Tracks');
    }
}
