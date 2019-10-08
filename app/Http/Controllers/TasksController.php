<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTask;
use App\Http\Resources\Task\TaskCollection;
use App\Http\Resources\Task\TaskResource;
use App\TrackUser;
use Symfony\Component\HttpFoundation\Response;
use App\Task;
use Illuminate\Http\Request;

class TasksController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $this->middleware(['role:superadmin', 'role:admin']);

            $tasks = Task::paginate(20);
            return TaskCollection::collection($tasks);
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
    public function store(StoreTask $request)
    {

        $this->middleware(['role:superadmin', 'role:admin']);

        $data = $request->validated();

        $task = Task::create($data);

//        if ($task) {
//            return TaskResource::collection(Task::all()->paginate(20));
//        }

        return response([
            'data' => TaskResource::collection($task->paginate(20)),
        ], Response::HTTP_CREATED);

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Task $id)
    {

        $this->middleware(['role:superadmin', 'role:admin']);

        $task = Task::find($id);

        if($task){
            return TaskResource::collection($task);
        }else{
            return response()->json([
                'message' => 'No Task found',
            ], 404);
        }

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
        $this->middleware(['role:superadmin', 'role:admin']);

        $task = Task::find($id);

        if ($task) {
            if ($task->update($request->all())) {
                return response([
                    'data' => new TaskResource($task)
                ]);
            }
        } else {
            return response([
                'message' => 'Task not Found'
            ], 404);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $this->middleware(['role:superadmin', 'role:admin']);

        $task = Task::find($id);

        if($task->delete()){
            return response(null, Response::HTTP_NO_CONTENT);
        }
    }

    public function viewTask($track_id, $id){

        $this->middleware(['role: intern']);

        $user_id = auth()->user()->id;

        $user_track = TrackUser::where('track_id', $track_id)->where('user_id', $user_id);

        if($user_track){
            $task = Task::where('track_id', $track_id)->where('id', $id);
            return TaskResource::collection($task);
        }
    }
}
