<?php

namespace App\Http\Controllers;

use Auth;
use App\User;
use Illuminate\Http\Request;
use App\Http\Classes\ResponseTrait;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

class StatusController extends Controller
{
    //
    use ResponseTrait;

    public function status()
    {
        $users = User::all();
        $List = []; 
        foreach ($users as $user) {

            $List[] = [
                'id'=> $user->id,
                'username' => $user->username, 
                'profile_img' => $user->profile->profile_img,
                'status' => $user->status()
                ] ;

           
        }

        return $this->sendSuccess( $List, 'Users Status fetched', 200);
       
    }
}
