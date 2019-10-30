<?php
namespace App\Http\Classes;

use App\User;
use App\Activity;
use App\Jobs\LogActivityJob;
use Carbon\Carbon;

trait ActivityTrait {

    protected function logInternActivity($message){
        return $this->addActivity($message, 'intern');
    }

    protected function logAdminActivity($message){
        return $this->addActivity($message, 'admin');
    }

    private function addActivity($message, $type){

        $job = (new LogActivityJob($message, $type))
                ->delay(Carbon::now()->addSeconds(3));

        dispatch($job);
    }

}