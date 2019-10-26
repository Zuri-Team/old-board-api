<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Classes\ResponseTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    use ResponseTrait;
    
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $notifications = Auth::user()->notifications()->get(['id','data', 'read_at', 'created_at', 'updated_at'])->toArray();
        // if ($notifications) {

            return $this->sendSuccess($notifications, 'All notifications', 200);
        // }
        // return $this->sendError('Internal server error.', 500, []);

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
    public function show($id)
    {
        //
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
    public function destroy()
    {
        Auth::user()->notifications()->delete();
        return $this->sendSuccess(Auth::user(), 'Notifications successfully deleted ', 200);  
    }

    public function markAsRead(Request $request)
    {
        Auth::user()->unreadNotifications->markAsRead();
        return $this->sendSuccess(Auth::user(), 'Notifications successfully marked as read ', 200);  
    }

    public function markOneAsRead(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'notification_id' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->sendError('', 400, $validator->errors());
        }
        $request = $request->all();
        
        Auth::user()->unreadNotifications->find($request['notification_id'])->markAsRead();
        // if($res){
            return $this->sendSuccess(Auth::user(), 'Notification successfully marked as read ', 200);  
        // }else{
        //     return $this->sendError('Failed', 500, []);
        // }
    }

    public function notification_count()
    {
        $total  = count(Auth::user()->unreadNotifications);
        // dd($total);
        $data = [
            'notification_count' => $total,
            // 'user' => Auth::user(),
        ];
        return $this->sendSuccess($data, "Notification count successful", 200);
        
    }
}   
