<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PostComment extends Model
{
    protected $fillable = ['comment'];

    public function user()
    {
        return $this->belongsTo('App\User');
    }
}
