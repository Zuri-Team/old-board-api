<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['title', 'description', 'created_by', 'updated_by'];

    public function posts()
    {
        return $this->hasMany('App\Post');
    }
}