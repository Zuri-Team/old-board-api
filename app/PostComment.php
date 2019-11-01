<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PostComment extends Model
{
    protected $fillable = ['comment', 'post_id', 'user_id'];

    public function user()
    {
        return $this->belongsTo('App\User');
    }
}
