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
            {
            "file": "Aaron-Kipkoech.py",
            "output": "Hello World, this is Aaron Kipkoech with HNGi7 ID HNG-03499  using python for stage 2 task",
            "name": "Aaron Kipkoech",
            "id": "HNG-03499",
            "email": "aaronrono42@gmail.com",
            "language": "python",
            "status": "pass"
            },
            {
            "file": "Abdulmalik-Quadri.js",
            "output": "Hello World, this is Quadri Abdulmalik with HNGi7 ID HNG-00837  using JavaScript for stage 2 task",
            "name": "Abdulmalik Quadri",
            "id": "HNG-00837",
            "email": "mackquadrizz@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Abdulmuqit-Shuaib.js",
            "output": "Hello World, this is Abdulmuqit Shuaib with HNGi7 ID HNG-02508  using Javascript for stage 2 task",
            "name": "Abdulmuqit Shuaib",
            "id": "HNG-02508",
            "email": "horleryeeworler@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Abdulrasheed-Adediran.js",
            "output": "Hello World, this is Abdulrasheed Adediran with HNGi7 ID HNG-02383  using JavaScript for stage 2 task.",
            "name": "Abdulrasheed Adediran",
            "id": "HNG-02383",
            "email": "adediran.ajibade@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Abiodun-Abdullateef.js",
            "output": "Hello World, this is Abiodun Abdullateef with HNGi7 ID HNG-00908  using JavaScript for stage 2 task",
            "name": "Abiodun Abdullateef",
            "id": "HNG-00908",
            "email": "yomlateef@yahoo.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Abiodun-Olushola.js",
            "output": "Hello World, this is Abiodun Olushola with HNGi7 ID HNG-03204  using JavaScript for stage 2 task",
            "name": "Abiodun Olushola",
            "id": "HNG-03204",
            "email": "olushola.abiodun@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Adakole-James.php",
            "output": "Hello World, this is Adakole Inalegwu James with HNGi7 ID HNG-00274  using PHP for stage 2 task",
            "name": "Adakole James",
            "id": "HNG-00274",
            "email": "jambone.james82@gmail.com",
            "language": "PHP",
            "status": "pass"
            },
            {
            "file": "Adariku-Isaac.js",
            "output": "Hello World, this is Adariku Isaac with HNGi7 ID HNG-03502  using JavaScript for stage 2 task.",
            "name": "Adariku Isaac",
            "id": "HNG-03502",
            "email": "isaacadariku05@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Adebare-Amos.js",
            "output": "Hello World, this is Adebare Amos with HNGi7 ID HNG-02063  using JavaScript for stage 2 task",
            "name": "Adebare Amos",
            "id": "HNG-02063",
            "email": "adeinfo2015@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Adebayo-Pelumi.js",
            "output": "Hello World, this is Pelumi Adebayo with HNGi7 ID HNG-03262  using Javascript for stage 2 task",
            "name": "Adebayo Pelumi",
            "id": "HNG-03262",
            "email": "adepelumi1996@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Adebimpe-Adewole.js",
            "output": "Hello World, this is Adebimpe Adewole with HNGi7 ID HNG-02819  using Javascript for stage 2 task",
            "name": "Adebimpe Adewole",
            "id": "HNG-02819",
            "email": "realadebimpe@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Adebowale-Akande.js",
            "output": "Hello World, this is Adebowale Akande with HNGi7 ID HNG-01754  using Javascript for stage 2 task",
            "name": "Adebowale Akande",
            "id": "HNG-01754",
            "email": "akandeadebowale0@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Adedayo-Adewole.py",
            "output": "Hello World, this is Adedayo Adewole with HNGi7 ID HNG-02957  using python for stage 2 task",
            "name": "Adedayo Adewole",
            "id": "HNG-02957",
            "email": "madedayo@gmail.com",
            "language": "python",
            "status": "pass"
            },
            {
            "file": "Adele-Jennifer.py",
            "output": "Hello World, this is Adele Jennifer with HNGi7 ID HNG-01007  using Python for stage 2 task",
            "name": "Adele Jennifer",
            "id": "HNG-01007",
            "email": "ekadele5@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "Ademola-Akinsola.py",
            "output": "Hello World, this is Ademola Akinsola with HNGi7 ID HNG-01126  using python for stage 2 task",
            "name": "Ademola Akinsola",
            "id": "HNG-01126",
            "email": "akinsolaademolatemitope@gmail.com",
            "language": "python",
            "status": "pass"
            },
            {
            "file": "Adeniyi-Lekan.js",
            "output": "Hello World, this is Adeniyi Lekan Femi with HNGi7 ID HNG-02968  using JavaScript for stage 2 task",
            "name": "Adeniyi Lekan",
            "id": "HNG-02968",
            "email": "holarfemilekan049@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Adetola-Adeyeye.java",
            "output": "",
            "name": "Adetola Adeyeye",
            "id": "null",
            "email": "",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "AdetolaAdeyeye.class",
            "output": "\u0001\u0000\u001d\u0000\u0000\u0000\u0002\u0000\u001e",
            "name": "AdetolaAdeyeye",
            "id": "null",
            "email": "",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "Adetunji-Tejumade.py",
            "output": "Hello world, this is Adetunji Tejumade With HNGi7 ID HNG-00420  using python for stage 2 task",
            "name": "Adetunji Tejumade",
            "id": "HNG-00420",
            "email": "tejumadeadetunji@gmail.com",
            "language": "python",
            "status": "pass"
            },
            {
            "file": "Adeyemi-Matthew.py",
            "output": "Hello World, this is Adeyemi Matthew Iyanuoluwa with HNGi7 ID HNG-03087  using python for stage 2 task",
            "name": "Adeyemi Matthew",
            "id": "HNG-03087",
            "email": "attadeyemi15r@gmail.com",
            "language": "python",
            "status": "pass"
            },
            {
            "file": "AdeyemiTimilehin.php",
            "output": "",
            "name": "AdeyemiTimilehin",
            "id": "null",
            "email": "",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "Adeyemo_Sulaiman.php",
            "output": "Hello world, this is Adeyemo Sulaiman with HNGi7 ID HNG-03721  using php for stage 2 task",
            "name": "Adeyemo_Sulaiman",
            "id": "HNG-03721",
            "email": "sulaiman_adeyinka@yahoo.com",
            "language": "php",
            "status": "pass"
            },
            {
            "file": "Adigun_samuel.js",
            "output": "Hello World, this is Adigun Samuel with HNGi7 ID HNG-02214  using javaScript for stage 2 task",
            "name": "Adigun_samuel",
            "id": "HNG-02214",
            "email": "creativequest321@gmail.com",
            "language": "javaScript",
            "status": "pass"
            },
            {
            "file": "Ahmed-Bankole.js",
            "output": "Hello World, this is Bankole Ahmed  with HNGi7 ID HNG-02467  using JavaScript for stage 2 task",
            "name": "Ahmed Bankole",
            "id": "null",
            "email": "kidrolex19@gmail.com",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "Ajibade-abdullah.js",
            "output": "Hello World, this is Ajibade abdullah with HNGi7 ID HNG-03951  using NodeJs for stage 2 task",
            "name": "Ajibade abdullah",
            "id": "HNG-03951",
            "email": "ajibadeabd@gmail.com",
            "language": "NodeJs",
            "status": "pass"
            },
            {
            "file": "Ajibola-Ojo.js",
            "output": "Hello World, this is Ajibola Ojo with HNGi7 ID HNG-004478  using JavaScript for stage 2 task",
            "name": "Ajibola Ojo",
            "id": "HNG-004478",
            "email": "pro.ajibolaojo@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Akash-Prasher.js",
            "output": "Hello World, this is Akash Prasher with HNGi7 ID HNG-06260  using JavaScript for stage 2 task",
            "name": "Akash Prasher",
            "id": "HNG-06260",
            "email": "17bcs2419@cuchd.in",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Akinlua-Bolamigbe.js",
            "output": "Hello World, this is Akinlua Bolamigbe Jomiloju with HNGi7 ID HNG-04764  using Javascript for stage 2 task",
            "name": "Akinlua Bolamigbe",
            "id": "HNG-04764",
            "email": "bolamigbeakinlua@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Akinyemi-Oluwayemisi.js",
            "output": "Hello World, this is Akinyemi Oluwayemisi with HNGi7 ID HNG-02253  using javascript for stage 2 task",
            "name": "Akinyemi Oluwayemisi",
            "id": "HNG-02253",
            "email": "akinyemitiana77@gmail.com",
            "language": "javascript",
            "status": "pass"
            },
            {
            "file": "Akoji-Francis.py",
            "output": "Hello World, this is Akoji Francis with HNGi7 ID HNG-01528  using Python for Stage 2 Task",
            "name": "Akoji Francis",
            "id": "HNG-01528",
            "email": "akfrendo@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "Akoke-Anto.php",
            "output": "Hello World, this is Akoke Anto with HNGi7 ID HNG-06678  using PHP for stage 2 task",
            "name": "Akoke Anto",
            "id": "HNG-06678",
            "email": "veeqanto@gmail.com",
            "language": "PHP",
            "status": "pass"
            },
            {
            "file": "Alexander-Ugwuanyi.js",
            "output": "Hello World, this is Alexander Ugwuanyi with HNGi7 ID HNG-02522  using JavaScript for stage 2 task",
            "name": "Alexander Ugwuanyi",
            "id": "HNG-02522",
            "email": "ugwuanyi.alexander.chukwuebuka@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Alumona-micah.php",
            "output": "Hello world, this is Alumona Micah  with HNGi7 ID HNG-04840  using PHP for stage 2 task",
            "name": "Alumona micah",
            "id": "null",
            "email": "micahalumona@gmail.com",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "Andrew-Nwose.js",
            "output": "Hello world, this is Andrew Nwose with HNGi7 ID HNG-00379  using JavaScript for stage 2 task",
            "name": "Andrew Nwose",
            "id": "HNG-00379",
            "email": "andreinwose@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Anita-Achu.py",
            "output": "Hello World, this is Anita Achu with HNGi7 ID HNG-04285  using python for stage 2 task",
            "name": "Anita Achu",
            "id": "HNG-04285",
            "email": "anitatom20@gmail.com",
            "language": "python",
            "status": "pass"
            },
            {
            "file": "Anita-Jessa.php",
            "output": "Hello world, this is Jessa Anita with HNGi7 ID HNG-02058  using PHP for stage 2 task.",
            "name": "Anita Jessa",
            "id": "HNG-02058",
            "email": "brightjaniluv@gmail.com",
            "language": "PHP",
            "status": "pass"
            },
            {
            "file": "Anjolaoluwa-Onifade.js",
            "output": "Hello World, this is Anjolaoluwa Onifade with HNGi7 ID HNG-02003  using Javascript for stage 2 task.",
            "name": "Anjolaoluwa Onifade",
            "id": "HNG-02003",
            "email": "anjyfade@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Anyankah_Netochukwu.js",
            "output": "Hello World, this is Anyankah Netochukwu with HNGi7 ID HNG-05168  using Javascript for stage 2 task",
            "name": "Anyankah_Netochukwu",
            "id": "HNG-05168",
            "email": "nanyankah@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Areh-Babatunde.js",
            "output": "Hello World, this is Areh Babatunde with HNGi7 ID HNG-01568  using Javascript for stage 2 task.",
            "name": "Areh Babatunde",
            "id": "HNG-01568",
            "email": "arehtunde96@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Augusta-Ehihebolo.js",
            "output": "Hello World, this is Augusta Ehihebolo with HNGi7 ID HNG-02000  using JavaScript for stage 2 task",
            "name": "Augusta Ehihebolo",
            "id": "HNG-02000",
            "email": "ehiheboloaugustar@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Augustine-Chukwu.py",
            "output": "Hello World, this is Augustine Chukwu with HNGi7 ID HNG-01030  using Python for stage 2 task",
            "name": "Augustine Chukwu",
            "id": "HNG-01030",
            "email": "caugust19.ac@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "Ayomide-Adefe.js",
            "output": "Hello World, this is Ayomide Adefe with HNGi7 ID HNG-02441  using Javascript for stage 2 task",
            "name": "Ayomide Adefe",
            "id": "HNG-02441",
            "email": "ayomideadefe@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Ayomide-Bamigboye.js",
            "output": "Hello World, this is Ayomide Bamigboye with HNGi7 ID HNG-05116  using JavaScript for stage 2 task",
            "name": "Ayomide Bamigboye",
            "id": "HNG-05116",
            "email": "bamigboyeayomide200@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Ayomide-Onibokun.php",
            "output": "Hello World, this is Ayomide Onibokun with HNGi7 ID HNG-02598   using PHP for stage 2 task",
            "name": "Ayomide Onibokun",
            "id": "HNG-02598",
            "email": "ayo6706@gmail.com",
            "language": "PHP",
            "status": "pass"
            },
            {
            "file": "Ayotunde-Oyekan.js",
            "output": "Hello World, this is Ayotunde Oyekan with HNGi7 ID HNG-00437  using JavaScript for stage 2 task",
            "name": "Ayotunde Oyekan",
            "id": "HNG-00437",
            "email": "oyekanayotunde56@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Bakare_yusuf.php",
            "output": "Hello World, this is Yusuf Bakare with HNGi7 ID HNG-03691  using PHP for stage 2 task",
            "name": "Bakare_yusuf",
            "id": "HNG-03691",
            "email": "info.yeag@gmail.com",
            "language": "PHP",
            "status": "pass"
            },
            {
            "file": "Bariesuador-Nwibani.php",
            "output": "Hello World, this is Bariesuador Harmony Nwibani with HNGi7 ID HNG-00046  using PHP for Stage 2 task",
            "name": "Bariesuador Nwibani",
            "id": "HNG-00046",
            "email": "esuador@gmail.com",
            "language": "PHP",
            "status": "pass"
            },
            {
            "file": "Bernard-Arhia.js",
            "output": "Hello world, this is Arhia Bernard with HNGi7 ID HNG-00347  using Javascript for stage 2 task",
            "name": "Bernard Arhia",
            "id": "HNG-00347",
            "email": "bernardarhia@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Bolaji-ola.js",
            "output": "Hello World, this is Bolaji Ola with HNGi7 ID HNG-1703  using JavaScript for stage 2 task",
            "name": "Bolaji ola",
            "id": "HNG-1703",
            "email": "afeezbolajiola@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Bolarinwa-Owuogba.js",
            "output": "Hello World, this is Bolarinwa Owuogba with HNGi7 ID HNG-04092  using JavaScript for stage 2 task",
            "name": "Bolarinwa Owuogba",
            "id": "HNG-04092",
            "email": "bhorlarinwah@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Boluwaji-Osakinle.js",
            "output": "Hello World, this is Boluwaji Osakinle with HNGi7 ID HNG-04673  using javascript for stage 2 task",
            "name": "Boluwaji Osakinle",
            "id": "HNG-04673",
            "email": "Boluwajiosakinle@gmail.com",
            "language": "javascript",
            "status": "pass"
            },
            {
            "file": "Boluwatife-ojo.js",
            "output": "Hello World, this is Boluwatife Ojo with HNGi7 ID HNG-01355  using Javascript for stage 2 task",
            "name": "Boluwatife ojo",
            "id": "HNG-01355",
            "email": "ojoboluwatife017@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Busayo-Olushola.py",
            "output": "Hello World, this is Busayo Olushola with HNGi7 ID HNG-02411  using python for stage 2 task",
            "name": "Busayo Olushola",
            "id": "HNG-02411",
            "email": "gideonbusayo@gmail.com",
            "language": "python",
            "status": "pass"
            },
            {
            "file": "Charles-Magbo.js",
            "output": "Hello World, this is Magbo Charles with HNGi7 ID HNG-05340  using Javascript for stage 2 task",
            "name": "Charles Magbo",
            "id": "HNG-05340",
            "email": "Magboelochukwu@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Chibueze-Nkwocha.js",
            "output": "Hello World, this is Chibueze Nkwocha with HNGi7 ID HNG-03305  using JavaScript for stage 2 task",
            "name": "Chibueze Nkwocha",
            "id": "HNG-03305",
            "email": "chibuezenkwocha@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Chidubem-Onyeukwu.js",
            "output": "Hello World, this isChidubem Onyeukwu with HNGi7 IDHNG-03733usingJavascript for stage 2 task",
            "name": "Chidubem Onyeukwu",
            "id": "null",
            "email": "",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "Chinedu-Ijeomah.js",
            "output": "Hello World, this is Chinedu Princewill Ijeomah with HNGi7 ID HNG-06104  using JavaScript for stage 2 task",
            "name": "Chinedu Ijeomah",
            "id": "HNG-06104",
            "email": "chinedu.ijeomah@yahoo.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Chinonso-Okafor.js",
            "output": "Hello World, this is Chinonso Okafor with HNGi7 ID HNG-01408  using JavaScript for stage 2 task",
            "name": "Chinonso Okafor",
            "id": "HNG-01408",
            "email": "justcoolk@yahoo.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Chizulum-Nnodu.py",
            "output": "",
            "name": "Chizulum Nnodu",
            "id": "null",
            "email": "",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "Christian-Nwachukwu.js",
            "output": "Node.js for stage 2 task.",
            "name": "Christian Nwachukwu",
            "id": "null",
            "email": "",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "Christianah-Amoo.js",
            "output": "Hello World, this is Christianah Amoo with HNGi7 ID HNG-05839  using Javascript for Stage 2 task",
            "name": "Christianah Amoo",
            "id": "HNG-05839",
            "email": "amoochristianah454@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Chukwuanieze-Samson.js",
            "output": "Hello World, this is Chukwuanieze Samson with HNGi7 ID HNG-05868  using Javascript for stage 2 task",
            "name": "Chukwuanieze Samson",
            "id": "HNG-05868",
            "email": "samsonnnamdi88@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Chukwubuikem-Chiabotu.js",
            "output": "Hello World, this is Chukwubuikem Chiabotu with HNGi7 ID HNG-05994  using JavaScript for stage 2 task",
            "name": "Chukwubuikem Chiabotu",
            "id": "HNG-05994",
            "email": "chibykes99@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Chukwudi-Ochuenwike.js",
            "output": "Hello World, this is Chukwudi Ochuenwike with HNGi7 ID HNG-05892  using JavaScript for stage 2 task",
            "name": "Chukwudi Ochuenwike",
            "id": "HNG-05892",
            "email": "ddmichael94@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Damianson-Wisdom-Uchegbu.py",
            "output": "Hello World, this is Uchegbu Damianson-Wisdom Onyekachi with HNGi7 ID HNG-00644  using python for stage 2 task",
            "name": "Damianson Wisdom Uchegbu",
            "id": "HNG-00644",
            "email": "damiansonuchegbu2017@gmail.com",
            "language": "python",
            "status": "pass"
            },
            {
            "file": "Damilare-Olagbende.js",
            "output": "Hello World, this is Damilare Richard Olagbende with HNGi7 ID HNG-01798  using Javascript for stage 2 task",
            "name": "Damilare Olagbende",
            "id": "HNG-01798",
            "email": "damilareshinaayo@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Damilola-Martin.js",
            "output": "Hello World, this is Damilola Martin with HNGi7 ID HNG-05598  using JavaScript for stage 2 task",
            "name": "Damilola Martin",
            "id": "HNG-05598",
            "email": "damolly97@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Daniel-Adeneye.js",
            "output": "Hello World, this is Daniel Adeneye with HNGi7 ID HNG-02304  using Javascript for stage 2 task",
            "name": "Daniel Adeneye",
            "id": "HNG-02304",
            "email": "adeneyedaniel007@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Daniel-Igwe.py",
            "output": "Hello World, this is Daniel Igwe with HNGi7 ID HNG-02682  using Python for stage 2 task",
            "name": "Daniel Igwe",
            "id": "HNG-02682",
            "email": "danielchibuzoigwe@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "Dashua_Gamaliel.js",
            "output": "Hello World, this is Dashua Gamaliel Chinkidda with HNGi7 ID HNG-02186  using JavaScript for stage 2 task",
            "name": "Dashua_Gamaliel",
            "id": "HNG-02186",
            "email": "gdashua29@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Dasilva_Akorede.py",
            "output": "Hello World, this is Akorede Da-Silva with HNGi7 ID HNG-01085  using Python for stage 2 task.",
            "name": "Dasilva_Akorede",
            "id": "HNG-01085",
            "email": "qorede@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "David-ENORAGBON.js",
            "output": "Hello World, this is David ENORAGBON with HNGi7 ID HNG-04977  using Javascript for stage 2 task",
            "name": "David ENORAGBON",
            "id": "HNG-04977",
            "email": "enoragbondavid35@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "David-Emmanuel.js",
            "output": "Hello world, this is David Emmanuel with HNGi7 ID HNG-03587  using Javascript for stage 2.",
            "name": "David Emmanuel",
            "id": "null",
            "email": "dave.emix@gmail.com",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "Deborah-ajayi.js",
            "output": "Hello World, this is Deborah Ajayi with HNGi7 ID HNG-03850  using Javascript for Stage 2 task",
            "name": "Deborah ajayi",
            "id": "HNG-03850",
            "email": "speak2debby@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Desola_Azeez.php",
            "output": "Hello World, this is Desola Azeez with HNGi7 ID HNG-04043  using php for stage 2 task.",
            "name": "Desola_Azeez",
            "id": "HNG-04043",
            "email": "azeezibukunoluwa@gmail.com",
            "language": "php",
            "status": "pass"
            },
            {
            "file": "Dev-Quie.php",
            "output": "Hello World, this is Otu Ekong with HNGi7 ID HNG-02060  using PHP for stage 2 task.",
            "name": "Dev Quie",
            "id": "HNG-02060",
            "email": "devquie@gmail.com",
            "language": "PHP",
            "status": "pass"
            },
            {
            "file": "Divakaran-T.py",
            "output": "Hello world, this is Divakaran With HNGi7 ID HNG-01546  using python for stage 2 task",
            "name": "Divakaran T",
            "id": "HNG-01546",
            "email": "dhvakr@gmail.com",
            "language": "python",
            "status": "pass"
            },
            {
            "file": "Divine-Ugorji.java",
            "output": "",
            "name": "Divine Ugorji",
            "id": "null",
            "email": "",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "Dolapo-Olatunji.js",
            "output": "Hello World, this is Dolapo Olatunji with HNGi7 ID HNG-01852  using JavaScript for stage 2 task",
            "name": "Dolapo Olatunji",
            "id": "HNG-01852",
            "email": "nofeesahdee@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Douglas-Dominic.py",
            "output": "Hello World, this is Douglas Dominic with HNGi7 ID HNG-01669  using Python for stage 2 task",
            "name": "Douglas Dominic",
            "id": "HNG-01669",
            "email": "ejise45@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "Duru-young-Raymond.js",
            "output": "Hello World, this is Duru-young Raymond with HNGi7 ID HNG-05544  using javaScript for stage 2 task",
            "name": "Duru young Raymond",
            "id": "HNG-05544",
            "email": "duruyoungcr@gmail.com",
            "language": "javaScript",
            "status": "pass"
            },
            {
            "file": "Eboreime-ThankGod.js",
            "output": "Hello World, this is Eboreime ThankGod chukwuweike with HNGi7 ID HNG-02109  using javascript for stage 2 task",
            "name": "Eboreime ThankGod",
            "id": "HNG-02109",
            "email": "eboreimethankgod@gmail.com",
            "language": "javascript",
            "status": "pass"
            },
            {
            "file": "Eboreime-rhoda.js",
            "output": "Hello World, this is Eboreime Rhoda with HNGi7 ID HNG-01078  using Javascript for stage 2 task",
            "name": "Eboreime rhoda",
            "id": "HNG-01078",
            "email": "rhorosely@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Edori-Atiri.js",
            "output": "Hello World, this is Edori Atiri with HNGi7 ID HNG-00398  using Javascript for stage 2 task.",
            "name": "Edori Atiri",
            "id": "HNG-00398",
            "email": "edoriatiri@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Efuetbeja-Bright.js",
            "output": "Hello World, this is Efuetbeja Bright with HNGi7 ID HNG-00907  using JavaScript for stage 2 task",
            "name": "Efuetbeja Bright",
            "id": "HNG-00907",
            "email": "tanzebright@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Egbekwu-Nwanedilobu.php",
            "output": "Hello World, this is Egbekwu Nwanedilobu Uche with HNGi7 ID HNG-02761  using PHP for stage 2 task",
            "name": "Egbekwu Nwanedilobu",
            "id": "HNG-02761",
            "email": "egbekwunwanedilobu@gmail.com",
            "language": "PHP",
            "status": "pass"
            },
            {
            "file": "Ekor-Ibor.js",
            "output": "Hello World, this is Ekor Ibor with HNGi7 ID HNG-03190  using JavaScript for stage 2 task",
            "name": "Ekor Ibor",
            "id": "HNG-03190",
            "email": "iborekor@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Elisha-Ukpong.php",
            "output": "Hello World, this is Elisha Ukpong with HNGi7 ID HNG-03659  using JavaScript for stage 2 task.",
            "name": "Elisha Ukpong",
            "id": "HNG-03659",
            "email": "ishukpong418@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Emem-Etukudo.py",
            "output": "Hello world, this is Emem Etukudo with HNGi7 ID HNG-02217  using python for stage 2 task",
            "name": "Emem Etukudo",
            "id": "HNG-02217",
            "email": "saspiee@gmail.com",
            "language": "python",
            "status": "pass"
            },
            {
            "file": "Emma-Omingo.js",
            "output": "Hello World, this is Emma Omingo with HNGi7 ID HNG-01412  using JavaScript for stage 2 task",
            "name": "Emma Omingo",
            "id": "HNG-01412",
            "email": "emma.omingo@riarauniversity.ac.ke",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Emmanuel-Abuga.php",
            "output": "Hello World, this is Emmanuel Abuga with HNGi7 ID HNG-03993   using PHP for stage 2 task",
            "name": "Emmanuel Abuga",
            "id": "HNG-03993",
            "email": "emma.abuga755@gmail.com",
            "language": "PHP",
            "status": "pass"
            },
            {
            "file": "Emmanuel-Aliyu.php",
            "output": "Hello World, this is Emmanuel Aliyu with HNGi7 ID HNG-02609  using php for stage 2 task.",
            "name": "Emmanuel Aliyu",
            "id": "HNG-02609",
            "email": "aliyue@ymail.com",
            "language": "php",
            "status": "pass"
            },
            {
            "file": "Emmanuel-Erasmus-Sulai.js",
            "output": "Hello World, this is Emmanuel Erasmus Sulai with HNGi7 ID HNG-01104  using Javascript for stage 2 task",
            "name": "Emmanuel Erasmus Sulai",
            "id": "HNG-01104",
            "email": "2016wealthtips@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Emmanuel-Ezenwigbo.js",
            "output": "Hello World, this is Emmanuel Ezenwigbo with HNGi7 ID HNG-00517  using JavaScript for stage 2 task",
            "name": "Emmanuel Ezenwigbo",
            "id": "HNG-00517",
            "email": "emmanuelezenwigbo@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Emmanuel-John.js",
            "output": "Hello world, this is Emmanuel John with HNGi7 ID HNG-03668  using JavaScript for stage 2 task.",
            "name": "Emmanuel John",
            "id": "HNG-03668",
            "email": "Emmanuelhashy@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Emmanuel-Nwachukwu.py",
            "output": "Hello World, this is Emmanuel Nwachukwu with INTERNSHIP ID: HNG-00964:  using Python for stage 2 task.",
            "name": "Emmanuel Nwachukwu",
            "id": "null",
            "email": "imanuelnwachukwu17@gmail.com",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "Emmanuel-Oluwatobi.py",
            "output": "Hello World, this is Oluwatobi Emmanuel with HNGi7 ID HNG-02167  using Python for stage 2 task.",
            "name": "Emmanuel Oluwatobi",
            "id": "HNG-02167",
            "email": "emmanueloluwatobi2000@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "Emmanuella-Abah.js",
            "output": "Hello World, this is Emmanuella Abah with HNGi7 ID HNG-01758  using JavaScript for stage 2 task.",
            "name": "Emmanuella Abah",
            "id": "HNG-01758",
            "email": "Titiemmanuella@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Emmanueltlom.js",
            "output": "Hello World, this is Emmanuel Nwabuodafi with HNGi7 ID HNG-01295  using JavaScript for stage 2 task",
            "name": "Emmanueltlom",
            "id": "HNG-01295",
            "email": "Nwabuodafiemmanuel@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Eyimofe-ogunbiyi.js",
            "output": "Hello World, this is Eyimofe Ogunbiyi with HNGi7 ID HNG-03694  using Javascript for stage 2 task",
            "name": "Eyimofe ogunbiyi",
            "id": "HNG-03694",
            "email": "ogunbiyioladapo33@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Ezeakudolu-Chibuzor.java",
            "output": "",
            "name": "Ezeakudolu Chibuzor",
            "id": "null",
            "email": "",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "Ezeukwu-Princewill.js",
            "output": "Hello World, this is Ezeukwu Princewill Chigozie with HNGi7 ID HNG-00646  using Javascript for stage 2 task",
            "name": "Ezeukwu Princewill",
            "id": "HNG-00646",
            "email": "princewillezeukwu@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Faidat-Akinwale.js",
            "output": "Hello world, this is Faidat Akinwale with HNGi7 ID HNG-04059  using JavaScript for Stage 2 task",
            "name": "Faidat Akinwale",
            "id": "HNG-04059",
            "email": "faidatakinwale@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Faith-Korir.php",
            "output": "Hello World, this is Faith Korir with HNGi7 ID HNG-00062   using PHP for stage 2 task",
            "name": "Faith Korir",
            "id": "HNG-00062",
            "email": "faithckorir@gmail.com",
            "language": "PHP",
            "status": "pass"
            },
            {
            "file": "Fasakin-Ayomide.js",
            "output": "Hello world, this is Fasakin Ayomide with HNGi7 ID HNG-01996  using javaScript for stage 2 task",
            "name": "Fasakin Ayomide",
            "id": "HNG-01996",
            "email": "ay.shmurda@gmail.com",
            "language": "javaScript",
            "status": "pass"
            },
            {
            "file": "Favour-Anefu.py",
            "output": "Hello World, this is Favour Anefu with HNGi7 ID HNG-01504  using Python for stage 2 task",
            "name": "Favour Anefu",
            "id": "HNG-01504",
            "email": "favouranefu@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "Folorunso-tolulope.js",
            "output": "Hello World, this is Folorunso Tolulope with HNGi7 ID HNG-00403  using NodeJs for stage 2 task",
            "name": "Folorunso tolulope",
            "id": "HNG-00403",
            "email": "tolufolorunso@yahoo.com",
            "language": "NodeJs",
            "status": "pass"
            },
            {
            "file": "Fongang-Rodrique.js",
            "output": "Hello World, this is Fongang Rodrique with HNGi7 ID HNG-03522  using Javascript for stage 2 task",
            "name": "Fongang Rodrique",
            "id": "HNG-03522",
            "email": "jarorodriq@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Francis-Adegbe.js",
            "output": "Hello World, this is Francis Adegbe with HNGi7 ID HNG-00130 using JavaScript for stage 2 task",
            "name": "Francis Adegbe",
            "id": "null",
            "email": "",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "Gabriel-Owusu.py",
            "output": "Hello World, this is Gabriel Owusu with HNGi7 ID HNG-00774 using Python for stage 2 task",
            "name": "Gabriel Owusu",
            "id": "null",
            "email": "",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "Gabriel_Raji.js",
            "output": "Hello World, this is Gabriel Raji with HNGi7 ID HNG-00100  using Javascript for stage 2 task",
            "name": "Gabriel_Raji",
            "id": "HNG-00100",
            "email": "rajigabrielebunoluwa@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Geoffery-Joseph.py",
            "output": "",
            "name": "Geoffery Joseph",
            "id": "null",
            "email": "",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "George-Uweh.js",
            "output": "Hello World, this is George Uweh with HNGi7 ID HNG-01125  using JavaScript for stage 2 task",
            "name": "George Uweh",
            "id": "HNG-01125",
            "email": "georgeamhuweh@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Helen-Jonathan.js",
            "output": "Hello World, this is Helen Jonathan with HNGi7 ID HNG-03824  using JavaScript for stage 2 task.",
            "name": "Helen Jonathan",
            "id": "HNG-03824",
            "email": "efebehelen95@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Hope-Okelue.py",
            "output": "Hello World, this is Hope okelue with HNGi7 ID HNG-03965  using Python for stage 2 task",
            "name": "Hope Okelue",
            "id": "HNG-03965",
            "email": "hope.okelue@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "Hussaini-Muhammad-Auwal.py",
            "output": "Hello World, this is Hussaini Muhammad Auwal with HNGi7 ID HNG-01895  using Python for stage 2 task",
            "name": "Hussaini Muhammad Auwal",
            "id": "HNG-01895",
            "email": "hauwal4969@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "Ibironke-Marvellous.js",
            "output": "Hello World, this is Ibironke Marvellous with HNGi7 ID HNG-03297   using JavaScript for stage 2 task",
            "name": "Ibironke Marvellous",
            "id": "HNG-03297",
            "email": "opemipo827@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Idahosa-josiah.py",
            "output": "Hello world, this is Idahosa Josiah with HNGi7 ID HNG-04694  using python for stage 2 task.",
            "name": "Idahosa josiah",
            "id": "HNG-04694",
            "email": "idahosajosiah5@gmail.com",
            "language": "python",
            "status": "pass"
            },
            {
            "file": "Idowu-Adeola.js",
            "output": "Hello World, this is Idowu Adeola with HNGi7 ID HNG-05002  using JavaScript for stage 2 task",
            "name": "Idowu Adeola",
            "id": "HNG-05002",
            "email": "adeolaisrael424@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Ifedayo-Adeniyi.py",
            "output": "Hello World, this is Ifedayo Adeniyi with HNGi7 ID HNG-02289  using python for stage 2 task",
            "name": "Ifedayo Adeniyi",
            "id": "HNG-02289",
            "email": "ifedayoadeniyi@gmail.com",
            "language": "python",
            "status": "pass"
            },
            {
            "file": "Ifediora-Adanma.js",
            "output": "",
            "name": "Ifediora Adanma",
            "id": "null",
            "email": "",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "Ifeoluwa-Akintayo.py",
            "output": "Hello World, this is Akintayo Ifeoluwa Janet with HNGi7 ID HNG-00295  using Python for stage 2 task.",
            "name": "Ifeoluwa Akintayo",
            "id": "HNG-00295",
            "email": "akintayoife94@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "Iheanacho-obinna.py",
            "output": "Hello World, this is Obinna Iheanacho with HNGi7 ID HNG-01124  using Python for stage 2 task",
            "name": "Iheanacho obinna",
            "id": "HNG-01124",
            "email": "Iheanachocharlie@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "Ihedioha-Chinedu.java",
            "output": "",
            "name": "Ihedioha Chinedu",
            "id": "null",
            "email": "",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "Ikechi-Okoro.js",
            "output": "Hello World, this is Okoro Ikechi with HNGi7 ID HNG-03711  using JavaScript for stage 2 task.",
            "name": "Ikechi Okoro",
            "id": "HNG-03711",
            "email": "ofoikechi@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Ikubanni-Paul.py",
            "output": "Hello World, this is Ikubanni Paul with HNGi7 ID HNG-03910  using Python for stage 2 task",
            "name": "Ikubanni Paul",
            "id": "HNG-03910",
            "email": "ipom4eva@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "Imonikhea-Ugbodaga.js",
            "output": "Hello World, this is Imonikhea Ugbodaga with HNGi7 ID HNG-06146  using javascript for stage 2 task",
            "name": "Imonikhea Ugbodaga",
            "id": "HNG-06146",
            "email": "imonikheaugbodaga@gmail.com",
            "language": "javascript",
            "status": "pass"
            },
            {
            "file": "Iniobong-Duff.js",
            "output": "Hello World, this is Iniobong Duff with HNGi7 ID HNG-01913  using JavaScript for stage 2 task",
            "name": "Iniobong Duff",
            "id": "HNG-01913",
            "email": "Duffdev001@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Ismael-Mariam-Saka.js",
            "output": "Hello World, this is Mariam Ismael Saka with HNGi7 ID HNG-01795  using JavaScript for stage 2 task",
            "name": "Ismael Mariam Saka",
            "id": "HNG-01795",
            "email": "mariamismael904@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Israel-Airenovboise.py",
            "output": "Hello World, this is Israel Airenovboise with HNGi7 ID HNG-05846  using python for stage 2 task",
            "name": "Israel Airenovboise",
            "id": "HNG-05846",
            "email": "airenov500@gmail.com",
            "language": "python",
            "status": "pass"
            },
            {
            "file": "Iyiola-Akanbi.js",
            "output": "Hello world, this is Iyiola Akanbi with HNGi7 ID HNG-00391  using Javascript for stage 2 task",
            "name": "Iyiola Akanbi",
            "id": "HNG-00391",
            "email": "iyiola.dev@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "James-Obongidiyake.js",
            "output": "Hello World, this is Obongidiyake Daniel with HNGi7 ID HNG-05689  using javascript for stage 2 task",
            "name": "James Obongidiyake",
            "id": "HNG-05689",
            "email": "obongidiyake@gmail.com",
            "language": "javascript",
            "status": "pass"
            },
            {
            "file": "James-Owusu-Appiah.py",
            "output": "Hello World, this is James Owusu Appiah with HNGi7 ID HNG-03682  using Python for stage 2 task",
            "name": "James Owusu Appiah",
            "id": "HNG-03682",
            "email": "jamesoappiah2003@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "James-OwusuAppiah.py",
            "output": "Hello World, this is James Owusu Appiah with HNGi7 ID HNG-03682 using Python for stage 2",
            "name": "James OwusuAppiah",
            "id": "null",
            "email": "task.jamesoappiah2003@gmail.com",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "Jamiu-Yusuf.js",
            "output": "Hello World, this is Jamiu Yusuf with HNGi7 ID HNG-02240  using Javascript for stage 2 task",
            "name": "Jamiu Yusuf",
            "id": "HNG-02240",
            "email": "boladeboss@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Jennifer-Onyeama.php",
            "output": "Hello World, this is Jennifer Onyeama with HNGi7 ID HNG-04437 using PHP for stage 2 task",
            "name": "Jennifer Onyeama",
            "id": "null",
            "email": "",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "Jeremiah-charles.js",
            "output": "Hello World, this is Jeremiah Charles with HNGi7 ID HNG-03830  using Javascript for stage 2 task",
            "name": "Jeremiah charles",
            "id": "HNG-03830",
            "email": "charlesjeremiah89@yahoo.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "John-Philip.js",
            "output": "Hello World, this is John Philip with HNGi7 ID HNG-01923  using Javascript for stage 2 task",
            "name": "John Philip",
            "id": "HNG-01923",
            "email": "developerphilo@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Joseph-obochi.js",
            "output": "Hello World, this is Joseph Obochi with HNGI7 ID HNG-05688  using javascript for stage 2 task",
            "name": "Joseph obochi",
            "id": "HNG-05688",
            "email": "obochi2@gmail.com",
            "language": "javascript",
            "status": "pass"
            },
            {
            "file": "Joshua-Paul.php",
            "output": "Hello world, this is Joshua Paul with HNGi7 ID HNG-05491  using PHP for stage 2 task",
            "name": "Joshua Paul",
            "id": "HNG-05491",
            "email": "veecthorpaul@gmail.com",
            "language": "PHP",
            "status": "pass"
            },
            {
            "file": "Jude-Biose.php",
            "output": "Hello World, this is Jude Biose with HNGi7 ID HNG-01207  using PHP for stage 2 task",
            "name": "Jude Biose",
            "id": "HNG-01207",
            "email": "Judebiose20@gmail.com",
            "language": "PHP",
            "status": "pass"
            },
            {
            "file": "Juliet-Adeboye.py",
            "output": "Hello World, this is Juliet Adeboye with HNGi7 ID HNG-00159  using Python for stage 2 task",
            "name": "Juliet Adeboye",
            "id": "HNG-00159",
            "email": "julietadeboye01@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "Karen-Efereyan.js",
            "output": "Hello World, this is Efereyan Karen Simisola with HNGi7 ID HNG-01050  using Javascript for stage 2 task",
            "name": "Karen Efereyan",
            "id": "HNG-01050",
            "email": "kimsyefe@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Kelvin-Ossai.js",
            "output": "Hello World, this is Kelvin Ossai with HNGi7 ID HNG-00849  using JavaScript for stage 2 task",
            "name": "Kelvin Ossai",
            "id": "HNG-00849",
            "email": "ifeanyiko4u@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Kenenna-Onwuagba.php",
            "output": "Hello World, this is Kenenna Onwuagba with HNGi7 ID HNG-02948  using PHP for stage 2 task",
            "name": "Kenenna Onwuagba",
            "id": "HNG-02948",
            "email": "onwuagbakenenna@gmail.com",
            "language": "PHP",
            "status": "pass"
            },
            {
            "file": "Kevin-Izuchukwu.js",
            "output": "Hello World, this is Kevin Izuchukwu with the HNGi7 ID HNG-04697 using Javascript for stage 2 task",
            "name": "Kevin Izuchukwu",
            "id": "null",
            "email": "",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "Kolajo-Tomike.js",
            "output": "Hello World, this is Kolajo Tomike with HNGi7 ID HNG-05840  using Javascript for Stage 2 task",
            "name": "Kolajo Tomike",
            "id": "HNG-05840",
            "email": "kolajoelizabeth@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Kosi-Anyaegbuna.dart",
            "output": "Hello World, this is Kosi Anyaegbuna with HNGi7 ID HNG-02471  using Dart for stage 2 task",
            "name": "Kosi Anyaegbuna",
            "id": "HNG-02471",
            "email": "kosilevan@yahoo.co.uk",
            "language": "Dart",
            "status": "pass"
            },
            {
            "file": "Lawal-Toheeb.js",
            "output": "Hello World, this is Lawal Toheeb Babatunde with HNGi7 ID HNG-01600  using javascript for stage 2 task",
            "name": "Lawal Toheeb",
            "id": "HNG-01600",
            "email": "lawaltoheeb231@gmail.com",
            "language": "javascript",
            "status": "pass"
            },
            {
            "file": "Layo-Ayorinde.py",
            "output": "Hello World, this is Ayorinde Layo with HNGi7 ID HNG-05529  using Python for stage 2 task",
            "name": "Layo Ayorinde",
            "id": "HNG-05529",
            "email": "ayorindelayot@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "Lemovou-Ivan.js",
            "output": "Hello World, this is Lemovou Dachi Ivan with HNGi7 ID HNG-04042  using Javascript for stage 2 task",
            "name": "Lemovou Ivan",
            "id": "HNG-04042",
            "email": "lemovou@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Lillian-Nwaoha.js",
            "output": "Hello World, this is Lillian Nwaoha with HNGi7 ID HNG-01291  using Javascript for stage 2 task",
            "name": "Lillian Nwaoha",
            "id": "HNG-01291",
            "email": "lillian.nwaoha@yahoo.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Mahi-Aliyu.py",
            "output": "Hello World, this is Mahi Aminu Aliyu with HNGi7 ID HNG-01114  using Python for stage 2 task",
            "name": "Mahi Aliyu",
            "id": "HNG-01114",
            "email": "mahigital@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "Malachy-Williams.py",
            "output": "Hello World, this is Malachy Williams with HNGi7 ID HNG-01302  using Python for stage 2 task.",
            "name": "Malachy Williams",
            "id": "HNG-01302",
            "email": "billmal071@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "Mannan-Bansal.py",
            "output": "Hello World, this is Mannan Bansal with HNGi7 ID HNG-00074  using python for stage 2 task",
            "name": "Mannan Bansal",
            "id": "HNG-00074",
            "email": "mannan_bansal@yahoo.com",
            "language": "python",
            "status": "pass"
            },
            {
            "file": "Margaret-Wambui.js",
            "output": "Hello World, this is Margaret Wambui with HNGi7 ID HNG-03494  using Javascript for stage 2 task",
            "name": "Margaret Wambui",
            "id": "HNG-03494",
            "email": "margarettom6@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Mariam-Ismael-Saka.js",
            "output": "Hello World, this is Mariam Ismael Saka with HNGi7 ID HNG-01795 using javascript for stage 2 task",
            "name": "Mariam Ismael Saka",
            "id": "null",
            "email": "",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "Mariam-Ismael.js",
            "output": "Hello World, this is Mariam Ismael Saka with HNGi7 ID HNG-01795 using javascript for stage 2 task",
            "name": "Mariam Ismael",
            "id": "null",
            "email": "",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "Merit-Dike.js",
            "output": "Hello World, this is Merit Dike with HNGi7 ID HNG-00392  using Javascript for stage 2 task",
            "name": "Merit Dike",
            "id": "HNG-00392",
            "email": "dike.merit@yahoo.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Micah-Elijah.py",
            "output": "Hello World, this is Micah Elijah with HNGi7 ID HNG-04316  using Python for stage 2 task",
            "name": "Micah Elijah",
            "id": "HNG-04316",
            "email": "melijah200@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "Michael-Ajanaku.py",
            "output": "Hello World, this is Michael Ajanaku with HNGi7 ID HNG-02854  using Python for stage 2 task.",
            "name": "Michael Ajanaku",
            "id": "HNG-02854",
            "email": "remiljw@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "Michael-Akor.py",
            "output": "Hello World. this is Akor Michael with HNGi7 IDHNG-00961and email  using  c# for stage 2 task",
            "name": "Michael Akor",
            "id": "null",
            "email": "michaelaim60@gmail.com",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "Mohammed-Kabir-Hussaini.php",
            "output": "Hello world, this is Mohammed Kabir Hussaini with HNGi7 ID HNG-00759  using php for stage 2 task",
            "name": "Mohammed Kabir Hussaini",
            "id": "HNG-00759",
            "email": "iamquintissential@gmail.com",
            "language": "php",
            "status": "pass"
            },
            {
            "file": "Moses-Benjamin.js",
            "output": "Hello World, this is Sunday Moses Benjamin with HNGi7 ID HNG-00680  using javascript for stage 2 task",
            "name": "Moses Benjamin",
            "id": "HNG-00680",
            "email": "sundaybenjamin08@gmail.com",
            "language": "javascript",
            "status": "pass"
            },
            {
            "file": "Muhammed-Adegbola.js",
            "output": "Hello World, this is Muhammed Adegbola with HNGi7 ID HNG-01834  using Javascript for stage 2 task",
            "name": "Muhammed Adegbola",
            "id": "HNG-01834",
            "email": "muhammedopeyemi5@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Munachimso-ike.py",
            "output": "Hello World, this is Munachimso Ike with HNGi7 ID HNG-00853  using Python for stage 2 task.",
            "name": "Munachimso ike",
            "id": "HNG-00853",
            "email": "ikemunachii@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "Murphy-Ogbeide.js",
            "output": "Hello World, this is Murphy Ogbeide with HNGi7 ID HNG-01770  using JavaScript for stage 2 task",
            "name": "Murphy Ogbeide",
            "id": "HNG-01770",
            "email": "ogbeidemurphy@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Mustapha-Mubarak.py",
            "output": "Hello World, this is Mubarak Mustapha with HNGi7 ID HNG-03644  using python for stage 2 task",
            "name": "Mustapha Mubarak",
            "id": "HNG-03644",
            "email": "pythagoras.dev15@gmail.com",
            "language": "python",
            "status": "pass"
            },
            {
            "file": "Mustapha-Raji.js",
            "output": "Hello world, this is Raji Mustapha with HNGi7 ID HNG-05012  using javaScript for stage 2 task",
            "name": "Mustapha Raji",
            "id": "HNG-05012",
            "email": "rajimustapha30@gmail.com",
            "language": "javaScript",
            "status": "pass"
            },
            {
            "file": "Mutiat-Akinwale.js",
            "output": "Hello world, this is Mutiat Akinwale with HNGi7 ID HNG-01406  using Javascript for Stage 2 task",
            "name": "Mutiat Akinwale",
            "id": "HNG-01406",
            "email": "mutiatakinwale@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "NK-Ugwu.py",
            "output": "Hello World, this is Nk Ugwu with HNGi7 ID HNG-02568  using python for stage 2 task",
            "name": "NK Ugwu",
            "id": "HNG-02568",
            "email": "egougwu11@yahoo.com",
            "language": "python",
            "status": "pass"
            },
            {
            "file": "Najeeb-Sulaiman.js",
            "output": "Hello World, this is Najeeb Sulaiman with HNGi7 ID HNG-05740  using Javascript for stage 2 task",
            "name": "Najeeb Sulaiman",
            "id": "HNG-05740",
            "email": "beejan003@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Nelson-Chinedu.php",
            "output": "Hello World, this is Nelson Chinedu with HNGi7 ID HNG-03542  using PHP for stage 2 task",
            "name": "Nelson Chinedu",
            "id": "null",
            "email": "nelsonnedum@gmail.com",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "Nsikak-Akpan.java",
            "output": "",
            "name": "Nsikak Akpan",
            "id": "null",
            "email": "",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "Nwankwo-Henry.js",
            "output": "Hello World, this is Nwankwo Henry with HNGi7 ID HNG-01972  using JavaScript for stage 2 task.",
            "name": "Nwankwo Henry",
            "id": "HNG-01972",
            "email": "nwankwohenry9@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Nwanozie-Promise.php",
            "output": "Hello world, this is Promise Nwanozie with HNGi7 ID HNG-03590  using PHP for stage 2 task",
            "name": "Nwanozie Promise",
            "id": "HNG-03590",
            "email": "nwanoziep@gmail.com",
            "language": "PHP",
            "status": "pass"
            },
            {
            "file": "Obafunmilayo-Lijadu.py",
            "output": "Hello World, this is, Obafunmilayo Samuel Lijadu, with HNGi7 ID, HNG-02821, and email,  , using, python, for stage 2 task",
            "name": "Obafunmilayo Lijadu",
            "id": "null",
            "email": "lijsamobafunmilayo@gmail.com",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "Obongidiyake-James.js",
            "output": "",
            "name": "Obongidiyake James",
            "id": "null",
            "email": "",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "Ode-Folashade.js",
            "output": "Hello World, this is Ode Folashade with HNGi7 ID HNG-02713  using Javascript for stage 2 task",
            "name": "Ode Folashade",
            "id": "HNG-02713",
            "email": "afolaode@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Odemakin-victoria.py",
            "output": "Hello World, this is Odemakin victoria with HNGi7 ID HNG-06582  using python for stage 2 task",
            "name": "Odemakin victoria",
            "id": "HNG-06582",
            "email": "ifeoluwaodemakin@gmail.com",
            "language": "python",
            "status": "pass"
            },
            {
            "file": "Ogheneovie-Oki-Peter.js",
            "output": "Hello World, this is Ogheneovie Oki-Peter with HNGi7 ID HNG-03714  using JavaScript for stage 2 task",
            "name": "Ogheneovie Oki Peter",
            "id": "HNG-03714",
            "email": "okipeterovie@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Ogwo-Chinaza.js",
            "output": "Hello World, this is Ogwo Chinaza with HNGi7 ID HNG-02067  using JavaScript for stage 2 task",
            "name": "Ogwo Chinaza",
            "id": "HNG-02067",
            "email": "meabout9@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Oke-olalekan.js",
            "output": "Hello World, this is Oke Olalekan with HNGi7 ID HNG-01322  using Javascript for stage 2 task",
            "name": "Oke olalekan",
            "id": "HNG-01322",
            "email": "speakingatoms@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Okeke-Victor.js",
            "output": "Hello World, this is Victor okeke with HNGi7 ID HNG-02141  using Javascript for stage 2 task",
            "name": "Okeke Victor",
            "id": "HNG-02141",
            "email": "vicspidin@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Okonwanji-Okechukwu.js",
            "output": "Hello World, this is Okechukwu Okonwanji with HNGi7 ID HNG-02631  using JavaScript for stage 2 task",
            "name": "Okonwanji Okechukwu",
            "id": "HNG-02631",
            "email": "okechukwu.okonwanji@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Okwuobi-Ifeanyi.js",
            "output": "Hello World, this is Ifeanyi Fredrick Okwuobi with HNGi7 ID: HNG-03608  and i am using JavaScript for stage 2 task",
            "name": "Okwuobi Ifeanyi",
            "id": "null",
            "email": "fredrickokwuobi@gmail.com",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "Olabisi-Olawale.js",
            "output": "Hello World, this is Olabisi Olawale with HNGi7 ID HNG-00458  using JavaScript for stage 2 task",
            "name": "Olabisi Olawale",
            "id": "HNG-00458",
            "email": "ejiolawale4@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Oladayo-Babalola.js",
            "output": "Hello World, this is Oladayo Babalola with HNGi7 ID HNG-00626  using JavaScript for stage 2 task",
            "name": "Oladayo Babalola",
            "id": "HNG-00626",
            "email": "oladayoBB@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Oladokun-Joshua.js",
            "output": "Hello World, this is Oladokun Joshua with HNGi7 ID HNG-03210  using Javascript for stage 2 task",
            "name": "Oladokun Joshua",
            "id": "HNG-03210",
            "email": "oladokunjoshua2016@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Olamide-Aboyeji.js",
            "output": "Hello World, This is Olamide Aboyeji with HNGi7 ID HNG-05560  using Javascript for stage 2 task",
            "name": "Olamide Aboyeji",
            "id": "HNG-05560",
            "email": "aboyejiolamide15@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Olanrewaju.php",
            "output": "",
            "name": "Olanrewaju",
            "id": "null",
            "email": "",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "Olatubosun-John.php",
            "output": "Hello World, this is John Olatubosun with HNGi7 ID HNG-01444  using PHP for stage 2 task",
            "name": "Olatubosun John",
            "id": "HNG-01444",
            "email": "toluolatubosun@gmail.com",
            "language": "PHP",
            "status": "pass"
            },
            {
            "file": "Olumide-Nwosu.dart",
            "output": "Hello World, this is Olumide Nwosu with HNGi7 ID HNG-00396  using Dart for stage 2 task.",
            "name": "Olumide Nwosu",
            "id": "HNG-00396",
            "email": "niolumi4eva@gmail.com",
            "language": "Dart",
            "status": "pass"
            },
            {
            "file": "Olushola-Ajayi.js",
            "output": "Hello World, this is Olushola Ajayi with HNGi7 ID HNG-03064  using JavaScript for stage 2 task",
            "name": "Olushola Ajayi",
            "id": "HNG-03064",
            "email": "speak2emmans@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Olutobi-Ogunsola.js",
            "output": "Hello World, this is Olutobi Ogunsola with HNGi7 ID HNG-02236  using javaScript for stage 2 task",
            "name": "Olutobi Ogunsola",
            "id": "HNG-02236",
            "email": "Olutobiogunsola@gmail.com",
            "language": "javaScript",
            "status": "pass"
            },
            {
            "file": "Oluwadamilola-Adediran.js",
            "output": "Hello World, this is Oluwadamilola Adediran with HNGi7 ID HNG-02794  using Javascript for stage 2 task",
            "name": "Oluwadamilola Adediran",
            "id": "HNG-02794",
            "email": "dammyadediran94@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Oluwafemi-Omotoso.py",
            "output": "Hello World, this is, Oluwafemi Omotoso, with HNGi7 ID, HNG-04959, and email,  , using, python, for stage 2",
            "name": "Oluwafemi Omotoso",
            "id": "null",
            "email": "femio82@gmail.com",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "Oluwaferanmi-Michael.py",
            "output": "Hello World, this is Oluwaferanmi Michael with HNGi7 ID HNG-02909  using Python for stage 2 task",
            "name": "Oluwaferanmi Michael",
            "id": "HNG-02909",
            "email": "aechealgr8@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "Oluwasegun-Aiyedona.js",
            "output": "Hello world, this is Oluwasegun Aiyedona with HNGi7 ID HNG-02498  using JavaScript for Stage 2 task",
            "name": "Oluwasegun Aiyedona",
            "id": "HNG-02498",
            "email": "segunaiyedona@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Oluwaseun-Adeniran.py",
            "output": "Hello world , this is Adeniran Oluwaseun with HNGi7 ID HNG- 03483   using python for stage 2 task",
            "name": "Oluwaseun Adeniran",
            "id": "null",
            "email": "adeniranoluwaseun0608@gmail.com",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "Oluwatimileyin-Adeyemi.js",
            "output": "Hello World, this is Adeyemi Oluwatimileyin with HNGi7 ID HNG-02238  using JavaScript for stage 2 task",
            "name": "Oluwatimileyin Adeyemi",
            "id": "HNG-02238",
            "email": "oluwatimilehin14@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Oluwatobi-Adeyokunnu.py",
            "output": "Hello World, this is Oluwatobi Adeyokunnu with HNGi7 ID HNG-02004  using python for stage 2 task",
            "name": "Oluwatobi Adeyokunnu",
            "id": "HNG-02004",
            "email": "adeyokunnuo@gmail.com",
            "language": "python",
            "status": "pass"
            },
            {
            "file": "Oluwatobiloba-Adelakun.js",
            "output": "Hello World, this is Adelakun Oluwatobiloba with HNGi7 ID HNG-04753  using JavaScript for stage 2 task",
            "name": "Oluwatobiloba Adelakun",
            "id": "HNG-04753",
            "email": "adelakuntobiloba1@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Oluwole-Dada.js",
            "output": "Hello World, this is Oluwole Dada with HNGi7 ID HNG-03623  using JavaScript for stage 2 task",
            "name": "Oluwole Dada",
            "id": "HNG-03623",
            "email": "dadaoluwafemitaiwo@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Oluyori-Oluwagbemiga.js",
            "output": "Hello World, this is Oluyori Oluwagbemiga with HNGi7 ID HNG-03797  using javascript for stage 2 task.",
            "name": "Oluyori Oluwagbemiga",
            "id": "HNG-03797",
            "email": "bencharlogh@gmail.com",
            "language": "javascript",
            "status": "pass"
            },
            {
            "file": "Omonigho-Oddiri.js",
            "output": "Hello World, this is Omonigho Ovie Oddiri with HNGi7 ID HNG-00690  using Javascript for stage 2 task",
            "name": "Omonigho Oddiri",
            "id": "HNG-00690",
            "email": "ovie52009@yahoo.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Onah-Tochukwu.js",
            "output": "Hello World, this is Onah Tochukwu with HNGi7 ID HNG-02371  using javascript for stage 2 task",
            "name": "Onah Tochukwu",
            "id": "HNG-02371",
            "email": "pearlpey1@gmail.com",
            "language": "javascript",
            "status": "pass"
            },
            {
            "file": "Onyinye-Ifemkpa.py",
            "output": "Hello World, this is Onyinye Ifemkpa with HNGi7 ID HNG-02659  using Python for stage 2 task",
            "name": "Onyinye Ifemkpa",
            "id": "HNG-02659",
            "email": "onyinyeifemkpa@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "Opeyemi-Adeyemi.js",
            "output": "Hello World, this is Adeyemi Opeyemi David with HNGi7 ID HNG-03135  using Javascript for stage 2 task",
            "name": "Opeyemi Adeyemi",
            "id": "HNG-03135",
            "email": "dyvvoo@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Opeyemi-peter.js",
            "output": "Hello World, this is Opeyemi Peter with HNGi7 ID HNG-01612  using Javascript for Stage 2 task",
            "name": "Opeyemi peter",
            "id": "HNG-01612",
            "email": "opeyemiadebowale1759@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Orji-Cecilia.js",
            "output": "Hello World, this is Orji Cecilia with HNGi7 ID HNG-02009  using JavaScript for stage 2 task.",
            "name": "Orji Cecilia",
            "id": "HNG-02009",
            "email": "ceciliaorji.co@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Osabiya-Eniola.php",
            "output": "Hello World, this is Eniola Osabiya with HNGi7 ID HNG-00143  using PHP for stage 2 task",
            "name": "Osabiya Eniola",
            "id": "HNG-00143",
            "email": "eosabiya@gmail.com",
            "language": "PHP",
            "status": "pass"
            },
            {
            "file": "Oscar-Ekeyekwu.js",
            "output": "Hello World, this is Oscar Ekeyekwu with HNGi7 ID HNG-05334  using JavaScript for Stage 2 task",
            "name": "Oscar Ekeyekwu",
            "id": "HNG-05334",
            "email": "oscarekeyekwu@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Osemudiamen-Eronlan.js",
            "output": "Hello World, this is Osemudiamen Eronlan with HNGi7 ID HNG-03059  using JavaScript for stage 2 task",
            "name": "Osemudiamen Eronlan",
            "id": "HNG-03059",
            "email": "oseeronlan@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Osiegbu Precious.py",
            "output": "",
            "name": "Osiegbu Precious",
            "id": "null",
            "email": "",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "Oyetayo-Micheal.js",
            "output": "Hello World, this is Oyetayo Micheal with HNGi7 ID HNG-03767  using Javascript for stage 2 task",
            "name": "Oyetayo Micheal",
            "id": "HNG-03767",
            "email": "Oyetayomicheal94@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Ozioiza-Audu.py",
            "output": "Hello World, this is Audu Ozioiza Queen with HNGi7 ID HNG-05210  using Python for stage 2 task",
            "name": "Ozioiza Audu",
            "id": "HNG-05210",
            "email": "auduoziq21@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "Paul-Arah.js",
            "output": "Hello World, this is Paul Arah with HNGi7 ID  HNG-02611 using JavaScript for stage 2 task",
            "name": "Paul Arah",
            "id": "null",
            "email": "p.arah@alustudent.com",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "Peace-adeoye.js",
            "output": "Hello World, this is Peace Adeoye with HNGi7 ID HNG-06335  using Javascript for stage 2 task",
            "name": "Peace adeoye",
            "id": "HNG-06335",
            "email": "adebolapeace0@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Pesova-Osueke.php",
            "output": "Hello World, this is Pesova Osueke with HNGi7 ID HNG-02105  using php for stage 2 task.",
            "name": "Pesova Osueke",
            "id": "HNG-02105",
            "email": "Pesova13@gmail.com",
            "language": "php",
            "status": "pass"
            },
            {
            "file": "Philip-Oghenetega.js",
            "output": "Hello World, this is Philip Daniel Oghenetega with HNGi7 ID HNG-02634  using JavaScript for stage 2 task.",
            "name": "Philip Oghenetega",
            "id": "HNG-02634",
            "email": "oghenetegaphilip@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Philip-akpan.js",
            "output": "Hello World, this is Philip Akpan with HNGi7 ID HNG-02972  using JavaScript for stage 2 task",
            "name": "Philip akpan",
            "id": "HNG-02972",
            "email": "akpanphilip1122@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Ponmile-Lawal.js",
            "output": "Hello World, this is ${name} with ID ${hng_id} ${email} using ${language} for stage 2 task",
            "name": "Ponmile Lawal",
            "id": "null",
            "email": "",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "Ponmile.js",
            "output": "Hello World, this is ${name} with ID ${hng_id} ${email} using ${language} for stage 2 task",
            "name": "Ponmile",
            "id": "null",
            "email": "",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "Pranati.py",
            "output": "Hello World, this is Pranati Shete with HNGi7 ID HNG-01345  using python for stage 2 task",
            "name": "Pranati",
            "id": "HNG-01345",
            "email": "pranatishete23@gmail.com",
            "language": "python",
            "status": "pass"
            },
            {
            "file": "Pranay-Yengandula.py",
            "output": "Hello World, this is Pranay Yenagandula with HNGi7 ID HNG-01542  using Python for stage 2 task",
            "name": "Pranay Yengandula",
            "id": "HNG-01542",
            "email": "pranay41@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "Precious-Chilaka.js",
            "output": "Hello World, this is Precious Chilaka with HNGi7 ID HNG-07770  using JavaScript for stage 2 task.",
            "name": "Precious Chilaka",
            "id": "HNG-07770",
            "email": "preshchilaka06@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Precious-Ndigwe.js",
            "output": "Hello World, this is Ndigwe Precious with HNGi7 ID HNG-02193  using JavaScript for stage 2 task",
            "name": "Precious Ndigwe",
            "id": "HNG-02193",
            "email": "pndigwe23@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Priscilla-Baah.py",
            "output": "Hello World, this is Priscilla Baah with HNGi7 ID HNG-04254  using Python for stage 2 task",
            "name": "Priscilla Baah",
            "id": "HNG-04254",
            "email": "pyfbaah@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "Progress-Eze.js",
            "output": "Hello World, this is Progress Eze with HNGi7 ID HNG-00291  using javascript for stage 2 task",
            "name": "Progress Eze",
            "id": "HNG-00291",
            "email": "progresseze@gmail.com",
            "language": "javascript",
            "status": "pass"
            },
            {
            "file": "Prosper-Ekwerike.js",
            "output": "Hello World, this is Prosper Ekwerike with HNGi7 ID HNG-05129  using Javascript for stage 2 task",
            "name": "Prosper Ekwerike",
            "id": "HNG-05129",
            "email": "pekwerike@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Rahmon-Azeez.py",
            "output": "Hello World, this is Rahmon Azeez with HNGi7 ID HNG-06010  using Python for stage 2 task",
            "name": "Rahmon Azeez",
            "id": "HNG-06010",
            "email": "rahmonazeez7@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "Razeb-Enyi.py",
            "output": "Hello World, this is Razeb Enyi with HNGi7 ID HNG-00043  using Python for stage 2 task",
            "name": "Razeb Enyi",
            "id": "HNG-00043",
            "email": "enyirazeb@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "Ridwan_Gbadamosi.php",
            "output": "Hello World, this is Ridwan Gbadamosi with HNGi7 ID HNG-04960  using PHP for stage 2 task.",
            "name": "Ridwan_Gbadamosi",
            "id": "HNG-04960",
            "email": "ridwangbadamosi@gmail.com",
            "language": "PHP",
            "status": "pass"
            },
            {
            "file": "Riliwan-Hassan.js",
            "output": "Hello World, this is Riliwan Hassan with HNGi7 ID HNG-02119  using JavaScript for stage 2 task",
            "name": "Riliwan Hassan",
            "id": "HNG-02119",
            "email": "riliwanhazzan@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Samuel-Ajayi.js",
            "output": "hello world this is Samuel Ajayi Toluwa with HNG-00302 and  using javascript for stage 2 task",
            "name": "Samuel Ajayi",
            "id": "null",
            "email": "troysammie7@gmail.com",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "Samuel-Bamgbose.py",
            "output": "Hello World, this is Samuel Bamgbose with HNGi7 ID HNG-00339  using Python for stage 2 task",
            "name": "Samuel Bamgbose",
            "id": "HNG-00339",
            "email": "bsaintdesigns@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "Sandy-Goodnews.js",
            "output": "Hello World, this is Goodnews Sandy with HNGi7 ID HNG-00370  using JavaScript for stage 2 task.",
            "name": "Sandy Goodnews",
            "id": "HNG-00370",
            "email": "goodnewssandy@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Sanni-Lukman.js",
            "output": "Hello World, this is Lukman Sanni with HNGi7 ID HNG-04230  using Javascript for stage 2 task",
            "name": "Sanni Lukman",
            "id": "HNG-04230",
            "email": "lukmansanni60@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Shuaibu-Fauzeeya.js",
            "output": "Hello World, this is Shuaibu Fauzeeya with HNGi7 ID HNG-03152  using javascript for stage 2 task",
            "name": "Shuaibu Fauzeeya",
            "id": "HNG-03152",
            "email": "Fauzeeya.nene@gmail.com",
            "language": "javascript",
            "status": "pass"
            },
            {
            "file": "Sifon-Isaac.js",
            "output": "Hello World, this is Sifon Isaac with HNGi7 ID HNG-06479  using JavaScript for stage 2 task",
            "name": "Sifon Isaac",
            "id": "HNG-06479",
            "email": "syfonisaac@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Simeon-Udoh.js",
            "output": "Hello World, This is Simeon Udoh with HNGi7 ID HNG-01827  using Javascript for stage 2 task.",
            "name": "Simeon Udoh",
            "id": "HNG-01827",
            "email": "simeon.udoh45@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Stephen-Chukwuma.js",
            "output": "Hello World, this is Stephen Chukwuma with HNGi7 ID HNG-05329  using Javascript for stage 2 task.",
            "name": "Stephen Chukwuma",
            "id": "HNG-05329",
            "email": "scariesmarch@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Stephen-Emmanuel.js",
            "output": "Hello World, this is Stephen Emmanuel with HNGi7 ID HNG-06244  using Javascript for stage 2 task",
            "name": "Stephen Emmanuel",
            "id": "HNG-06244",
            "email": "emmanuelstephen024@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Steven_Kolawole.py",
            "output": "Hello World, this is Steven Kolawole with HNGi7 ID HNG-01749  using python for stage 2 task.",
            "name": "Steven_Kolawole",
            "id": "HNG-01749",
            "email": "kolawolesteven99@gmail.com",
            "language": "python",
            "status": "pass"
            },
            {
            "file": "Sunday-Comfort.js",
            "output": "Hello World, this is Sunday Comfort with HNGi7 ID HNG-06524  using JavaScript for stage 2 task",
            "name": "Sunday Comfort",
            "id": "HNG-06524",
            "email": "comfortjumbo5@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Sunday-Gabriel.js",
            "output": "Hello World, this is Sunday Gabriel with HNGi7 ID HNG-03808  using JavaScript for stage 2 task.",
            "name": "Sunday Gabriel",
            "id": "HNG-03808",
            "email": "iriemena@yahoo.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Sunday-Okoromi.js",
            "output": "Hello World, this is Sunday Victor Okoromi with HNGi7 ID HNG-03987  using Javascript for stage 2 task",
            "name": "Sunday Okoromi",
            "id": "HNG-03987",
            "email": "okoromivictorsunday@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Sunday_Morenikeji.js",
            "output": "Hello World, this is Sunday Morenikeji with HNGi7 ID HNG-01787  using JavaScript for stage 2 task",
            "name": "Sunday_Morenikeji",
            "id": "HNG-01787",
            "email": "morenikejicodexiphaar@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Temiloluwa-Adelowo.php",
            "output": "Hello World, this is Temiloluwa Adelowo with HNGi7 ID HNG-02772  using PHP for stage 2 task",
            "name": "Temiloluwa Adelowo",
            "id": "HNG-02772",
            "email": "moboluwaji003@gmail.com",
            "language": "PHP",
            "status": "pass"
            },
            {
            "file": "Temitope-Japheth.php",
            "output": "Hello world, this is Temitope Japheth with HNGi7 ID HNG-04757  using PHP for stage 2 task",
            "name": "Temitope Japheth",
            "id": "HNG-04757",
            "email": "japhethtemitope@gmail.com",
            "language": "PHP",
            "status": "pass"
            },
            {
            "file": "Temitoyin-Ayorinde.js",
            "output": "Hello World, this is Temitoyin Ayorinde with HNGi7 ID HNG-02612  using JavaScript for stage 2 task",
            "name": "Temitoyin Ayorinde",
            "id": "HNG-02612",
            "email": "tjayorinde@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Terna-Nev.js",
            "output": "Hello World, this is Nev Terna with HNGi7 ID HNG-00141  using javascript for stage 2 task",
            "name": "Terna Nev",
            "id": "HNG-00141",
            "email": "ternanev@gmail.com",
            "language": "javascript",
            "status": "pass"
            },
            {
            "file": "Timfon-Ekott.js",
            "output": "Hello World, this is Timfon Ekott with HNGi7 ID HNG-04227  using JavaScript for stage 2 task.",
            "name": "Timfon Ekott",
            "id": "HNG-04227",
            "email": "edmund.timfon@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Timilehin-Odulate.js",
            "output": "Hello World, this is Timilehin Odulate with HNGi7 ID HNG-00395  using JavaScript for stage 2 task.",
            "name": "Timilehin Odulate",
            "id": "HNG-00395",
            "email": "timiodulate@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Tobi-Osimosu.js",
            "output": "Hello World, this is Osimosu Oluwatobiloba James with HNGi7 ID HNG-01133  using JavaScript for stage 2 task",
            "name": "Tobi Osimosu",
            "id": "HNG-01133",
            "email": "osimosutobi@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Tobi-Sangosanya.js",
            "output": "Hello World, this is Sangosanya Tobi with HNGi7 ID HNG-00860  using Javascript for stage 2 task",
            "name": "Tobi Sangosanya",
            "id": "HNG-00860",
            "email": "tobbysangosanya@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Tochukwu-Onwunali.js",
            "output": "Hello World, this is Onwunali Tochukwu with HNGi7 ID HNG-01014  using Javascript for stage 2 task",
            "name": "Tochukwu Onwunali",
            "id": "HNG-01014",
            "email": "onwunalitochukwu63@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Tolulope-Kehinde.js",
            "output": "Hello World, This is Tolulope Kehinde with HNGi7 ID HNG-02223  using JavaScript for stage 2 task.",
            "name": "Tolulope Kehinde",
            "id": "HNG-02223",
            "email": "tolulopeolajumoke97@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Toluwalase-Okuwoga.js",
            "output": "Hello World, this is Toluwalase Okuwoga with HNGi7 ID HNG-01109  using JavaScript for stage 2 task",
            "name": "Toluwalase Okuwoga",
            "id": "HNG-01109",
            "email": "toluwalaseokuwoga@yahoo.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Uchechukwu-Nwafor.js",
            "output": "Hello World, this is Uchechukwu Nwafor with HNGi7 ID HNG-03041  using Javascript for stage 2 task",
            "name": "Uchechukwu Nwafor",
            "id": "HNG-03041",
            "email": "nwaforuchechukwu2007@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Udochi-Dikamsi.py",
            "output": "Hello world, this is Udochi Dikamsi with HNGi7 ID HNG-04832  using Python for stage 2 task",
            "name": "Udochi Dikamsi",
            "id": "HNG-04832",
            "email": "youngudochi15@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "Ufoegbulam-Chukwuemeka-Kingsley.php",
            "output": "Hello World, this is Ufoegbulam Chukwuemeka Kingsley with HNGi7 ID HNG-05670and email  using PHP for stage 2 task.",
            "name": "Ufoegbulam Chukwuemeka Kingsley",
            "id": "null",
            "email": "kingsleyemeka31@gmail.com",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "Victor-shigaba.js",
            "output": "Hello World, this is Victor shigaba with HNGi7 ID HNG-02863  using Javasript for stage 2 task",
            "name": "Victor shigaba",
            "id": "HNG-02863",
            "email": "victorshigaba300@gmail.com",
            "language": "Javasript",
            "status": "pass"
            },
            {
            "file": "Victoria-Salami.js",
            "output": "Hello World, this is Victoria Salami with HNGi7 ID HNG-03877  using Javascript for stage 2 task.",
            "name": "Victoria Salami",
            "id": "HNG-03877",
            "email": "victoriasalami18@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Vikas-Rathore.py",
            "output": "Hello World, this is Vikas Rathore with HNGi7 ID HNG-00296  using Python for stage 2 task",
            "name": "Vikas Rathore",
            "id": "HNG-00296",
            "email": "vikasrathour162@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "Yusuf-Akinpeju.js",
            "output": "Hello World, this is Akinpeju Yusuf with HNGi7 ID HNG-03409  using JavaScript for stage 2 task",
            "name": "Yusuf Akinpeju",
            "id": "HNG-03409",
            "email": "thekodezilla@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Yusuf-Bakare",
            "output": "",
            "name": "Yusuf Bakare",
            "id": "null",
            "email": "",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "Yusuf-Taiwo.js",
            "output": "Hello World, this is Yusuf Taiwo with HNGi7 ID HNG-04777  using JavaScript for stage 2 task",
            "name": "Yusuf Taiwo",
            "id": "HNG-04777",
            "email": "teehazzan@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "Zakari-Umar.js",
            "output": "Hello world, this is Umar Muhammad Zakari with HNGi7 ID HNG-01897  using Javascript for stage 2 task",
            "name": "Zakari Umar",
            "id": "HNG-01897",
            "email": "umarfarouqft@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "Zephaniah Joshua HNG-06681.js",
            "output": "",
            "name": "Zephaniah Joshua HNG 06681",
            "id": "null",
            "email": "",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "abdulkarim-sarumi.py",
            "output": "Hello World, this is Abdulkarim Sarumi with HNGi7 ID HNG-03529  using Python for stage 2 task",
            "name": "abdulkarim sarumi",
            "id": "HNG-03529",
            "email": "sarumi329@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "abdullah-momoh.php",
            "output": "Hello World, this is Abdullah Momoh with HNGi7 ID HNG-00030  using PHP for stage 2 task",
            "name": "abdullah momoh",
            "id": "HNG-00030",
            "email": "momohabdullah20@gmail.com",
            "language": "PHP",
            "status": "pass"
            },
            {
            "file": "abdullahi.aliyu.js",
            "output": "console.log&quot;Hello World, this is &quot; + me.firstName + &quot; &quot; + me.lastName +  &quot; with HNGi7 ID &quot; + me.HNGID + &quot; &quot; + me.email + &quot; using &quot; + me.language + &quot; for stage 2 task&quot;",
            "name": "abdullahi",
            "id": "null",
            "email": "",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "abdulrafik.py",
            "output": "",
            "name": "abdulrafik",
            "id": "null",
            "email": "",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "abisola-morohunfolu.js",
            "output": "Hello World, this is Abisola Morohunfolu with HNGi7 ID HNG-01645  using javascript for stage 2 task",
            "name": "abisola morohunfolu",
            "id": "HNG-01645",
            "email": "amorohunfolu@gmail.com",
            "language": "javascript",
            "status": "pass"
            },
            {
            "file": "abolade-kasope.js",
            "output": "Hello World, this is Abolade Kasope with HNGi7 ID HNG-06623  using Javascript for stage 2 task",
            "name": "abolade kasope",
            "id": "HNG-06623",
            "email": "aboladekasope@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "adeleye-ladejobi.php",
            "output": "Hello World, this is Adeleye Ladejobi with HNGi7 ID HNG-02958  using php for stage 2 task",
            "name": "adeleye ladejobi",
            "id": "HNG-02958",
            "email": "emmy007.el@gmail.com",
            "language": "php",
            "status": "pass"
            },
            {
            "file": "adeoluwa-adetoyese.py",
            "output": "Hello World, this is Adeoluwa Adetoyese with HNGi7 ID HNG-04248  using Python for stage 2 task",
            "name": "adeoluwa adetoyese",
            "id": "HNG-04248",
            "email": "adetoyeseadeoluwa@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "adesoji-adejumo.js",
            "output": "Hello World, this is Adesoji Adejumo with HNGi7 ID HNG-01599  using JavaScript for stage 2 task",
            "name": "adesoji adejumo",
            "id": "HNG-01599",
            "email": "adesojiadejumo@yahoo.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "adewale-obidairo.js",
            "output": "Hello World, this is Adewale Samson Obidairo with HNGi7 ID HNG-04791  using javascript for stage 2 task",
            "name": "adewale obidairo",
            "id": "HNG-04791",
            "email": "mascot4sure@gmail.com",
            "language": "javascript",
            "status": "pass"
            },
            {
            "file": "adeyosola-mustapha.js",
            "output": "Hello World, this is Adeyosola Mustapha with HNGi7 ID HNG-05255  using JavaScript for stage 2 task",
            "name": "adeyosola mustapha",
            "id": "HNG-05255",
            "email": "adeyossy1@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "agbo-monica.php",
            "output": "Hello World, this is Agbo Monica Onyemowo with HNGi7 ID HNG-03531  using PHP for stage 2 task",
            "name": "agbo monica",
            "id": "HNG-03531",
            "email": "agbomonica.am@gmail.com",
            "language": "PHP",
            "status": "pass"
            },
            {
            "file": "ahmed-khaled.js",
            "output": "Hello World, this is Ahmed Khaled with HNGi7 ID HNG-02924  using javascript for stage 2 task",
            "name": "ahmed khaled",
            "id": "HNG-02924",
            "email": "mywork304@gmail.com",
            "language": "javascript",
            "status": "pass"
            },
            {
            "file": "ahunanya-uche.js",
            "output": "Hello World, this is Uche Ahunanya Blair with HNGi7 ID HNG-04756  using JavaScript for stage 2 task",
            "name": "ahunanya uche",
            "id": "HNG-04756",
            "email": "thatboywayne95@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "aikins-laryea.js",
            "output": "Hello World, this is Aikins Laryea with HNGi7 ID HNG-05219  using Javascript for stage 2 task",
            "name": "aikins laryea",
            "id": "HNG-05219",
            "email": "aikinslaryea@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "akerele-babatunde.js",
            "output": "Hello World, this is Akerele Babatunde with HNGi7 ID HNG-04839  using Javascript for stage 2 task",
            "name": "akerele babatunde",
            "id": "HNG-04839",
            "email": "babsake@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "akinde-david.js",
            "output": "Hello World, this is Akinde David with HNGi7 ID HNG-03801  using JavaScript for stage 2 task",
            "name": "akinde david",
            "id": "HNG-03801",
            "email": "daviking95@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "akinwale-adetola.js",
            "output": "Hello World, this is Akinwale ADETOLA with HNGi7 ID HNG-03515  using javascript for stage 2 task",
            "name": "akinwale adetola",
            "id": "HNG-03515",
            "email": "hackinwale.developer@gmail.com",
            "language": "javascript",
            "status": "pass"
            },
            {
            "file": "akorede-fodilu.py",
            "output": "Hello World, this is Akorede Fodilu with HNGi7 ID HNG-04279  using Python for stage 2 task",
            "name": "akorede fodilu",
            "id": "HNG-04279",
            "email": "afordeal88@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "alaneme-ikenna.js",
            "output": "Hello World, this is Alaneme Ikenna with HNGi7 ID HNG-00763  using javaScript for stage 2 task",
            "name": "alaneme ikenna",
            "id": "HNG-00763",
            "email": "ialaneme@yahoo.com",
            "language": "javaScript",
            "status": "pass"
            },
            {
            "file": "alexander-domakyaareh.js",
            "output": "Hello World, this is Alexander Domakyaareh with HNGi7 ID HNG-01520  using JavaScript for stage 2 task",
            "name": "alexander domakyaareh",
            "id": "HNG-01520",
            "email": "zeimedee@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "ali_abdulsamad.js",
            "output": "Hello World, this is Ali Abdulsamad Tolulope with HNGi7 ID HNG-02955  using JavaScript for stage 2 task",
            "name": "ali_abdulsamad",
            "id": "HNG-02955",
            "email": "iphenom01@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "alozieuwa-emmanuel.py",
            "output": "Hello world, this is Alozieuwa Emmanuel with HNGi7 ID HNG-01505  using Python for stage 2 task.",
            "name": "alozieuwa emmanuel",
            "id": "HNG-01505",
            "email": "emmanuelalozieuwa@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "aminat-okunuga.php",
            "output": "Hello World, this is Aminat Okunuga with HNGi7 ID HNG-03888  using PHP for stage 2 task.",
            "name": "aminat okunuga",
            "id": "HNG-03888",
            "email": "makadeaminat@gmail.com",
            "language": "PHP",
            "status": "pass"
            },
            {
            "file": "anash-uddin.js",
            "output": "Hello World, this is Anash Uddin with HNGi7 ID HNG-00049  using Javascript for stage 2 task",
            "name": "anash uddin",
            "id": "HNG-00049",
            "email": "anashuddin433@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "ani-stanley.js",
            "output": "Hello World, this is Ani Stanley with HNGi7 ID HNG-05725  using Javascript for stage 2 task",
            "name": "ani stanley",
            "id": "HNG-05725",
            "email": "anistanley2016@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "annah-mweru-nichola.js",
            "output": "Hello World, this is Annah Nichola with HNGi7 ID HNG-01677  using javascript for stage 2 task",
            "name": "annah mweru nichola",
            "id": "HNG-01677",
            "email": "annmweru9@gmail.com",
            "language": "javascript",
            "status": "pass"
            },
            {
            "file": "annah-nichola.js",
            "output": "Hello World, this is Annah Nichola with HNGi7 ID HNG-01677  using javascript for stage 2 task",
            "name": "annah nichola",
            "id": "HNG-01677",
            "email": "annmweru9@gmail.com",
            "language": "javascript",
            "status": "pass"
            },
            {
            "file": "anthony-nwanze.py",
            "output": "Hello World, this is Anthony Nwanze with HNGi7 ID HNG-01556  using python for stage 2 task",
            "name": "anthony nwanze",
            "id": "HNG-01556",
            "email": "anthonynwanze27@gmail.com",
            "language": "python",
            "status": "pass"
            },
            {
            "file": "ashiru-olawale.js",
            "output": "Hello World, this is Ashiru Olawale with HNGi7 ID HNG-01958  using JavaScript for stage 2 task",
            "name": "ashiru olawale",
            "id": "HNG-01958",
            "email": "walebant1@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "atabo-ufedojo.py",
            "output": "Hello World, this is Ufedojo Atabo with HNGi7 ID HNG-00325  using python for stage 2 task",
            "name": "atabo ufedojo",
            "id": "HNG-00325",
            "email": "ufedojoatabo@gmail.com",
            "language": "python",
            "status": "pass"
            },
            {
            "file": "awa-felix.dart",
            "output": "Hello World, this is Awa Felix with HNGi7 ID HNG-00187  using Dart for stage 2 task",
            "name": "awa felix",
            "id": "HNG-00187",
            "email": "felixhope30@gmail.com",
            "language": "Dart",
            "status": "pass"
            },
            {
            "file": "ayinde-john.js",
            "output": "Hello World, this is Ayinde John with HNGi7 ID HNG-00952  using JavaScript for stage 2 task",
            "name": "ayinde john",
            "id": "HNG-00952",
            "email": "lolaayinde@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "ayobami-fadeni.js",
            "output": "Hello world, this is Ayobami Fadeni with HNGi7 ID HNG-00940  using JavaScript for stage 2 task",
            "name": "ayobami fadeni",
            "id": "HNG-00940",
            "email": "fadeniayobami@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "ayodejinicholas.py",
            "output": "Hello world, this is Ayodeji Nicholas with email  with HNGi7 ID HNG-00743 using python for stage 2 task",
            "name": "ayodejinicholas",
            "id": "null",
            "email": "ayodejinicholas7@gmail.com",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "azeez-okelabi.php",
            "output": "Hello World, this is Azeez Okelabi with HNGi7 ID HNG-02466  using PHP for stage 2 task",
            "name": "azeez okelabi",
            "id": "HNG-02466",
            "email": "aokelabi10@gmail.com",
            "language": "PHP",
            "status": "pass"
            },
            {
            "file": "banjoko-judah.py",
            "output": "Hello World, this is Banjoko Judah with HNGi7 ID HNG-00697  using Python for stage 2 task.",
            "name": "banjoko judah",
            "id": "HNG-00697",
            "email": "banjokojudah@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "barnabas-asha.js",
            "output": "Hello World, this is Barnabas Asha with HNGi7 ID HNG-01877  using Javascript for stage 2 task",
            "name": "barnabas asha",
            "id": "HNG-01877",
            "email": "barnabee58@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "bolaji-ayeni.js",
            "output": "Hello World, my name is Bolaji Ayeni with Internship ID: HNG-01495  using JavaScript for the stage 2 task",
            "name": "bolaji ayeni",
            "id": "null",
            "email": "ayenibolaji3@gmail.com",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "bolarinwa-kazeem.js",
            "output": "Hello World, this is Bolarinwa Kazeem with HNGi7 ID HNG-00305  using JavaScript for stage 2 task",
            "name": "bolarinwa kazeem",
            "id": "HNG-00305",
            "email": "bola@reliancehmo.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "boluwatife-akinsola.js",
            "output": "Hello World, this is Akinsola Boluwatife with HNGi7 ID HNG-01814  using Javascript for stage 2 task",
            "name": "boluwatife akinsola",
            "id": "HNG-01814",
            "email": "boluwatifeakinsolas@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "caleb-ali.js",
            "output": "Hello World, this is Caleb Ali with HNGi7 ID HNG-01156  using JavaScript for stage 2 task",
            "name": "caleb ali",
            "id": "HNG-01156",
            "email": "caleb_ali@yahoo.co.uk",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "caleb-effiong.js",
            "output": "Hello world, this is Caleb Effiong with HNGi7 ID HNG-00951  using JavaScript for stage 2 task",
            "name": "caleb effiong",
            "id": "HNG-00951",
            "email": "calebarchi@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "celestine-okonkwo.py",
            "output": "Hello World, this is Okonkwo Celestine with HNGi7 ID HNG-00342  using Python for stage 2 task",
            "name": "celestine okonkwo",
            "id": "HNG-00342",
            "email": "macstine@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "chegbe-oyiguh.js",
            "output": "Hello World,this is Oyiguh Ojochegbe with HNGi7 ID:HNG-03796 using javascript for stage 2 task.",
            "name": "chegbe oyiguh",
            "id": "null",
            "email": "",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "chidubem-nwigwe.java",
            "output": "",
            "name": "chidubem nwigwe",
            "id": "null",
            "email": "",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "chima-ugbo.py",
            "output": "Hello World, this is Chima Ugbo with HNGi7 ID HNG-01119  using Python for stage 2 task",
            "name": "chima ugbo",
            "id": "HNG-01119",
            "email": "chimaugbo@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "chimamanda.js",
            "output": "Hello World, this is Onunwa Glory Chimamanda with HNGi7 ID HNG-03761  using javascript for stage 2 task.",
            "name": "chimamanda",
            "id": "HNG-03761",
            "email": "mcparadikay546@gmail.com",
            "language": "javascript",
            "status": "pass"
            },
            {
            "file": "chimaoge-aniuha.js",
            "output": "Hello World, This is Chimaoge Aniuha with HNGi7 ID HNG-02075  using Javascript for stage 2 task.",
            "name": "chimaoge aniuha",
            "id": "HNG-02075",
            "email": "chimaaniuha@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "chinaza-obiekwe.js",
            "output": "Hello World, this is Chinaza Obiekwe with HNGi7 ID HNG-06705  using Javascript for stage 2 task",
            "name": "chinaza obiekwe",
            "id": "HNG-06705",
            "email": "obiekweagnesmary@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "chinedu-mbah.js",
            "output": "Hello World, this is Chinedu Mbah with HNGi7 ID HNG-01190  using javaScript for stage 2 task",
            "name": "chinedu mbah",
            "id": "HNG-01190",
            "email": "lrrchinedu@gmail.com",
            "language": "javaScript",
            "status": "pass"
            },
            {
            "file": "chinemerem-ugwu.py",
            "output": "Hello World, this is Chinemerem Ugwu with HNGi7 ID HNG-02542  using Python for stage 2 task",
            "name": "chinemerem ugwu",
            "id": "HNG-02542",
            "email": "chinemerempromiseugwu@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "chineta-adinnu.js",
            "output": "Hello World, this is Chineta Adinnu with HNGi7 ID HNG-01204  using javascript for stage 2 task",
            "name": "chineta adinnu",
            "id": "HNG-01204",
            "email": "chinetaadinnu@gmail.com",
            "language": "javascript",
            "status": "pass"
            },
            {
            "file": "chukwuemeka-ndubuisi.js",
            "output": "Hello World, this is chukwuemeka ndubuisi with HNGi7 ID HNG-01433  using Javascript for stage 2 task",
            "name": "chukwuemeka ndubuisi",
            "id": "HNG-01433",
            "email": "ndubuisichukwuemeka2@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "chukwuka-chimdindu.js",
            "output": "Hello World, this is Chukwuka Chimdindu with HNGi7 ID HNG-04338  using JavaScript for stage 2 task",
            "name": "chukwuka chimdindu",
            "id": "HNG-04338",
            "email": "chimdindue@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "chukwuka-okonkwo.py",
            "output": "Hello world, this is Chukwuka Okonkwo David with HNGi7 ID HNG-01486 and ,using python for stage 2 task",
            "name": "chukwuka okonkwo",
            "id": "null",
            "email": "okonkwochukwuka56@gmail.com",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "claire-munyole.js",
            "output": "Hello World, this is Claire Munyole with HNGi7 ID HNG-00045  using Javascript for stage 2 task",
            "name": "claire munyole",
            "id": "HNG-00045",
            "email": "munyolec@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "collins-enebeli.js",
            "output": "Hello World, this is Collins Enebeli with HNGi7 ID HNG-05252  using Javascript for stage 2 task",
            "name": "collins enebeli",
            "id": "HNG-05252",
            "email": "collynizy@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "dami-oyediran.php",
            "output": "Hello World, this is Victor Damilola Oyediran with HNGi7 ID HNG-00894  using PHP for stage 2 task",
            "name": "dami oyediran",
            "id": "HNG-00894",
            "email": "oyediran.viktor@gmail.com",
            "language": "PHP",
            "status": "pass"
            },
            {
            "file": "damilola-oseni.js",
            "output": "Hello World, this is Damilola Oseni with HNGi7 ID HNG-00746  using Javascript for stage 2 task",
            "name": "damilola oseni",
            "id": "HNG-00746",
            "email": "mlola.oseni@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "david-ajawu.py",
            "output": "Hello World, this is David Ajawu with HNGi7 ID HNG-04265  using python for stage 2 task",
            "name": "david ajawu",
            "id": "HNG-04265",
            "email": "ajawudavid@gmail.com",
            "language": "python",
            "status": "pass"
            },
            {
            "file": "david-inyene.js",
            "output": "Hello World, this is David Inyene with HNGi7 ID HNG-02776  using Javascript for stage 2 task",
            "name": "david inyene",
            "id": "HNG-02776",
            "email": "etoedia@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "deborah-inyang.js",
            "output": "Hello World, this is Deborah Inyang with HNGi7 ID HNG-01672  using Javascript for stage 2 task.",
            "name": "deborah inyang",
            "id": "HNG-01672",
            "email": "deborahfinyang@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "dolly-kpobi.js",
            "output": "Hello World, this is Dolly Kpobi with HNGi7 ID HNG-00926  using Javascript for stage 2 task",
            "name": "dolly kpobi",
            "id": "HNG-00926",
            "email": "dkpobi7@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "dona-Ghosh.php",
            "output": "Hello world, this is Dona Ghosh with HNGi7 ID HNG-01185  using php for stage 2 task",
            "name": "dona Ghosh",
            "id": "HNG-01185",
            "email": "donaghosh3110@gmail.com",
            "language": "php",
            "status": "pass"
            },
            {
            "file": "edikan-ukpong.js",
            "output": "Hello World, this is Edikan Ukpong with HNGi7 ID HNG-03228  using Javascript for stage 2 task",
            "name": "edikan ukpong",
            "id": "HNG-03228",
            "email": "edikanukpong06@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "edwin-kayang.js",
            "output": "Hello World, this is Edwin Kayang with HNGi7 ID HNG-00019  using Javascript for stage 2 task",
            "name": "edwin kayang",
            "id": "HNG-00019",
            "email": "pelpuo2000@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "egekenze-kelechi.py",
            "output": "Hello World, this is Egekenze Kelechi with HNGi7 ID HNG-02308  using Python for stage 2 task",
            "name": "egekenze kelechi",
            "id": "HNG-02308",
            "email": "kelss451@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "ejeh-godwin.py",
            "output": "Hello World, this is Ejeh Godwin with HNGi7 ID HNG-00319  using python for stage 2 task",
            "name": "ejeh godwin",
            "id": "HNG-00319",
            "email": "ejehgodwin60@gmail.com",
            "language": "python",
            "status": "pass"
            },
            {
            "file": "ekeopara-praise.py",
            "output": "Hello World, this is Ekeopara Praise Udochukwu with HNGi7 ID HNG-03953  using Python for stage 2 task",
            "name": "ekeopara praise",
            "id": "HNG-03953",
            "email": "ekeoparapraise@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "elijah-edun.py",
            "output": "Hello World, this is Elijah Edun with HNGi7 ID HNG-02781  using python for stage 2 task",
            "name": "elijah edun",
            "id": "HNG-02781",
            "email": "edunelijah18@gmail.com",
            "language": "python",
            "status": "pass"
            },
            {
            "file": "emmanuel-erondu.php",
            "output": "Hello World, this is Erondu Emmanuel with HNGi7 ID HNG-06303  using PHP for Stage 2 task",
            "name": "emmanuel erondu",
            "id": "HNG-06303",
            "email": "erone007@gmail.com",
            "language": "PHP",
            "status": "pass"
            },
            {
            "file": "emmanuel-ikekwere.js",
            "output": "Hello World, this is Emmanuel Ikekwere with HNGi7 ID HNG-00209  using Javascript for stage 2 task",
            "name": "emmanuel ikekwere",
            "id": "HNG-00209",
            "email": "emmaikekwere@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "emmanuel-ikwuoma.js",
            "output": "Hello World, this is Ikwuoma Emmanuel with HNGi7 ID HNG-04940  using JavaScript for stage 2 task",
            "name": "emmanuel ikwuoma",
            "id": "HNG-04940",
            "email": "emmanuelikwuoma7@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "emmanuel-itakpe.py",
            "output": "Hello World, this is Emmanuel Itakpe Anuoluwa with HNGi7 ID HNG-01311  using Python for stage 2 task",
            "name": "emmanuel itakpe",
            "id": "HNG-01311",
            "email": "itakpeemma@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "emmanuel-nwabuodafi.js",
            "output": "Hello World, this is Emmanuel Nwabuodafi with HNGi7 ID HNG-01295  using JavaScript for stage 2 task",
            "name": "emmanuel nwabuodafi",
            "id": "HNG-01295",
            "email": "Nwabuodafiemmanuel@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "emmanuel-okoye.py",
            "output": "Hello World, this is Emmanuel Okoye with HNGi7 ID HNG-02995  using python for stage 2 task",
            "name": "emmanuel okoye",
            "id": "HNG-02995",
            "email": "tricelex@gmail.com",
            "language": "python",
            "status": "pass"
            },
            {
            "file": "emmanuel-olowu.py",
            "output": "Hello World, this is Emmanuel Olowu with HNGi7 ID HNG-03010  using Python for stage 2 task",
            "name": "emmanuel olowu",
            "id": "HNG-03010",
            "email": "olowurobin@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "ephraim-omenai.js",
            "output": "Hello World, this is Ephraim Omenai with HNGi7 ID HNG-02742  using JavaScript for stage 2 task",
            "name": "ephraim omenai",
            "id": "HNG-02742",
            "email": "eomenaiofficial@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "eric-ibu.js",
            "output": "Hello World, this is Ibu Eric with HNGi7 ID HNG-04677  using Javascript for stage 2 task",
            "name": "eric ibu",
            "id": "HNG-04677",
            "email": "martinirex@yahoo.co.uk",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "esther-ninyo.js",
            "output": "Hello world, this is Esther Ninyo with HNGi7 ID HNG-04176  using Javascript for stage 2 task",
            "name": "esther ninyo",
            "id": "HNG-04176",
            "email": "ninyhorlah6@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "esther_vaati.py",
            "output": "Hello World, this is Esther Vaati with HNGi7 ID HNG-06183  using Python for stage 2 task",
            "name": "esther_vaati",
            "id": "HNG-06183",
            "email": "vaatiesther@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "fahd-mohammed.js",
            "output": "Hello World, this is Fahd Mohammed with HNGi7 ID HNG-00561  using JavaScript for stage 2 task",
            "name": "fahd mohammed",
            "id": "HNG-00561",
            "email": "fahdmoh.1@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "faithfulness-alamu.py",
            "output": "Hello World, this is Faithfulness Alamu with HNGi7 ID HNG-02407  using Python for stage 2 task",
            "name": "faithfulness alamu",
            "id": "HNG-02407",
            "email": "vaguemail369@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "farouk-afolabi.js",
            "output": "Hello World, this is Farouk Afolabi with HNGi7 ID HNG-01256  using JavaScript for stage 2 task.",
            "name": "farouk afolabi",
            "id": "HNG-01256",
            "email": "afolabifarouk99@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "faruk-adekola.js",
            "output": "Hello World, this is Faruk Adekola with HNGi7 ID HNG-01835  using JavaScript for stage 2 task",
            "name": "faruk adekola",
            "id": "HNG-01835",
            "email": "adekoladamilola4@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "fidele-kirezi-cyisa.py",
            "output": "Hello World, this is Kirezi Cyisa Fidele with HNGi7 ID HNG-03961  using Python for stage 2 task",
            "name": "fidele kirezi cyisa",
            "id": "HNG-03961",
            "email": "fihacker000@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "folarin-oyenuga.js",
            "output": "Hello World, this is Folarin Oyenuga with HNGi7 ID HNG-00063  using JavaScript for stage 2 task.",
            "name": "folarin oyenuga",
            "id": "HNG-00063",
            "email": "folami25@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "fredrick-njeri.py",
            "output": "Hello World, this is Fredrick Njeri with HNGi7 ID HNG-00655  using Python for stage 2 task",
            "name": "fredrick njeri",
            "id": "HNG-00655",
            "email": "fredricknjeri64@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "fuadOlatunji.js",
            "output": "Hello World, this is Fuad Olatunji with HNGi7 ID HNG-06089  using JavaScript for stage 2 task",
            "name": "fuadOlatunji",
            "id": "HNG-06089",
            "email": "fuadolatunji@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "gideon-etim.js",
            "output": "Hello World, this is Gideon Etim with HNGi7 ID HNG-00144  using Javascript for stage 2 task",
            "name": "gideon etim",
            "id": "HNG-00144",
            "email": "gidiblack@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "gift-egbujuo.js",
            "output": "Hello World, this is Egbujuo Gift with HNGi7 ID HNG-04683  using Javascript for stage 2 task",
            "name": "gift egbujuo",
            "id": "HNG-04683",
            "email": "gifftchiedozie@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "glory-emmanuel.js",
            "output": "Hello World, this is Glory Emmanuel with HNGi7 ID HNG-01013  using Javascript for stage 2 task",
            "name": "glory emmanuel",
            "id": "HNG-01013",
            "email": "emmaglorypraise@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "grant-iloba.js",
            "output": "Hello World, this is Grant Iloba with HNGi7 ID HNG-01210  using javascript for stage 2 task",
            "name": "grant iloba",
            "id": "HNG-01210",
            "email": "grantiloba@gmail.com",
            "language": "javascript",
            "status": "pass"
            },
            {
            "file": "grenalyn.js",
            "output": "Hello World, this is Adwoa Asare with HNGi7 ID HNG-03497  using JavaScript for stage 2 task",
            "name": "grenalyn",
            "id": "HNG-03497",
            "email": "jakazzy@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "habeeb-awoyemi.js",
            "output": "Hello World, this is Habeeb Awoyemi with HNGi7 ID HNG-02639  using Javascript for stage 2 task",
            "name": "habeeb awoyemi",
            "id": "HNG-02639",
            "email": "awoyemi.habeeb@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "hassan-said.php",
            "output": "Hello World, this is Hassan Said with HNGi7 ID HNG-06283  using php for stage 2 task",
            "name": "hassan said",
            "id": "HNG-06283",
            "email": "kronikkronix@gmail.com",
            "language": "php",
            "status": "pass"
            },
            {
            "file": "henry-mutegeki.js",
            "output": "Hello World, this is Henry Mutegeki with HNGi7 ID HNG-01577  using Javascript for stage 2 task",
            "name": "henry mutegeki",
            "id": "HNG-01577",
            "email": "henrymutegeki117@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "ibukunoluwa-olajide.py",
            "output": "Hello World, this is Olajide Ibukunoluwa Temitope with HNGi7 ID HNG-02393  using Python for stage 2 task",
            "name": "ibukunoluwa olajide",
            "id": "HNG-02393",
            "email": "ibk12mails@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "idris-ifeoluwa.py",
            "output": "Hello world, this is Idris Ifeoluwa with HNGi7 ID HNG-01480  using Python for stage 2 task.",
            "name": "idris ifeoluwa",
            "id": "HNG-01480",
            "email": "idrisloove@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "ifeanyichukwu-alichi.py",
            "output": "Hello World, this is Alichi Ifeanyichukwu with HNGi7 ID HNG-01341  using Python for stage 2 task",
            "name": "ifeanyichukwu alichi",
            "id": "HNG-01341",
            "email": "alichiifeanyi@yahoo.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "iheme-chioma.js",
            "output": "Hello World, this is iheme  chioma grace with HNGi7 ID HNG-00571  using Javascript for stage 2 task",
            "name": "iheme chioma",
            "id": "null",
            "email": "graceiheme@gmail.com",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "ikechukwu_chukwudi.py",
            "output": "",
            "name": "ikechukwu_chukwudi",
            "id": "null",
            "email": "",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "iniabasi-affiah.py",
            "output": "Hello World, this is Affiah Ini-Abasi Bernard with HNGi7 ID HNG-00758  using Python for stage 2 task",
            "name": "iniabasi affiah",
            "id": "HNG-00758",
            "email": "iniabasi.bernard@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "iniubong-obonguko.js",
            "output": "Hello World, this is Iniubong Obonguko with HNGi7 ID HNG-03927  using javascript for stage 2 task",
            "name": "iniubong obonguko",
            "id": "HNG-03927",
            "email": "iniubongobonguko2018@gmail.com",
            "language": "javascript",
            "status": "pass"
            },
            {
            "file": "iselen-triumph.py",
            "output": "Hello World, this is Iselen Triumph with HNGi7 ID HNG-04439  using python for stage 2 task",
            "name": "iselen triumph",
            "id": "HNG-04439",
            "email": "t4riumph@gmail.com",
            "language": "python",
            "status": "pass"
            },
            {
            "file": "isiko_saidiali.py",
            "output": "Hello world, this is Isiko Saidiali with HNGi7 ID HNG-01856  using Python for stage 2 task",
            "name": "isiko_saidiali",
            "id": "HNG-01856",
            "email": "isikosaidiali@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "itunu-lamina.js",
            "output": "Hello world, this is Itunu Lamina with HNGi7 ID HNG-01371  using JavaScript for stage 2 task",
            "name": "itunu lamina",
            "id": "HNG-01371",
            "email": "Nix4phun@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "iyanu-oladele.dart",
            "output": "Hello World, this is Oladele Iyanu with HNGi7 ID HNG-02314  using Dart for stage 2 task",
            "name": "iyanu oladele",
            "id": "HNG-02314",
            "email": "iyanuoladele123@gmail.com",
            "language": "Dart",
            "status": "pass"
            },
            {
            "file": "jackson-jonah.php",
            "output": "Hello World, this is Jonah Jackson Joseph with HNGi7 ID HNG-01443  using php for stage 2 task",
            "name": "jackson jonah",
            "id": "HNG-01443",
            "email": "jonahjacksonj@gmail.com",
            "language": "php",
            "status": "pass"
            },
            {
            "file": "jalil-zakaria.js",
            "output": "Hello World, this is abdul jalil zakaria with HNGi7 ID HNG-03280  using javascript for stage 2 task",
            "name": "jalil zakaria",
            "id": "HNG-03280",
            "email": "abduljalilzakaria1@gmail.com",
            "language": "javascript",
            "status": "pass"
            },
            {
            "file": "james-dayo.py",
            "output": "Hello World, this is James Dayo with HNGi7 ID HNG-05786  using Python for stage 2 task",
            "name": "james dayo",
            "id": "HNG-05786",
            "email": "jdayo2012@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "jemimanaomi-ben.php",
            "output": "Hello World, this is JemimaNaomi Godwin Ben with HNGi7 ID HNG-02526  using php for stage 2 task",
            "name": "jemimanaomi ben",
            "id": "HNG-02526",
            "email": "benjemimanaomi@gmail.com",
            "language": "php",
            "status": "pass"
            },
            {
            "file": "jesse-ojo.py",
            "output": "Hello World, this is Jesse Ojo with HNGi7 ID HNG-04073  using python for Stage 2 task",
            "name": "jesse ojo",
            "id": "HNG-04073",
            "email": "jesseswags@gmail.com",
            "language": "python",
            "status": "pass"
            },
            {
            "file": "jimoh-oluwatosin.js",
            "output": "Hello World, this is Oluwatosin Jimoh with HNGi7 ID HNG-00977  using Javascript for stage 2 task",
            "name": "jimoh oluwatosin",
            "id": "HNG-00977",
            "email": "jayoluwatosin@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "jobafash.js",
            "output": "Hello World, this is Oluwajoba Fashogbon with HNGi7 ID HNG-02405  using javascript for stage 2 task",
            "name": "jobafash",
            "id": "HNG-02405",
            "email": "jobafash3@gmail.com",
            "language": "javascript",
            "status": "pass"
            },
            {
            "file": "john_shodipo.py",
            "output": "Hello World, this is John Shodipo with HNGi7 ID HNG-00428  using Python for stage 2 task",
            "name": "john_shodipo",
            "id": "HNG-00428",
            "email": "newtonjohn043@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "joshua-erondu.js",
            "output": "Hello World, this is Joshua Erondu with HNGi7 ID HNG-00077  using javascript for Stage 2 task",
            "name": "joshua erondu",
            "id": "HNG-00077",
            "email": "joshuaerondu4@gmail.com",
            "language": "javascript",
            "status": "pass"
            },
            {
            "file": "joshua-ogbonna.js",
            "output": "Hello World, this is Joshua Ogbonna with HNGi7 ID HNG-03663  using javascript for stage 2 task",
            "name": "joshua ogbonna",
            "id": "HNG-03663",
            "email": "devjaykes@gmail.com",
            "language": "javascript",
            "status": "pass"
            },
            {
            "file": "josiah-augustine.py",
            "output": "Hello World, this is Josiah Augustine Onyemaechi with HNGi7 ID HNG-00887  using Python for stage 2 task",
            "name": "josiah augustine",
            "id": "HNG-00887",
            "email": "josiah.augustine.o@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "jules-tjahe.py",
            "output": "Hello World, this is Tjahe Essomba Jules Renaud with HNGi7 ID HNG-01452  using python for stage 2 task",
            "name": "jules tjahe",
            "id": "HNG-01452",
            "email": "julesrenaud10@gmail.com",
            "language": "python",
            "status": "pass"
            },
            {
            "file": "kayode-omotehinse.js",
            "output": "Hello World, this is Kayode Omotehinse with HNGi7 ID HNG-04498 using JavaScript for stage 2 task.",
            "name": "kayode omotehinse",
            "id": "null",
            "email": "",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "kehindeBankole.js",
            "output": "Hello World, this is kehinde Bankole with HNGi7 ID HNG-03913  using JavaScript for stage 2 task",
            "name": "kehindeBankole",
            "id": "HNG-03913",
            "email": "bankolek1@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "kevin_izuchukwu.js",
            "output": "Hello World, my name is Kevin Izuchukwu with the HNGi7 ID HNG-04697 using Javascript for stage 2 task",
            "name": "kevin_izuchukwu",
            "id": "null",
            "email": "",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "khabbab_abdurrazaq.php",
            "output": "Hello World, this is Khabbab Abdurrazaq with HNGi7 ID HNG-05015  using PHP for stage 2 task",
            "name": "khabbab_abdurrazaq",
            "id": "HNG-05015",
            "email": "swartjide@gmail.com",
            "language": "PHP",
            "status": "pass"
            },
            {
            "file": "kolajo_tomike.js",
            "output": "Hello World, this is Kolajo to mike with HNGi7 ID HNG-05840  using JavaScript for stage 2 task",
            "name": "kolajo_tomike",
            "id": "HNG-05840",
            "email": "kolajoelizabeth@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "lateef-quadri.js",
            "output": "Hello World, this is Lateef Quadri Olayinka with HNGi7 ID HNG-02622  using JavaScript for stage 2 task",
            "name": "lateef quadri",
            "id": "HNG-02622",
            "email": "lateef9816@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "layan-grace.py",
            "output": "Hello World, this is Layan Grace with HNGi7 ID HNG-00297  using python for stage 2 task",
            "name": "layan grace",
            "id": "HNG-00297",
            "email": "layangrace00@gmail.com",
            "language": "python",
            "status": "pass"
            },
            {
            "file": "lois-adegbohungbe.js",
            "output": "Hello World, this is Lois Adegbohungbe with HNGi7 ID HNG-04138  using Javascript for stage 2 task",
            "name": "lois adegbohungbe",
            "id": "HNG-04138",
            "email": "loisadegbohungbe@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "loyal-amaechi.js",
            "output": "Hello world, this is Loyal Amaechi with HNGi7 ID HNG-04661  using Javascript for stage 2 task",
            "name": "loyal amaechi",
            "id": "HNG-04661",
            "email": "contactloyal287@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "lucas-okafor.js",
            "output": "Hello World, this is Lucas Okafor with HNGi7 ID HNG-02912  using Javascript for stage 2 task",
            "name": "lucas okafor",
            "id": "HNG-02912",
            "email": "lucas.matehc@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "lucky-lawrence.js",
            "output": "Hello World, this is Lawrence Lucky with HNGi7 ID HNG-00524  using JavaScript for stage 2 task",
            "name": "lucky lawrence",
            "id": "HNG-00524",
            "email": "lawrencelucky1999@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "marcus_elendu.js",
            "output": "Hello World, this is Marcus Elendu with HNGi7 ID HNG-00390  using Javascript for stage 2 task",
            "name": "marcus_elendu",
            "id": "HNG-00390",
            "email": "jomarc233@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "mariam-hamzat.js",
            "output": "Hello World, this is Mariam Hamzat with HNGi7 ID HNG-02768  using JavaScript for stage 2 task",
            "name": "mariam hamzat",
            "id": "HNG-02768",
            "email": "titilayobolamide247@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "marline-khavele.py",
            "output": "Hello World, this is Marline Khavele with HNGi7 ID HNG-04957  using python for stage 2 task",
            "name": "marline khavele",
            "id": "HNG-04957",
            "email": "khavelemarline@gmail.com",
            "language": "python",
            "status": "pass"
            },
            {
            "file": "maryam-mudasiru.js",
            "output": "Hello World, this is Maryam Mudasiru with HNGi7 ID HNG-00905  using javascript for stage 2 task",
            "name": "maryam mudasiru",
            "id": "HNG-00905",
            "email": "maryammudasiru@gmail.com",
            "language": "javascript",
            "status": "pass"
            },
            {
            "file": "michael-ibinola.js",
            "output": "Hello World, this is Michael Ibinola with HNGi7 ID HNG-02066  using Javascript for stage 2 task",
            "name": "michael ibinola",
            "id": "HNG-02066",
            "email": "ibinolamichael1@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "michael-olowe.py",
            "output": "Hello World, this is Michael Olowe with HNGi7 ID HNG-02021  using python for stage 2 task",
            "name": "michael olowe",
            "id": "HNG-02021",
            "email": "michaelolowe321@gmail.com",
            "language": "python",
            "status": "pass"
            },
            {
            "file": "moises-borracha.php",
            "output": "Hello World, this is Moises Wenikeni Suquila Borracha with HNGi7 ID HNG-00308  using PHP for stage 2 task",
            "name": "moises borracha",
            "id": "HNG-00308",
            "email": "moisesnt2@gmail.com",
            "language": "PHP",
            "status": "pass"
            },
            {
            "file": "moses-aizee.js",
            "output": "Hello World, this is Moses Aizee with HNGi7 ID HNG-03671  using Javascript for stage 2 task.",
            "name": "moses aizee",
            "id": "HNG-03671",
            "email": "azmotech@outlook.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "murtala-aliyu.js",
            "output": "Hello World, this is Murtala Aliyu with HNGi7 ID HNG-00925  using javascript for stage 2 task.",
            "name": "murtala aliyu",
            "id": "HNG-00925",
            "email": "talktoaliyu@gmail.com",
            "language": "javascript",
            "status": "pass"
            },
            {
            "file": "mutmainah-yunus.js",
            "output": "Hello World, this is Yunus Mutmainah with HNGi7 ID HNG-02696  using javascript for stage 2 task",
            "name": "mutmainah yunus",
            "id": "HNG-02696",
            "email": "yunusmutmainah@gmail.com",
            "language": "javascript",
            "status": "pass"
            },
            {
            "file": "muttalib-Soladoye.py",
            "output": "Hello World, this is Muttalib Soladoye with HNGi7 ID HNG-03318  using Pythoon for stage 2 task",
            "name": "muttalib Soladoye",
            "id": "HNG-03318",
            "email": "soladoyeolaos@gmail.com",
            "language": "Pythoon",
            "status": "pass"
            },
            {
            "file": "myName.js",
            "output": "",
            "name": "myName",
            "id": "null",
            "email": "",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "nandom-alfred.js",
            "output": "Hello World, this is Nandom Alfred with HNGi7 ID HNG-05959  using JavaScript for stage 2 task",
            "name": "nandom alfred",
            "id": "HNG-05959",
            "email": "nandommamdam@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "nasiru-danjuma.js",
            "output": "Hello World, this is Nasiru Danjuma with HNGi7 ID HNG-00512  using JavaScript for stage 2 task.",
            "name": "nasiru danjuma",
            "id": "HNG-00512",
            "email": "talk2danjumanas@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "nerona-sook.py",
            "output": "Hello World, this is Nerona Sook with HNGi7 ID HNG-00194  using python for stage 2 task",
            "name": "nerona sook",
            "id": "HNG-00194",
            "email": "neronasook@gmail.com",
            "language": "python",
            "status": "pass"
            },
            {
            "file": "nkechi-emmanuel.js",
            "output": "Hello World, this is Nkechi Emmanuel with HNGi7 ID HNG-04417  using JavaScript for stage 2 task.",
            "name": "nkechi emmanuel",
            "id": "HNG-04417",
            "email": "nkechiemmanuel95@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "nlewedim_chisombiri.js",
            "output": "Hello World, this is Nlewedim Chisombiri with HNGi7 ID HNG-01454  using JavaScript for stage 2 task",
            "name": "nlewedim_chisombiri",
            "id": "HNG-01454",
            "email": "chisombiri@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "nnaji-victor.js",
            "output": "Hello World, this is Victor Nnaji with HNGi7 ID HNG-04553  using javaScript for stage 2 task",
            "name": "nnaji victor",
            "id": "HNG-04553",
            "email": "nnajivictor0@gmail.com",
            "language": "javaScript",
            "status": "pass"
            },
            {
            "file": "nnamdi-aninye.php",
            "output": "Hello World, this is Nnamdi Aninye with HNGi7 ID HNG-04740  using JavaScript for stage 2 task",
            "name": "nnamdi aninye",
            "id": "HNG-04740",
            "email": "unix1gl@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "nusrah-farri-ghazal.js",
            "output": "Hello World, this is Nusrah Farri Ghazal with HNGi7 ID HNG-00310  using javascript for stage 2 task",
            "name": "nusrah farri ghazal",
            "id": "HNG-00310",
            "email": "nusrahfarri@gmail.com",
            "language": "javascript",
            "status": "pass"
            },
            {
            "file": "obasa_samuel.py",
            "output": "Hello World, this is Obasa Samuel Temitope with HNGi7 ID HNG-04751  using Python for stage 2 task",
            "name": "obasa_samuel",
            "id": "HNG-04751",
            "email": "obasasamuel96@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "obatobi-ayeni.py",
            "output": "Hello world, this is Obatobi Ayeni with HNGi7 ID HNG-04196 using python for stage 2 task",
            "name": "obatobi ayeni",
            "id": "null",
            "email": "",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "ofeimu-david.js",
            "output": "Hi everyone i am: David , 1234 is my ID and i code with Javascript.",
            "name": "ofeimu david",
            "id": "null",
            "email": "",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "ogbonna-kezie.js",
            "output": "Hello World, this is Ogbonna Chikezie with HNGi7 ID HNG-03736  using Javascript for stage 2 task",
            "name": "ogbonna kezie",
            "id": "HNG-03736",
            "email": "ogbonnakezie@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "ojo-temitope.js",
            "output": "Hello World, this is Temitope Emmanuel Ojo with HNGi7 ID HNG-01398  using Javascript for stage 2 task",
            "name": "ojo temitope",
            "id": "HNG-01398",
            "email": "temitopeojo0@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "ojora-oyeyinka.js",
            "output": "Hello World, this is OJORA OYEYINKA with HNGi7 ID HNG-00431  using JavaScript for stage 2 task",
            "name": "ojora oyeyinka",
            "id": "HNG-00431",
            "email": "oyeyinkaojoro@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "okanlawon-jamiu.js",
            "output": "Hello World, this is Jamiu Okanlawon with HNGi7 ID HNG-03940  using Javascript for stage 2 task",
            "name": "okanlawon jamiu",
            "id": "HNG-03940",
            "email": "developerjamiu@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "okereke-okereke.py",
            "output": "Hello World, this is Okereke Kalu Okereke with HNGi7 ID HNG-04125  using Python for stage 2 task",
            "name": "okereke okereke",
            "id": "HNG-04125",
            "email": "okereke.o@live.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "okonkwo-emmanuel.php",
            "output": "Hello World, this is Okonkwo Emmanuel with HNGi7 ID HNG-00082  using php for stage 2 task",
            "name": "okonkwo emmanuel",
            "id": "HNG-00082",
            "email": "emmanchigo10@gmail.com",
            "language": "php",
            "status": "pass"
            },
            {
            "file": "okorie-nnamdi.js",
            "output": "Hello World, this is Okorie Nnamdi with HNGi7 ID HNG-03880  using javaScript for stage 2 task",
            "name": "okorie nnamdi",
            "id": "HNG-03880",
            "email": "okorie.nnamdy@gmail.com",
            "language": "javaScript",
            "status": "pass"
            },
            {
            "file": "oladimeji-abiola.py",
            "output": "Hello World, this is Oladimeji Abiola with HNGi7 ID HNG-04204  using Python for stage 2 task",
            "name": "oladimeji abiola",
            "id": "HNG-04204",
            "email": "bizzdimeji@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "oladugba-demilade.py",
            "output": "Hello World, this is Oladugba Demilade with HNGi7 ID HNG-00976  using Python for stage 2 task",
            "name": "oladugba demilade",
            "id": "HNG-00976",
            "email": "demiladeoladugba@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "olamide-agboola.js",
            "output": "Hello World, this is Olamide Agboola with HNGi7 ID HNG-00261  using Javascript for stage 2 task",
            "name": "olamide agboola",
            "id": "HNG-00261",
            "email": "saintlammy@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "olamide-akinyemi.js",
            "output": "Hello World, this is Olamide Akinyemi with HNGi7 ID HNG-01639  using javascript for stage 2 task",
            "name": "olamide akinyemi",
            "id": "HNG-01639",
            "email": "olamideakinyemi41@gmail.com",
            "language": "javascript",
            "status": "pass"
            },
            {
            "file": "olamide-davids.js",
            "output": "Hello World, this is Olamide Davids with HNGi7 ID HNG-01138  using Javascript for stage 2 task",
            "name": "olamide davids",
            "id": "HNG-01138",
            "email": "olamidedavid189@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "olayiwola-Olanrewaju.js",
            "output": "Hello World, this is Olayiwola Olanrewaju with HNGi7 ID HNG-02315  using Javascript for stage 2 task",
            "name": "olayiwola Olanrewaju",
            "id": "HNG-02315",
            "email": "larry_coal@outlook.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "oliverotchere.js",
            "output": "Hello World, this is Oliver Otchere with HNGi7 ID HNG-01090  using javaScript for stage 2 task",
            "name": "oliverotchere",
            "id": "HNG-01090",
            "email": "oliverotchere4@gmail.com",
            "language": "javaScript",
            "status": "pass"
            },
            {
            "file": "olufemi-fadahunsi.js",
            "output": "Hello World, this is Olufemi Fadahunsi with HNGi7 ID HNG-00269  using JavaScript for stage 2 task.",
            "name": "olufemi fadahunsi",
            "id": "HNG-00269",
            "email": "olufemifad@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "oluwafemi-oyepeju.py",
            "output": "Hello World, this is Oluwafemi Oyepeju with HNGi7 ID HNG-00897  using Python for stage 2 task",
            "name": "oluwafemi oyepeju",
            "id": "HNG-00897",
            "email": "oluwafemioyepeju@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "oluwamayowa-george.js",
            "output": "Hello World, this is Oluwamayowa George with HNGi7 ID HNG-00461  using Javascript for stage 2 task",
            "name": "oluwamayowa george",
            "id": "HNG-00461",
            "email": "themayowageorge@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "oluwasayo-oyedepo.php",
            "output": "Hello World, this is Oluwasayo Oyedepo with HNGi7 ID HNG-00026  using PHP for stage 2 task",
            "name": "oluwasayo oyedepo",
            "id": "HNG-00026",
            "email": "oluwasayo12@gmail.com",
            "language": "PHP",
            "status": "pass"
            },
            {
            "file": "oluwaseyi-awotunde.js",
            "output": "Hello World, this is Oluwaseyi Awotunde with HNGi7 ID HNG-00262  using JavaScript for Stage 2 task",
            "name": "oluwaseyi awotunde",
            "id": "HNG-00262",
            "email": "seyi.juliana@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "oluwatimilehin-idowu.js",
            "output": "Hello World, this is Oluwatimilehin Idowu with HNGi7 ID HNG-05074  using Javascript for stage 2 task",
            "name": "oluwatimilehin idowu",
            "id": "HNG-05074",
            "email": "oluwatimilehin.id@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "omogbare-sikpojie.js",
            "output": "Hello World, this is Omogbare Sikpojie with HNGi7 ID HNG-00289  using JavaScript for stage 2 task.",
            "name": "omogbare sikpojie",
            "id": "HNG-00289",
            "email": "raymondomon@yahoo.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "omoruyi-ohuoba.js",
            "output": "Hello World, this is Omoruyi Ohuoba with HNGi7 ID HNG-03458  using javaScript for stage 2 task",
            "name": "omoruyi ohuoba",
            "id": "HNG-03458",
            "email": "davidngozi2000@yahoo.com",
            "language": "javaScript",
            "status": "pass"
            },
            {
            "file": "omotayo-kasim.php",
            "output": "Hello World, this is Omotayo kasim with HNGi7 ID HNG-03625  using PHP for stage 2 task",
            "name": "omotayo kasim",
            "id": "HNG-03625",
            "email": "smartfocusdrive@gmail.com",
            "language": "PHP",
            "status": "pass"
            },
            {
            "file": "omwenga-obed.js",
            "output": "Hello World, this is Obed Omwenga with HNGi7 ID HNG-05708  using Javascript for stage 2 task",
            "name": "omwenga obed",
            "id": "HNG-05708",
            "email": "omwenga.obed39@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "opeyemi-popoola.js",
            "output": "Hello World, this is opeyemi popoola with HNGi7 ID HNG-06043  using Javascript for stage 2 task",
            "name": "opeyemi popoola",
            "id": "HNG-06043",
            "email": "opmatcode@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "osinaya-oludare.js",
            "output": "Hello World, this is Osinaya Oludare with HNGi7 ID HNG-04443  using Javascript for stage 2 task",
            "name": "osinaya oludare",
            "id": "HNG-04443",
            "email": "osinayaoludare@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "osondu-tochukwu.js",
            "output": "Hello World, this is Osondu Tochukwu with HNGi7 ID HNG-00158  using JavaScript for stage 2 task",
            "name": "osondu tochukwu",
            "id": "HNG-00158",
            "email": "tosolife@yahoo.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "oyelola-emmanuel.py",
            "output": "Hello World, this is Oyelola Emmanuel with HNGi7 ID HNG-02651  using python for stage 2 task",
            "name": "oyelola emmanuel",
            "id": "HNG-02651",
            "email": "emmanoyelola@gmail.com",
            "language": "python",
            "status": "pass"
            },
            {
            "file": "passTest.js",
            "output": "",
            "name": "passTest",
            "id": "null",
            "email": "",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "paul-oisamoje.php",
            "output": "Hello World, this is Paul Oismoje with HNGi7 ID HNG-01966  using php for stage 2 task.",
            "name": "paul oisamoje",
            "id": "HNG-01966",
            "email": "poisamoje@gmail.com",
            "language": "php",
            "status": "pass"
            },
            {
            "file": "peter-chigozie.py",
            "output": "Hello World, this is Peter Chigozie Osundu with HNGi7 ID HNG-05167  using Python for stage 2 task",
            "name": "peter chigozie",
            "id": "HNG-05167",
            "email": "peterchigozieosondu@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "peter-onum.py",
            "output": "Hello world, this is Peter Onum with HNGi7 ID HNG-00133  using Python for stage 2 task",
            "name": "peter onum",
            "id": "HNG-00133",
            "email": "onumdev@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "philemonbrain-Anagwu.py",
            "output": "Hello World, this is Anagwu Brain Philemon with HNGi7 ID HNG-04096  using Python for stage 2 task",
            "name": "philemonbrain Anagwu",
            "id": "HNG-04096",
            "email": "philbrainy@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "praise-ajayi.py",
            "output": "Hello World, this is Praise Ajayi with HNGi7 ID HNG-04513  using python for stage 2 task",
            "name": "praise ajayi",
            "id": "HNG-04513",
            "email": "praiseajayi2@gmail.com",
            "language": "python",
            "status": "pass"
            },
            {
            "file": "prince-ugwuegbu.py",
            "output": "Hello World, this is Ugwuegbu Prince with HNGi7 ID HNG-00430  using Python for stage 2 task",
            "name": "prince ugwuegbu",
            "id": "HNG-00430",
            "email": "chibex40@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "priscilla-achizue.py",
            "output": "Hello World, this is Priscilla Achizue with HNGi7 ID HNG-00721  using python for stage 2 task",
            "name": "priscilla achizue",
            "id": "HNG-00721",
            "email": "baeewele27@gmail.com",
            "language": "python",
            "status": "pass"
            },
            {
            "file": "promise-johnson.js",
            "output": "Hello World, this is Promise Johnson with HNGi7 ID HNG-03039  using Javascript for stage 2 task.",
            "name": "promise johnson",
            "id": "HNG-03039",
            "email": "chiemelapromise30@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "raiyan-mukhtar.py",
            "output": "Hello world, this is Raiyan Mukhtar with HNGi7 ID HNG-01816  using python for stage 2 task.",
            "name": "raiyan mukhtar",
            "id": "HNG-01816",
            "email": "raiyan.dev6@gmail.com",
            "language": "python",
            "status": "pass"
            },
            {
            "file": "richard-madu.py",
            "output": "Hello World this is Richard Madu with HNGi7 ID HNG-01777  using python for stage 2 task",
            "name": "richard madu",
            "id": "HNG-01777",
            "email": "madurichard09@gmail.com",
            "language": "python",
            "status": "pass"
            },
            {
            "file": "robertojr-principio.js",
            "output": "Hello World, this is Roberto Principio Jr with HNGi7 ID HNG-03150  using JavaScript for stage 2 task",
            "name": "robertojr principio",
            "id": "HNG-03150",
            "email": "rdprincipio.jr@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "saka-rebecca.py",
            "output": "Hello world, this is, Rebecca Saka, With HNGi7 ID, HNG-05990, and email,  , using python for stage 2 task",
            "name": "saka rebecca",
            "id": "null",
            "email": "beccasaka@gmail.com",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "salifu-sani.js",
            "output": "Hello World, this is Salifu Sani Rich with HNGi7 ID HNG-00246  using JavaScript for stage 2 task",
            "name": "salifu sani",
            "id": "HNG-00246",
            "email": "sarscodes@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "samuel-fatodu.js",
            "output": "Hello World, this is Samuel Fatodu with HNGi7 ID HNG-03538  using JavaScript for stage 2 task.",
            "name": "samuel fatodu",
            "id": "HNG-03538",
            "email": "samuelfatodu@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "samuelsholademi.php",
            "output": "Hello world, this is Sholademi Samuel with HNGi7 ID HNG-02065  using php for stage 2 task",
            "name": "samuelsholademi",
            "id": "HNG-02065",
            "email": "samuelsholademi37@gmail.com",
            "language": "php",
            "status": "pass"
            },
            {
            "file": "sangobiyi-titiayo.js",
            "output": "Hello World, this is Sangobiyi Titilayo with HNGi7 ID HNG-04718  using JavaScript for stage 2 task.",
            "name": "sangobiyi titiayo",
            "id": "HNG-04718",
            "email": "florencetitilayk@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "sanusi-victor.js",
            "output": "Hello World, this is Sanusi Victor with HNGi7 ID HNG-03071  using JavaScript for stage 2 task",
            "name": "sanusi victor",
            "id": "HNG-03071",
            "email": "sanvicola2000@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "semiu-biliaminu.php",
            "output": "Hello World, this is semiu biliaminu with HNGi7 ID HNG-04209  using PHP for stage 2 task",
            "name": "semiu biliaminu",
            "id": "HNG-04209",
            "email": "codedash07@gmail.com",
            "language": "PHP",
            "status": "pass"
            },
            {
            "file": "shekinah-adaramola.js",
            "output": "Hello World, this is Shekinah Adaramola with HNGi7 ID HNG-03539  using Javascript for stage 2 task",
            "name": "shekinah adaramola",
            "id": "HNG-03539",
            "email": "shekinahadaramola@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "silas-agbaje.py",
            "output": "Hello World, this is Agbaje Silas with HNGi7 ID HNG-02233  using python for stage 2 task",
            "name": "silas agbaje",
            "id": "HNG-02233",
            "email": "silasagbaje@gmail.com",
            "language": "python",
            "status": "pass"
            },
            {
            "file": "simi-dawalang.py",
            "output": "Hello World, this is Simi Da-Walang with HNGi7 ID HNG-04578  using Python for stage 2 task",
            "name": "simi dawalang",
            "id": "HNG-04578",
            "email": "simidawalang@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "sobaki-ademola.js",
            "output": "Hello World, this is Sobaki Ademola with HNGi7 ID HNG-00190  using javascript for stage 2 task",
            "name": "sobaki ademola",
            "id": "HNG-00190",
            "email": "demolasobaki@gmail.com",
            "language": "javascript",
            "status": "pass"
            },
            {
            "file": "sodiq-oyedotun.php",
            "output": "Hello World, this is Sodiq Oyedotun with HNGi7 ID HNG-05622  using PHP for stage 2 task.",
            "name": "sodiq oyedotun",
            "id": "HNG-05622",
            "email": "oyedotunsodiq045@gmail.com",
            "language": "PHP",
            "status": "pass"
            },
            {
            "file": "sola-agboola.php",
            "output": "Hello World, this is Agboola Sola with HNGi7 ID HNG-04047  using PHP for stage 2 task",
            "name": "sola agboola",
            "id": "HNG-04047",
            "email": "agboolasola6@gmail.com",
            "language": "PHP",
            "status": "pass"
            },
            {
            "file": "somtochukwu-onoh.js",
            "output": "Hello World, this is Somtochukwu Onoh with HNGi7 ID HNG-02984  using JavaScript for stage 2 task",
            "name": "somtochukwu onoh",
            "id": "HNG-02984",
            "email": "onohsomtochukwubasil@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "sunday-orimoyegun.js",
            "output": "Hello World, this is Orimoyegun Sunday with HNGi7 ID HNG-01400  using Javascript for stage 2 task",
            "name": "sunday orimoyegun",
            "id": "HNG-01400",
            "email": "orimoyegun.sunday@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "susan-wangari.js",
            "output": "Hello World, this is Susan Wangari with HNGi7 ID HNG-02129  using JavaScript for stage 2 task",
            "name": "susan wangari",
            "id": "HNG-02129",
            "email": "susanwangari810@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "suyom-rolandjethro.js",
            "output": "Hello World, this is Roland Jethro Suyom with HNGi7 ID HNG-01426  using JavaScript for stage 2 task",
            "name": "suyom rolandjethro",
            "id": "HNG-01426",
            "email": "rolandjethrosuyom@yahoo.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "sylvanus-jerome.php",
            "output": "using PHP for stage 2 task&quot;;",
            "name": "sylvanus jerome",
            "id": "null",
            "email": "",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "thaddeus-ojike.py",
            "output": "Hello World, this is Thaddeus Ojike with HNGi7 ID HNG-04124  using Python for stage 2 task",
            "name": "thaddeus ojike",
            "id": "HNG-04124",
            "email": "thaddeusojike@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "thankgod-eboreime.js",
            "output": "Hello World, this is Eboreime ThankGod with HNGI7 ID 02109 and  using javascript for stage 2 task",
            "name": "thankgod eboreime",
            "id": "null",
            "email": "eboreimethankgod@gmail.com",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "the_algorithmist.py",
            "output": "Hello World, this is Erastus Amunwe with HNGi7 ID HNG-00762  using Python for stage 2 task",
            "name": "the_algorithmist",
            "id": "HNG-00762",
            "email": "eneamunwe@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "tobiloba-olugbemi.js",
            "output": "Hello world, this is Tobiloba Olugbemi with HNGi7 ID HNG-03473  using JavaScript for stage 2 task",
            "name": "tobiloba olugbemi",
            "id": "HNG-03473",
            "email": "tobilobaolugbemi@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "tola-shobowale.js",
            "output": "Hello World, this is Tola Shobowale with HNGi7 ID HNG 00272  using javascript for stage 2 task",
            "name": "tola shobowale",
            "id": "null",
            "email": "shobowaletola@gmail.com",
            "language": "null",
            "status": "fail"
            },
            {
            "file": "toluwaleke-ogidan.py",
            "output": "Hello world, this is ogidan toluwaleke with HNGi7 ID HNG-03053  using python for stage 2 task",
            "name": "toluwaleke ogidan",
            "id": "HNG-03053",
            "email": "toluwalekeogidan@gmail.com",
            "language": "python",
            "status": "pass"
            },
            {
            "file": "uchenna-ugwumadu.php",
            "output": "Hello World, this is Uchenna Ugwumadu with HNGi7 ID HNG-02970  using PHP for stage 2 task.",
            "name": "uchenna ugwumadu",
            "id": "HNG-02970",
            "email": "josebright29@gmail.com",
            "language": "PHP",
            "status": "pass"
            },
            {
            "file": "ugochukwu-chigbata.php",
            "output": "Hello world, this is Ugochukwu Chigbata with HNGi7 ID HNG-04221  using PHP for stage 2 task",
            "name": "ugochukwu chigbata",
            "id": "HNG-04221",
            "email": "Princeugo.remix@gmail.com",
            "language": "PHP",
            "status": "pass"
            },
            {
            "file": "ugonna-erondu.py",
            "output": "Hello World!, this is Ugonna Erondu with HNGi7 ID HNG-00761  using Python for Stage 2 task",
            "name": "ugonna erondu",
            "id": "HNG-00761",
            "email": "kelvinpiano@gmail.com",
            "language": "Python",
            "status": "pass"
            },
            {
            "file": "umoren-samuel.js",
            "output": "Hello World, this is Umoren Samuel with HNGi7 ID HNG-04290  using JavaScript for stage 2 task",
            "name": "umoren samuel",
            "id": "HNG-04290",
            "email": "samuelumoren365@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "victor-ojewale.js",
            "output": "Hello World, this is Ojewale Victor with HNGi7 ID HNG-02963  using JavaScript for stage 2 task",
            "name": "victor ojewale",
            "id": "HNG-02963",
            "email": "vikingdavid41@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "victor-okeke.js",
            "output": "Hello World, this is Victor Okeke with HNGi7 ID HNG-03505  using Javascript for stage 2 task",
            "name": "victor okeke",
            "id": "HNG-03505",
            "email": "victornonso44@gmail.com",
            "language": "Javascript",
            "status": "pass"
            },
            {
            "file": "victory-ndukwu.js",
            "output": "Hello World, this is Victory Ndukwu with HNGi7 ID HNG-01687  using JavaScript for stage 2 task",
            "name": "victory ndukwu",
            "id": "HNG-01687",
            "email": "victoryndukwu7@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "wisdom-ojiakor.js",
            "output": "Hello world, this is Wisdom Ojiakor with HNGi7 ID HNG-01979  using JavaScript for stage 2 task",
            "name": "wisdom ojiakor",
            "id": "HNG-01979",
            "email": "wojiakor@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "yezid-olanase.php",
            "output": "Hello World, this is Yezid Olanase with HNGi7 ID HNG-01607  using PHP for stage 2 task.",
            "name": "yezid olanase",
            "id": "HNG-01607",
            "email": "olanaseyezid@gmail.com",
            "language": "PHP",
            "status": "pass"
            },
            {
            "file": "yusuf-abdulrahman.js",
            "output": "Hello World, this is Yusuf Abdulrahman with HNGi7 ID HNG-04927  using JavaScript for stage 2 task&quot;",
            "name": "yusuf abdulrahman",
            "id": "HNG-04927",
            "email": "aabdulrahmanyusuf@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            },
            {
            "file": "yusuf-kehinde.js",
            "output": "Hello World, this is Yusuf Kehinde Hussein with HNGi7 ID HNG-02156  using JavaScript for stage 2 task&quot;",
            "name": "yusuf kehinde",
            "id": "HNG-02156",
            "email": "yusufkehinde11@gmail.com",
            "language": "JavaScript",
            "status": "pass"
            }
            ]';

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

                    // $arr['emails'][] = $email;
 
                    $slack_id =  $user->slack_id;
                    Slack::removeFromChannel($slack_id, 1);
                    Slack::addToChannel($slack_id, 2);
                    $user->stage = 2;
                    $user->save();
                }else{
                    continue;
                }
            }else{
                $failed_submissions++;
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

    