<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTask;

use App\Http\Resources\TaskResource;
use App\TrackUser;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Classes\ActivityTrait;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use App\Http\Classes\ResponseTrait;

//use App\Http\Resources\Task\TaskCollection;
//use App\Http\Resources\Task\TaskResource;

use App\Task;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TasksController extends Controller
{

    use ActivityTrait;
    use ResponseTrait;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $this->middleware(['role:superadmin', 'role:admin']);


            $tasks = Task::orderBy('id', 'desc')->with(['track', 'tasks'])->get();
            
            if($tasks){
                return TaskResource::collection($tasks);
            }else{
                return response([
                    'message' => Response::HTTP_NOT_FOUND
                ], 404);
            }

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
        $this->middleware(['role:superadmin', 'role:admin']);

         $validator = Validator::make($request->all(), [
            'track_id' => 'required',
            'title' => 'required|max:255',
            'body' => 'required',
            'deadline' => 'required',
            'is_active' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->sendError('', 400, $validator->errors());
        }

        $data = $request->all();

        $task = Task::create($data);

        $this->logAdminActivity("created " . $task->title . " Task");

        return response([
            'data' => TaskResource::collection($task->paginate(20)),
        ], Response::HTTP_CREATED);

    }

    


    // $messages = [
    //     'user_id.unique' => "You have already submitted",
    //     'submission_link.required' => "Provide a submission link",
    //     'submission_link.url' => "Submission link must be a URL",
    //     'task_id.required' => "No task selected",
    // ];

    // $validator = Validator::make($request->all(), [
    //     'task_id' => ['bail', 'required', 'integer'],
    //     'user_id' => 'bail|required|integer',
    //     'submission_link' => 'bail|required',
    //     'comment' => 'bail|required|string',
    //     // 'is_submitted' => 'integer',
    //     // 'is_graded' => 'integer'
    // ], $messages);

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Task $id)
    {

        $this->middleware(['role:superadmin', 'role:admin']);

        $task = Task::find($id)->with('tracks');

        if ($task) {
            return TaskResource::collection($task);
        } else {
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

                $this->logAdminActivity("updated " . $task->title . " Task");
                return response([
                    'data' => new TaskResource($task),
                ]);
            }
        } else {
            return response([
                'message' => 'Task not Found',
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

        if (Task::destroy($id)) {

            $this->logAdminActivity("deleted a Task");
            return response('', 'Task successfully deleted');
        }
    }


    public function view_track_task($track_id)
    {

        $this->middleware(['role: intern', 'role:superadmin', 'role:admin']);

        $track_tasks = Task::where('track_id', $track_id)->orderBy('created_at', 'desc')->get();

        if ($track_tasks) {
            return TaskResource::collection($track_tasks);
        } else {
            return \response([
                'message' => 'Track task not available'
            ]);
        }
    }
    
    #Get tasks based on Interns track(s)
     public function intern_view_track_task()
    {
        $this->middleware(['role: intern']);
        
        $user_tracks = auth()->user()->track;

        dd($user_tracks);
                         
         foreach($user_tracks as $user_track){
             
             //Get track id
             $track_id = Track::where('track_name', $user_track)->first();
             
             //Get all task for the task
             $track_tasks = Task::where('track_id', $track_id)->orderBy('created_at', 'desc')->get();
             
             if ($track_tasks) {
                return TaskResource::collection($track_tasks);
             } else {
                return \response([
                    'message' => 'Track task not available'
                ]);
             }
         }
        
     } 

    public function view_task($id)
    {

        $this->middleware(['role:superadmin', 'role:admin']);

        $task = Task::where('id', $id)->get();

        if ($task) {
            return TaskResource::collection($task);
        } else {
            return \response([
                'message' => 'No Task available'
            ]);
        }

    }
  
//    public function viewTrack($track){
//
//
//        $this->middleware(['role: intern', 'role:superadmin']);
//
//        $user_id = auth()->user()->id;
//
//        $user_track = TrackUser::where('user_id', $user_id)->where('track_name', 'LIKE', "%{$track}%");
//
//
//        if($user_track){
//            $task = Task::find($user_track->id);
//
//            return TaskResource::collection($task);
//        }else{
//            return \response([
//                'message' => 'Track not available'
//            ]);
//        }
//    }
//
//    public function viewTask($track_id, $id){
//
//        $this->middleware(['role: intern', 'role:superadmin']);
//
//        $user_id = auth()->user()->id;
//
//        $user_track = TrackUser::where('user_id', $user_id)->where('track_trid', $track_id);
//
//        $task_track = Task::where('track_id', $track_id)->where();
//
//        if($user_track && $task_track){
//
//            $task = Task::find($user_track->id);
//            return TaskResource::collection($task);
//        }else{
//            return \response([
//                'message' => 'No Task available'
//            ]);
//        }
//    }

    public function changeTaskStatus(Request $request, $id)
    {
        $this->middleware(['role:superadmin', 'role:admin']);
        $request->validate([
            'status' => ['required', Rule::in(['OPEN', 'CLOSED'])],
        ]);

        $task = Task::find($id)->first();
        $task->status = $request->status;
        if ($task->save()) {

            $this->logAdminActivity("changed " . $task->title . " Task status");
            return self::SUCCESS('Task ' . $request->status . ' successfully', $task);
        }
    }

    public function getActiveTasks()
    {
        $tasks = Task::with(['track', 'tasks'])->where('is_active', '1')->orderBy('id', 'desc')->get();
            
        if($tasks){
            return TaskResource::collection($tasks);
        }else{
            return response([
                'message' => Response::HTTP_NOT_FOUND
            ], 404);
        }
    }

}