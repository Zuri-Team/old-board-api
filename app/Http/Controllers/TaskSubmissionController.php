<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskSubmission;
use App\Http\Resources\TaskSubmissionResource;
use App\Task;
use App\Slack;
use App\User;
use App\TaskSubmission;
use App\TrackUser;
use App\Course;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use App\Http\Classes\ResponseTrait;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Http\Classes\ActivityTrait;
use DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\TeamTaskImport;
use App\Exports\StartNGFinalExports;
use App\Probation;



class TaskSubmissionController extends Controller
{

    use ResponseTrait;
    use ActivityTrait;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!auth('api')->user()->hasAnyRole(['admin', 'superadmin'])) {
            return $this->ERROR('You dont have the permission to perform this action');
        }
        $submissions = TaskSubmission::orderBy('created_at', 'desc')->with(['user', 'task', 'graded_by:id,firstname,lastname,email,username'])->get();
        if ($submissions) {
            // return TaskSubmissionResource::collection($submissions);
            return $this->sendSuccess($submissions, 'AllTasks submissions fetched', 200);
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
    public function store(StoreTaskSubmission $request)
    {
        //$u = auth()->user();
        //return $u;

        //dd('ddi');

//         if (!auth('api')->user()->hasRole(['intern'])) {
//         //if (!auth('api')->user()->hasAnyRole(['admin', 'superadmin', 'intern'])) {    
//             return $this->ERROR('You dont have the permission to perform this action');
//         }

        //$data = $request->validated();

        // Check if the User is found in the trackUser
        if (!TrackUser::where('user_id', $data['user_id'])->first()) {
            // if (!TrackUser::where('user_id', auth()->user()->id)) {
            // return $this->errorResponse('User does not belong to this track', 422);
            return $this->sendError('User does not belong to this track', 422, []);
        }

        // Check if the Task Submission date has past => done
        if (Task::find($data['task_id'])->first()->deadline < Carbon::now()) {
            // return $this->errorResponse('Submission date has elapsed', 422);
            return $this->sendError('Deadline date has elapsed', 422, []);
        }

        // Check if Status is still open for submission.
        if (Task::find($data['task_id'])->first()->status == 'CLOSED') {
            // return $this->errorResponse('Task submission Closed', 422);
            return $this->sendError('Task submission Closed', 422, []);
        }

        $task = TaskSubmission::create($request->all());
        if ($task) {
            // return new TaskSubmissionResource($task);
            return $this->sendSuccess($task, 'Task submitted successfully', 200);
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
        if (!auth('api')->user()->hasAnyRole(['admin', 'superadmin', 'intern'])) {
            return $this->ERROR('You dont have the permission to perform this action');
        }

        if ($submission = TaskSubmission::whereId($id)->where('user_id', auth('api')->user()->id)->first()) {
            // return new TaskSubmissionResource($submission);
            return $this->sendSuccess($submission, 'Task submission fetched', 200);
            
        } else {
            // return $this->errorResponse('Submission not found', 404);
            return $this->sendError('Submission not found', 404, []);
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
        if (!auth('api')->user()->hasAnyRole(['admin', 'superadmin'])) {
            return $this->ERROR('You dont have the permission to perform this action');
        }
        
        $intern_submission = TaskSubmission::destroy($id);
        if ($intern_submission) {
            return $this->sendSuccess($intern_submission, 'Task Submitted deleted', 200);
        }
        return $this->sendError('Internal server error.', 500, []);
    }
    
    /**
     * View all interns score for a task resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function view_all_intern_grades(Request $request, $id){

        if (!auth('api')->user()->hasAnyRole(['admin', 'superadmin'])) {
            return $this->ERROR('You dont have the permission to perform this action');
        }

        $interns_task_submission = TaskSubmission::
        with(['task', 'user'])
            ->where('task_id', $id)->get();

        if ($interns_task_submission) {
            return new TaskSubmissionResource($interns_task_submission);
        } else {
            // return $this->errorResponse('Task has not been graded', 404);
            return $this->sendError('Task has not been graded', 404, []);
        }
    }

    public function grade_task_for_interns(Request $request, $id){

        $validator = Validator::make($request->all(), [
            'grade_score' => 'required|array',
        ]);

        if ($validator->fails()) {
            return $this->sendError('', 400, $validator->errors());
        }

        if (!auth('api')->user()->hasAnyRole(['admin', 'superadmin'])) {
            return $this->ERROR('You dont have the permission to perform this action');
        }

        $interns_task_submissions = TaskSubmission::where('task_id', $id)->get();

        $scores = $request->input('grade_score');

        foreach ($scores as $score) {
            //dd($value);
            $data = [
                'grade_score' => $score['grade_score']
            ];

            TaskSubmission::where('task_id', $id)->update($data);
        }

    }

    public function grade_intern_task(Request $request, $id){

        if (!auth('api')->user()->hasAnyRole(['admin', 'superadmin'])) {
            return $this->ERROR('You dont have the permission to perform this action');
        }

        $validator = Validator::make($request->all(), [
            'grade_score' => 'bail|required',
            'user_id' => 'bail|required|integer'
        ]);

        if ($validator->fails()) {
            return $this->sendError('', 400, $validator->errors());
        }

        $user_id = $request->user_id;

        $task = Task::find($id);
        if(!$task){
            return $this->sendError('Task doesnt exists', 404, []);
        }

        $intern_submission = TaskSubmission::where('user_id', $user_id)->where('task_id', $id)->first();

        if ($intern_submission) {
            $data = [
                'grade_score' => (int)$request->input('grade_score'),
            ];

            // SEND NOTIFICATION HERE
            $intern_submission->grade_score = $request->input('grade_score');
            $intern_submission->is_graded = 1;
            $intern_submission->graded_by = auth()->id();
            $res = $intern_submission->save();
            
            // $res =  $intern_submission->update($data);

            if($res){
                $user = auth()->user();
                $message = $user->firstname . ' ' . $user->lastname . ' ('. $user->email .') graded ' . $intern_submission->user->firstname . ' ('. $intern_submission->user->email . ') ' . $intern_submission->user->lastname. ', Score ' . $request->input('grade_score'). ' for task: '. $intern_submission->task->title;
                $this->logAdminActivity($message);

                return $this->sendSuccess($intern_submission, 'Task submission successfully graded', 200);
            }else{
                return $this->sendError('Task submission wasn not graded', 422, []);
            }

            //jude
            // return TaskSubmission::find($id)->update($data);
        } else {
            // return $this->errorResponse('Task has not been graded', 404);
            return $this->sendError('Intern has not submitted this task', 404, []);
        }
    }

    public function intern_view_task_grade(Request $request, $user_id, $id){

        if (!auth('api')->user()->hasAnyRole(['admin', 'superadmin', 'intern'])) {
            return $this->ERROR('You dont have the permission to perform this action');
        }

        $intern_submission = TaskSubmission::where('user_id', $user_id)->where('task_id', $id)->get();

        if ($intern_submission) {
            // return TaskSubmissionResource($intern_submission);
            return $this->sendSuccess($intern_submission, 'Task submission fetched', 200);
        } else {
            // return $this->errorResponse('Task has not been graded', 404);
            return $this->sendError('Task has not been graded', 404, []);
        }
    }

    public function intern_view_task_grades($id){

        if (!auth('api')->user()->hasAnyRole(['admin', 'superadmin', 'intern'])) {
            return $this->ERROR('You dont have the permission to perform this action');
        }

        $task_submission_grades = TaskSubmission::where('task_id', $id)->get();

        if ($task_submission_grades) {
            // return TaskSubmissionResource($task_submission_grades);
            return $this->sendSuccess($task_submission_grades, 'Task submission fetched', 200);
        } else {
            // return $this->errorResponse('Task has not been graded', 404);
            return $this->sendError('Task has not been graded', 404, []);
        }
    }

    //retrieve_interns_submission
    public function retrieve_interns_submission($id)
    {

        $user = auth()->user();

        // if (!auth('api')->user()->hasAnyRole(['intern', 'admin', 'superadmin'])) {
        //     return $this->ERROR('You dont have the permission to perform this action');
        // }

        $submissions = TaskSubmission::where('task_id', $id)->where('user_id', $user->id)->with('user')->get();
        if ($submissions) {
            // return TaskSubmissionResource::collection($submissions);
            return $this->sendSuccess($submissions, 'AllTasks submissions fetched', 200);
        }
    }
    
    public function admin_retrieve_interns_submission($id)
    {
        if (!auth('api')->user()->hasAnyRole(['admin', 'superadmin'])) {
            return $this->ERROR('You dont have the permission to perform this action');
        }
        $submissions = TaskSubmission::where('task_id', $id)->with('user')->get();
        if ($submissions) {
            // return TaskSubmissionResource::collection($submissions);
            return $this->sendSuccess($submissions, 'AllTasks submissions fetched', 200);
        }
    }
    
    public function delete_interns_submissions($taskId)
    {
        if (!auth('api')->user()->hasAnyRole(['admin', 'superadmin'])) {
            return $this->ERROR('You dont have the permission to perform this action');
        }
        
        $interns_submissions = TaskSubmission::where('task_id', $taskId)->delete();
        
        if ($interns_submissions) {
            return $this->sendSuccess($interns_submissions, 'All Submissions deleted', 200);
        }
        return $this->sendError('Internal server error.', 500, []);
    }
    
    public function delete_all_submission(){
        
        if (!auth('api')->user()->hasAnyRole(['admin', 'superadmin'])) {
            return $this->ERROR('You dont have the permission to perform this action');
        }
        
        if (TaskSubmission::delete()) {
            return $this->sendSuccess($interns_submissions, 'All Submissions deleted', 200);
        }
        return $this->sendError('Internal server error.', 500, []);
    }

    public function submit(Request $request)
    {
        // check if task exist
        $checkTask = Task::where('id', $request->task_id)->first();

        if(!$checkTask){
            return $this->sendError('task does not exists', 404, []);
        }

        $check = TaskSubmission::where('task_id', $request->task_id)->where('user_id', $request->user_id)->first();

        if($check){
            return $this->sendError('You have already submitted this task', 422, []);
        }

        // Check if the User is found in the trackUser
        if (!TrackUser::where('user_id', $request['user_id'])->get()) {
            // if (!TrackUser::where('user_id', auth()->user()->id)) {
            // return $this->errorResponse('User does not belong to this track', 422);
            return $this->sendError('User does not belong to this track', 422, []);
        }

        // Check if the Task Submission date has past => done
        // if ($checkTask->deadline->lte(Carbon::now())) {
            //if ($checkTask->deadline < Carbon::now()) {
            if ($checkTask->deadline < Carbon::now()) {
            // return $this->errorResponse('Submission date has elapsed', 422);
            return $this->sendError('Deadline date has elapsed', 422, []);
        }

        // Check if Status is still open for submission.
        if ($checkTask->status == 'CLOSED') {
            // return $this->errorResponse('Task submission Closed', 422);
            return $this->sendError('Task submission Closed', 422, []);
        }

        //check if task is the second task
        //if yes, confirm if first task has been submited
        //if yes, promote
        //if not, don't allow submission

        $lucidTaskId = 1;
        $githubTaskId = 2;
        $designTaskId = 4;

        if($request->task_id == $lucidTaskId){
            $link = $request->submission_link;
            $word = 'lucid.blog';

            if(strpos($link, $word) !== false){
                $task = TaskSubmission::create($request->all());
                if ($task) {
                    // return new TaskSubmissionResource($task);
                    return $this->sendSuccess($task, 'Task submitted successfully', 200);
                }
            } else{
                return $this->sendError('Invalid submission for Lucid task', 400, []);
            }
        }else if($request->task_id == $githubTaskId){
            $u = auth()->user();
            $checkPrev = TaskSubmission::where('user_id', $u->id)->where('task_id', $lucidTaskId)->count();

            if($checkPrev > 0){
                $link = $request->submission_link;
                $word = 'github.com';

                if(strpos($link, $word) !== false){
                    $task = TaskSubmission::create($request->all());
                    if ($task) {
                        Slack::removeFromChannel($u->slack_id, '0');
                        Slack::addToChannel($u->slack_id, '1');
                        $u->stage = 1;
                        $u->save();
                        return $this->sendSuccess($task, 'Task submitted successfully', 200);
                    }else{
                        return $this->sendError('Something went wrong', 400, []);
                    }
                } else{
                    return $this->sendError('Invalid submission for Github task', 400, []);
                }
            }

            return $this->sendError('Submit your Lucid Task first', 400, []);
        }else{
            $task = TaskSubmission::create($request->all());
            if ($task) {
                // return new TaskSubmissionResource($task);
                return $this->sendSuccess($task, 'Task submitted successfully', 200);
            }else{
                return $this->sendError('Something went wrong', 400, []);
            }
        }

        

    }

    public function promote(){
        $users = User::where('role', 'intern')->where('stage', 1)->get();

        foreach($users as $user){
            //get all their submissions
            $submissions = $user->submissions;
            $submissionsArray = $submissions->pluck('task_id')->all();
            $courses = $user->courses;
            $tasksArray = array();
            foreach($courses as $course){
                $aTask = Task::where('course_id', $course->id)->orderBy('created_at', 'asc')->first();
                array_push($tasksArray, $aTask->id);
            }

            $diff = array_diff($tasksArray, $submissionsArray);
            $stage = $user->stage;

            if(count($diff) == 0){
                //promote user
                if($stage == 1){
                    $slack_id =  $user->slack_id;
                    Slack::removeFromChannel($slack_id, 1);
                    Slack::addToChannel($slack_id, 2);
                    $user->stage = 2;
                    $user->save();
                }
            }
            // else{
            //     //demote if in stage 1
            //     if($stage == 2){
            //         $slack_id =  $user->slack_id;
            //         Slack::removeFromChannel($slack_id, 2);
            //         Slack::addToChannel($slack_id, 1);
            //         $user->stage = 1;
            //         $user->save();
            //     }
            // }
        }
        return $this->sendSuccess($user, 'successfully promoted interns', 200);

    }

    public function promote_to_stage_2(){
        $users = User::where('role', 'intern')->where('stage', 1)->get();

        foreach($users as $user){
            //get all their submissions
            $submissions = $user->submissions;
            $submissionsArray = $submissions->pluck('task_id')->all();
            $courses = $user->courses;
            $tasksArray = array();
            foreach($courses as $course){
                $aTask = Task::where('course_id', $course->id)->orderBy('created_at', 'asc')->first();
                array_push($tasksArray, $aTask->id);
            }

            $diff = array_diff($tasksArray, $submissionsArray);
            if(count($diff) == 0){
                //promote user
                $slack_id =  $user->slack_id;
                Slack::removeFromChannel($slack_id, 1);
                Slack::addToChannel($slack_id, 2);
                $user->stage = 2;
                $user->save();
            }else{
                continue;
            }
        }
        return $this->sendSuccess($user, 'successfully promoted interns', 200);
    }

    public function array_flatten(array $array)
{
    $flat = array(); // initialize return array
    $stack = array_values($array); // initialize stack
    while($stack) // process stack until done
    {
        $value = array_shift($stack);
        if (is_array($value)) // a value to further process
        {
            $stack = array_merge(array_values($value), $stack);
        }
        else // a value to take
        {
           $flat[] = $value;
        }
    }
    return $flat;
}

    public function promote_to_stage_3(){
        $users = User::where('role', 'intern')->where('stage', 2)->get();
        $count = 0;
        $rr = array();

        foreach($users as $user){
            //get all their submissions
            $submissions = $user->submissions;
            $submissionsArray = $submissions->pluck('task_id')->all();
            $courses = $user->courses;
            $tasksArray = array();
            if(count($submissionsArray) > 0 && count($courses) > 0){
            foreach($courses as $course){
                // $aTask = Task::where('course_id', $course->id)->where('id', '!=', 88)->where('id', '!=', 87)->orderBy('created_at', 'asc')->get();
                // $aTask = Task::where('course_id', $course->id)->whereIn('id', [49, 71, 74, 83, 51, 73, 48, 50, 52, 76, 53, 68, 72, 82])->get();
                $aTask = Task::where('course_id', $course->id)->whereIn('id', [48, 76, 82, 53, 50, 83, 73, 68, 74, 71, 72, 52])->get();
                $arrT = $aTask->pluck('id')->all();
                // array_push($tasksArray, $aTask->id);
                $r = array();
                array_push($tasksArray, $arrT);
                // $tasksArray = array_merge($tasksArray, $arrT);
            }

            $tasksArray = $this->array_flatten($tasksArray);
            $diff = array_diff($tasksArray, $submissionsArray);
            if(count($diff) == 0){
                //promote user
                $slack_id =  $user->slack_id;
                Slack::removeFromChannel($slack_id, 2);
                Slack::addToChannel($slack_id, 3);
                $user->stage = 3;
                $user->save();
            }else{
                continue;
            }

        }

            
        }
        return $this->sendSuccess([$count, $rr], 'successfully promoted interns', 200);
    }

    public function promote_to_stage_4(){
        $users = User::where('role', 'intern')->where('stage', 3)->get();
        $count = 0;
        $rr = array();

        foreach($users as $user){
            //get all their submissions
            $submissions = $user->submissions;
            $submissionsArray = $submissions->pluck('task_id')->all();
            $courses = $user->courses;
            $tasksArray = array();
            if(count($submissionsArray) > 0 && count($courses) > 0){
            foreach($courses as $course){
                $aTask = Task::where('course_id', $course->id)->whereIn('id', [88, 114, 93, 87, 89, 113, 119, 120, 122, 124, 126, 125])->get();
                $arrT = $aTask->pluck('id')->all();
                $r = array();
                array_push($tasksArray, $arrT);
            }

            $tasksArray = $this->array_flatten($tasksArray);
            $diff = array_diff($tasksArray, $submissionsArray);
            if(count($diff) == 0){
                //promote user
                // $count += 1;
                // array_push($rr, $user->username);
                $slack_id =  $user->slack_id;
                Slack::removeFromChannel($slack_id, 3);
                Slack::addToChannel($slack_id, 4);
                $user->stage = 4;
                $user->save();
            }else{
                continue;
            }

        }
            
        }
        return $this->sendSuccess([$count, $rr], 'successfully promoted interns', 200);
    }

    public function promote_to_stage_5(){
        $users = User::where('role', 'intern')->where('stage', 4)->get();
        $count = 0;
        $rr = array();

        foreach($users as $user){
            //get all their submissions
            $submissions = $user->submissions;
            $submissionsArray = $submissions->pluck('task_id')->all();
            $courses = $user->courses;

            $coursesArr = $courses->pluck('id')->all();
            $legitCourses = [1, 2, 3, 4, 5, 6, 8, 9, 10, 11, 12];
            // $arrDiff = array_diff($legitCourses, $coursesArr);
            $arrDiff = array_diff($coursesArr, $legitCourses);

            $tasksArray = array();
            if(count($submissionsArray) > 0 && count($courses) > 0 && count($arrDiff) == 0){
            foreach($courses as $course){
                $aTask = Task::where('course_id', $course->id)->whereIn('id', [128, 131, 132, 139, 140, 141, 145, 146, 147, 148, 149])->get();
                $arrT = $aTask->pluck('id')->all();
                $r = array();
                array_push($tasksArray, $arrT);
            }

            $tasksArray = $this->array_flatten($tasksArray);
            $diff = array_diff($tasksArray, $submissionsArray);
            

            if(count($diff) == 0){
                //promote user
                // $count += 1;
                // array_push($rr, $user->username);
                $slack_id =  $user->slack_id;
                Slack::removeFromChannel($slack_id, 4);
                Slack::addToChannel($slack_id, 5);
                $user->stage = 5;
                $user->save();
            }else{
                continue;
            }

        }
            
        }
        return $this->sendSuccess([$count, $rr], 'successfully promoted interns', 200);
    }

    public function promote_admins($stage){
        $users = User::where('role', 'admin')->get();

        foreach($users as $user){
                //promote user
                $slack_id =  $user->slack_id;
                // Slack::removeFromChannel($slack_id, 1);
                Slack::addToChannel($slack_id, $stage);
                $user->stage = $stage;
                $user->save();
        }
        return $this->sendSuccess($user, 'successfully promoted admin', 200);
    }

    public function remove_stage_3(){
        $users = User::where('role', 'intern')->where('stage', 3)->get();

        foreach($users as $user){
            $slack_id =  $user->slack_id;
            Slack::removeFromChannel($slack_id, 3);
            Slack::addToChannel($slack_id, 2);
            $user->stage = 2;
            $user->save();
        }

    }


    public function test_promotion(){

        $users = User::where('role', 'intern')->where('stage', 4)->get();
        $count = 0;
        $rr = array();

        foreach($users as $user){
            //get all their submissions
            $submissions = $user->submissions;
            $submissionsArray = $submissions->pluck('task_id')->all();
            $courses = $user->courses;

            $coursesArr = $courses->pluck('id')->all();
            $legitCourses = [1, 2, 3, 4, 5, 6, 8, 9, 10, 11, 12];
            // $arrDiff = array_diff($legitCourses, $coursesArr);
            $arrDiff = array_diff($coursesArr, $legitCourses);

            $tasksArray = array();
            if(count($submissionsArray) > 0 && count($courses) > 0 && count($arrDiff) == 0){
            foreach($courses as $course){
                $aTask = Task::where('course_id', $course->id)->whereIn('id', [128, 131, 132, 139, 140, 141, 145, 146, 147, 148, 149])->get();
                $arrT = $aTask->pluck('id')->all();
                $r = array();
                array_push($tasksArray, $arrT);
            }

            $tasksArray = $this->array_flatten($tasksArray);

            $diff = array_diff($tasksArray, $submissionsArray);
            if(count($diff) == 0){
                array_push($rr, $user->username);
                $count += 1;
            }else{
                continue;
            }

        }
            
        }
        return $this->sendSuccess([$count, $rr], 'successfully promoted interns', 200);
    }

    public function grading_task_submissions(Request $request){
        $task_id = $request->task_id;
        $grade = $request->grade;

        if(!$task_id){  
            return $this->sendError('No Task ID', 404, []);
        }

        if(!$grade){  
            return $this->sendError('No Grade', 404, []);
        }

        $res = TaskSubmission::where('task_id', $task_id)->update([
            'grade_score' => $grade,
            'is_graded' => true,
            'graded_by' => 2
        ]);

        return $this->sendSuccess($res, 'successfully graded task', 200);
    }

    public function export() 
    {
        $export = new InvoicesExport([
            [1, 2, 3],
            [4, 5, 6]
        ]);

        return Excel::download($export, 'invoices.xlsx');
    }

    public function percent($percent){
        $users = User::where('stage', 5)->get();
        $arr = array();
        $count = 0;

        foreach($users as $user){
            $coursesTotal = $user->courseTotal();
            $totalScore = $user->totalScore();
            $courses = $user->courses;

            $percentValue = round(($percent / 100) * $coursesTotal, 2);
            $userPercent = round(($totalScore / $coursesTotal) * 100, 2);

            if($totalScore >= $percentValue && count($courses) > 0 && $totalScore > 0 && $coursesTotal > 0){
                // $arr['interns'][] = $user->username . " ------------- " . $totalScore . " out of " . $coursesTotal . " -------------------- Percent: ". $userPercent;
                $arr['interns'][] = $user->email;
                $count++;
            }
            
        }
        $arr['count'] = $count;
        return $arr;
    }

    public function dynamic_percent($percent, $type){
        $users = User::where('stage', 5)->get();
        $arr = array();
        $count = 0;

        foreach($users as $user){
            $coursesTotal = $user->courseTotal();
            $totalScore = $user->totalScore();
            $courses = $user->courses;

            $percentValue = round(($percent / 100) * $coursesTotal, 2);
            $userPercent = round(($totalScore / $coursesTotal) * 100, 2);

            // if($type == 'score'){
            //     if($totalScore >= $percent && count($courses) > 0 && $totalScore > 0 && $coursesTotal > 0){
            //         $arr['interns'][] = $user->username . " ------------- " . $totalScore . " out of " . $coursesTotal . " -------------------- Percent: ". $userPercent;
            //         $count++;
            //     }
            // }else{
                if($totalScore >= $percentValue && count($courses) > 0 && $totalScore > 0 && $coursesTotal > 0){
                    $arr['interns'][] = $user->email;
                    // $arr['interns'][] = $user->username . " ------------- " . $totalScore . " out of " . $coursesTotal . " -------------------- Percent: ". $userPercent;
                    $count++;
                }
            // }
            
        }
        $arr['count'] = $count;
        return $arr;

    }

    public function exportFinals(){
        return Excel::download(new StartNGFinalExports, 'startng_finals.xlsx');
    }

    public function check_percent($percent){

        //get all courses
        $courses = Course::all();

        $arr = array();
        foreach($courses as $course){
            $i = array();
            //get maximum attainable points
            $tasksTotal = DB::table('tasks')
                         ->select(DB::raw('SUM(total_score) as total, course_id'))
                         ->where('course_id', '=', $course->id)
                         ->groupBy('course_id')
                         ->first();
            $sub = $this->getCourseSubmissions($course->id, $tasksTotal->total, $percent);
            
            $percentValue = round(($percent / 100) * $tasksTotal->total, 2);

            $i['total'] = $tasksTotal;
            $i['course_name'] = $course->name;
            $i['percent'] = $percent;
            $i['percent_value'] = $percentValue;
            $i['count'] = count($sub);
            $i['sub'] = $sub;
            
            $arr[] = $i;
        }
        //get maximum attainable points for each courses
        //get list of interns that have 80% of the score

        return $arr;
    }

    public function getCourseSubmissions($courseId, $total, $percent){
        $arr = array();
        $tasks = Task::where('course_id', $courseId)->get();
        $users = User::where('stage', 5)->get();
        foreach($users as $user){
            foreach($tasks as $task){
                $subs = DB::table('task_submissions')
                             ->select(DB::raw('SUM(grade_score) as score, user_id'))
                             ->where('task_id', '=', $task->id)
                             ->where('user_id', '=', $user->id)
                             ->groupBy('user_id')
                             ->get()
                             ->toArray();

                if(!is_null($subs) && !count($subs) == 0){
                    $arr[] = $subs;
                }
            }
        }

        $sum = array_reduce($arr, function ($a, $b) {
            $b = (array) $b[0];
            isset($a[$b['user_id']]) ? $a[$b['user_id']]['score'] += $b['score'] : $a[$b['user_id']] = $b;  
            return $a;
        });

        $percentValue = round(($percent / 100) * $total, 2);
        $t = array();
        foreach(array_values($sum) as $j){
            if($j['score'] >= $percentValue){
                $t[] = $j;
            }
        }
        return $t;

    } 


    public function submitTeamTask(Request $request)
    {
            if($request->hasFile('sheet')){

                $sheet = request()->file('sheet')->getRealPath();
                $import = Excel::import(new TeamTaskImport(), $request->file('sheet'));
    
                if($import){
                return $this->sendSuccess('Team task submitted successfully Imported', 200);
    
                }else{
                    return $this->sendError('Could not process', 500, []);
                }
            }
    }
    public function moveToZero(){
        $users = User::where('role', 'intern')->get();

        foreach($users as $user){

            TrackUser::updateOrCreate([
                'user_id' => $user->id,
                'track_id' => 6
            ]);

            // $t->save();
                //promote user
                // $slack_id =  $user->slack_id;
                // Slack::removeFromChannel($slack_id, 1);
                // Slack::addToChannel($slack_id, 0);
        }
        return $this->sendSuccess($user, 'successfully moved interns to general', 200);
    }

    public function sendSlackMessage(Request $request){
        $message = $request->message;
        $channel = '#' . $request->channel;

        $result = SlackChat::message($channel, $message);
        return $result;
    }

    public function task_2_promotion(Request $request){
        // $url = $request->url;

        // $cURLConnection = curl_init();
        // curl_setopt($cURLConnection, CURLOPT_URL, $url);
        // curl_setopt($cURLConnection, CURLOPT_RETURNTRANSFER, true);

        // $submissionList = curl_exec($cURLConnection);
        // curl_close($cURLConnection);

        // $data = json_decode($submissionList, true);

        $json = '
        [
            [
                {
                   "file":"00090-2rhayor.js",
                   "output":"Hello World, this is Motunrayo Ojo with HNGi7 ID HNG-00090 using JavaScript for stage 2 task itunshy@gmail.com",
                   "name":"Motunrayo Ojo",
                   "id":"HNG-00090",
                   "email":"itunshy@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"00103-haneefah.js",
                   "output":"Hello World, this is Haneefah Aliu with HNGi7 ID HNG-00103 using javascript for stage 2 task aliuhaneefah@gmail.com",
                   "name":"Haneefah Aliu",
                   "id":"HNG-00103",
                   "email":"aliuhaneefah@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"00112-RiyaJ.py",
                   "output":"Hello World, this is Riya J with HNGi7 ID HNG-00112 using Python for stage 2 task blueroseri121@gmail.com",
                   "name":"Riya J",
                   "id":"HNG-00112",
                   "email":"blueroseri121@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"00150-6lackHat.py",
                   "output":"Hello World, this is Paul Ogolla with HNGi7 ID HNG-00150 using Python for stage 2 task paulotieno2@gmail.com",
                   "name":"Paul Ogolla",
                   "id":"HNG-00150",
                   "email":"paulotieno2@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"00175-winnie.php",
                   "output":"Hello World, this is Kiage Winnie with HNGi7 ID HNG-00175 using php for stage 2 task kiagew@gmail.com",
                   "name":"Kiage Winnie",
                   "id":"HNG-00175",
                   "email":"kiagew@gmail.com",
                   "language":"php",
                   "status":"Pass"
                },
                {
                   "file":"00177-Drebakare.js",
                   "output":"Hello World, this is Bakare Damilare with HNGi7 ID HNG-00177 using Javascript for stage 2 task emmanueldmlr@gmail.com",
                   "name":"Bakare Damilare",
                   "id":"HNG-00177",
                   "email":"emmanueldmlr@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"00198-EEBRU.js",
                   "output":"Hello World, this is Ibrahim Alao with HNGi7 ID HNG-00198 using JavaScript for stage 2 task alaoopeyemi101@gmail.com",
                   "name":"Ibrahim Alao",
                   "id":"HNG-00198",
                   "email":"alaoopeyemi101@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"00213-yiltech.js",
                   "output":"Hello World, this is John Rotgak with HNGi7 ID HNG-00213 using javascript for stage 2 task info2rotgak@gmail.com",
                   "name":"John Rotgak",
                   "id":"HNG-00213",
                   "email":"info2rotgak@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"00216-Maureen.py",
                   "output":"Hello World, this is Maureen Thama with HNGi7 ID HNG-00216 using Python for stage 2 task maureenthamar@gmail.com",
                   "name":"Maureen Thama",
                   "id":"HNG-00216",
                   "email":"maureenthamar@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"00223-Feyikemi.js",
                   "output":"Hello World, this is Agboola Feyikemi with HNGi7 ID HNG-00223 using Javascript for stage 2 task agboolafeyikemi93@gmail.com",
                   "name":"Agboola Feyikemi",
                   "id":"HNG-00223",
                   "email":"agboolafeyikemi93@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"00224-bernice.js",
                   "output":"Hello World, this is Bernice Johnson with HNGi7 ID HNG-00224 using JavaScript for stage 2 task bernicejohnsonuche@gmail.com",
                   "name":"Bernice Johnson",
                   "id":"HNG-00224",
                   "email":"bernicejohnsonuche@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"00232-BabatundeAdeniran.js",
                   "output":"Hello World, this is Babatunde Adeniran with HNGi7 ID HNG-00232 using JavaScript for stage 2 task tuneshdev@gmail.com",
                   "name":"Babatunde Adeniran",
                   "id":"HNG-00232",
                   "email":"tuneshdev@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"00245-Mayowa.js",
                   "output":"Hello World, this is Mayowa Odebode with HNGi7 ID HNG-00245 using Javascript for stage 2 task mayowaodebode@gmail.com",
                   "name":"Mayowa Odebode",
                   "id":"HNG-00245",
                   "email":"mayowaodebode@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"00284-logan1x.py",
                   "output":"Hello World, this is Khushal Sharma with HNGi7 ID HNG-00284 using python for stage 2 task sharmakhushal78@gmail.com",
                   "name":"Khushal Sharma",
                   "id":"HNG-00284",
                   "email":"sharmakhushal78@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"00296-Vikas.py",
                   "output":"Hello World, this is Vikas Rathore with HNGi7 ID HNG-00296 using Python for stage 2 task vikasrathour162@gmail.com",
                   "name":"Vikas Rathore",
                   "id":"HNG-00296",
                   "email":"vikasrathour162@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"00300-Aus.js",
                   "output":"Hello World, this is Tangban Austin Bisong with HNGi7 ID HNG-00300 using javascript for stage 2 task aotangban@gmail.com",
                   "name":"Tangban Austin Bisong",
                   "id":"HNG-00300",
                   "email":"aotangban@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"00345-sabina.php",
                   "output":"Hello World, this is Sabina Moraa with HNGi7 ID HNG-00345 using php for stage 2 task sabinabenerdette@gmail.com",
                   "name":"Sabina Moraa",
                   "id":"HNG-00345",
                   "email":"sabinabenerdette@gmail.com",
                   "language":"php",
                   "status":"Pass"
                },
                {
                   "file":"00351-Mystry.js",
                   "output":"Hello World, this is Alaneme Henry with HNGi7 ID HNG-00351 using JavaScript for stage 2 task alanemehenry6@gmail.com",
                   "name":"Alaneme Henry",
                   "id":"HNG-00351",
                   "email":"alanemehenry6@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"00359-winner.js",
                   "output":"Hello World, this is Victor Nwimo with HNGi7 ID HNG-00359 using JavaScript for stage 2 task vnwimo13@gmail.com",
                   "name":"Victor Nwimo",
                   "id":"HNG-00359",
                   "email":"vnwimo13@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"00369-Operah.js",
                   "output":"Hello World, this is Opeyemi Bantale with HNGi7 ID HNG-00369 using JavaScript for stage 2 task opeyemibantale@gmail.com",
                   "name":"Opeyemi Bantale",
                   "id":"HNG-00369",
                   "email":"opeyemibantale@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"00383-Daniel2code.js",
                   "output":"hello world, this is Daniel Nwoke with HNGi7 ID HNG-00383 using Javascript for stage 2 task danielnwoke20@gmail.com",
                   "name":"Daniel Nwoke",
                   "id":"HNG-00383",
                   "email":"danielnwoke20@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"00401-yuceehuchey.py",
                   "output":"Hello World, this is Nduanya Uchenna with HNGi7 ID HNG-00401 using python for stage 2 task yuceehuchey@gmail.com",
                   "name":"Nduanya Uchenna",
                   "id":"HNG-00401",
                   "email":"yuceehuchey@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"00417-rotense.js",
                   "output":"Hello World, this is Rotense Gabriel with HNGi7 ID HNG-00417 using javascript for stage 2 task rotense@gmail.com",
                   "name":"Rotense Gabriel",
                   "id":"HNG-00417",
                   "email":"rotense@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"00425-gabriel1990.js",
                   "output":"Hello World, this is Elakpa Gabriel with HNGi7 ID HNG-00425 using javascript for stage 2 task anselmgerald@gmail.com",
                   "name":"Elakpa Gabriel",
                   "id":"HNG-00425",
                   "email":"anselmgerald@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"00436-Ritika.php",
                   "output":"Hello World, this is Ritika Agrawal with HNGi7 ID HNG-00436 using php for stage 2 task ritikaagrawal339@gmail.com",
                   "name":"Ritika Agrawal",
                   "id":"HNG-00436",
                   "email":"ritikaagrawal339@gmail.com",
                   "language":"php",
                   "status":"Pass"
                },
                {
                   "file":"00485-Toluwani.js",
                   "output":"Hello World, this is Toluwani Elemosho with HNGi7 ID HNG-00485 using Javascript for stage 2 task toluwanielemosho@gmail.com",
                   "name":"Toluwani Elemosho",
                   "id":"HNG-00485",
                   "email":"toluwanielemosho@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"00487-Melissa.js",
                   "output":"Hello World, this is Melissa Ugrai with HNGi7 ID HNG-00487 using javaScript for stage 2 task mugrai@gmail.com",
                   "name":"Melissa Ugrai",
                   "id":"HNG-00487",
                   "email":"mugrai@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"00491-Hadeybamz.js",
                   "output":"Hello World, this is Bamgbala Shuaib Adeyemi with HNGi7 ID HNG-00491 using JavaScript for stage 2 task adeyemi.bamgbala@gmail.com",
                   "name":"Bamgbala Shuaib Adeyemi",
                   "id":"HNG-00491",
                   "email":"adeyemi.bamgbala@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"00494-vivyanne.py",
                   "output":"Hello World, this is Vivian Nwosu with HNGi7 ID HNG-00494 using python for stage 2 task viviannwosu05@gmail.com",
                   "name":"Vivian Nwosu",
                   "id":"HNG-00494",
                   "email":"viviannwosu05@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"00504-Femi.js",
                   "output":"Hello World, this is Akinsiku Oluwafemi with HNGi7 ID HNG-00504 using Javascript for stage 2 task akinsiku.o@yahoo.com",
                   "name":"Akinsiku Oluwafemi",
                   "id":"HNG-00504",
                   "email":"akinsiku.o@yahoo.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"00549-Dorcas.js",
                   "output":"Hello World, this is Dorcas Joe with HNGi7 ID HNG-00549 using JavaScript for stage 2 task dorcasejoe@gmail.com",
                   "name":"Dorcas Joe",
                   "id":"HNG-00549",
                   "email":"dorcasejoe@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"00553-CApitanMA.py",
                   "output":"Hello world, this is Muhammad-ahid Abdulsalam with HNGi7 ID HNG-00553 using python for stage 2 task muhahid18@gmail.com",
                   "name":"Muhammad-ahid Abdulsalam",
                   "id":"HNG-00553",
                   "email":"muhahid18@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"00605-jayumaks.py",
                   "output":"Hello World, this is Umakhihe Oluwashola with HNGi7 ID HNG-00605 using python for stage 2 task sholaumakhihe@gmail.com",
                   "name":"Umakhihe Oluwashola",
                   "id":"HNG-00605",
                   "email":"sholaumakhihe@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"00616-Osmanbell.js",
                   "output":"Hello World, this is Bello Usman Abiodun with HNGi7 ID HNG-00616 using Javascript for stage 2 task husmanbell@gmail.com",
                   "name":"Bello Usman Abiodun",
                   "id":"HNG-00616",
                   "email":"husmanbell@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"00684-jumoke.php",
                   "output":"Hello World, this is Jumoke Olaleye with HNGi7 ID HNG-00684 using PHP for stage 2 task jumokeolaleye0@gmail.com",
                   "name":"Jumoke Olaleye",
                   "id":"HNG-00684",
                   "email":"jumokeolaleye0@gmail.com",
                   "language":"php",
                   "status":"Pass"
                },
                {
                   "file":"00713-kingsley.py",
                   "output":"Hello world, this is Okpara Kingsley with HNGi7 ID HNG-00713 using python for stage 2 task buteng2000@yahoo.com",
                   "name":"Okpara Kingsley",
                   "id":"HNG-00713",
                   "email":"buteng2000@yahoo.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"00715-dayang.js",
                   "output":"Hello World, this is David Ayang with HNGi7 ID HNG-00715 using Javascript for stage 2 task david.ayang1@gmail.com",
                   "name":"David Ayang",
                   "id":"HNG-00715",
                   "email":"david.ayang1@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"00744-Naijabas.php",
                   "output":"Hello World, this is Ibitoye Basit with HNGi7 ID HNG-00744 using PHP for stage 2 task basitibitoye@gmail.com",
                   "name":"Ibitoye Basit",
                   "id":"HNG-00744",
                   "email":"basitibitoye@gmail.com",
                   "language":"php",
                   "status":"Pass"
                },
                {
                   "file":"00745-cyril.js",
                   "output":"Hello World, this is Chukwuebuka Muofunanya with HNGi7 ID HNG-00745 using Javascript for stage 2 task muofunanya3@gmail.com",
                   "name":"Chukwuebuka Muofunanya",
                   "id":"HNG-00745",
                   "email":"muofunanya3@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"00789-mstrings.js",
                   "output":"Hello World, this is PAULINUS MFON FAVOUR with HNGi7 ID HNG-00789 using javascript for stage 2 task mstrings11@gmail.com",
                   "name":"PAULINUS MFON FAVOUR",
                   "id":"HNG-00789",
                   "email":"mstrings11@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"00817-seniorman.js",
                   "output":"Hello World, this is Ezekiel Jude with HNGi7 ID HNG-00817 using javascript for stage 2 task ezekiel.jude5@gmail.com",
                   "name":"Ezekiel Jude",
                   "id":"HNG-00817",
                   "email":"ezekiel.jude5@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"00824-meritoriaghe.js",
                   "output":"Hello World, this is Oriaghemuoria Merit with HNGi7 ID HNG-00824 using javascript for stage 2 task meritoriaghe@gmail.com",
                   "name":"Oriaghemuoria Merit",
                   "id":"HNG-00824",
                   "email":"meritoriaghe@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"00825-Oyindamola.js",
                   "output":"Hello World, this is Kareem Taiwo with HNGi7 ID HNG-00825 using Javascript for stage 2 task oyindamolataiwo23@gmail.com",
                   "name":"Kareem Taiwo",
                   "id":"HNG-00825",
                   "email":"oyindamolataiwo23@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"00867-fatimah.js",
                   "output":"Hello World, this is Yusuf Fatimah Olayinka with HNGi7 ID HNG-00867 using javascript for stage 2 task Olayinkaf85@gmail.com",
                   "name":"Yusuf Fatimah Olayinka",
                   "id":"HNG-00867",
                   "email":"Olayinkaf85@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"00918-AISOSA.php",
                   "output":"Hello World, this is Aisosa Ugono with HNGi7 ID HNG-00918 using PHP for stage 2 task akpeugono@gmail.com",
                   "name":"Aisosa Ugono",
                   "id":"HNG-00918",
                   "email":"akpeugono@gmail.com",
                   "language":"php",
                   "status":"Pass"
                },
                {
                   "file":"00965-Huswat.js",
                   "output":"Hello World, this is Huswat Omowabi with HNGi7 ID HNG-00965 using javascript for stage 2 task wabbywat@gmail.com",
                   "name":"Huswat Omowabi",
                   "id":"HNG-00965",
                   "email":"wabbywat@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"00979-ahmadabdoul.php",
                   "output":"Hello World, this is Ahmad Abdulkadir with HNGi7 ID HNG-00979 using php for stage 2 task aabdulkadir109@gmail.com",
                   "name":"Ahmad Abdulkadir",
                   "id":"HNG-00979",
                   "email":"aabdulkadir109@gmail.com",
                   "language":"php",
                   "status":"Pass"
                },
                {
                   "file":"01004-Fessy.php",
                   "output":"Hello World, this is Festus Oluwaseyi Ogundele with HNGi7 ID HNG-01004 using PHP for stage 2 task festusogundele9@gmail.com",
                   "name":"Festus Oluwaseyi Ogundele",
                   "id":"HNG-01004",
                   "email":"festusogundele9@gmail.com",
                   "language":"php",
                   "status":"Pass"
                },
                {
                   "file":"01008-Tolu.py",
                   "output":"Hello World, this is Tolulope Mosuro with HNGi7 ID HNG-01008 using Python for stage 2 task mosurotolulope@gmail.com",
                   "name":"Tolulope Mosuro",
                   "id":"HNG-01008",
                   "email":"mosurotolulope@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"01016-Peachy.js",
                   "output":"Hello World, this is Temilade Opanuga with HNGi7 ID HNG-01016 using JavaScript for stage 2 task temmynladejesu@gmail.com",
                   "name":"Temilade Opanuga",
                   "id":"HNG-01016",
                   "email":"temmynladejesu@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"01049-samju.js",
                   "output":"Hello World, this is Juwon Adeyemi with HNGi7 ID HNG-01049 using javascript for stage 2 task samju7778@gmail.com",
                   "name":"Juwon Adeyemi",
                   "id":"HNG-01049",
                   "email":"samju7778@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"01070-taiwo.php",
                   "output":"Hello World, this is Taiwo Akinbile with HNGi7 ID HNG-01070 using PHP for stage 2 task akinbile6@gmail.com",
                   "name":"Taiwo Akinbile",
                   "id":"HNG-01070",
                   "email":"akinbile6@gmail.com",
                   "language":"php",
                   "status":"Pass"
                },
                {
                   "file":"01073-Afoke.js",
                   "output":"Hello World, this is Afoke Oghnekowho with HNGi7 ID HNG-01073 using Javascript for stage 2 task afokekowho@gmail.com",
                   "name":"Afoke Oghnekowho",
                   "id":"HNG-01073",
                   "email":"afokekowho@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"01096-Amara.js",
                   "output":"Hello World, this is Iheanacho Amarachi Sharon with HNGi7 ID HNG-01096 using JavaScript for stage 2 task amarachi2812@gmail.com",
                   "name":"Iheanacho Amarachi Sharon",
                   "id":"HNG-01096",
                   "email":"amarachi2812@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"01107-sylvia.js",
                   "output":"Hello World, this is Sylvia Nkosi with HNGi7 ID HNG-01107 using javascript for stage 2 task sylviapsnkosi@gmail.com",
                   "name":"Sylvia Nkosi",
                   "id":"HNG-01107",
                   "email":"sylviapsnkosi@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"01112-__femi.py",
                   "output":"Hello World, this is Oluwafemi Uduak Oderinde with HNGi7 ID HNG-01112 using Python 3 for stage 2 task oderindeoluwafemi@gmail.com",
                   "name":"Oluwafemi Uduak Oderinde",
                   "id":"HNG-01112",
                   "email":"oderindeoluwafemi@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"01121-TemmyJoy.js",
                   "output":"Hello World, this is Oyelami Temidayo with HNGi7 ID HNG-01121 using JavaScript for stage 2 task oyelamitemidayo99@gmail.com",
                   "name":"Oyelami Temidayo",
                   "id":"HNG-01121",
                   "email":"oyelamitemidayo99@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"01141-ehizman.py",
                   "output":"Hello World, this is Ehimwenman Edemakhiota with HNGi7 ID HNG-01141 using python for stage 2 task edemaehiz@gmail.com",
                   "name":"Ehimwenman Edemakhiota",
                   "id":"HNG-01141",
                   "email":"edemaehiz@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"01175-Tekuor.js",
                   "output":"Hello World, this is Gifty Mate-Kole with HNGi7 ID HNG-00175 using javascript for stage 2 task matekuor94@gmail.com",
                   "name":"Gifty Mate-Kole",
                   "id":"HNG-00175",
                   "email":"matekuor94@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"01187-adebayo.js",
                   "output":"Hello World, this is Adebayo Solomon with HNGi7 ID HNG-01187 using javascript for stage 2 task adebayosolomon74@gmail.com",
                   "name":"Adebayo Solomon",
                   "id":"HNG-01187",
                   "email":"adebayosolomon74@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"01214-Obu.php",
                   "output":"Hello World, this is Emmnanuel Obu Junior with HNGi7 ID HNG-01214 using PHP for stage 2 task obu.junior.emmanuel@gmail.com",
                   "name":"Emmnanuel Obu Junior",
                   "id":"HNG-01214",
                   "email":"obu.junior.emmanuel@gmail.com",
                   "language":"php",
                   "status":"Pass"
                },
                {
                   "file":"01251-fiona.js",
                   "output":"Hello World, this is Chiamaka Odoboh with HNGi7 ID HNG-01251 using JavaScript for stage 2 task fionaodoboh@gmail.com",
                   "name":"Chiamaka Odoboh",
                   "id":"HNG-01251",
                   "email":"fionaodoboh@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"01262-TimothyAdegbasa.py",
                   "output":"Hello world, this is Timothy Adegbasa with HNGi7 ID HNG-02162 using python for stage 2 task timothyadegbasa@gmail.com",
                   "name":"Timothy Adegbasa",
                   "id":"HNG-02162",
                   "email":"timothyadegbasa@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"01278-Jenny.js",
                   "output":"",
                   "name":"nill",
                   "id":"nill",
                   "email":"nil",
                   "language":"js",
                   "status":"Fail"
                },
                {
                   "file":"01282-popeAshiedu.py",
                   "output":"Hello World, this is Benedict Ashiedu with HNGi7 ID HNG-01282 using Python for stage 2 task popeashiedu@gmail.com",
                   "name":"Benedict Ashiedu",
                   "id":"HNG-01282",
                   "email":"popeashiedu@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"01338-Awajioyem.php",
                   "output":"Hello World, this is Ikoawaji Awajioyem Miracle with HNGi7 ID HNG-01338 using PHP for stage 2 task drawajioyem@gmail.com",
                   "name":"Ikoawaji Awajioyem Miracle",
                   "id":"HNG-01338",
                   "email":"drawajioyem@gmail.com",
                   "language":"php",
                   "status":"Pass"
                },
                {
                   "file":"01348-Chiz.py",
                   "output":"Hello World, this is Chizulum Nnodu with HNGi7 ID HNG-01348 using python for stage 2 task cin2@students.calvin.edu",
                   "name":"Chizulum Nnodu",
                   "id":"HNG-01348",
                   "email":"cin2@students.calvin.edu",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"01364-hafsah.js",
                   "output":"Hello World, this is Hafsah Emekoma with HNGi7 ID HNG-01364 using JavaScript for stage 2 task hafsyezinne@gmail.com",
                   "name":"Hafsah Emekoma",
                   "id":"HNG-01364",
                   "email":"hafsyezinne@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"01378-Oluwadamilola.js",
                   "output":"Hello world, this is Oluwadamilola with HNGi7 ID HNG-01378 using javascript for stage 2 task oluwadamilolaadejumo10@gmail.com",
                   "name":"Oluwadamilola",
                   "id":"HNG-01378",
                   "email":"oluwadamilolaadejumo10@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"01388-Maelle.js",
                   "output":"Hello World, this is Gabrielle Kurah with HNGi7 ID HNG-01388 using Javascript for stage 2 task gskurah@yahoo.co.uk",
                   "name":"Gabrielle Kurah",
                   "id":"HNG-01388",
                   "email":"gskurah@yahoo.co.uk",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"01439-Imran.js",
                   "output":"Hello World, this is Imran Abubakar with HNGi7 ID HNG-01439 using javascript for stage 2 task narmi.abu@gmail.com",
                   "name":"Imran Abubakar",
                   "id":"HNG-01439",
                   "email":"narmi.abu@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"01442-judeokoroafor.php",
                   "output":"Hello World, this is Jude Okoroafor with HNGi7 ID HNG-01442 using php for stage 2 task judeokoroafor@gmail.com",
                   "name":"Jude Okoroafor",
                   "id":"HNG-01442",
                   "email":"judeokoroafor@gmail.com",
                   "language":"php",
                   "status":"Pass"
                },
                {
                   "file":"01487-Francis.py",
                   "output":"Hello World, this is Francis OHara with HNGi7 ID HNG-01487 using python for stage 2 task kofiohara@gmail.com",
                   "name":"Francis OHara",
                   "id":"HNG-01487",
                   "email":"kofiohara@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"01532-Nisha.py",
                   "output":"Hello World, this is Nisha Mehra with HNGi7 ID HNG-01532 using python for stage 2 task nishamehra2052@gmail.com",
                   "name":"Nisha Mehra",
                   "id":"HNG-01532",
                   "email":"nishamehra2052@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"01560-dauntless.js",
                   "output":"Hello World, this is Linda Okorie with HNGi7 ID HNG-01560 using Javascript for stage 2 task lindaokorie27@gmail.com",
                   "name":"Linda Okorie",
                   "id":"HNG-01560",
                   "email":"lindaokorie27@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"01575-isunday.js",
                   "output":"Hello world, this is Ifiok Sunday Uboh with HNGi7 ID HNG-01575 using javascript for stage 2 task ifuboh@gmail.com",
                   "name":"Ifiok Sunday Uboh",
                   "id":"HNG-01575",
                   "email":"ifuboh@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"01694-Mahaly.py",
                   "output":"Hello World, this is Mahalinoro Razafimanjato with HNGi7 ID HNG-01694 using Python for stage 2 task m.razafiman@alustudent.com",
                   "name":"Mahalinoro Razafimanjato",
                   "id":"HNG-01694",
                   "email":"m.razafiman@alustudent.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"01717-itunuloluwa.js",
                   "output":"Hello World, this is Itunuloluwa Fatoki with HNGi7 ID HNG-01717 using javascript for stage 2 task itunuworks@gmail.com",
                   "name":"Itunuloluwa Fatoki",
                   "id":"HNG-01717",
                   "email":"itunuworks@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"01726-maureen.py",
                   "output":"Hello World, this is Maureen Onabajo with HNGi7 ID HNG-01726 using Python for stage 2 task isetire@gmail.com",
                   "name":"Maureen Onabajo",
                   "id":"HNG-01726",
                   "email":"isetire@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"01751-Ikpemosi.js",
                   "output":"Hello World, this is Ikpemosi Arnold with HNGi7 ID HNG-01751 using JavaScript for stage 2 task Ikpemosi@protonmail.com",
                   "name":"Ikpemosi Arnold",
                   "id":"HNG-01751",
                   "email":"Ikpemosi@protonmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"01761-thykingdoncome.js",
                   "output":"Hello World, this is Azemoh Israel with HNGi7 ID HNG-01761 using JavaScript for stage 2 task davidisrael194@gmail.com",
                   "name":"Azemoh Israel",
                   "id":"HNG-01761",
                   "email":"davidisrael194@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"01811-apparentdev.js",
                   "output":"Hello World, this is Ibrahim Alausa with HNGi7 ID HNG-01811 using Javascript for stage 2 task tosinibrahim96@gmail.com",
                   "name":"Ibrahim Alausa",
                   "id":"HNG-01811",
                   "email":"tosinibrahim96@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"01821-lolade.js",
                   "output":"Hello World, this is Olayiwola Ololade with HNGi7 ID HNG-01821 using javascript for stage 2 task ololadeolayiwola01@gmail.com",
                   "name":"Olayiwola Ololade",
                   "id":"HNG-01821",
                   "email":"ololadeolayiwola01@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"01832-xt.js",
                   "output":"Hello World, this is Oluwatobi Okewole with HNGi7 ID HNG-01832 using javascript for stage 2 task thetechtekker@gmail.com",
                   "name":"Oluwatobi Okewole",
                   "id":"HNG-01832",
                   "email":"thetechtekker@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"01857-omowunmi.py",
                   "output":"Hello World, this is faozziyyah Daud opeyemi with HNGi7 ID HNG-01857 using python for stage 2 task omowunmidaud1@gmail.com",
                   "name":"faozziyyah Daud opeyemi",
                   "id":"HNG-01857",
                   "email":"omowunmidaud1@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"01892-mctoluene.js",
                   "output":"Hello World, this is Ojulari Abdulhamid with HNGi7 ID HNG-01892 using Javascript for stage 2 task toluenelarry@gmail.com",
                   "name":"Ojulari Abdulhamid",
                   "id":"HNG-01892",
                   "email":"toluenelarry@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"01946-Dante.php",
                   "output":"Hello World, this is Dante Frank with HNGi7 ID HNG-01946 using PHP for stage 2 task davidfrankoziwo@gmail.com",
                   "name":"Dante Frank",
                   "id":"HNG-01946",
                   "email":"davidfrankoziwo@gmail.com",
                   "language":"php",
                   "status":"Pass"
                },
                {
                   "file":"01992-beegee.py",
                   "output":"Hello World, this is Sheila Birgen with HNGi7 ID HNG-01992 using python for stage 2 task jeronobergen@gmail.com",
                   "name":"Sheila Birgen",
                   "id":"HNG-01992",
                   "email":"jeronobergen@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"01998-parthpandyappp.py",
                   "output":"Hello World, this is Parth Pandya with HNGi7 ID HNG-01998 using Python for stage 2 task parthpandyappp@gmail.com",
                   "name":"Parth Pandya",
                   "id":"HNG-01998",
                   "email":"parthpandyappp@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"01999-muhammed.php",
                   "output":"Hello World, this is Muhammed Saifudeen Salaudeen with HNGi7 ID HNG-01999 using PHP for stage 2 task mr.salaudeen.official@gmail.com",
                   "name":"Muhammed Saifudeen Salaudeen",
                   "id":"HNG-01999",
                   "email":"mr.salaudeen.official@gmail.com",
                   "language":"php",
                   "status":"Pass"
                },
                {
                   "file":"02011-Valerie.js",
                   "output":"Hello World, this is Valerie Oakhu with HNGi7 ID HNG-02011 using Javascript for stage 2 task ooakhu@gmail.com",
                   "name":"Valerie Oakhu",
                   "id":"HNG-02011",
                   "email":"ooakhu@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"02015-goody.js",
                   "output":"Hello World, this is Goodness Obi with HNGi7 ID HNG-02015 using Javascript for stage 2 task goodnessobi@gmail.com",
                   "name":"Goodness Obi",
                   "id":"HNG-02015",
                   "email":"goodnessobi@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"02039-Nnenna.js",
                   "output":"Hello World, this is Nnenna Amagwula with HNGi7 ID HNG-02039 using javascript for stage 2 task amagwulannenna@gmail.com",
                   "name":"Nnenna Amagwula",
                   "id":"HNG-02039",
                   "email":"amagwulannenna@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"02079-atim.js",
                   "output":"Hello World, this is Atim Emenyi with HNGi7 ID HNG-02079 using JavaScript for stage 2 task atim2011@gmail.com",
                   "name":"Atim Emenyi",
                   "id":"HNG-02079",
                   "email":"atim2011@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"02080-funke.js",
                   "output":"Hello World, this is Olufunke John-Oluleye with HNGi7 ID HNG-02080 using Javascript for stage 2 task funkeoluleye@gmail.com",
                   "name":"Olufunke John-Oluleye",
                   "id":"HNG-02080",
                   "email":"funkeoluleye@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"02091-iamJay.js",
                   "output":"Hello World, this is Joshua Afolayan with HNGI7 ID HNG-02091 using JavaScript for stage 2 task afolayanoj@outlook.com",
                   "name":"Joshua Afolayan",
                   "id":"HNG-02091",
                   "email":"afolayanoj@outlook.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"02094-Yahya.js",
                   "output":"Hello world, this is Thalhatou Yahya with HNGi7 ID HNG-02094 using Javascript for stage 2 task thalhatouyahya5352@gmail.com",
                   "name":"Thalhatou Yahya",
                   "id":"HNG-02094",
                   "email":"thalhatouyahya5352@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"02140-ItsKmama.py",
                   "output":"Hello world, this is Olukanyinsola Olomi with HNGi7 ID HNG-02140 using Python for stage 2 task kanyinb@gmail.com",
                   "name":"Olukanyinsola Olomi",
                   "id":"HNG-02140",
                   "email":"kanyinb@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"02162-TimothyAdegbasa.py",
                   "output":"Hello World, this is Timothy Adegbasa with HNGi7 ID HNG-02162 using python for stage 2 task timothyadegbasa@gmail.com",
                   "name":"Timothy Adegbasa",
                   "id":"HNG-02162",
                   "email":"timothyadegbasa@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"02194-Esther.py",
                   "output":"Hello World, this is Esther Adesunloye with HNGi7 ID HNG-02194 using python for stage 2 task estheradesunloye96@gmail.com",
                   "name":"Esther Adesunloye",
                   "id":"HNG-02194",
                   "email":"estheradesunloye96@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"02227-KimayaUrdhwareshe.py",
                   "output":"Hello World, this is Kimaya Urdhwareshe with HNGi7 ID HNG-02227 using Python for stage 2 task kimaya23@gmail.com",
                   "name":"Kimaya Urdhwareshe",
                   "id":"HNG-02227",
                   "email":"kimaya23@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"02248-Olanre.js",
                   "output":"Hello World, this is Ojo Cornelius Lanre with HNGi7 ID HNG-02248 using Javascript for stage 2 task ojoolanrewaju62@gmail.com",
                   "name":"Ojo Cornelius Lanre",
                   "id":"HNG-02248",
                   "email":"ojoolanrewaju62@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"02272-tmac.js",
                   "output":"Hello World, this is Omotola Macaulay with HNGi7 ID HNG-02272 using javascript for stage 2 task tolamacaulay@gmail.com",
                   "name":"Omotola Macaulay",
                   "id":"HNG-02272",
                   "email":"tolamacaulay@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"02274-joywan.js",
                   "output":"Hello World, this is Joy Wangui with HNGi7 ID HNG-02274 using Javascript for stage 2 task feliwangui@gmail.com",
                   "name":"Joy Wangui",
                   "id":"HNG-02274",
                   "email":"feliwangui@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"02282-Dapo.py",
                   "output":"Hello World, this is Oke Ifedapo with HNGi7 ID HNG-02282 using python for stage 2 task ifedapo.john@gmail.com",
                   "name":"Oke Ifedapo",
                   "id":"HNG-02282",
                   "email":"ifedapo.john@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"02331-Jawsh.js",
                   "output":"Hello World, this is Joshua Oghenekowhegba with HNGi7 ID HNG-02331 using Javascript for stage 2 task kowhegbajosh@gmail.com",
                   "name":"Joshua Oghenekowhegba",
                   "id":"HNG-02331",
                   "email":"kowhegbajosh@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"02386-Pharoah.js",
                   "output":"Hello World, this is Izuchukwu Stephen Azubuike with HNGi7 ID HNG-02386 using JavaScript for stage 2 task zubix4all@hotmail.com",
                   "name":"Izuchukwu Stephen Azubuike",
                   "id":"HNG-02386",
                   "email":"zubix4all@hotmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"02425-maris.js",
                   "output":"Hello World, this is ikeonyia Somtochukwu Stellamaris with HNGI7 id HNG-12425 using javascript for stage 2 task stellamarissomto@gmail.com",
                   "name":"ikeonyia Somtochukwu Stellamaris",
                   "id":"HNG-12425",
                   "email":"stellamarissomto@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"02442-motdde.js",
                   "output":"Hello World, this is Oluwaseun Oyebade with HNGi7 ID HNG-02442 using JavaScript for stage 2 task telloluwaseunnow@yahoo.com",
                   "name":"Oluwaseun Oyebade",
                   "id":"HNG-02442",
                   "email":"telloluwaseunnow@yahoo.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"02459-barnes.js",
                   "output":"Hello World, this is Oduro Twumasi John Barnes with HNGi7 ID HNG-02459 using javascript for stage 2 task ce-jbodurotwumasi4619@st.umat.edu.gh",
                   "name":"Oduro Twumasi John Barnes",
                   "id":"HNG-02459",
                   "email":"ce-jbodurotwumasi4619@st.umat.edu.gh",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"02483-kpoke.js",
                   "output":"Hello World, this is Olojakpoke, Daniel with HNGi7 ID HNG-02483 using javascript for stage 2 task danielolojakpoke@gmail.com",
                   "name":"Olojakpoke, Daniel",
                   "id":"HNG-02483",
                   "email":"danielolojakpoke@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"02489-menekam.js",
                   "output":"Hello World, this is Menekam Rudy with HNGi7 ID HNG-02489 using Javascript for stage 2 task menekamrudy@gmail.com",
                   "name":"Menekam Rudy",
                   "id":"HNG-02489",
                   "email":"menekamrudy@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"02586-Nikkifeso.js",
                   "output":"Hello World, this is Adenike Awofeso with HNGi7 ID HNG-02586 using JavaScript for stage 2 task adenikeawofeso@gmail.com",
                   "name":"Adenike Awofeso",
                   "id":"HNG-02586",
                   "email":"adenikeawofeso@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"02599-ashgan.php",
                   "output":"Hello World, this is Ashgan Mustafa A. Mohammed with HNGi7 ID HNG-02599 using PHP for stage 2 task ashganwiki@gmail.com",
                   "name":"Ashgan Mustafa A. Mohammed",
                   "id":"HNG-02599",
                   "email":"ashganwiki@gmail.com",
                   "language":"php",
                   "status":"Pass"
                },
                {
                   "file":"02604-Adeolaade.php",
                   "output":"Hello World, this is Adeola Aderibigbe with HNGi7 ID HNG-02604 using php for stage 2 task adeolaaderibigbe09@gmail.com",
                   "name":"Adeola Aderibigbe",
                   "id":"HNG-02604",
                   "email":"adeolaaderibigbe09@gmail.com",
                   "language":"php",
                   "status":"Pass"
                },
                {
                   "file":"02618-sulayibraheem.js",
                   "output":"Hello World, this is Ibrahim Sule with HNGi7 ID HNG-02618 using JavaScript for stage 2 task sulayibraheem@gmail.com",
                   "name":"Ibrahim Sule",
                   "id":"HNG-02618",
                   "email":"sulayibraheem@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"02626-Joshua.js",
                   "output":"Hello World, this is Nwokoye Joshua with HNGi7 ID HNG-02626 using javascript for stage 2 task ratdans@gmail.com",
                   "name":"Nwokoye Joshua",
                   "id":"HNG-02626",
                   "email":"ratdans@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"02700-greatihevueme.py",
                   "output":"Hello World, this is Great Ihevueme with HNGi7 ID HNG-02700 using python for stage 2 task ihevuemeg@gmail.com",
                   "name":"Great Ihevueme",
                   "id":"HNG-02700",
                   "email":"ihevuemeg@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"02709-giddy.py",
                   "output":"Hello World, this is Gideon Anyalewechi with HNGi7 ID HNG-02709 using python for stage 2 task ganyalewechi1997@gmail.com",
                   "name":"Gideon Anyalewechi",
                   "id":"HNG-02709",
                   "email":"ganyalewechi1997@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"02744-Kenny.js",
                   "output":"Hello World, this is Kehinde Bobade with HNGi7 ID HNG-02744 using JavaScript for stage 2 task kennygbeminiyi@gmail.com",
                   "name":"Kehinde Bobade",
                   "id":"HNG-02744",
                   "email":"kennygbeminiyi@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"02750-charles.js",
                   "output":"Hello World, this is CHARLES UBAH with HNGi7 ID HNG-02750 using javascript for stage 2 task charlesubah47@gmail.com",
                   "name":"CHARLES UBAH",
                   "id":"HNG-02750",
                   "email":"charlesubah47@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"02797-bluehood.js",
                   "output":"Hello world, this is Samuel Oluwatosin Oniyilo with HNGi7 HNG-02797 using Javascript for stage 2 task masterarcher6321@gmail.com",
                   "name":"Samuel Oluwatosin Oniyilo",
                   "id":"HNG-02797",
                   "email":"nil",
                   "language":"js",
                   "status":"Fail"
                },
                {
                   "file":"02836-Amostox.php",
                   "output":"Hello World, this is Amos Awoniyi with HNGi7 ID HNG-02836 using Php for stage 2 task amostawo@gmail.com",
                   "name":"Amos Awoniyi",
                   "id":"HNG-02836",
                   "email":"amostawo@gmail.com",
                   "language":"php",
                   "status":"Pass"
                },
                {
                   "file":"02868-Neba.py",
                   "output":"Hello World, this is Neba Roland with HNGi7 ID HNG-02868 using python for stage 2 task n.ngwa@alustudent.com",
                   "name":"Neba Roland",
                   "id":"HNG-02868",
                   "email":"n.ngwa@alustudent.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"02893-KHALEED.py",
                   "output":"Hello World, this is khaleed Oyeleke with HNGi7 ID HNG-02893 using python for stage 2 task oyelekekhaleed@gmail.com",
                   "name":"khaleed Oyeleke",
                   "id":"HNG-02893",
                   "email":"oyelekekhaleed@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"02916-queenade.php",
                   "output":"Hello World, this is Adelodun Adeola .M. with HNGi7 ID HNG-02916 using PHP for stage 2 task amadelodun@gmail.com",
                   "name":"Adelodun Adeola .M.",
                   "id":"HNG-02916",
                   "email":"amadelodun@gmail.com",
                   "language":"php",
                   "status":"Pass"
                },
                {
                   "file":"02977-LuchySho.js",
                   "output":"Hello World, this is Oluchi Sodeinde with HNGi7 ID HNG-02977 using JavaScript for stage 2 task oluchined@gmail.com",
                   "name":"Oluchi Sodeinde",
                   "id":"HNG-02977",
                   "email":"oluchined@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"02990-Oghenegare.js",
                   "output":"Hello World, this is Emumejakpor Oghenegare with HNGi7 ID HNG-02990 using Javascript for stage 2 task jakporg31@gmail.com",
                   "name":"Emumejakpor Oghenegare",
                   "id":"HNG-02990",
                   "email":"jakporg31@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"03001-walz.js",
                   "output":"Hello World, this is Izaak Chukwuma with HNGi7 ID HNG-03001 using JavaScript for stage 2 task iblack.xyz@gmail.com",
                   "name":"Izaak Chukwuma",
                   "id":"HNG-03001",
                   "email":"iblack.xyz@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"03016-AbiodunE.js",
                   "output":"Hello world, this is Adebayo Abiodun Emmanuel with HNGi7 ID HNG-03016 using JavaScript for stage 2 task adebayoabiodun93@gmail.com",
                   "name":"Adebayo Abiodun Emmanuel",
                   "id":"HNG-03016",
                   "email":"adebayoabiodun93@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"03033-nuel.js",
                   "output":"Hello world, this is Nuel Okon with HNGi7 ID HNG-03033 using JavaScript for stage 2 task nuelljnr@gmail.com",
                   "name":"Nuel Okon",
                   "id":"HNG-03033",
                   "email":"nuelljnr@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"03062-imoteriannah.php",
                   "output":"Hello World, this is Iannah Imoter Paul with HNGi7 ID HNG-03062 using PHP for stage 2 task imoteriannah@gmail.com",
                   "name":"Iannah Imoter Paul",
                   "id":"HNG-03062",
                   "email":"imoteriannah@gmail.com",
                   "language":"php",
                   "status":"Pass"
                },
                {
                   "file":"03068-eirene.py",
                   "output":"",
                   "name":"nill",
                   "id":"nill",
                   "email":"nil",
                   "language":"py",
                   "status":"Fail"
                },
                {
                   "file":"03101-Kehinde.js",
                   "output":"Hello World, this is Kehinde Adedolapo Adebayo with HNGi7 ID HNG-03101 using Javascript for stage 2 task adebayo.kenny240@gmail.com",
                   "name":"Kehinde Adedolapo Adebayo",
                   "id":"HNG-03101",
                   "email":"adebayo.kenny240@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"03122-Tutu_.py",
                   "output":"Hello World, this is Takon Judith with HNGi7 ID HNG-03122 using python for stage 2 task tututakon@gmail.com",
                   "name":"Takon Judith",
                   "id":"HNG-03122",
                   "email":"tututakon@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"03192-victoriakazeem.js",
                   "output":"Hello World, this is Victoria Kazeem with HNGi7 ID HNG-03192 using JavaScript for stage 2 task kelzvictoria@gmail.com",
                   "name":"Victoria Kazeem",
                   "id":"HNG-03192",
                   "email":"kelzvictoria@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"03200-Yassine-Hadry.py",
                   "output":"Hello World, this is Yassine Hadry with HNGi7 ID HNG-03200 using Python for stage 2 task Hadryyassine@gmail.com",
                   "name":"Yassine Hadry",
                   "id":"HNG-03200",
                   "email":"Hadryyassine@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"03205-olubiyisther.py",
                   "output":"Hello World, this is Olubiyi Imoleayo Esther with HNGi7 ID HNG-03205 using python for stage 2 task olubiyisther@gmail.com",
                   "name":"Olubiyi Imoleayo Esther",
                   "id":"HNG-03205",
                   "email":"olubiyisther@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"03230-Nwanyibihe.js",
                   "output":"Hello World, this is Nwanyibihe Uhegbu with HNGi7 ID HNG-03230 using javascript for stage 2 task nwanyibihe@gmail.com",
                   "name":"Nwanyibihe Uhegbu",
                   "id":"HNG-03230",
                   "email":"nwanyibihe@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"03251-Ajiri.php",
                   "output":"Hello World, this is OSIOBE ENOCH AJIRI with HNGi7 ID HNG-03251 using php for stage 2 task ajiriosiobe@gmail.com",
                   "name":"OSIOBE ENOCH AJIRI",
                   "id":"HNG-03251",
                   "email":"ajiriosiobe@gmail.com",
                   "language":"php",
                   "status":"Pass"
                },
                {
                   "file":"03257-Bukola.py",
                   "output":"Hello world, this is Bukola Idowu with HNGi7 ID HNG-03257 using python for stage 2 task idowu98official@gmail.com",
                   "name":"Bukola Idowu",
                   "id":"HNG-03257",
                   "email":"idowu98official@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"03264-Noimot.js",
                   "output":"Hello World, this is Noimot Abiodun Alabi with HNGi7 ID HNG-03264 using Javascript for stage 2 task kikkyal@gmail.com",
                   "name":"Noimot Abiodun Alabi",
                   "id":"HNG-03264",
                   "email":"kikkyal@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"03266-ts.js",
                   "output":"Hello World, this is Taiwo Sulaimon with HNGi7 ID HNG-03266 using javascript for stage 2 task tsulaimon96@gmail.com",
                   "name":"Taiwo Sulaimon",
                   "id":"HNG-03266",
                   "email":"tsulaimon96@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"03279-Giye.js",
                   "output":"Hello World, this is Amadi Onengiye Williams with HNGi7 ID HNG-03279 using JavaScript for stage 2 task onengiyeamadi@gmail.com",
                   "name":"Amadi Onengiye Williams",
                   "id":"HNG-03279",
                   "email":"onengiyeamadi@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"03290-Psalmzy.js",
                   "output":"Hello World, this is Oladokun Samuel with HNGi7 ID HNG-03290 using Javascript for stage 2 task psalmwelloladokun@gmail.com",
                   "name":"Oladokun Samuel",
                   "id":"HNG-03290",
                   "email":"psalmwelloladokun@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"03298-Abass_1998.js",
                   "output":"Hello World, this is Oluokun Abass with HNGi7 ID HNG-03298 using Javascript for stage 2 task abassodunola56@gmail.com",
                   "name":"Oluokun Abass",
                   "id":"HNG-03298",
                   "email":"abassodunola56@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"03301-vasanth.py",
                   "output":"Hello World, this is Vasanth Kumar Cheepurupalli with HNGi7 ID HNG-03301 using Python for stage 2 task cheepurupalli.vasanthkumar@gmail.com",
                   "name":"Vasanth Kumar Cheepurupalli",
                   "id":"HNG-03301",
                   "email":"cheepurupalli.vasanthkumar@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"03306-patrickevah.py",
                   "output":"Hello World, this is Evah Patrick with HNGi7 ID HNG-03306 using python for stage 2 task patrickevah4@gmail.com",
                   "name":"Evah Patrick",
                   "id":"HNG-03306",
                   "email":"patrickevah4@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"03334-tolutech.js",
                   "output":"Hello World, this is Toluwase Ajibola-Thomas with HNGi7 ID HNG-03334 using Javascript for stage 2 task toluwasethomas1@gmail.com",
                   "name":"Toluwase Ajibola-Thomas",
                   "id":"HNG-03334",
                   "email":"toluwasethomas1@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"03407-Esther.js",
                   "output":"Hello World, this is Esther Umoh with HNGi7 ID HNG-03407 using JavaScript for stage 2 task umohesther08@gmail.com",
                   "name":"Esther Umoh",
                   "id":"HNG-03407",
                   "email":"umohesther08@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"03466-abdulsamadola.php",
                   "output":"Hello World, this is Suleiman Abdulsamad Olamilekan with HNGi7 ID HNG-03466 using PHP for stage 2 task suleimanolamilekan03@gmail.com",
                   "name":"Suleiman Abdulsamad Olamilekan",
                   "id":"HNG-03466",
                   "email":"suleimanolamilekan03@gmail.com",
                   "language":"php",
                   "status":"Pass"
                },
                {
                   "file":"03471-Onz.js",
                   "output":"Hello World, this is Owen Maina with HNGi7 ID HNG-03471 using Javascript for stage 2 task owenonzomw@gmail.com",
                   "name":"Owen Maina",
                   "id":"HNG-03471",
                   "email":"owenonzomw@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"03489-Efosa.py",
                   "output":"Hello World, this is Blessing Uduose with HNGi7 ID HNG-03489 using Python for stage 2 task blessingefosa@gmail.com",
                   "name":"Blessing Uduose",
                   "id":"HNG-03489",
                   "email":"blessingefosa@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"03506-Adjoah.py",
                   "output":"Hello World, this is Adwoa Serwaa Boafo with HNGi7 ID HNG-03506 using Python for stage 2 task adwoaserwaaboafo@gmail.com",
                   "name":"Adwoa Serwaa Boafo",
                   "id":"HNG-03506",
                   "email":"adwoaserwaaboafo@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"03520-AngelNagaba.js",
                   "output":"Hello World, this is Angel Nagaba with HNGi7 ID HNG-03520 using Javascript for stage 2 task angelnagaba99@gmail.com",
                   "name":"Angel Nagaba",
                   "id":"HNG-03520",
                   "email":"angelnagaba99@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"03559-chioma.py",
                   "output":"Hello World, this is Chioma Ilo with HNGi7 ID HNG-03559 using Python for stage 2 task emeldachichi1@gmail.com",
                   "name":"Chioma Ilo",
                   "id":"HNG-03559",
                   "email":"emeldachichi1@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"03584-oma16.php",
                   "output":"Hello World, this is Alli Mariam Titilope with HNGi7 ID HNG-03584 using php for stage 2 task titioba95@gmail.com",
                   "name":"Alli Mariam Titilope",
                   "id":"HNG-03584",
                   "email":"titioba95@gmail.com",
                   "language":"php",
                   "status":"Pass"
                },
                {
                   "file":"03615-Onyekachi.js",
                   "output":"Hello World, this is Acha Joy with HNGi7 ID HNG-03615 using Javascript for stage 2 task joyonyeka.acha@gmail.com",
                   "name":"Acha Joy",
                   "id":"HNG-03615",
                   "email":"joyonyeka.acha@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"03638-chukwudi.py",
                   "output":"Hello World, this is Wowo Chukwudi with HNGi7 ID HNG-03638 using python for stage 2 task woworoseline@gmail.com",
                   "name":"Wowo Chukwudi",
                   "id":"HNG-03638",
                   "email":"woworoseline@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"03774-Maryam.js",
                   "output":"Hello world, this is Maryam Sulaiman with HNGi7 ID HNG-03774 using Javascript ES6 for stage 2 task sulaiman.maryam@yahoo.co.uk",
                   "name":"Maryam Sulaiman",
                   "id":"HNG-03774",
                   "email":"sulaiman.maryam@yahoo.co.uk",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"03791-jane.js",
                   "output":"Hello World, this is Chimdi Jane Samuel with HNGi7 ID HNG-03791 using Javascript for stage 2 task janesamuel308@gmail.com",
                   "name":"Chimdi Jane Samuel",
                   "id":"HNG-03791",
                   "email":"janesamuel308@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"03806-ifuu.js",
                   "output":"Hello World, this is Chiezie Ifunanya Eucharia with HNGi7 IDHNG-03806 using javascript for stage 2 task chiezieifunanya08gmail.com",
                   "name":"Chiezie Ifunanya Eucharia",
                   "id":"HNG-03806",
                   "email":"nil",
                   "language":"js",
                   "status":"Fail"
                },
                {
                   "file":"03843-ejallow.js",
                   "output":"Hello World, this is Ebrima G. Jallow with HNGi7 ID HNG-03843 using javascript for stage 2 task egjallow10@gmail.com",
                   "name":"Ebrima G. Jallow",
                   "id":"HNG-03843",
                   "email":"egjallow10@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"03875-msogunz.js",
                   "output":"Hello world, this is Ogunderu Naomi with HNGi7 ID HNG-03875 using JavaScript for stage 2 task NaomiOgunderu@gmail.com",
                   "name":"Ogunderu Naomi",
                   "id":"HNG-03875",
                   "email":"NaomiOgunderu@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"03933-kome.js",
                   "output":"Hello World, this is Oghenekome Akaka with HNGi7 ID HNG-03933 using javascript for stage 2 task oghenekomeakaka@gmail.com",
                   "name":"Oghenekome Akaka",
                   "id":"HNG-03933",
                   "email":"oghenekomeakaka@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"03936-Brembie.py",
                   "output":"Hello World, this is Maame Adwoa Brembah with HNGi7 ID HNG-03936 using Python for stage 2 task adwoabrembah@gmail.com",
                   "name":"Maame Adwoa Brembah",
                   "id":"HNG-03936",
                   "email":"adwoabrembah@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"03968-Waliko.js",
                   "output":"Hello World, this is Walikonadi Sichinga with HNGi7 ID HNG-03968 using javascript for stage 2 task likosichinga@yahoo.com",
                   "name":"Walikonadi Sichinga",
                   "id":"HNG-03968",
                   "email":"likosichinga@yahoo.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"04004-BarbaraIkeonyia.js",
                   "output":"Hello World, this is Ikeonyia Barbara Ijeoma with HNGi7 id HNG-04004 using javascript for stage 2 task ikeonyiaijeoma@gmail.com",
                   "name":"Ikeonyia Barbara Ijeoma",
                   "id":"HNG-04004",
                   "email":"ikeonyiaijeoma@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"04012-Yinkash.js",
                   "output":"Hello World, this is Adeyinka Adebiyi with HNGi7 ID HNG-04012 using JavaScript for stage 2 task yinkash1000@gmail.com",
                   "name":"Adeyinka Adebiyi",
                   "id":"HNG-04012",
                   "email":"yinkash1000@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"04030-Akinsolapaul.js",
                   "output":"Hello World, this is Akinsola Olawoagbo with HNGi7 ID HNG-04030 using JavaScript for stage 2 task solar4ars_1@yahoo.com",
                   "name":"Akinsola Olawoagbo",
                   "id":"HNG-04030",
                   "email":"solar4ars_1@yahoo.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"04052-Teniade.js",
                   "output":"Hello World, this is Oyelakin Oluwateniola with HGNi7 ID HNG-04052 using javascript for stage 2 task oluwateniolaoyelakin1@gmail.com",
                   "name":"Oyelakin Oluwateniola",
                   "id":"HNG-04052",
                   "email":"nil",
                   "language":"js",
                   "status":"Fail"
                },
                {
                   "file":"04062-kijified.php",
                   "output":"Hello World, this is Olaniyan Boluwatife with HNGi7 ID HNG-04062 using php for stage 2 task iamorelord@gmail.com",
                   "name":"Olaniyan Boluwatife",
                   "id":"HNG-04062",
                   "email":"iamorelord@gmail.com",
                   "language":"php",
                   "status":"Pass"
                },
                {
                   "file":"04082-Asmail.js",
                   "output":"Hello World, this is Asmail with HNGi7 ID HNG-04082 using javascript for stage 2 task asmayahya1@gmail.com",
                   "name":"Asmail",
                   "id":"HNG-04082",
                   "email":"asmayahya1@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"04090-Isane.js",
                   "output":"Hello World, this is Isane Mphela with HNGi7 ID HNG-04090 using javascript for stage 2 task isanejossy@gmail.com",
                   "name":"Isane Mphela",
                   "id":"HNG-04090",
                   "email":"isanejossy@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"04095-bolex.py",
                   "output":"hello world, this is adeojo bolu with HNGi7 ID HNG-04095 using python for stage 2 task boluwatufeadeojo@gmail.com",
                   "name":"adeojo bolu",
                   "id":"HNG-04095",
                   "email":"boluwatufeadeojo@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"04097-Adeorgomes.js",
                   "output":"Hello world, this isAyodele Ogunleye with HNGi7-ID HNG-04097using Javascript for stage 2 task ayodelegomes@gmail.com",
                   "name":"nill",
                   "id":"HNG-04097",
                   "email":"nil",
                   "language":"js",
                   "status":"Fail"
                },
                {
                   "file":"04100-ufeaneikrystel.py",
                   "output":"Hello World, this is Ufeanei Krystel with HNGi7 ID HNG-04100 using Python for stage 2 task krystelufeanei@gmail.com",
                   "name":"Ufeanei Krystel",
                   "id":"HNG-04100",
                   "email":"krystelufeanei@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"04115-jaybeloved.py",
                   "output":"Hello World, this is Lawal John with HNGi7 ID HNG-04115 using python for stage 2 task info.jaybeloved@gmail.com",
                   "name":"Lawal John",
                   "id":"HNG-04115",
                   "email":"info.jaybeloved@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"04167-rugue.js",
                   "output":"Hello World, this is Osarugue Enehizena with HNGi7 ID HNG-04167 using JavaScript for stage 2 task nehirugue@gmail.com",
                   "name":"Osarugue Enehizena",
                   "id":"HNG-04167",
                   "email":"nehirugue@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"04192-oloye.py",
                   "output":"Hello World, this is Mercy Oyeniran with HNGi7 ID HNG-04192 using python for task 2 oyenimiercy@gmail.com",
                   "name":"Mercy Oyeniran",
                   "id":"HNG-04192",
                   "email":"nil",
                   "language":"py",
                   "status":"Fail"
                },
                {
                   "file":"04206-codeblooded.py",
                   "output":"Hello World, this is Abdulrazaq Habib with HNGi7 ID HNG-04206 using python for stage 2 task slimkid84@gmail.com",
                   "name":"Abdulrazaq Habib",
                   "id":"HNG-04206",
                   "email":"slimkid84@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"04212-Fatima29.js",
                   "output":"Hello World, this is Olanrewaju Fatima with HNGi7 ID HNG-04212 using JavaScript language for stage 2 task folanrewaju044@gmail.com",
                   "name":"Olanrewaju Fatima",
                   "id":"HNG-04212",
                   "email":"folanrewaju044@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"04218-Lamin.py",
                   "output":"Hello World, this is Lamin Minteh with HNGi7 ID HNG-04218 using Python for stage 2 task mintehl6@gmail.com",
                   "name":"Lamin Minteh",
                   "id":"HNG-04218",
                   "email":"mintehl6@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"04225-ib.js",
                   "output":"Hello World, this is Ibifuro odu with HNGi7 ID HNG-04225 using javascript for stage 2 task oduibifuro@gmail.com",
                   "name":"Ibifuro odu",
                   "id":"HNG-04225",
                   "email":"oduibifuro@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"04233-femathic.js",
                   "output":"Hello World, this is Oluwafemi Opanuga with HNGi7 ID HNG-04233 using JavaScript for stage 2 task femathic@gmail.com",
                   "name":"Oluwafemi Opanuga",
                   "id":"HNG-04233",
                   "email":"femathic@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"04235-Oben.js",
                   "output":"Hello World, this is Oben Tabendip Tabiayuk with HNGi7 ID HNG-04235 using Javascript for stage 2 task obentabiayuk1@gmail.com",
                   "name":"Oben Tabendip Tabiayuk",
                   "id":"HNG-04235",
                   "email":"obentabiayuk1@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"04317-SebastinGabriel.php",
                   "output":"Hello World, this is Sebastin Chinedu Gabriel with HNGi7 ID HNG-04317 using PHP for stage 2 task chinedugabriel29@gmail.com",
                   "name":"Sebastin Chinedu Gabriel",
                   "id":"HNG-04317",
                   "email":"chinedugabriel29@gmail.com",
                   "language":"php",
                   "status":"Pass"
                },
                {
                   "file":"04327-timiderinola.py",
                   "output":"Hello World, this is Timilehin Aderinola with HNGi7 ID HNG-04327 using Python for stage 2 task timiderinola@gmail.com",
                   "name":"Timilehin Aderinola",
                   "id":"HNG-04327",
                   "email":"timiderinola@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"04332-horlahjummy.py",
                   "output":"Hello World, this is Araromi Haonat Olajumoke with HNGi7 ID HNG-04332 using python for stage 2 task haonatararomi@gmail.com",
                   "name":"Araromi Haonat Olajumoke",
                   "id":"HNG-04332",
                   "email":"haonatararomi@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"04345-zenbakle.py",
                   "output":"Hello World, this is Zenret Orse Bakle with HNGi7 ID HNG-04345 using python for stage 2 task zenretbakle@gmail.com",
                   "name":"Zenret Orse Bakle",
                   "id":"HNG-04345",
                   "email":"zenretbakle@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"04368-jhybomob.py",
                   "output":"Hello World, this is Ajibola Areo with HNGi7 ID HNG-04368 using python for stage 2 task jibo.areo@gmail.com",
                   "name":"Ajibola Areo",
                   "id":"HNG-04368",
                   "email":"jibo.areo@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"04373-kairabakeita.js",
                   "output":"Hello World, this is Kairaba Keita with HNGi7 ID HNG-04373 using javascript for stage 2 task kairabakeita97@gmail.com",
                   "name":"Kairaba Keita",
                   "id":"HNG-04373",
                   "email":"kairabakeita97@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"04376-Mary.py",
                   "output":"",
                   "name":"nill",
                   "id":"nill",
                   "email":"nil",
                   "language":"py",
                   "status":"Fail"
                },
                {
                   "file":"04393-nyakinyua.py",
                   "output":"Hello World, this is Joyce Nyakinyua with HNGi7 ID HNG-04393 using Python for stage 2 task wanyakinyua968@gmail.com",
                   "name":"Joyce Nyakinyua",
                   "id":"HNG-04393",
                   "email":"wanyakinyua968@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"04548-Dexter.py",
                   "output":"Hello World, this is Daniel Tobi Onipe with HNGi7 ID HNG-04548 using python for stage 2 task dexterousguru49@gmail.com",
                   "name":"Daniel Tobi Onipe",
                   "id":"HNG-04548",
                   "email":"dexterousguru49@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"04615-maryam.js",
                   "output":"Hello World, this is Maryam Awesu with HNGi7 ID HNG-04615 using JavaScript for stage 2 task maryamawesu29@gmail.com",
                   "name":"Maryam Awesu",
                   "id":"HNG-04615",
                   "email":"maryamawesu29@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"04684-jboy.js",
                   "output":"Hello World, this is Taiwo Adejare Emmanuel with HNGi7 ID HNG-04684 using JavaScript for stage 2 task adejareemma@gmail.com",
                   "name":"Taiwo Adejare Emmanuel",
                   "id":"HNG-04684",
                   "email":"adejareemma@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"04692-Aduragbemi.py",
                   "output":"Hello World, this is Anuoluwapo Tayo-Alabi with HNGi7 ID HNG-04692 using python for stage 2 task anualabi2017@gmail.com",
                   "name":"Anuoluwapo Tayo-Alabi",
                   "id":"HNG-04692",
                   "email":"anualabi2017@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"04694-praiz.js",
                   "output":"Hello World, this is Praise Obia with HNGi7 ID HNG-04694 using JavaScript for stage 2 task praizobia@gmail.com",
                   "name":"Praise Obia",
                   "id":"HNG-04694",
                   "email":"praizobia@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"04701-othniel.js",
                   "output":"Hello World, this is Othniel Agera with HNGi7 ID HNG-04701 using javascript for stage 2 task otagera@gmail.com",
                   "name":"Othniel Agera",
                   "id":"HNG-04701",
                   "email":"otagera@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"04704-aimeee.js",
                   "output":"Hello World, this is Opaluwa Emidowo-ojo with HNGi7 ID HNG-04704 using Javascript for stage 2 task opaluwaamy@gmail.com",
                   "name":"Opaluwa Emidowo-ojo",
                   "id":"HNG-04704",
                   "email":"opaluwaamy@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"04709-gbengaoj.py",
                   "output":"Hello World, this is Gbenga Ojo with HNGi7 ID HNG-04709 using Python for stage 2 task gbenga.ojo@clicktgi.net",
                   "name":"Gbenga Ojo",
                   "id":"HNG-04709",
                   "email":"gbenga.ojo@clicktgi.net",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"04721-paulxx.js",
                   "output":"Hello World, this is Awe Paul with HNGi7 ID HNG-04721 using JavaScript for stage 2 task awe.paulsq@gmail.com",
                   "name":"Awe Paul",
                   "id":"HNG-04721",
                   "email":"awe.paulsq@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"04732-vikki.js",
                   "output":"Hello World, this is Ezeganya Victor with HNGi7 ID HNG-04732 using JavaScript for stage 2 task vikkiezeganya@gmail.com",
                   "name":"Ezeganya Victor",
                   "id":"HNG-04732",
                   "email":"vikkiezeganya@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"04744-joekenpat.php",
                   "output":"Hello World, this is Patrick Joel Kenneth with HNGi7 ID HNG-04744 using PHP for stage 2 task joekenpat@gmail.com",
                   "name":"Patrick Joel Kenneth",
                   "id":"HNG-04744",
                   "email":"joekenpat@gmail.com",
                   "language":"php",
                   "status":"Pass"
                },
                {
                   "file":"04750-Techworm.js",
                   "output":"Hello World, this is Ibe Andyson Andrew with HNGi7 ID HNG-04750 using Javascript for stage 2 task ibeandyson123@gmail.com",
                   "name":"Ibe Andyson Andrew",
                   "id":"HNG-04750",
                   "email":"ibeandyson123@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"04760-Emike.js",
                   "output":"Hello World, this is Emike Aigbodioh with HNGi7 ID HNG-04760 using JavaScript for stage 2 task laigbodioh@gmail.com",
                   "name":"Emike Aigbodioh",
                   "id":"HNG-04760",
                   "email":"laigbodioh@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"04795-tifeonifade.py",
                   "output":"Hello World, this is Onifade Boluwatife Basit with HNGi7 ID HNG-04795 using Python3.7 for stage 2 task onifadebolu64@gmail.com",
                   "name":"Onifade Boluwatife Basit",
                   "id":"HNG-04795",
                   "email":"onifadebolu64@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"04828-ogunsolu.js",
                   "output":"Hello World, this is ogunsolu qudus with HNGi7 ID HNG-04828 using Javascript for stage 2 task ogunsolu30@gmail.com",
                   "name":"ogunsolu qudus",
                   "id":"HNG-04828",
                   "email":"ogunsolu30@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"04837-samuel.py",
                   "output":"Hello World, this is Oni Samuel with HNGi7 ID HNG-04837 using python for stage 2 task onis784@gmail.com",
                   "name":"Oni Samuel",
                   "id":"HNG-04837",
                   "email":"onis784@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"04908-joseph.js",
                   "output":"Hello World, this is Afolabi Joseph Ajani with HNGI7 ID HNG-04908 using JavaScript for stage 2 task ask4josef@gmail.com",
                   "name":"Afolabi Joseph Ajani",
                   "id":"HNG-04908",
                   "email":"ask4josef@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"05035-Mheeday.py",
                   "output":"Hello world, this is Joshua OYADOKUN with HNGi7 ID HNG-05035 using Python for stage 2 task oyadokunjosh@gmail.com",
                   "name":"Joshua OYADOKUN",
                   "id":"HNG-05035",
                   "email":"oyadokunjosh@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"05042-Mojisola.py",
                   "output":"Hello World, this is Mojisola Salaudeen with HNGi7 ID HNG-05042 using Python for stage 2 task lmsalaudeen@gmail.com",
                   "name":"Mojisola Salaudeen",
                   "id":"HNG-05042",
                   "email":"lmsalaudeen@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"05047-Nanya.js",
                   "output":"Hello World, this is Ifunanya Ugochukwu with HNGi7 ID HNG-05047 using javaScript for stage 2 task darlingnanya@gmail.com",
                   "name":"Ifunanya Ugochukwu",
                   "id":"HNG-05047",
                   "email":"darlingnanya@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"05064-Kanyincode.py",
                   "output":"Hello World, this is Oluwakanyinsola Sowemimo with HNGi7 ID HNG-05064 using python for stage 2 task kanyintolulope17@gmail.com",
                   "name":"Oluwakanyinsola Sowemimo",
                   "id":"HNG-05064",
                   "email":"kanyintolulope17@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"05085-djfemz.js",
                   "output":"Hello World, this is Oladeji Oluwafemi with HNGi7 ID HNG-05085 using golang for stage 2 task oladejifemi00@gmail.com",
                   "name":"Oladeji Oluwafemi",
                   "id":"HNG-05085",
                   "email":"oladejifemi00@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"05106-BoluPhillips.js",
                   "output":"Hello World, this is Boluwatife Phillips with HNGi7 ID HNG-05106 using javascript for stage 2 task phillipsbolu@gmail.com",
                   "name":"Boluwatife Phillips",
                   "id":"HNG-05106",
                   "email":"phillipsbolu@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"05137-Wizman.py",
                   "output":"Hello World, this is Solomon Nuhu Abe with HNGi7 ID HNG-05137 using python for stage 2 task solotinted@yahoo.com",
                   "name":"Solomon Nuhu Abe",
                   "id":"HNG-05137",
                   "email":"solotinted@yahoo.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"05142-aquils.js",
                   "output":"Hello World, this is Emmanuel Afuadajo with HNGi7 ID HNG-05142 using javascript for stage 2 task aquilaafuadajo@gmail.com",
                   "name":"Emmanuel Afuadajo",
                   "id":"HNG-05142",
                   "email":"aquilaafuadajo@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"05176-EmmyLeke.js",
                   "output":"Hello World, this is Emmy Leke with HNGi7 ID HNG-05176 using Javascript for stage 2 task elekeemmy@gmail.com",
                   "name":"Emmy Leke",
                   "id":"HNG-05176",
                   "email":"elekeemmy@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"05211-iamnwani.js",
                   "output":"Hello World, this is Victory Nwani with HNGi7 ID HNG-05211 using JavaScript for stage 2 task vickywane@gmail.com",
                   "name":"Victory Nwani",
                   "id":"HNG-05211",
                   "email":"vickywane@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"05240-Riyike.js",
                   "output":"Hello World, this is Odusanya Iyanuoluwa with HNGi7 ID HNG-05240 using JavaScript for stage 2 task odusanyaiyanuoluwa@gmail.com",
                   "name":"Odusanya Iyanuoluwa",
                   "id":"HNG-05240",
                   "email":"odusanyaiyanuoluwa@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"05242-Mihde.py",
                   "output":"Hello World, this is Odusanya Ayomide with HNGi7 ID HNG-05242 using Python for stage 2 task odusanyamd@gmail.com",
                   "name":"Odusanya Ayomide",
                   "id":"HNG-05242",
                   "email":"odusanyamd@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"05320-Frieda.py",
                   "output":"Hello World, this is Njie Frieda Egbe with HNGi7 ID HNG-05320 using python for stage 2 task friedarhema@gmail.com",
                   "name":"Njie Frieda Egbe",
                   "id":"HNG-05320",
                   "email":"friedarhema@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"05339-Oreoluwa.js",
                   "output":"Hello World, this is Oreoluwa Catherine Ogunlaja with HNGi7 ID HNG-05339 using Javascript for stage 2 task Ogunlajaoreoluwa@gmail.com",
                   "name":"Oreoluwa Catherine Ogunlaja",
                   "id":"HNG-05339",
                   "email":"Ogunlajaoreoluwa@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"05350-thekachi.js",
                   "output":"Hello World, this is Onyedikachi Nze-Ben with HNGi7 ID HNG-005350 using Javascript for stage 2 task nzebenflorence@gmail.com",
                   "name":"Onyedikachi Nze-Ben",
                   "id":"HNG-00535",
                   "email":"nil",
                   "language":"js",
                   "status":"Fail"
                },
                {
                   "file":"05355-Abdurrahman.js",
                   "output":"Hello World, this is Abdurrahman Abolaji with HNGi7 ID HNG-05355 using javascript for stage 2 task abolajiabdurrahman@gmail.com",
                   "name":"Abdurrahman Abolaji",
                   "id":"HNG-05355",
                   "email":"abolajiabdurrahman@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"05391-Tukor11.js",
                   "output":"Hello World, this is Sylvester Ifenna with HNGi7 ID HNG-05391 using JavaScript for stage 2 task tukor11@icloud.com",
                   "name":"Sylvester Ifenna",
                   "id":"HNG-05391",
                   "email":"tukor11@icloud.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"05467-michelle.py",
                   "output":"Hello World, this is Michelle Kyalo with HNGi7 ID HNG-05467 using Python for stage 2 task kyalomichelle06@gmail.com",
                   "name":"Michelle Kyalo",
                   "id":"HNG-05467",
                   "email":"kyalomichelle06@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"05511-Blossom.js",
                   "output":"Hello World, this is Babalola Blossom with HNGi7 ID HNG-05511 using Javascript for stage 2 task saphashb@gmail.com",
                   "name":"Babalola Blossom",
                   "id":"HNG-05511",
                   "email":"saphashb@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"05530-shola.js",
                   "output":"Hello World, this is Kolade Victor with HNGi7 ID HNG-05530 using javascript for stage 2 task ohksam@gmail.com",
                   "name":"Kolade Victor",
                   "id":"HNG-05530",
                   "email":"ohksam@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"05563-onyinyebeks.js",
                   "output":"Hello World, this is Goodness Chris-Ugari with HNGi7 ID HNG-05563 using JavaScript for stage 2 task goodnesschrisugari@yahoo.com",
                   "name":"Goodness Chris-Ugari",
                   "id":"HNG-05563",
                   "email":"goodnesschrisugari@yahoo.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"05636-TomiAdeniji.js",
                   "output":"Hello World, this is Ifeoluwatomi Adeniji with HNGi7 ID HNG-05636 using javascript for stage 2 task ifeoluwatomi@gmail.com",
                   "name":"Ifeoluwatomi Adeniji",
                   "id":"HNG-05636",
                   "email":"ifeoluwatomi@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"05641-Sommy.js",
                   "output":"Hello World, this is Blessing Chisom with HNGi7 ID HNG-05641 using JavaScript for stage 2 task chisomanikwenze@gmail.com",
                   "name":"Blessing Chisom",
                   "id":"HNG-05641",
                   "email":"chisomanikwenze@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"05648-Korlahwarleh90.php",
                   "output":"Hello World, this is JIMOH Mofoluwasho K. with HNGi7 ID HNG-05648 using PHP for stage 2 task jmkolawole@gmail.com",
                   "name":"JIMOH Mofoluwasho K.",
                   "id":"HNG-05648",
                   "email":"jmkolawole@gmail.com",
                   "language":"php",
                   "status":"Pass"
                },
                {
                   "file":"05652-autumn.php",
                   "output":"Hello World, this is Esther Ukandu with HNGi7 ID HNG-05652 using php for stage 2 task esthere.amara@gmail.com",
                   "name":"Esther Ukandu",
                   "id":"HNG-05652",
                   "email":"esthere.amara@gmail.com",
                   "language":"php",
                   "status":"Pass"
                },
                {
                   "file":"05703-EmmanuelNwaka.py",
                   "output":"Hello World, this is Emmanuel Nwaka with HNGi7 ID HNG-05703 using Python for stage 2 task nwakaemmanuel89@gmail.com",
                   "name":"Emmanuel Nwaka",
                   "id":"HNG-05703",
                   "email":"nwakaemmanuel89@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"05714-Iyanuoluwa.py",
                   "output":"Hello World, this is Fesobi Iyanuoluwa with HNGi7 ID HNG-05714 using python for stage 2 task iyanufes@gmail.com",
                   "name":"Fesobi Iyanuoluwa",
                   "id":"HNG-05714",
                   "email":"iyanufes@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"05716-BrendaOkumu.js",
                   "output":"Hello World, this is Brenda Okumu with HNGi7 ID HNG-05716 using JavaScript for stage 2 task brenda.okumu2@gmail.com",
                   "name":"Brenda Okumu",
                   "id":"HNG-05716",
                   "email":"brenda.okumu2@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"05818-obiora.js",
                   "output":"Hello World, this is Obiora Nwude with HNGi7 ID HNG-05818 using JavaScript for stage 2 task tobynwude@gmail.com",
                   "name":"Obiora Nwude",
                   "id":"HNG-05818",
                   "email":"tobynwude@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"05873-Ridumatics.js",
                   "output":"Hello World, this is Ridwan Onikoyi with HNGi7 ID HNG-05873 using Javascript for stage 2 task Onikoyiridwan@gmail.com",
                   "name":"Ridwan Onikoyi",
                   "id":"HNG-05873",
                   "email":"Onikoyiridwan@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"05880-ayevbeosa.py",
                   "output":"Hello World, this is Ayevbeosa Iyamu with HNGi7 ID HNG-05880 using Python for stage 2 task ayevbeosa.j@gmail.com",
                   "name":"Ayevbeosa Iyamu",
                   "id":"HNG-05880",
                   "email":"ayevbeosa.j@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"05912-Oree.js",
                   "output":"Hello World, this is Keziah Odoi with HNGi7 ID HNG-05912 using JavaScript for stage 2 task keziahodoi05@gmail.com",
                   "name":"Keziah Odoi",
                   "id":"HNG-05912",
                   "email":"keziahodoi05@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"05913-Brigance.py",
                   "output":"Hello World, this is Alawode Samuel with HNGi7 ID HNG-05913 using Python for stage 2 task tolulope.alawode@gmail.com",
                   "name":"Alawode Samuel",
                   "id":"HNG-05913",
                   "email":"tolulope.alawode@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"05916-oluwasegun.js",
                   "output":"Hello World, this is Peter Oluwasegun with HNGi7 ID HNG-05916 using JavaScript for stage 2 task petersheg@gmail.com",
                   "name":"Peter Oluwasegun",
                   "id":"HNG-05916",
                   "email":"petersheg@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"05919-petersq.js",
                   "output":"Hello World, this is Awe Peter with HNGi7 ID HNG-05919 using Javascript for stage 2 task Awe.petersq@gmail.com",
                   "name":"Awe Peter",
                   "id":"HNG-05919",
                   "email":"Awe.petersq@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"06025-Oluwaferanmi.py",
                   "output":"Hello World, this is Oluwaferanmi Olatunji with HNGi7 ID HNG-06025 using python for stage 2 task feranmiayomide@gmail.com",
                   "name":"Oluwaferanmi Olatunji",
                   "id":"HNG-06025",
                   "email":"feranmiayomide@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"06065-Adebayo.js",
                   "output":"Hello World, this is Adebayo Ilerioluwa with HNGi7 ID HNG-06065 using javascript for stage 2 task adebayorilerioluwa@gmail.com",
                   "name":"Adebayo Ilerioluwa",
                   "id":"HNG-06065",
                   "email":"adebayorilerioluwa@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"06095-Alkaseem.js",
                   "output":"Hello World, this is Alkaseem Abubakar with HNGi7 ID HNG-06095 using javascript for stage 2 task alkaseemabubakar27@gmail.com",
                   "name":"Alkaseem Abubakar",
                   "id":"HNG-06095",
                   "email":"alkaseemabubakar27@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"06173-poojithamiryala.js",
                   "output":"Hello World, this is Poojitha Miryala with HNGi7 ID HNG-06173 using javascript for stage 2 task poojithamiryala@gmail.com",
                   "name":"Poojitha Miryala",
                   "id":"HNG-06173",
                   "email":"poojithamiryala@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"06221-meyo.py",
                   "output":"Hello World, this is Ikwechegh Ukandu with HNGi7 ID HNG-06221 using Python for stage 2 task mrbjm1994@gmail.com",
                   "name":"Ikwechegh Ukandu",
                   "id":"HNG-06221",
                   "email":"mrbjm1994@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"06231-Nicole.js",
                   "output":"Hello World, this is Adedoyin Adeyemi with HNGi7 ID HNG-06231 using Javascript for stage 2 task dedoyinnicole@gmail.com",
                   "name":"Adedoyin Adeyemi",
                   "id":"HNG-06231",
                   "email":"dedoyinnicole@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"06279-dinesh.js",
                   "output":"Hello World, this is Dinesh Somaraju with HNGi7 ID HNG-06279 using javascript for stage 2 task dinesh99639@gmail.com",
                   "name":"Dinesh Somaraju",
                   "id":"HNG-06279",
                   "email":"dinesh99639@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"06309-atieno.py",
                   "output":"Hello World, this is Jinet Onyango with HNGi7 ID HNG-06309 using Python for stage 2 task jeanadagi@gmail.com",
                   "name":"Jinet Onyango",
                   "id":"HNG-06309",
                   "email":"jeanadagi@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"06319-Dahila.py",
                   "output":"Hello World, this is Gloria Iyobosa with HNGi7 ID HNG-06319 using Python for stage 2 task just_aig@rocketmail.com",
                   "name":"Gloria Iyobosa",
                   "id":"HNG-06319",
                   "email":"just_aig@rocketmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"06370-kabiratakande.js",
                   "output":"Hello World, this is Kabirat Akande with HNGi7 ID HNG-06370 using JavaScript for stage 2 task kabiratakandefolake@gmail.com",
                   "name":"Kabirat Akande",
                   "id":"HNG-06370",
                   "email":"kabiratakandefolake@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"06423-Derick.js",
                   "output":"Hello World, this is Okafor Chiemena with HNGi7 ID HNG-06423 using JavaScript for stage 2 task chiemenaokafor.co@gmail.com",
                   "name":"Okafor Chiemena",
                   "id":"HNG-06423",
                   "email":"chiemenaokafor.co@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"06502-Sarpong.py",
                   "output":"Hello World, this is Onesimus Sarpong Wiafe with HNGi7 ID HNG-06502 using python for stage 2 task sarpnissi4@gmail.com",
                   "name":"Onesimus Sarpong Wiafe",
                   "id":"HNG-06502",
                   "email":"sarpnissi4@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"06539-zahira.py",
                   "output":"Hello World, this is zahira with HNGi7 ID HNG-06539 using python for stage 2 task zahira-arain786@outlook.com",
                   "name":"zahira",
                   "id":"HNG-06539",
                   "email":"zahira-arain786@outlook.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"06540-Modupe.py",
                   "output":"Hello World, this is Falodun Modupe, with HNGi7 ID HNG-06540 using Python for stage 2 task falodunmodupeola@gmail.com",
                   "name":"Falodun Modupe,",
                   "id":"HNG-06540",
                   "email":"falodunmodupeola@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"06580-emwhylaj.js",
                   "output":"Hello World, this is Olalekan Muyiwa Olawale with HNGi7 ID HNG-06580 using Javascript for stage 2 task olawaleolalekan1307@gmail.com",
                   "name":"Olalekan Muyiwa Olawale",
                   "id":"HNG-06580",
                   "email":"olawaleolalekan1307@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"06605-ameliaoneh.js",
                   "output":"Hello World, this is Amelia Oneh with HNGi7 ID HNG-06605 using JavaScript for stage 2 task aimieisaac@yahoo.com",
                   "name":"Amelia Oneh",
                   "id":"HNG-06605",
                   "email":"aimieisaac@yahoo.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"06761-nnekasandra.py",
                   "output":"Hello World, this is Njoku Nneka Sandra with HNGi7 IDHNG-06761 using Python for stage 2 task nnekasandra2016@gmail.com",
                   "name":"Njoku Nneka Sandra",
                   "id":"HNG-06761",
                   "email":"nil",
                   "language":"py",
                   "status":"Fail"
                },
                {
                   "file":"HNG-00287-Bhenjameen.py",
                   "output":"Hello World, this is Eyoh Benjamin Patrick with HNGi7 ID HNG-00287 using Python for stage 2 task bp.eyoh@gmail.com",
                   "name":"Eyoh Benjamin Patrick",
                   "id":"HNG-00287",
                   "email":"bp.eyoh@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"HNG-00828-Liz.js",
                   "output":"Hello World, this is Elizabeth Bassey with HNGi7 ID HNG-00828 using javascript for stage 2 task basseyelizabeth569@gmail.com",
                   "name":"Elizabeth Bassey",
                   "id":"HNG-00828",
                   "email":"basseyelizabeth569@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"HNG-01123-mary.php",
                   "output":"Hello World, this is Mary Effiong-Okon with HNGi7 ID HNG-01123 using Php for stage 2 task maryeffiong90@gmail.com",
                   "name":"Mary Effiong-Okon",
                   "id":"HNG-01123",
                   "email":"maryeffiong90@gmail.com",
                   "language":"php",
                   "status":"Pass"
                },
                {
                   "file":"HNG-01262-Arhnu.py",
                   "output":"Hello World, this is Anuoluwapo Oyeboade with HNGi7 ID HNG-01262 using python for stage 2 task oyeboadeanuoluwapo@gmail.com",
                   "name":"Anuoluwapo Oyeboade",
                   "id":"HNG-01262",
                   "email":"oyeboadeanuoluwapo@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"HNG-01819-mercyk.php",
                   "output":"Hello world, this is Mercy Kipyegon with HNGi7 ID HNG-01819 using PHP for stage 2 task mercyjemosop@gmail.com",
                   "name":"Mercy Kipyegon",
                   "id":"HNG-01819",
                   "email":"mercyjemosop@gmail.com",
                   "language":"php",
                   "status":"Pass"
                },
                {
                   "file":"HNG-01899-Comfort.py",
                   "output":"Hello World, this is Abodunrin Comfort with HNGi7 ID HNG-01899 using Python for Stage 2 task comfortabodunrin@gmail.com",
                   "name":"Abodunrin Comfort",
                   "id":"HNG-01899",
                   "email":"comfortabodunrin@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"HNG-01949-Resa.php",
                   "output":"Hello World, this is Theresa Obamwonyi with HNGi7 ID HNG-01949 using PHP for stage 2 task theresaobamwonyi@gmail.com",
                   "name":"Theresa Obamwonyi",
                   "id":"HNG-01949",
                   "email":"theresaobamwonyi@gmail.com",
                   "language":"php",
                   "status":"Pass"
                },
                {
                   "file":"HNG-02042-moe.js",
                   "output":"Hello World, this is Mary Ojo with HNGi7 ID HNG-02042 using JavaScript for stage 2 task maryojo3@gmail.com",
                   "name":"Mary Ojo",
                   "id":"HNG-02042",
                   "email":"maryojo3@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"HNG-02083-startoffs.js",
                   "output":"Hello World, this is Morounfolu Adesanya with HNGi7 ID HNG-02083 using Javascript for stage 2 task adelekesays@gmail.com",
                   "name":"Morounfolu Adesanya",
                   "id":"HNG-02083",
                   "email":"adelekesays@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"HNG-02085-amaps.js",
                   "output":"Hello World, this is Omieibi Promise Amapakabo with HNGi7 ID HNG-02085 using Javascript for stage 2 task aomieibi@yahoo.com",
                   "name":"Omieibi Promise Amapakabo",
                   "id":"HNG-02085",
                   "email":"aomieibi@yahoo.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"HNG-02354-mcsepro.js",
                   "output":"Hello World, this is Martins Arem with HNGi7 ID HNG-02354 using JavaScript for stage 2 task aremson4love@gmail.com",
                   "name":"Martins Arem",
                   "id":"HNG-02354",
                   "email":"aremson4love@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"HNG-02593-Adekemi.js",
                   "output":"Hello World, this is Adekemi Borode with HNGi7 ID HNG-02593 using Javascript for stage 2 task borodeadekemi@gmail.com",
                   "name":"Adekemi Borode",
                   "id":"HNG-02593",
                   "email":"borodeadekemi@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"HNG-03437-sweetheart.php",
                   "output":"Hello World, this is Amadikwa Joy with HNGi7 ID HNG-03437 using PHP for stage 2 task amadikwajoyn@gmail.com",
                   "name":"Amadikwa Joy",
                   "id":"HNG-03437",
                   "email":"amadikwajoyn@gmail.com",
                   "language":"php",
                   "status":"Pass"
                },
                {
                   "file":"HNG-03857-david.js",
                   "output":"Hello World, this is David Ajibade with HNGi7 ID HNG-03857 using JavaScript for stage 2 task ajidaveini@gmail.com",
                   "name":"David Ajibade",
                   "id":"HNG-03857",
                   "email":"ajidaveini@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"HNG-04036-kwaku.php",
                   "output":"Hello World, this is kwaku antwi with HNGi7 ID HNG-04036 using PHP for stage 2 task kwaku.takyi@gmail.com",
                   "name":"kwaku antwi",
                   "id":"HNG-04036",
                   "email":"kwaku.takyi@gmail.com",
                   "language":"php",
                   "status":"Pass"
                },
                {
                   "file":"HNG-04659-adebola.js",
                   "output":"Hello World, this is Adebola Adeniran with HNGi7 ID HNG-04659 using JavaScript for stage 2 task adebola.rb.js@gmail.com",
                   "name":"Adebola Adeniran",
                   "id":"HNG-04659",
                   "email":"adebola.rb.js@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"HNG-05244-okoroaforchristian.py",
                   "output":"Hello World, this is Okoroafor Christian with HNGi7 ID HNG-05244 using Python Programming language for stage 2 task chrisnonsookoroafor@gmail.com",
                   "name":"Okoroafor Christian",
                   "id":"HNG-05244",
                   "email":"chrisnonsookoroafor@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"HNG-05245-samheart.js",
                   "output":"Hello World, this is Samuel Ndukwe with HNGi7 ID HNG-05245 using JavaScript for stage 2 task ndukwesamuel23@gmail.com",
                   "name":"Samuel Ndukwe",
                   "id":"HNG-05245",
                   "email":"ndukwesamuel23@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"HNG-05337-halimah.php",
                   "output":"Hello World, this is AbdulAzeez Halimah with HNGi7 ID HNG-05337 using PHP for stage 2 task abdulazeezhaleemah@gmail.com",
                   "name":"AbdulAzeez Halimah",
                   "id":"HNG-05337",
                   "email":"abdulazeezhaleemah@gmail.com",
                   "language":"php",
                   "status":"Pass"
                },
                {
                   "file":"HNG-05367-itsmaleeq.js",
                   "output":"Hello World, this is BABAJIDE ONAYEMI with HNGi7 ID HNG-05367 using javascript for stage 2 task princefemibabs@gmail.com",
                   "name":"BABAJIDE ONAYEMI",
                   "id":"HNG-05367",
                   "email":"princefemibabs@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"HNG-05481-zibest.py",
                   "output":"Hello World, this is WISDOM OBINNA with HNGi7 ID HNG-05481 using Python for stage 2 task kcwisdom7@gmail.com",
                   "name":"WISDOM OBINNA",
                   "id":"HNG-05481",
                   "email":"kcwisdom7@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"HNG-05597-gbengaoo.js",
                   "output":"Hello World, this is Olugbenga Odedele with HNGi7 ID HNG-05597 using javascript for stage 2 task odedeleg@gmail.com",
                   "name":"Olugbenga Odedele",
                   "id":"HNG-05597",
                   "email":"odedeleg@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"HNG-05632-akinshafi.php",
                   "output":"Hello World, this is Shafi Akinropo with HNGi7 ID HNG-05632 using PHP for stage 2 task sakinropo@gmail.com",
                   "name":"Shafi Akinropo",
                   "id":"HNG-05632",
                   "email":"sakinropo@gmail.com",
                   "language":"php",
                   "status":"Pass"
                },
                {
                   "file":"HNG-05635-khalid.py",
                   "output":"Hello World, this is Khalid Bello with HNGi7 ID HNG-05365 using python for stage 2 task khalidbello279@gmail.com",
                   "name":"Khalid Bello",
                   "id":"HNG-05365",
                   "email":"khalidbello279@gmail.com",
                   "language":"py",
                   "status":"Pass"
                },
                {
                   "file":"HNG-06243-greycexcel.js",
                   "output":"Hello World, this is Chioma Nkwonwe with HNGi7 ID HNG-06243 using JavaScript for stage 2 task chiomankwonwe123@gmail.com",
                   "name":"Chioma Nkwonwe",
                   "id":"HNG-06243",
                   "email":"chiomankwonwe123@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"HNG-06256-Nikhil.js",
                   "output":"Hello World, this is Nikhil Lalam with HNGi7 ID HNG-06256 using javascript for stage 2 task nikhil.lalam123@gmail.com",
                   "name":"Nikhil Lalam",
                   "id":"HNG-06256",
                   "email":"nikhil.lalam123@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"HNG-06510-phil_.js",
                   "output":"Hello World, this is Linda Ezeoba with HNGi7 ID HNG-06510 using javascript for stage 2 task lyndaezeoba@gmail.com",
                   "name":"Linda Ezeoba",
                   "id":"HNG-06510",
                   "email":"lyndaezeoba@gmail.com",
                   "language":"js",
                   "status":"Pass"
                },
                {
                   "file":"_rophyat.js_",
                   "output":"Hello World, this is Rofiat Korodo with HNGi7 ID HNG-06180 using JS for stage 2 task rophyataderonke@gmail.com",
                   "name":"Rofiat Korodo",
                   "id":"HNG-06180",
                   "email":"rophyataderonke@gmail.com",
                   "language":"js_",
                   "status":"Pass"
                },
                {
                   "file":"hng-06252-rcramachandra.js",
                   "output":"Hello World, this is Rama chandra Nidamarthi with HNGi7 ID HNG-06252 using javascript for stage 2 task ramachandrajune25@gmail.com",
                   "name":"Rama chandra Nidamarthi",
                   "id":"HNG-06252",
                   "email":"ramachandrajune25@gmail.com",
                   "language":"js",
                   "status":"Pass"
                }
             ]
            ';

        $data = json_decode($json, true);

        $all_submissions = 0;
        $passed_submissions = 0;
        $failed_submissions = 0;

        $arr = array();

        foreach($data as $datum){
            $all_submissions++;
            if($datum['status'] == 'pass'){
                $passed_submissions++;

                $email = str_replace(' ', '', $datum['email']);
                $user = User::where('email', $email)->first();

                if(!empty($user) && $user->stage == 1){
                    //promote user here

                    // $slack_id =  $user->slack_id;
                    // Slack::removeFromChannel($slack_id, 1);
                    // Slack::addToChannel($slack_id, 2);
                    // $user->stage = 2;
                    // $user->save();
                }else{
                    continue;
                    
                }
            }else{
                $failed_submissions++;
                // $exit_date = Carbon::now()->addDays(1);
                // $reason = 'FAILED_TEAM_TASK';
                // Probation::insert(['user_id'=>$user->id, 'probated_by' => Auth::user()->id, 'probation_reason' => $reason ?? null, 'exit_on' => $exit_date]);
                // Slack::addToGroup($slack_id, 'isolation-center');
            }
        }

        // $arr = array();
        $arr['total'] = $all_submissions;
        $arr['pass_count'] = $passed_submissions;
        $arr['fail_count'] = $failed_submissions;

        return $arr;
    }

    public function get_pass_list(Request $request){
        $url = $request->url;

        $cURLConnection = curl_init();
        curl_setopt($cURLConnection, CURLOPT_URL, $url);
        curl_setopt($cURLConnection, CURLOPT_RETURNTRANSFER, true);

        $submissionList = curl_exec($cURLConnection);
        curl_close($cURLConnection);

        $data = json_decode($submissionList, true);

        $arr = array();

        foreach($data as $datum){
            $all_submissions++;
            if($datum['status'] == 'pass'){
                $email = str_replace(' ', '', $datum['email']);
                $arr[] = $email;
            }
        }

        return $arr;
    }
}

    