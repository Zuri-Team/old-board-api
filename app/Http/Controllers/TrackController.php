<?php

namespace App\Http\Controllers;

use App\User;
use App\Track;
use App\TrackUser;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Classes\ResponseTrait;

use Illuminate\Support\Facades\Auth;
use App\Notifications\TrackNotifications;
use Illuminate\Support\Facades\Validator;
use App\Http\Classes\ActivityTrait;

class TrackController extends Controller
{

    use ResponseTrait;
    use ActivityTrait;
    /**
     * Apply middleware to each CRUD which checks if logged in user can perform
     * the crud roles or return a failure notice
     * 
     * Check and use Requests class to validate requests;
     */
    public function create_track(Request $request){
        if(!Auth::user()->hasAnyRole(['admin', 'superadmin'])){
            return $this->ERROR('You dont have the permission to perform this action');
        }

        // $validator = Validator::make($request->all(), [
        //     'track_id' => 'bail|required|integer',
        //     'user_id' => 'required|integer',
        // ]);

        // if ($validator->fails()) {
        //     return $this->sendError('', 400, $validator->errors());
        // }
        
        $track = $request->all();
        $track['user_id'] = Auth::user()->id;
        
        try{
            if(Track::create($track)){
                logger('Track creation successfull' . $track['track_name']);
                $track_name = $track['track_name'];
                $this->logAdminActivity("created " . $track_name . " track");
                return $this->SUCCESS('Track creation successfull', $track);
            }           
        }catch(\Throwable $e){
            logger('Track creation failed' . $track['track_name']);
            return $this->ERROR('Track creation failed', $e);
        }
    }
    
    public function edit_track(Request $request){
        if(!Auth::user()->hasAnyRole(['admin', 'superadmin'])){
            return $this->ERROR('You dont have the permission to perform this action');
        }
        $track = Track::find($request->track_id);
        try{
            if ($track){
                $track->track_name = isset($request->track_name) ? $request->track_name : $track->track_name;
                $track->track_description = isset($request->track_description) ? $request->track_description : $track->track_description;
                if($track->save()){
                    logger('Track modification successfull' . $track);
                    $this->logAdminActivity("modified " . $track->track_name . " track");
                    return $this->SUCCESS('Track modification successfull', $track);
                }
            }else return $this->ERROR('Track not found');
        }catch(\Throwable $e){
            logger('Track modification failed' . $track);
            return $this->ERROR('Track modification failed', $e);
        }
    }

    public function delete_track(Request $request){
        if(!Auth::user()->hasAnyRole(['admin', 'superadmin'])){
            return $this->ERROR('You dont have the permission to perform this action');
        }
        try{
            $track = Track::find($request->track_id);
            if ($track){
                $track->delete();
                logger('Track deleted' . $track);
                $this->logAdminActivity("deleted " . $track->track_name . " track");
                return $this->SUCCESS('Track deleted', $track);
            } else return $this->ERROR('Track not found');
        }catch(\Throwable $e){
            logger('Track creation failed' . $track);
            return $this->ERROR('Track creation failed', $e);
        }
    }

    public function join_track(Request $request){
        $request = $request->all();
        $request['user_id'] = Auth::user()->id;
        $track = Track::find($request['track_id']);
        try{
            $has_joined = TrackUser::where($request)->first();
            $this->logAdminActivity("joined " . $track->track_name . " track");
            if (!$track) return $this->ERROR('Track does not exist');
            if($has_joined) return $this->ERROR('You have already joined this track');
            
            TrackUser::create($request);
            logger(Auth::user()->email . ' joined ' . $track->track_name);
            return $this->SUCCESS('Track joined', $track);

        }catch(\Throwable $e){
            logger('Track joining failed' . $track);
            return $this->ERROR('Track joining failed', $e);
        }
    }

    /**
     * @param int track_id
     * @param int user_id
     * @return Response
     */
    public function add_user_to_track(Request $request){
        if(!Auth::user()->hasAnyRole(['admin', 'superadmin'])){
            return $this->ERROR('You dont have the permission to perform this action');
        }

        $validator = Validator::make($request->all(), [
            'track_id' => 'bail|required|integer',
            'user_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->sendError('', 400, $validator->errors());
        }

        $request = $request->all();
        $user = User::find($request['user_id']);
        $track = Track::find($request['track_id']);
        $has_joined = TrackUser::where($request)->first();
        try{
            if(!$user) return $this->ERROR('User does not exist');
            if (!$track) return $this->ERROR('Track does not exist');
            if($has_joined) return $this->ERROR('User already joined this track');
            
            TrackUser::create($request);

            //SEND NOTIFICATION HERE
            $message = [
                'message'=>"You have been added to a new track.",
            ];
            $user->notify(new TrackNotifications($message));
            $this->logAdminActivity('added '. $user->email . '  to ' . $track->track_name . ' track');

            return $this->SUCCESS('Track joined', $track);

        }catch(\Throwable $e){
            logger('Track joining failed' . $track);
            return $this->ERROR('Track joining failed', $e);
        }
    }

    public function remove_user_from_track(Request $request){
        if(!Auth::user()->hasAnyRole(['admin', 'superadmin'])){
            return $this->ERROR('You dont have the permission to perform this action');
        }
        $request = $request->all();
        $user = User::find($request['user_id']);
        $track = TrackUser::where($request)->first();
        try{
            if(!$user) return $this->ERROR('User does not exist');
            if (!$track) return $this->ERROR('User not associated with selected track');
            $track->delete();
            logger(Auth::user()->email . ' removed ' . $user->email . ' from a track');

            $message = [
                'message'=>`You have been removed from ${$track->track_name} track.`,
            ];
            $user->notify(new TrackNotifications($message));
            $this->logAdminActivity('removed '. $user->email . '  from ' . $track->track_name . ' track');
            
            return $this->SUCCESS('User successfully removed from track');
        }catch(\Throwable $e){
            logger('Track removal failed' . $track);
            return $this->ERROR('Track removal failed', $e);
        }
    }

    public function get_all_tracks(){
        if(!Auth::user()->hasAnyRole(['admin', 'superadmin'])){
            return $this->ERROR('You dont have the permission to perform this action');
        }
        // $this->RESTRICTED_TO('admin');

        $tracks = Track::orderBy('created_at', 'desc')->paginate(10);
        logger('Tracks retrieved');        
        if(!$tracks) return $this->ERROR('No track found');
        return $this->SUCCESS('Tracks retrieved', $tracks);
    }

    //get all tracks
    public function all(){
        $tracks = Track::orderBy('created_at', 'desc')->paginate(10);
        logger('Tracks retrieved');        
        if(!$tracks) return $this->ERROR('No track found');
        return $this->SUCCESS('Tracks retrieved', $tracks);
    }

    public function get_track_by_id(int $id){
        if(!isset($id)) return $this->ERROR('No track specified');
        $track = Track::find($id);
        if(!$track) return $this->ERROR('Track not found', [], 404);
        logger('Track retrieved' . $track);        
        return $this->SUCCESS('Track retrieved', $track);
    }
    
    public function get_all_users_in_track(int $track_id){
        if(!Auth::user()->hasAnyRole(['admin', 'superadmin'])){
            return $this->ERROR('You dont have the permission to perform this action');
        }
        if(!isset($track_id)) return $this->ERROR('No track specified');
        $track = Track::find($track_id)->users()->get();
        if(!$track) return $this->ERROR('Track not found', [], 404);
        logger('Track retrieved' . $track);        
        return $this->SUCCESS('Track retrieved', $track);
    }
}