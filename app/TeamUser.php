<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TeamUser extends Model
{
    protected $table = "team_user";
    protected $fillable = [
        'team_id', 'user_id',
    ];
}
