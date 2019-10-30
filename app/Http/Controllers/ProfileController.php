<?php

namespace App\Http\Controllers;

use JD\Cloudder\Facades\Cloudder;
use App\User;
use App\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Http\Classes\ResponseTrait;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{

    use ResponseTrait;

    public function __construct()
    {
        // $this->middleware('auth:api')->except('logout');
    }

    public function index(User $user){

        if($user){
            return $this->sendSuccess([$user, $user->profile], 'User Profile ', 200);

            }else{
                return $this->sendError('User not found', 500, []);
            }
    }

    public function upload(Request $request, User $user)
    {
        //
        if($request->hasFile('profile_img')){

            $data = $request->all();

            $image = request()->file('profile_img')->getRealPath();

            Cloudder::upload($image, null, $options = array("folder" => "hngojet/profile_img/",));
            
            $picPath = Cloudder::show(Cloudder::getPublicId());

            $data['profile_img'] = $picPath;
            
            auth()->user()->profile->update($data);

            if($user){
            return $this->sendSuccess($user->profile, 'User Profile updated successfully.', 200);

            }else{
                return $this->sendError('Could not Update profile photo', 500, []);
            }
        }
    }


    public function update(Request $request)
    {
        //
         $data = request()->all();
       
         $profile = auth()->user()->update($data);

         if($profile){
            return $this->sendSuccess($data, 'User Profile ', 200);

            }else{
                return $this->sendError('User not found', 500, []);
            }


        //fixed

        // DB::beginTransaction();

        // try{

        // // Cloudder::upload($image, null, $options = array("folder" => "hngojet/profile_img/", "use_filename" => true));
        
        // // $picPath = Cloudder::show(Cloudder::getPublicId());

        // if($request->hasFile('profile_img')){

        // $image = request()->file('profile_img')->getRealPath();

        //     $cloudder     = Cloudder::upload($image);
        //     //Request the image info from api and save to db
        //     $uploadResult = $cloudder->getResult();
        //     //Get the public id or the image name
        //     $file_url     = $uploadResult["public_id"];
        //     //Get the image format from the api
        //     $format       = $uploadResult["format"];

        //     $user_image   = $uploadResult['url'];
        // }


        // // $data['profile_img'] =  $picPath;
        // // $data['profile_img'] =  $user_image;

        // $getUserId = auth()->user()->id;

        // $user = User::find($getUserId);
        // $res = $user->update($request->all());
        // // if($request->firstname) $user->firstname = $request->firstname;
        // // if($request->lastname) $user->lastname = $request->lastname;
        // // if($request->username) $user->username = $request->username;
        // // if($request->location) $user->location = $request->location;
        // // if($request->gender) $user->gender = $request->gender;
        // // if($request->email) $user->email = $request->email;
        // // if($request->bio) $user->bio = $request->bio;
        // // if($request->url) $user->url = $request->url;
        // if($user_image) $user->profile_img = $user_image;

        // // $res = $user->save();
        
        // // $updatedProfile = auth()->user()->update($data);

        // if($res){

        //     return $this->sendSuccess($user, 'User Profile updated successfully.', 200);

        //     }else{
        //         return $this->sendError('Could not Update profile photo', 500, []);
        //     }

        //     //if operation was successful save commit+ save to database
        //     DB::commit();

        // }catch (\Exception $e){

        //   DB::rollBack();
        //     Log::error($e->getMessage());
        //    return $this->sendError('Internal server error.', 500, []);
        // }

    }
}
