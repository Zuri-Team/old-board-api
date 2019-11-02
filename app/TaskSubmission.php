<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TaskSubmission extends Model
{
    protected $fillable = ['user_id', 'task_id', 'submission_link'];

    public function tasks()
    {
        return $this->belongsTo('App\Task');
    }
    
    public function users()
    {
        return $this->hasMany('App\User');
    }
}
