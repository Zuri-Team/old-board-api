<?php

namespace App\Http\Controllers;

use App\Http\Classes\ResponseTrait;
use App\Notifications\UserNotifications;
use App\Slack;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class UserProfileController extends Controller
{

    use ResponseTrait;

    public function __construct()
    {
        // $this->middleware(['role:superadmin'], ['except' => ['index']]);
    }

    public function index($user)
    {
//         $getUser = $user->with('teams')->with('tracks')->with('profile')->get();
        $getUser = User::where('id', $user)->with('teams')->with('tracks')->first();
        $getUser['profile_img'] = 'https://res.cloudinary.com/hngojet/image/upload/v1573196562/hngojet/profile_img/no-photo_mpbetk.png';

        if ($getUser) {
            return $this->sendSuccess($getUser, 'User profile info fetched', 200);
        }
        return $this->sendError('Internal server error.', 500, []);
    }

    //Get or retieve all users tracks
    public function user_tracks($user)
    {

        $getUser = User::where('id', $user)->with('tracks')->get();

        if ($getUser) {
            return $this->sendSuccess($getUser, 'User profile info fetched', 200);
        }
        return $this->sendError('Internal server error.', 500, []);
    }

    public function promote(User $user)
    {

        try {
            // //$user = User::find($id)->first();

            if ($user) {

                $currentStage = $user->stage;
                $nextStage = $currentStage + 1;

                if ($nextStage > 10) {
                    return $this->sendError('Intern cannot exceed Stage 10', 404, []);
                }

                $user->stage = $nextStage;
                $result = $user->save();

                if ($result) {

                    $slack_id = $user->slack_id;
                    $pre_stage = $user->stage;
                    $next_stage = $nextStage;

                    Slack::removeFromChannel($slack_id, $currentStage);
                    Slack::addToChannel($slack_id, $nextStage);

                    // SEND NOTIFICATION HERE
                    $message = [
                        'message' => 'You have been promoted to stage ' . $nextStage,
                    ];

                    //$user->notify(new UserNotifications($message));

                    return $this->sendSuccess($user, 'Intern successfully promoted to stage ' . $nextStage, 200);
                }

            } else {
                return $this->sendError('User not found', 404, []);
            }

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError('Internal server error.', 500, []);
        }
    }

    public function demote(User $user)
    {

        try {
            //$user = User::find($id)->first();

            if ($user) {

                $currentStage = $user->stage;
                $lastStage = $currentStage - 1;

                if ($lastStage < 1) {
                    return $this->sendError('Intern cannot go below stage 1', 404, []);
                }

                $user->stage = $lastStage;
                $result = $user->save();

                if ($result) {

                    $slack_id = $user->slack_id;
                    Slack::removeFromChannel($slack_id, $currentStage);
                    Slack::addToChannel($slack_id, $lastStage);

                    // SEND NOTIFICATION HERE
                    $message = [
                        'message' => 'You have been demoted to stage ' . $lastStage,
                    ];

                    //$user->notify(new UserNotifications($message));

                    return $this->sendSuccess($user, 'Intern successfully demoted to stage ' . $lastStage, 200);
                }

            } else {
                return $this->sendError('User not found', 404, []);
            }

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError('Internal server error.', 500, []);
        }
    }

    public function update_stage(Request $request, User $user)
    {
        $validator = Validator::make($request->all(), [
            'stage' => 'required|integer:min:1|max:10',
        ]);

        if ($validator->fails()) {
            return $this->sendError('', 400, $validator->errors());
        }

        try {

            //$user = User::find($id)->first();

            if ($user) {

                $stage = $request->stage;
                $currentStage = $user->stage;

                if ($stage < 1 || $stage > 10) {
                    return $this->sendError('Stage can only be between 1 - 10', 404, []);
                }

                $user->stage = (int) $stage;
                $result = $user->save();

                if ($result) {

                    $slack_id = $user->slack_id;
                    Slack::removeFromChannel($slack_id, $currentStage);
                    Slack::addToChannel($slack_id, $stage);

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

    public function deactivate(User $user)
    {

        try {
            //$user = User::find($id)->first();

            if ($user) {

                $status = $user->active;

                if (!$status) {
                    return $this->sendError('Intern is already Deactivated.', 404, []);
                }

                $user->active = false;
                $result = $user->save();

                if ($result) {
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

    public function activate(User $user)
    {
        try {
            //$user = User::find($id)->first();

            if ($user) {

                $status = $user->active;

                if ($status) {
                    return $this->sendError('Intern is already Active.', 404, []);
                }

                $user->active = true;
                $result = $user->save();

                if ($result) {
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

    public function make_admin(User $user)
    {

        try {
            //$user = User::find($id)->first();

            if ($user) {

                $userRole = $user->role;

                if ($user->hasRole('superadmin')) {
                    return $this->sendError('Cannot change role of a Superadmin', 404, []);
                }

                if ($user->hasRole('admin')) {
                    return $this->sendError('User already an admin', 404, []);
                }

                //remove role
                if (!$user->removeRole('intern')) {
                    return $this->sendError('Cannot make user an admin', 404, []);
                }

                //assign role of admin to user
                if ($user->assignRole('admin')) {
                    $user->role = 'admin';
                    $user->save();

                    $message = [
                        'message' => 'You have been promoted to admin level ',
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

    public function remove_admin(User $user)
    {

        try {
            //$user = User::find($id)->first();

            if ($user) {

                $userRole = $user->role;

                if ($user->hasRole('superadmin')) {
                    return $this->sendError('Cannot change role of a Superadmin', 404, []);
                }

                if ($user->hasRole('intern')) {
                    return $this->sendError('User is not an admin', 404, []);
                }

                //remove role
                if (!$user->removeRole('admin')) {
                    return $this->sendError('Cannot remove user as an admin', 404, []);
                }

                //assign role of intern to user
                if ($user->assignRole('intern')) {
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

    public function destroy(User $user)
    {

        try {

            // $user = User::find($id);
            if ($user) {

                if ($user->hasAnyRole(['superadmin', 'admin'])) {
                    return $this->sendError('Cannot delete an Admin', 404, []);
                }

                $slack_id = $user->slack_id;
                $stage = $user->stage;

                if ($user->delete()) {
                    Slack::removeFromChannel($slack_id, $currentStage);
                    return $this->sendSuccess([], 'User has been deleted successfully.', 200);
                }
            } else {
                return $this->sendError('User not found', 404, []);
            }

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError('Internal server error.', 500, []);
        }
    }

    public function resetUserPass(User $user)
    {
        try {
            //$user = User::find($id)->first();

            if ($user) {

                $user->password = bcrypt('12345678');
                $result = $user->save();

                if ($result) {
                    return $this->sendSuccess($user, 'Successfully reset Password', 200);
                }

            } else {
                return $this->sendError('User not found', 404, []);
            }

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError('Internal server error.', 500, []);
        }
    }

    public function getUserDetails(User $user)
    {
        try {
            //$user = User::find($id)->first();

            if ($user) {
                $data = array();
                $data['user'] = $user;
                $data['roles'] = $user->roles;

                $total = $user->totalScore();
                $data['total_score'] = $total;

                return $this->sendSuccess($user, 'User details', 200);

            } else {
                return $this->sendError('User not found', 404, []);
            }

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError('Internal server error.', 500, []);
        }
    }

    public function getTotalScore()
    {
        try {
            $user = auth()->user();

            if ($user) {
                $total = $user->totalScore();

                $data = array();
                $data['total_score'] = $total;

                return $this->sendSuccess($data, 'User total score', 200);

            } else {
                return $this->sendError('User not found', 404, []);
            }

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError('Internal server error.', 500, []);
        }
    }

    public function makeIntern()
    {
        $users = User::with('roles')->get();

        foreach ($users as $user) {
            $roles = $user->roles->toArray();
            if (count($roles) > 0) {
                continue;
            }
            $user->assignRole('intern');
        }

        return $this->sendSuccess([], 'make intern', 200);
    }

    public function promoteByCommand(Request $request)
    {
        // Log::info($request);
        if (!$request->text) {
            return response()->json('You failed to specify a slack handle', 200);
        }

        $user = User::where('slack_id', $request->user_id)->first();
        if (!$user) {
            return response()->json('You are not a valid user on this workspace', 200);
        }
        if ($user->role === 'intern') {
            return response()->json('This operation is only reserved for admins', 200);
        }

        // if (!is_numeric(substr($request->channel_name, -1)) || strpos($request->channel_name, 'stage') === false || strlen($request->channel_name) > 7) {
        //     return response()->json('Promotion can only be done in stage 1 to stage 10 channels', 200);
        // };

        $users = explode('<@', preg_replace('/\s+/', '', $request->text));
        $stage = $users[0];
        array_splice($users, 0, 1);
        $count = 0;
        if (!is_numeric($stage)) {
            return response()->json('Please specify a stage', 200);

        }

        foreach ($users as $user) {
            $slack_id = explode('|', $user)[0];
            $user = User::where('slack_id', $slack_id)->first();
            if ($user) {
                $currentStage = $user->stage;
                $nextStage = $currentStage + 1;
                if (intval($stage) < 1 || intval($stage) > 10) {
                    continue;
                } else {
                    Slack::removeFromChannel($slack_id, $currentStage);
                    Slack::addToChannel($slack_id, $stage);
                    $user->stage = $stage;
                    $count += 1;
                    $user->save();
                }
            }
        }

        return response()->json($count . " user(s) promoted to stage " . $stage . " successfully. " . (count($users) - $count) . " failed", 200);

    }

    public function demoteByCommand(Request $request)
    {
        if (!$request->text) {
            return response()->json('You failed to specify a slack handle', 200);
        }
        $user = User::where('slack_id', $request->user_id)->first();
        if (!$user) {
            return response()->json('You are not a valid user on this workspace', 200);
        }
        if ($user->role === 'intern') {
            return response()->json('This operation is only reserved for admins', 200);
        }

        $users = explode('<@', preg_replace('/\s+/', '', $request->text));
        array_splice($users, 0, 1);


        $count = 0;

        foreach ($users as $user) {
            $slack_id = explode('|', $user)[0];

            $user = User::where('slack_id', $slack_id)->first();
            if ($user) {
                $currentStage = $user->stage;
                $previousStage = $currentStage - 1;

                if ($previousStage < 1) {
                    continue;
                } else {
                    Slack::removeFromChannel($slack_id, $currentStage);
                    Slack::addToChannel($slack_id, $previousStage);
                    $user->stage = $previousStage;
                    $count += 1;
                    $user->save();
                }
            }
        }

        return response()->json($count . " user(s) demoted successfully. " . (count($users) - $count) . " failed", 200);

    }

    public function isolateByCommand(Request $request)
    {
        if (!$request->text) {
            return response()->json('You failed to specify a slack handle', 200);
        }
        $user = User::where('slack_id', $request->user_id)->first();
        if (!$user) {
            return response()->json('You are not a valid user on this workspace', 200);
        }
        if ($user->role === 'intern') {
            return response()->json('This operation is only reserved for admins', 200);
        }

        $users = explode('<@', preg_replace('/\s+/', '', $request->text));
        array_splice($users, 0, 1);


        $count = 0;

        foreach ($users as $user) {
            $slack_id = explode('|', $user)[0];

            $user = User::where('slack_id', $slack_id)->first();
            if ($user) {
                Slack::addToGroup($slack_id, 'isolation-center');
                $count += 1;
                $user->save();
            }
        }

        return response()->json($count . " user(s) moved to isolation center successfully. " . (count($users) - $count) . " failed", 200);

    }

    public function pardonByCommand(Request $request)
    {
        if (!$request->text) {
            return response()->json('You failed to specify a slack handle', 200);
        }
        $user = User::where('slack_id', $request->user_id)->first();
        if (!$user) {
            return response()->json('You are not a valid user on this workspace', 200);
        }
        if ($user->role === 'intern') {
            return response()->json('This operation is only reserved for admins', 200);
        }

        $users = explode('<@', preg_replace('/\s+/', '', $request->text));

        $count = 0;

        foreach ($users as $user) {
            $slack_id = explode('|', $user)[0];

            $user = User::where('slack_id', $slack_id)->first();
            if ($user) {
                Slack::removeFromGroup($slack_id, 'isolation-center');
                $count += 1;
                $user->save();
            }
        }

        return response()->json($count . " user(s) removed from isolation center successfully. " . (count($users) - $count) . " failed", 200);

    }

}