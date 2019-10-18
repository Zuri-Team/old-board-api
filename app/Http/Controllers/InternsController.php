<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Classes\ResponseTrait;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use App\User;

class InternsController extends Controller
{

    use ResponseTrait;

    public function get_all_interns(){
        //$interns = User::role('intern')->with('teams')->get();
         $interns = User::all()->get();
        
        if ($interns) {
            return $this->sendSuccess($interns, 'Fetched all interns', 200);
        }
        return $this->sendError('Internal server error.', 500, []);
    }
}
