<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CourseUser extends Model
{
    protected $table = "course_user";
    protected $fillable = [
        'course_id', 'user_id',
    ];

    public function courses()
    {
        return $this->belongsToMany('App\Course', 'course_id')->withTimestamps();
    }

    public function users()
    {
        return $this->belongsToMany('App\User', 'user_id')->withTimestamps();
    }

}
