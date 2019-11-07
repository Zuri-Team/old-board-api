<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{

    protected $fillable = [
        'team_name', 'max_team_mates', 'team_description', 'team_lead', 'invite_link',
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
    
    public function generateInvitationToken() {
        $this->invite_link = substr(md5(rand(0, 9) . $this->team_name . time()), 0, 32);
    }
}
