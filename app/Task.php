<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

use App\TrackTask;

class Task extends Model
{
    protected $fillable = ['title', 'body', 'deadline', 'is_active', 'status'];
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

    public static function getTracks($tracks, $task_id){

        // Loop through tracks
        foreach ($tracks as $track) {

            TrackTask::create([
                'task_id' => $task_id,
                'track_id' => $track
            ]);
        }

    }
}