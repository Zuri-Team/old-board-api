<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskSubmission;
use App\Http\Resources\TaskSubmissionResource;
use App\Task;
use App\TaskSubmission;
use App\TrackUser;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TaskSubmissionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
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
    public function store(StoreTaskSubmission $request)
    {
        $data = $request->validated();

        // Check if the User is found in the trackUser
        if (!TrackUser::where('user_id', $data['user_id'])->first()) {
            return $this->errorResponse('User does not belong to this track', 422);
        }

        // Check if the Task Submission date has past => done
        if (Task::find($data['task_id'])->first()->deadline < Carbon::now()) {
            return $this->errorResponse('Submission date elaps', 422);
        }

        // Check if Status is still open for submission.
        if (Task::find($data['task_id'])->first()->status == 'CLOSED') {
            return $this->errorResponse('Task submission Closed', 422);
        }

        $task = TaskSubmission::create($data);
        if ($task) {
            return new TaskSubmissionResource($task);
        }

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
    public function destroy($id)
    {
        //
    }
}