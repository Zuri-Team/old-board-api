<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    protected $fillable = [
        'user_id', 'type', 'message'
    ];

    public function user()
    {
        return $this->belongsTo('App\User');
    }
}
