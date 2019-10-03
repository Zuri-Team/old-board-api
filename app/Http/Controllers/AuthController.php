<?php


namespace App\Http\Controllers;
use App\RoleUser;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Validator;


class AuthController extends Controller
{

    public function login(){ 

        //logic for logging in with username or email, and password.
        if(Auth::attempt([
            'email' => request('email'),
            'password' => request('password')
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
            'stack' => 'required',
            'locked' => '',
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
        $token = $user->createToken('HNGApp')->accessToken;

        $role = Role::findByName('intern', 'intern');

        RoleUser::create([
            'role_id' => $role->id,
            'user_id' => $user->id,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Registration successful',
            'token' => $token,
            'user' => $user
        ], 200);
        
    } 

}
