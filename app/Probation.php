<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Probation extends Model
{
    use SoftDeletes;
    
    public function user(){
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function probator(){
        return $this->belongsTo(User::class, 'probated_by');
    }
}
