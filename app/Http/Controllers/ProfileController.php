<?php

namespace App\Http\Controllers;

use Cloudder;
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

        $image = request()->file('profile_img')->getRealPath();

        Cloudder::upload($image, null, $options = array("folder" => "hngojet/profile_img/",));
        
        $picPath = Cloudder::show(Cloudder::getPublicId());


        $data['profile_img'] =  $picPath;
        
        // request()->file('profile_img')->store('profile_img/'.$fileNameWithExt, 'public');

        $updatedProfile = auth()->user()->profile->update($data);

        return response()->json([
            'user' => $updatedProfile,
            'status' => 'Profile Updated'
        ], 200);

    }
}
