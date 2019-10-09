<?php

namespace App\Http\Controllers;


use App\User;
use App\Profile;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct()
    {
        // $this->middleware('auth:api')->except('logout');
    }

    public function index(User $user){

        return response()->json([

            'user' => $user,
            'profile' => $user->profile
        ], 200);

    }


    public function update()
    {
        //
        $data = request()->all();

        $fileNameWithExt = request()->file('profile_img')->getClientOriginalName();

        $data['profile_img'] = request()->file('profile_img')->store('uploads/'.$fileNameWithExt, 'public');

        $updatedProfile = auth()->user()->profile->update($data);

        return response()->json([
            'user' => $updatedProfile,
            'status' => 'Profile Updated'
        ], 200);

    }
}
