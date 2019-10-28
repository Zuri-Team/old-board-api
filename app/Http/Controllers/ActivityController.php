<?php

namespace App\Http\Controllers;

use App\User;
use App\Activitiy;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Classes\ResponseTrait;
use Illuminate\Support\Facades\Log;

class ActivityController extends Controller
{
    use ResponseTrait;

    public function get_all_activities(){

        $activities = Activity::orderBy('created_at', 'desc')->get();
        if ($activities) {

            return $this->sendSuccess($activities, 'All Activity Logs', 200);
        }
        return $this->sendError('Internal server error.', 500, []);
    }

    public function get_all_intern_activities(){
        $activities = Activity::where('type', 'intern')->orderBy('created_at', 'desc')->get();
        if ($activities) {

            return $this->sendSuccess($activities, 'All Intern Activity Logs', 200);
        }
        return $this->sendError('Internal server error.', 500, []);
    }

    public function get_all_admin_activities(){
        $activities = Activity::where('type', 'admin')->orderBy('created_at', 'desc')->get();
        if ($activities) {

            return $this->sendSuccess($activities, 'All Admin Activity Logs', 200);
        }
        return $this->sendError('Internal server error.', 500, []);
    }

    public function search_all_logs($query){
        $activities = Activity::where('message', 'LIKE', "%{$query}%")->orderBy('created_at', 'desc');
        $results = $activities->get();

        if ($results) {
            $results['result_count'] = $activities->count();
            return $this->sendSuccess($results, 'Activity Logs seach results', 200);
        }
        return $this->sendError('Internal server error.', 500, []);
    }

    public function search_all_intern_logs($query){
        $activities = Activity::where('type', 'intern')->where('message', 'LIKE', "%{$query}%")->orderBy('created_at', 'desc');
         $results = $activities->get();

        if ($results) {
            $results['result_count'] = $activities->count();
        
            return $this->sendSuccess($results, 'Activity Logs seach results', 200);
        }
        return $this->sendError('Internal server error.', 500, []);
    }

    public function search_all_admin_logs($query){
        $activities = Activity::where('type', 'admin')->where('message', 'LIKE', "%{$query}%")->orderBy('created_at', 'desc');
         $results = $activities->get();

        if ($results) {
            $results['result_count'] = $activities->count();
        
            return $this->sendSuccess($results, 'Activity Logs seach results', 200);
        }
        return $this->sendError('Internal server error.', 500, []);
    }
}
