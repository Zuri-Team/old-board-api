<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Probation;
use Carbon\Carbon;
use App\User;
use Auth;
use DB;

class ProbationController extends Controller
{
    public function probate(Request $request){
        // Only admin can do this
        if(!Auth::user()->hasAnyRole(['admin', 'superadmin'])){
            return $this->ERROR('You dont have the permission to perform this action');
        }
        
        $validation = Validator::make($request->all(),[
            'user_id' => 'required',
            'reason' => 'nullable',
            'exit_on' => 'date_format:Y-m-d'
        ]);

        if($validation->fails()) return $this->ERROR( $validation->errors());

        $exit_date = Carbon::now()->addDays(1);
        if($request->exit_on){
            if(Carbon::make($request->exit_on)->isPast()) return $this->ERROR('Exit date must be in the future', $validator->errors());
            $exit_date = $request->exit_on;
        } 
        
        $is_user = User::find($request->user_id);
        $is_on_probation = Probation::where('user_id', $request->user_id)->first();

        if(!$is_user)  return $this->ERROR('Specified user does not exist');
        if($is_user->hasAnyRole(['admin', 'superadmin'])) return $this->ERROR('An admin cannot go on probation');
        if($is_on_probation) return $this->ERROR('Specified user is already on probation');        
        
        Probation::insert(['user_id'=>$request->user_id, 'probated_by'=>Auth::user()->id, 'probation_reason'=>$request->reason ?? null, 'exit_on'=>$exit_date]);
        return $this->SUCCESS('Probation successful');   
    }

    public function is_on_onprobation(int $user_id){
        $data = Probation::where('user_id', $user_id)->first();
        return $this->SUCCESS($data ? 'true' : 'false', $data ? true : false);
    }

    public function unprobate_by_admin(Request $request){
        // Only admin can do this
        if(!Auth::user()->hasAnyRole(['admin', 'superadmin'])){
            return $this->ERROR('You dont have the permission to perform this action');
        }
        $query = Probation::where('user_id', $request->user_id)->first();
        if($query) {
            $query->delete();
            return $this->SUCCESS('Successfully removed user from probation');
        }else{
            return $this->SUCCESS('Specified user is not on probations');
        }

    }

    public function unprobate_by_system(Request $request){
        // This action will be triggered by a schedular
        $today = Carbon::now()->startOfDay()->format('Y-m-d');
        Probation::where('exit_on', '<=', $today)->delete();
    }

    public function unprobate_by_action(Request $request){
        // Not clear yet what action by the user should remove him from probation
    }


    public function list_probations(Request $request){
        // Only admin can do this
        if(!Auth::user()->hasAnyRole(['admin', 'superadmin'])){
            return $this->ERROR('You dont have the permission to perform this action');
        }
        $data = Probation::join('users', 'users.id', 'probations.user_id')
                        ->join('users AS probator', 'probator.id', 'probations.probated_by')
                        ->select(
                            'probations.*', DB::raw("CONCAT(users.firstname,' ',users.lastname) as name"),
                            DB::raw("CONCAT(probator.firstname,' ',probator.lastname) as probated_by")
                        )
                        ->get();
        return $this->SUCCESS('All probations retrieved', $data);
    }
}
