<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{

    protected $fillable = ['track_id', 'name', 'description'];
    
    public function users(){
        return $this->belongsToMany('App\User');
    }
}
