<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Course;
use App\Imports\CourseUserImports;

use App\Http\Classes\ResponseTrait;
use Illuminate\Http\JsonResponse;
use App\User;
use Carbon\Carbon;
use App\Http\Classes\ActivityTrait;

class CourseController extends Controller
{
    use ResponseTrait;
    use ActivityTrait;

    public function importCourse(Request $request)
    {
        $courseId = $request->course_id;
        $course = Course::find($courseId);

        if($course){
            if($request->hasFile('sheet')){

                $sheet = request()->file('sheet')->getRealPath();
                $import = Excel::import(new CourseUserImports($courseId), $request->file('sheet'));
    
                if($import){
                return $this->sendSuccess('Course Sheet successfully Imported', 200);
    
                }else{
                    return $this->sendError('Could not Import Course Sheet', 500, []);
                }
            }
            return $this->sendError('No Sheet Uploaded', 400, []);
        }
        return $this->sendError('Course Doesnt exist', 404, []);
        
    }

}
