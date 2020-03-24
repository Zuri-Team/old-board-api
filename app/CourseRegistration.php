<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\softDeletes;

class CourseRegistration extends Model
{

    use softDeletes;

    protected $fillable = ['user_id', 'course_id', 'approved_by', 'updated_by', 'deleted_by'];

    protected $dates = ['deleted_at'];


    public function track(){
        return $this->belongsTo('App\Course', 'course_id');
    }
}
