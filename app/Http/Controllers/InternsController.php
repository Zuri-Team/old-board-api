<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\User;

class InternsController extends Controller
{
    public function get_all_interns(){
        $interns = User::role('intern')->get();
        
        if ($interns) {

            return $this->sendSuccess($interns, 'Fetch all intenrs', 200);
        }
        return $this->sendError('Internal server error.', 500, []);
    }
}
