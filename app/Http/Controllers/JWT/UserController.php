<?php

namespace App\Http\Controllers\JWT;

use App\RoleUser;
use App\User;
use Validator;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    // use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    /**
     * Create a new controller instance.
     *
     * @return void
     */

    public function __construct()
    {
        // $this->middleware('guest')->except('logout');

        auth()->shouldUse('api_user');
    } 


    public function login(Request $request)
    {
        try {
            $credentials = $request->only('email', 'password');

            if(!$token = auth()->attempt([
                'email' => $request->input('email'), 
                'password' => $request->input('password'),
            ])) {

                return response()->json([
                    'errors' => [
                        'email' => ['Your email and/or password may be incorrect.']
                    ]
                ], 422);
            }
        } catch (JWTException $e) {
            return response()->json(['message' => 'Could not create token!'], 401);
        }
        $status = true;
        return $this->respondWithToken($token, $status);
    } 

    public function register(Request $request)
    {

        $validator = Validator::make($request->all(),[
            'firstname' => 'required',
            'lastname' => 'required',
            'username' => 'required|unique:users,username|max:30',
            'email' => 'required|email|unique:users,email',
            'password' => 'required',
            'confirm_password' => 'required|same:password',
            'stack' => 'required',
            'location' => 'required',
        ]);

        if($validator->fails()){
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 401);
        }


        $input = $request->all();

        $input['password'] = bcrypt($input['password']);

        $user = User::create($input);

        $token = auth()->login($user);

        $status = true;

        // $role = Role::findByName('intern');

        // RoleUser::create([
        //     'role_id' => $role->id,
        //     'user_id' => $user->id,
        // ]);

        return $this->respondWithToken($token, $status);
    }

    protected function respondWithToken($token, $status): JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
            "status" => $status,
            // 'user_id' => auth()->user()->id,
            // 'role' => auth()->user()->role,
            // 'name' => auth()->user()->name,
            // 'email' => auth()->user()->email,
            'type' => 'user' //api_user guard 
        ]);
    } 
}
