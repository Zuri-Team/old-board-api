<?php

namespace App\Http\Controllers;

use App\Slack;
use Validator;
use Illuminate\Http\Request;
use Craftyx\SlackApi\Facades\SlackApi;
use Craftyx\SlackApi\Facades\SlackChat;
use Craftyx\SlackApi\Facades\SlackTeam;
use Craftyx\SlackApi\Facades\SlackUser;
use Craftyx\SlackApi\Facades\SlackGroup;
use Craftyx\SlackApi\Facades\SlackChannel;

class SlackController extends Controller
{

    public function test(){
        // $result = SlackUser::lookupByEmail('seyi@hng.tech');

        // $result = Slack::removeFromChannel('U013RAALL3W', '2');
        // $result = Slack::addToChannel('U013RAALL3W', '2');
        // $result = Slack::addToGroup('U013RAALL3W', 'team-incredibles');
        // $result = Slack::addToGroup('U013RAALL3W', 'stage3');

        $groups = SlackChannel::lists(false);

        $result = array();
                    
        foreach($groups->channels as $group){
            array_push($result, $group->name);
            // if($group->name == $stage_name){
            //     return $group->id;
            //     break;
            // }
        }
        // $result = SlackGroup::lists(); 

        // $result = SlackGroup::invite('C0146LZ5DFD', 'U013RAALL3W');

        // $us = SlackUser::lists(
        //     [
        //         'cursor' => 'dxN1cjpVMDE0NTE1REJKQg=='
        //     ]
        // ); //all()

        // dd($us);

        // $us = SlackApi::get('users.list', [
        //     'cursor' => 'dXNlcjpVMDE0NTE1REJKQg==',
        //     'limit' => 2
        // ]);

        // $result = slack()->get('users.list', [
        //     'cursor' => 'dxN1cjpVMDE0NTE1REJKQg=='
        // ]);


        // $result = array();
        // $result[] = $us->response_metadata->next_cursor;

        // foreach($us->members as $u){
        //     $result[] = $u->id;
        // }

        dd($result);
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // dd(SlackGroup::kick('GPLMC3ZCK', 'UPY990HUP'));
        // dd(SlackGroup::lists(true));
        // dd(SlackChannel::lists(true));


        // dd(SlackUser::lookupByEmail('solomoneseme@gmail.com'));

        $result = Slack::removeAddToGroup('UPYT556BY', 'stage11', 'Prob');
        dd($result);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($prev, $next)
    {
        $slack_id =  'U013RAALL3W';//$user->slack_id; CODEBUG
        $pre_stage = $prev;//.$user->stage;
        $next_stage = $next;//.$nextStage;

        // $prestage = Slack::removeFromGroup($slack_id, $pre_stage);
        // $nextstage = Slack::addToGroup($slack_id, $next_stage);

        $prestage = Slack::removeFromChannel($slack_id, $pre_stage);
        $nextstage = Slack::addToChannel($slack_id, $next_stage);

         dd($prestage, $nextstage);
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
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }


    public function verify_user(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'email' => 'required|email|unique:users,email',
        ]);

        if($validator->fails()){
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 401);
        }

        $input = $request->all();
        $slackUser = SlackUser::lookupByEmail($input['email']);

        if($slackUser->ok){
            return response()->json([
                'status' => true,
                'message' => 'User is found on slack',
                'SlackUser' => $slackUser,
            ], 200);
        }
        return $this->ERROR('Please confirm your email. Please try again');
    }

    public function slack_user_profile(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'slack_id' => 'required|string',
        ]);

        if($validator->fails()){
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 401);
        }

        $input = $request->all();
        $slackUser = SlackUser::info($input['slack_id']);

        if($slackUser->ok){
            return response()->json([
                'status' => true,
                'message' => 'User is found on slack',
                'SlackUser' => $slackUser,
            ], 200);
        }
        return $this->ERROR('Please confirm your slack ID. Please try again');
    }

    public function createPrivateChannel(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'channel' => 'required|string',
        ]);

        if($validator->fails()){
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 401);
        }

        $input = $request->all();
        $slackGroup = SlackGroup::open($input['channel']);

        // STORE THE NEW GROUP INFOR
        
    }

}
