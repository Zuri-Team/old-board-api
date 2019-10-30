<?php

namespace App\Http\Controllers;

use App\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Classes\ResponseTrait;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

use App\Notifications\UserNotifications;
use Illuminate\Support\Facades\Validator;
use Craftyx\SlackApi\Facades\SlackGroup;

class UserProfileController extends Controller
{

    use ResponseTrait;

    public function __construct(){
        $this->middleware('role:superadmin', ['except' => ['index']]);
    }

    public function index($user) {
//         $getUser = $user->with('teams')->with('tracks')->get();
        $getUser = User::where('id', $user)->with('teams')->with('tracks')->first();

        if ($getUser) {
            return $this->sendSuccess($getUser, 'User profile info fetched', 200);
        }
        return $this->sendError('Internal server error.', 500, []);
    }

    public function promote(User $user){

        try {
        // //$user = User::find($id)->first();

            if($user){

                $currentStage = $user->stage;
                $nextStage = $currentStage + 1;

                if($nextStage > 10){
                    return $this->sendError('Intern cannot exceed Stage 10', 404, []);
                }

                $user->stage = $nextStage;
                $result = $user->save();

                if($result){
                    
                    $slack_id =  $user->slack_id;
                    $pre_stage = 'stage'.$user->stage;
                    $next_stage = 'stage'.$nextStage;
                    
                    Slack::removeFromGroup($slack_id, $pre_stage);
                    Slack::addToGroup($slack_id, $next_stage);

                    // SEND NOTIFICATION HERE
                    $message = [
                        'message'=>'You have been promoted to stage '.$nextStage,
                    ];
                    
                    $user->notify(new UserNotifications($message));

                    return $this->sendSuccess($user, 'Intern successfully promoted to stage '.$nextStage, 200);
                }

            } else {
                return $this->sendError('User not found', 404, []);
            }

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError('Internal server error.', 500, []);
        }
    }

    public function demote(User $user){

        try{
        //$user = User::find($id)->first();

            if($user){

                $currentStage = $user->stage;
                $lastStage = $currentStage - 1;

                if($lastStage < 1){
                    return $this->sendError('Intern cannot go below stage 1', 404, []);
                }

                $user->stage = $lastStage;
                $result = $user->save();

                if($result){

                    // SEND NOTIFICATION HERE
                    $message = [
                        'message'=>'You have been demoted to stage '.$lastStage,
                    ];
                    
                    $user->notify(new UserNotifications($message));
                    
                    return $this->sendSuccess($user, 'Intern successfully demoted to stage '.$lastStage, 200);
                }

            } else {
                return $this->sendError('User not found', 404, []);
            }

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError('Internal server error.', 500, []);
        }
    }

    public function update_stage(Request $request, User $user){
        $validator = Validator::make($request->all(), [
            'stage' => 'required|integer:min:1|max:10',
        ]);

        if ($validator->fails()) {
            return $this->sendError('', 400, $validator->errors());
        }

        try {

        //$user = User::find($id)->first();

            if($user){

                $stage = $request->stage;

                if($stage < 1 || $stage > 10 ){
                    return $this->sendError('Stage can only be between 1 - 10', 404, []);
                }

                $user->stage = (int)$stage;
                $result = $user->save();

                if($result){
                    return $this->sendSuccess($user, 'Intern stage successfully updated ', 200);
                }

            } else {
                return $this->sendError('User not found', 404, []);
            }

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError('Internal server error.', 500, []);
        }
    }

    public function deactivate(User $user){

        try {
            //$user = User::find($id)->first();

            if($user){

                $status = $user->active;

                if(!$status){
                    return $this->sendError('Intern is already Deactivated.', 404, []);
                }

                $user->active = false;
                $result = $user->save();

                if($result){
                    return $this->sendSuccess($user, 'Intern successfully deactivated', 200);
                }
            } else {
                return $this->sendError('User not found', 404, []);
            }

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError('Internal server error.', 500, []);
        }
    }

    public function activate(User $user){
        try{
            //$user = User::find($id)->first();

            if($user){

                $status = $user->active;

                if($status){
                    return $this->sendError('Intern is already Active.', 404, []);
                }

                $user->active = true;
                $result = $user->save();

                if($result){
                    return $this->sendSuccess($user, 'Intern successfully activated', 200);
                }

            } else {
                return $this->sendError('User not found', 404, []);
            }

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError('Internal server error.', 500, []);
        }
    }

    public function make_admin(User $user){

        try{
            //$user = User::find($id)->first();

            if($user){

                $userRole = $user->role;

                if($user->hasRole('superadmin')){
                    return $this->sendError('Cannot change role of a Superadmin', 404, []);
                }

                if($user->hasRole('admin')){
                    return $this->sendError('User already an admin', 404, []);
                }

                //remove role
                if(!$user->removeRole('intern')){
                    return $this->sendError('Cannot make user an admin', 404, []);
                }

                //assign role of admin to user
                if($user->assignRole('admin')){
                    $user->role = 'admin';
                    $user->save();

                    $message = [
                        'message'=>'You have been promoted to admin level ',
                    ];
                    
                    $user->notify(new UserNotifications($message));
                    
                    return $this->sendSuccess($user, 'User successfully promoted to admin', 200);
                }

            } else {
                return $this->sendError('User not found', 404, []);
            }

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError('Internal server error.', 500, []);
        }
    }

    public function remove_admin(User $user){

        try{
            //$user = User::find($id)->first();

            if($user){

                $userRole = $user->role;

                if($user->hasRole('superadmin')){
                    return $this->sendError('Cannot change role of a Superadmin', 404, []);
                }

                if($user->hasRole('intern')){
                    return $this->sendError('User is not an admin', 404, []);
                }

                //remove role
                if(!$user->removeRole('admin')){
                    return $this->sendError('Cannot remove user as an admin', 404, []);
                }

                //assign role of intern to user
                if($user->assignRole('intern')){
                    $user->role = 'intern';
                    $user->save();
                    return $this->sendSuccess($user, 'User now an intern', 200);
                }

            } else {
                return $this->sendError('User not found', 404, []);
            }

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError('Internal server error.', 500, []);
        }
    }

    public function destroy(User $user){
        

         try {

            // $user = User::find($id);
            if ($user) {

                if($user->hasAnyRole(['superadmin', 'admin'])){
                    return $this->sendError('Cannot delete an Admin', 404, []);
                }

                if ($user->delete()) {
                    return $this->sendSuccess($post, 'User has been deleted successfully.', 200);
                }
            } else {
                return $this->sendError('User not found', 404, []);
            }

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError('Internal server error.', 500, []);
        }
    }
}
