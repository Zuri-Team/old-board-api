<?php

namespace App\Http\Controllers;
use App\Task;
use App\User;
use App\TaskSubmission;

use Illuminate\Http\Request;

class LeaderboardController extends Controller
{
    public function viewAll($week = 0){
        $users = User::where('role', '=', 'intern')
                ->with('tracks')
                ->paginate(15);

        $res = [];

        foreach($users->data as $user){
            $user['total_score'] = $user->totalScoreForWeek();
            $track_name = '';
            foreach($user->tracks as $sub){
                $track_name  = $track_name . "". $sub->track_name. ", ";
            }
            $user['all_tracks'] = $track_name;
            $user['tracks'] = [];
            array_push($res, $user);
        }
        return $res;
    }
}
