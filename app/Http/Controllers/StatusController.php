<?php

namespace App\Http\Controllers;


use App\User;
use Illuminate\Http\Request;
use App\Http\Classes\ResponseTrait;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

class StatusController extends Controller
{
    //
    use ResponseTrait;

    public function status(User $user)
    {
        if($user->status())
        {
            $online = true;
            return $this->sendSuccess($online, 'User is online', 200);
        }else{
            $online = false;
            return $this->sendSuccess($online, 'User is offline', 200);
        }
    }
}
