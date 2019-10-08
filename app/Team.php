<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{

    protected $fillable = [
        'team_name', 'max_team_mates', 'team_description', 'team_lead'
    ];

    /**
     * The members of the team
     */
    public function members()
    {
        return $this->belongsToMany('App\User')->withTimestamps();
    }

    public function team_leader()
    {
        return $this->belongsTo('App\User', 'team_lead');
    }
}
