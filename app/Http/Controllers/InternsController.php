<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Classes\ResponseTrait;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use App\User;

class InternsController extends Controller
{

    use ResponseTrait;

    public function get_all_interns(){
        $interns = User::role('intern')->with('teams')->with('tracks')->get();
        //  $interns = User::all();
        
        if ($interns) {

            $all = [];

            foreach($interns as $intern){
                $intern['profile_img'] = $intern->profile->profile_img;
                array_push($all, $intern);
            }
            return $this->sendSuccess($all, 'Fetched all interns', 200);
        }
        return $this->sendError('Internal server error.', 500, []);
    }

    public function get_all_admins(){
        $admins = User::whereIn('role', ['admin', 'superadmin'])->get();
        //  $interns = User::all();
        
        if ($admins) {

            $all = [];

            foreach($admins as $admin){
                $admin['profile_img'] = $admin->profile->profile_img;
                array_push($all, $admin);
            }
            return $this->sendSuccess($all, 'Fetched all admins', 200);
        }
        return $this->sendError('Internal server error.', 500, []);
    }

    public function search_users($query){
        $users = User::where('firstname', 'LIKE', "%{$query}%")->orWhere('lastname', 'LIKE', "%{$query}%")->orWhere('username', 'LIKE', "%{$query}%")->orderBy('firstname', 'asc')->get();

        if ($users) {

            $data = [];
            foreach($users as $user){
                $user['profile_img'] = $user->profile->profile_img;
                array_push($data, $user);
            }

            return $this->sendSuccess($data, 'Search results for '.$query, 200);
        }
        return $this->sendError('Internal server error.', 500, []);
    }
	
	public function destroy($id)
    {

        if (!auth('api')->user()->hasAnyRole(['admin', 'superadmin', 'intern'])) {
            return $this->ERROR('You dont have the permission to perform this action');
        }

        try {

            if ($intern = User::role('intern')->find($id)) {
                if ($intern->delete()) {
                    return $this->sendSuccess($intern, 'Post has been deleted successfully.', 200);
                }
            } else {
                return $this->sendError('Post not found', 404, []);
            }

        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError('Internal server error.', 500, []);
        }
    }
}
