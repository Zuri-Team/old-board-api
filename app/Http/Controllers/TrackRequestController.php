<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Classes\ResponseTrait;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use App\User;
use App\TrackRequest;
use App\Track;
use App\TrackUser;
use Carbon\Carbon;
use App\Notifications\TrackNotifications;
use App\Http\Classes\ActivityTrait;

class TrackRequestController extends Controller
{
    use ResponseTrait;
    use ActivityTrait;

    public function all(){
        $allRequests = TrackRequest::where('approved', false)->orderBy('created_at', 'desc')->with('user')->with('track')->get();
        
        if ($allRequests) {

            return $this->sendSuccess($allRequests, 'Fetched all track requests', 200);
        }
        return $this->sendError('Internal server error.', 500, []);
    }

    public function get_request_count(){
        $count = TrackRequest::where('approved', false)->count();
        $data['requests_count'] = $count;
            return $this->sendSuccess($data, 'get track requests count', 200);
    }

    public function request(Request $request){
        $validator = Validator::make($request->all(), [
            'action' => 'bail|required|in:add,remove',
            'track_id' => 'required|integer',
            'reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->sendError('', 400, $validator->errors());
        }

        $user_id = auth()->id();
        $user = auth()->user();

        $checkTrack = Track::find($request->track_id);
        if(!$checkTrack) return $this->sendError('Track not found', 400, []);

        $userTracks = $user->tracks;
        foreach($user->tracks as $userTrack){
            if($userTrack->id == $request->track_id && $request->action == 'add'){
                return $this->sendError('You are already on this Track!', 400, []);
            }
        }

        // $check = TrackRequest::where('user_id', $user_id)->where('track_id', $request->track_id)->where('created_at', '>', $timeAfter)->first();
        $checkRequest = TrackRequest::where('user_id', $user_id)->where('track_id', $request->track_id)->first();
        if($checkRequest){
            return $this->sendError('You already requested for Modification on this Track', 400, []);
        }
          
        // if($check){
        //     $date_created = $check->created_at->addDays(1);
        //     if($date_created < Carbon::now()) return $this->sendError('You already requested for Modification on this Track. Please wait in 24Hours to make another change', 400, []);
        // }

        try {
            $trackRequest = TrackRequest::create([
                'user_id' => $user_id,
                'track_id' => $request->track_id,
                'reason' => $request->reason,
		        'action' => $request->action
            ]);


            if ($trackRequest) {
                $act = $request->action == 'add' ? 'ADDED to' : 'REMOVED from';
                $this->logInternActivity($user->firstname .' '. $user->lastname . ' requested to be '. $act . ' '. $checkTrack->track_name. ' Track');

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
        
        $trackRequest = TrackRequest::find($id);
        if(!$trackRequest) return $this->sendError('Track request not found', 400, []);

        $user = User::find($trackRequest->user_id);
        $track = Track::find($trackRequest->track_id);
        $has_joined = TrackUser::where('user_id', $trackRequest->user_id)->where('track_id', $trackRequest->track_id)->first();
        try{
            if(!$user) return $this->sendError('User does not exist', 400, []);
            if(!$track) return $this->sendError('Track does not exist', 400, []);

            if($trackRequest->action == 'add'){
                if($has_joined) return $this->sendError('User already joined this track', 400, []);
                TrackUser::create([
                    'user_id' => $trackRequest->user_id,
                    'track_id' => $trackRequest->track_id
                ]);

                $trackRequest->update([
                    'approved' => true
                ]);

                //SEND NOTIFICATION HERE
                $message = [
                    'message'=>"You have been added to a new track.",
                ];
                $user->notify(new TrackNotifications($message));
                $this->logAdminActivity('added '. $user->email . '  to ' . $track->track_name . ' track');
            }else if($trackRequest->action == 'remove'){
                if(!$has_joined) return $this->sendError('User is not on this track', 400, []);
                $has_joined->delete();

                $trackRequest->update([
                    'approved' => true
                ]);

                //SEND NOTIFICATION HERE
                $message = [
                    'message'=>"You have been removed ". $track->track_name ." track.",
                ];
                $user->notify(new TrackNotifications($message));
                $this->logAdminActivity('removed '. $user->email . '  from ' . $track->track_name . ' track');

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
        $trackReq = TrackRequest::find($id);
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

    

}