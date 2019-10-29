<?php
namespace App\Http\Classes;

use App\User;
use App\Activity;

trait ActivityTrait {

    protected function logInternActivity($user_id, $message){
        return addActivity('intern', $user_id, $message);
    }

    protected function logAdminActivity($user_id, $message){
        return addActivity('admin', $user_id, $message);
    }

    private function addActivity($type, $user_id, $message = ''){

        $res = Activity::create([
            'type' => $type,
            'user_id' => $user_id,
            'message' => $message
        ]);

        return $res;
    }

}