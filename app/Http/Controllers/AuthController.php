<?php


namespace App\Http\Controllers;
use App\User;
use Validator;
use App\RoleUser;
use App\TrackUser;
use Carbon\Carbon;
use App\PasswordReset;
use App\Http\Classes\ResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\PasswordResetMail;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Notifications\UserRegistration;
use Craftyx\SlackApi\Facades\SlackUser;
use App\Slack;



class AuthController extends Controller
{

    use ResponseTrait;
    public function login(Request $request){ 

        //logic for logging in with username or email, and password.
        if(Auth::attempt([
            'email' => $request->email,
            'password' => $request->password
        ])){
            $user = Auth::user();
            $token = $user->createToken('HNGApp')->accessToken;
            return response()->json([
                'status' => true,
                'message' => 'Login successful',
                'token' => $token,
                'user' => $user
            ],
            200
        );
        }else{
            return response()->json(['status' => false, 
            'error' => 'Unauthorized'
        ], 401);
        }
    }


    public function register(Request $request) { 
        //logic for sign up
        
        $messages = [];
        $validator = Validator::make($request->all(),[
            'firstname' => 'required',
            'lastname' => 'required',
            'username' => 'required|unique:users,username|max:30',
            'email' => 'required|email|unique:users,email',
            'password' => 'required',
            'confirm_password' => 'required|same:password',
            'location' => 'required',
            'gender' => 'nullable'
        ]);

        
        $input = $request->all();
        
        $slackUser = SlackUser::lookupByEmail($input['email']);

        if (!$slackUser->ok) {
            return $this->ERROR('Please confirm that your email is used on slack and try again');
        }

        if($validator->fails()){
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 401);
        }
        
        $input['slack_id'] = $slackUser->user->id;
        // dd($input['slack_id']);
        $input['password'] = bcrypt($input['password']);
        $input['role'] = 'intern';
        $input['stack'] = 'Default';
        $input['stage'] = 0;

        DB::beginTransaction();
        $user = User::create($input);
        
        $tracks = $request->tracks;
        // if ($tracks && is_array($tracks)) {
            // foreach ($tracks as $track) {
                $trackUser = new TrackUser;
                $trackUser->user_id = $user->id;
                // $trackUser->track_id = $tracks;
                $trackUser->track_id = $request->track;
                $trackUser->save();

                $t = new TrackUser;
                $t->user_id = $user->id;
                $t->track_id = 6;
                $t->save();
            // }
        // }
        $user->assignRole('intern');

        DB::commit();
        
        // $user->notify(new UserRegistration($user));

        //add user to first stage
        Slack::addToChannel($input['slack_id'], '0');

        return response()->json([
            'status' => true,
            'message' => 'Registration successful',
            'user' => $user,
        ], 200);
        
    } 

    public function testMail(){
        $email = "jonathanjude27@gmail.com";
        $token = "ftdchdcdccgytdby";

        return Mail::to($email)->send(new PasswordResetMail($token));
    }

    public function requestReset(Request $request){
        
        try{
            $request->validate([ 'email' => 'required|string|email' ]);
            $user = User::where('email', $request->email)->first();
            if (!$user) return $this->ERROR("We can't find a user with the e-mail address " . $request->email);

            $passwordReset = PasswordReset::updateOrCreate(
                ['email' => $request->email],
                [
                    'email' => $request->email,
                    'token' => $this->GENERATE_TOKEN(60)
                ]
            );
            $email = PasswordReset::where('email', $request->email)->first();

            if ($user && $passwordReset){
                //Send email
                Mail::to($user->email)->send(new PasswordResetMail($email->token));
            } 
            // logger("Password reset link sent to " . Auth::user()->email);
            return $this->SUCCESS("Password reset link sent: " . $user->email);
        }
        catch(\Throwable $e){
            // logger("Password reset failed for " . Auth::user()->email);
            return $this->ERROR('Password rest failed. Please try again', );
        }
    }



    public function resetPassword(Request $request){
       
            $validation = Validator::make($request->all(), [
                'email' => 'required|string|email',
                'password' => 'required',
                'confirm_password' => 'required|same:password',
                'token' => 'required|string'
            ]);
            
            if ($validation->fails())  return $this->sendError($validation->errors(), 400);

            $passwordReset = PasswordReset::where([
                'email' => $request->email,
                'token' => $request->token
                
            ])->first();

            if (!$passwordReset) return $this->sendError('This password reset email does not exist.', 404);

            if (Carbon::parse($passwordReset->updated_at)->addMinutes(10)->isPast()) {
                $passwordReset->delete();
                return $this->sendError('This password reset token is invalid.', 404);
            }

            $user = User::whereEmail($request->email)->first();
            if (!$user) return $this->sendError("We can't find a user with the e-mail address " . $request->email, 404); 
            
            $user->password = bcrypt($request->password);
            $user->save();
            $passwordReset->delete();

            return $this->sendSuccess('Password successfully changed', 201);
    }


    public function updatePassword(Request $request){
        try{
            $validation = Validator::make($request->all(), [
                'current' => 'required',
                'password' => 'required|confirmed',
                'password_confirmation' => 'required'
            ]);
            
            if ($validation->fails()) {
                return $this->ERROR('Password update failed', $validation->errors());
            }
    
            $user = User::find(Auth::user()->id);
            if (!Hash::check($request->current, $user->password)) {
                return $this->ERROR('Current password does not match');
            }
    
            $user->password = bcrypt($request->password);
            $user->save();
    
            return $this->SUCCESS('Password changed');
        }
        catch(\Throwable $e){
            return $this->ERROR('Password update failed', $e);
        }
    }


    public function logout(){
        $accessToken = Auth::user()->token();
        DB::table('oauth_refresh_tokens')
            ->where('access_token_id', $accessToken->id)
            ->update([
                'revoked' => true
            ]);

        $accessToken->revoke();        
        return $this->SUCCESS('You are successfully logged out');
    }

    public function clear_session(){
        $token_id = Auth::user()->token()->id; // Use this to revoke refresh_token
        $user_id = Auth::user()->id;
        DB::table('oauth_access_tokens AS OAT')
            ->join('oauth_refresh_tokens AS ORT', 'OAT.id', 'ORT.access_token_id')
            ->where('OAT.id', '!=', $token_id)
            ->where('OAT.user_id', $user_id)
            ->update([
                'OAT.revoked' => true,
                'ORT.revoked' => true
            ]);
        return $this->SUCCESS();
    }


    public function verify(Request $request, $token)
    {
        if ($user = User::where('token', $request->token)->first()) {
            $user->token = null;
            if ($user->save() && $user->markEmailAsVerified()) {
                return $this->SUCCESS('You are successfully verified');
            }
        } else {
            return $this->ERROR('Invalid verification code');
        }
    }

    private function generateOTP(int $n)
    {
        $generator = "1234567890";
        $result = "";

        for ($i = 1; $i <= $n; $i++) {
            $result .= \substr($generator, (rand() % (strlen($generator))), 1);
        }
        return $result;
    }

}
