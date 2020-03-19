<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = ['title', 'body', 'deadline', 'is_active', 'status', 'course_id'];
    // protected $with = ['track'];
    // protected $dates = ['deadline'];


    public function users(){
        return $this->belongsToMany('App\User');
    }

    public function tasks(){
        return $this->hasMany('App\TaskSubmission');
    }

    public function track(){
        return $this->belongsTo('App\Track', 'track_id');
    }

    public function course(){
        return $this->belongsTo('App\Course', 'course_id');
    }
}