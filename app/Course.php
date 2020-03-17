<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $fillable = ['course_name', 'track_id'];


    public function track(){
        return $this->belongsTo('App\Track', 'track_id');
    }

}
