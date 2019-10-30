<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Craftyx\SlackApi\Facades\SlackChat;
use Craftyx\SlackApi\Facades\SlackUser;
use Craftyx\SlackApi\Facades\SlackGroup;
use Craftyx\SlackApi\Facades\SlackTeam;
use Craftyx\SlackApi\Facades\SlackChannel;

class Slack extends Model
{
    
    public static function removeFromGroup($user, $group_name)
    {
        $group_id = self::getGroupIDFromName($group_name);
        return SlackGroup::kick($group_id, $user);
    }

    public static function addToGroup($user, $group_name)
    {
        $group_id = self::getGroupIDFromName($group_name);
        return SlackGroup::invite($group_id, $user);
    }


    private static function getGroupIDFromName($stage_name){
        
        $groups = SlackGroup::lists(true);
                    
        foreach($groups->groups as $group){
            if($group->name == $stage_name){
                return $group->id;
                break;
            }
        }
    }
}