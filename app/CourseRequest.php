<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CourseRequest extends Model
{
    protected $fillable = ['user_id', 'course_id', 'reason', 'action'];
    public function user()
    {
        return $this->belongsTo('App\User', 'user_id');
    }

    public function course()
    {
        return $this->belongsTo('App\Track', 'course_id');
    }
}
