<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CourseRegistration extends Model
{
    protected $fillable = ['user_id', 'course_id', 'approved_by', 'updated_by'];


    public function track(){
        return $this->belongsTo('App\Course', 'course_id');
    }
}
