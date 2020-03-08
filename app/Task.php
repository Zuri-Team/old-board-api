<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = ['track_id', 'title', 'body', 'deadline', 'is_active', 'status'];
    // protected $with = ['track'];
    protected $dates = ['deadline'];


    public function users(){
        return $this->belongsToMany('App\User');
    }

    public function tasks(){
        return $this->hasMany('App\TaskSubmission');
    }

    public function track(){
        return $this->belongsTo('App\Track', 'track_id');
    }
}