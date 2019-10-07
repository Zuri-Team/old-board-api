<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $fillable = ['track_id', 'title', 'body', 'deadline', 'is_active'];


    public function users(){
        return $this->hasMany('App\User');
    }
}
