<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Classes\ResponseTrait;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use App\Team;
use App\Post;
use App\User;
use App\Track;
use App\TrackUser;
use DB;

class StatsController extends Controller
{
    use ResponseTrait;

    public function dashboard(){
        $interns = User::role('intern')->count();
        $posts = Post::count();
        $teams = Team::count();
        
        $data = [];

        // if ($posts && $interns && $teams) {

        $data['total_posts'] = $posts;
        $data['total_teams'] = $teams;
        $data['total_interns'] = $interns;

            return $this->sendSuccess($data, 'Statistics for Dashboard', 200);
        // }
        return $this->sendError('Internal server error.', 500, []);
    }

    public function summary(){
        $id = 1;
        $tracks = TrackUser::join('tracks', 'tracks.id', 'track_users.track_id')
                        ->join('users', 'users.id', 'track_users.user_id')
                        ->groupBy('tracks.track_name')
                        ->select( 'tracks.track_name AS series', DB::raw('count(users.id) AS value') )
                        ->get();
        foreach($tracks as $track){ $track->id = $id; $track->group = 'Tracks'; $id+=1;}

        $id = 1;
        $genders = User::groupBy('gender')->select( DB::raw('count(id) AS value'), 'gender AS series' )->get();
        foreach($genders as $gender){ $gender->id = $id; $gender->group = 'Gender'; $id+=1;}
        
        $id = 1;
        $stages = User::groupBy('active', 'stage')->select( DB::raw('count(id) AS value'), 'stage AS group', 'active AS series' )->get();
        foreach($stages as $track){ $track->id = $id; $track->group = 'Stage '.$track->group; $track->series = $track->series == 1 ? 'Active' : 'Inactive'; $id+=1;}
        

        return $this->SUCCESS('Data Retrieved', ['stages'=>$stages, 'gender'=>$genders, 'tracks'=>$tracks]);
    }
}
