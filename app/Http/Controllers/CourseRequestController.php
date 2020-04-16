<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Classes\ResponseTrait;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use App\User;
use App\CourseRequest;
use App\Course;
use App\CourseUser;
use Carbon\Carbon;
// use App\Notifications\CourseNotifications;
use App\Http\Classes\ActivityTrait;

class CourseRequestController extends Controller
{
    use ResponseTrait;
    use ActivityTrait;

    public function all(){
        $allRequests = CourseRequest::where('approved', false)->orderBy('created_at', 'desc')->with('user')->with('course')->get();
        
        if ($allRequests) {
            return $this->sendSuccess($allRequests, 'Fetched all course requests', 200);
        }
        return $this->sendError('Internal server error.', 500, []);
    }

    public function get_request_count(){
        $count = CourseRequest::where('approved', false)->count();
        $data['requests_count'] = $count;
            return $this->sendSuccess($data, 'get course requests count', 200);
    }

    public function request(Request $request){
        $validator = Validator::make($request->all(), [
            'action' => 'bail|required|in:add,remove',
            'course_id' => 'required|integer',
            'reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('', 400, $validator->errors());
        }

        $user_id = auth()->id();
        $user = auth()->user();

        $checkCourse = Course::find($request->course_id);
        if(!$checkCourse) return $this->sendError('Course not found', 400, []);

        $userCourses = $user->courses;
        foreach($user->courses as $userCourse){
            if($userCourse->id == $request->course_id && $request->action == 'add'){
                return $this->sendError('You are already on this Course!', 400, []);
            }
        }

        // $check = CourseRequest::where('user_id', $user_id)->where('course_id', $request->course_id)->where('created_at', '>', $timeAfter)->first();
        $checkRequest = CourseRequest::where('user_id', $user_id)->where('course_id', $request->course_id)->where('approved', false)->first();
        if($checkRequest){
            return $this->sendError('You already requested for Modification on this Course', 400, []);
        }
          
        // if($check){
        //     $date_created = $check->created_at->addDays(1);
        //     if($date_created < Carbon::now()) return $this->sendError('You already requested for Modification on this Course. Please wait in 24Hours to make another change', 400, []);
        // }

        try {
            $trackRequest = CourseRequest::create([
                'user_id' => $user_id,
                'course_id' => $request->course_id,
                'reason' => $request->reason,
		        'action' => $request->action
            ]);


            if ($trackRequest) {
                $act = $request->action == 'add' ? 'ADDED to' : 'REMOVED from';
                $this->logInternActivity($user->firstname .' '. $user->lastname . ' requested to be '. $act . ' '. $checkCourse->name. ' Course');

                    return $this->sendSuccess($trackRequest, 'Request sent successfully.', 200);
            } else {
                return $this->sendError('Request could not be sent', 404, []);
            }

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError('Internal server error.', 500, []);
        }

    }

    public function accept($id){
        
        $trackRequest = CourseRequest::find($id);
        if(!$trackRequest) return $this->sendError('Course request not found', 400, []);

        $user = User::find($trackRequest->user_id);
        $track = Course::find($trackRequest->course_id);
        $has_joined = CourseUser::where('user_id', $trackRequest->user_id)->where('course_id', $trackRequest->course_id)->first();
        try{
            if(!$user) return $this->sendError('User does not exist', 400, []);
            if(!$track) return $this->sendError('Course does not exist', 400, []);

            if($trackRequest->action == 'add'){
                if($has_joined) return $this->sendError('User already joined this track', 400, []);
                CourseUser::create([
                    'user_id' => $trackRequest->user_id,
                    'course_id' => $trackRequest->course_id
                ]);

                $trackRequest->update([
                    'approved' => true
                ]);

                //SEND NOTIFICATION HERE
                $message = [
                    'message'=>"You have been added to a new track.",
                ];
                // $user->notify(new CourseNotifications($message));
                $this->logAdminActivity('added '. $user->email . '  to ' . $track->name . ' track');
            }else if($trackRequest->action == 'remove'){
                if(!$has_joined) return $this->sendError('User is not on this track', 400, []);
                $has_joined->delete();

                $trackRequest->update([
                    'approved' => true
                ]);

                //SEND NOTIFICATION HERE
                $message = [
                    'message'=>"You have been removed ". $track->name ." track.",
                ];
                // $user->notify(new CourseNotifications($message));
                $this->logAdminActivity('removed '. $user->email . '  from ' . $track->name . ' track');

            } 

            $trackRequest->approved = 1;
            $trackRequest->save();

                return $this->sendSuccess($track, 'Request accepted successfully successfully.', 200);

        }catch(\Throwable $e){
            return $this->sendError('Accepting request failed: '. $e, 500, []);
        }

    }

    public function reject($id){

        try{
        $trackReq = CourseRequest::find($id);
        //$res = $trackReq->delete();

        if($trackReq->delete()){
            return $this->sendSuccess($trackReq, 'Request rejected successfully successfully.', 200);
        }else{
            return $this->sendError('Rejecting request failed: ', 500, []);
        }
        }catch(\Throwable $e){
            return $this->sendError('Accepting request failed: '. $e, 500, []);
        }
    }

    public function deleteAll(){
        CourseRequest::truncate();
    }

    

}