<?php

namespace App\Http\Controllers;

use App\Team;
use App\User;
use App\TeamUser;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Classes\ResponseTrait;
use Illuminate\Support\Facades\Log;
use App\Notifications\TeamNotifications;
use Illuminate\Support\Facades\Validator;

class TeamController extends Controller
{

    use ResponseTrait;

    public function __construct()
    {
        $this->middleware(['role:superadmin'])->except(['index', 'viewMembers']);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $teams = Team::orderBy('created_at', 'desc')->paginate(10);
        if ($teams) {

            return $this->sendSuccess($teams, 'All Teams', 200);
        }
        return $this->sendError('Internal server error.', 500, []);

    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request) : JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'team_name' => 'bail|required|unique:teams,team_name|min:3',
            'max_team_mates' => 'required|integer',
            // 'team_lead' => 'required|integer',
            'team_description' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return $this->sendError('', 400, $validator->errors());
        }

        $teamCollection = [];
        try{
            $request['team_lead'] = 1; //to remove
            $teamCollection = Team::create($request->all());

        }catch (\Exception $e){
            Log::error($e->getMessage());
           return $this->sendError('Internal server error.', 500, []);
        }

        return $this->sendSuccess($teamCollection, 'Team has been created successfully.', 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if ($team = Team::find($id)) {
             $team['team_leader'] = $team->team_lead;
                return $this->sendSuccess($team, 'View a Team', 200);
        } else {
            return $this->sendError('Team not found', 404, []);
        }


    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //

    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'team_name' => 'bail|required|min:3',
            'max_team_mates' => 'required|integer',
            // 'team_lead' => 'required|integer',
            'team_description' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return $this->sendError('', 400, $validator->errors());
        }

        try {

            if ($team = Team::findOrFail($id)) {
                if ($team->update($request->all())) {
                    return $this->sendSuccess($team, 'Team has been updated successfully.', 200);
                }
            } else {
                return $this->sendError('Team not found', 404, []);
            }

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError('Internal server error.', 500, []);
        }

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {

            if ($team = Team::findOrFail($id)) {
                if ($team->delete()) {
                    return $this->sendSuccess($team, 'Team has been deleted successfully.', 200);
                }
            } else {
                return $this->sendError('Team not found', 404, []);
            }

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError('Internal server error.', 500, []);
        }
    }

    public function addMember(Request $request){
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'team_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->sendError('', 400, $validator->errors());
        }

        $request = $request->all();
        $user = User::find($request['user_id']);
        $team = Team::find($request['team_id']);
        $has_joined = TeamUser::where($request)->first();
        try{
            if(!$user) return $this->sendError('User does not exist', 404, []);

            if (!$team) return $this->sendError('Team does not exist', 404, []);

            if($has_joined) return $this->sendError('Intern already among the team', 400, []);
            
            TeamUser::create($request);

            //SEND NOTIFICATION HERE
            $name = $team->team_name;
            $message = [
                'message'=>'You have been added to '.$name.' team.',
            ];
            
            $user->notify(new TeamNotifications($message));

            return $this->sendSuccess($team, 'Intern added to team successfully', 200);

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError('Internal server error. '.$e->getMessage(), 500, []);
        }

        // try {

        //     //validations
        //     $team = Team::where('id', $request->team_id)->count();
        //     $user = User::where('id', $request->user_id)->count();

        //     if(!$team) return $this->sendError('Team does not exist', 404, []);
        //     if(!$user) return $this->sendError('User does not exist', 404, []);

        //     $userTeamsArray = User::find($request->user_id)->teams->pluck('id')->toArray();
        //     if(in_array($request->team_id, $userTeamsArray)) return $this->sendError('Intern already among the team', 400, []);

        //     if ($teamUser = TeamUser::create($request->all())) {
        //             return $this->sendSuccess($teamUser, 'Intern added to team successfully', 200);
        //     } else {
        //         return $this->sendError('Something went wrong ', 400, []);
        //     }

        // } catch (\Exception $e) {
        //     Log::error($e->getMessage());
        //     return $this->sendError('Internal server error.', 500, []);
        // }


    }

    public function removeMember(Request $request){
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'team_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->sendError('', 400, $validator->errors());
        }

            $request = $request->all();
            $user = User::find($request['user_id']);
            $team = TeamUser::where($request)->first();

            try {
                if (!$user) {
                    return $this->sendError('User does not exist', 404, []);
                }

                if (!$team) {
                    return $this->sendError('Intern is not on the Team', 400, []);
                }

                $team->delete();
                // logger(Auth::user()->email . ' removed ' . $user->email . ' from a track');

                //SEND NOTIFICATION HERE
                // dd($team);
                $teamName = Team::find($team->team_id)->team_name;
                $message = [
                    'message'=>'You have been removed from '.$teamName.' team.',
                ];
                
                $user->notify(new TeamNotifications($message));
                

                return $this->sendSuccess([], 'Intern removed from team successfully', 200);

            } catch (\Exception $e) {
                Log::error($e->getMessage());
                return $this->sendError('Internal server error.', 500, []);
            }


    }

    public function viewMembers($id)
    {
        if ($team = Team::find($id)) {

            $team['members'] = $team->members;
            $team['team_leader'] = $team->team_leader;

                return $this->sendSuccess($team, 'Successfull fetched members', 200);
        } else {
            return $this->sendError('Team not found', 404, []);
        }


    }
}