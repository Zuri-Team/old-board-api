<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{

    protected $fillable = ['track_id', 'name', 'description', 'difficulty'];

    public function interns(){
        return $this->belongsToMany('App\User');
    }
}
