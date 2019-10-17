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
}
