<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TrackTask extends Model
{
    protected $fillable = ['track_id', 'task_id'];
}
