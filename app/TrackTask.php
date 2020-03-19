<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TrackTask extends Model
{
    protected $fillable = ['track_id', 'task_id'];


    public function tasks(){
        return $this->hasMany('App\TaskSubmission');
    }

    public function tracks(){
        return $this->hasMany('App\Track', 'track_id');
    }
}
