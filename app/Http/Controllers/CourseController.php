<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Course;
use App\Imports\CourseUserImports;

use App\Http\Classes\ResponseTrait;
use Illuminate\Http\JsonResponse;
use App\User;
use App\Track;
use Auth;
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

     public function createCourse(Request $request){

            $trackId = $request->track_id;
            $track = Track::find($trackId);

            if(!$track){
                return $this->sendError('Track does not exist', 404, []);
            }
            
            $course = $request->all();
                if(Course::create($course)){
                    $course_name = $course['name'];
                    return $this->sendError('Course creation successful', 200, $course);
                } 
                return $this->sendError('Course creation failed', 500, []);
        }

        public function allCourses(){

            $courses = Course::all();
            return $this->sendError('All courses', 200, $courses);
        }

        //getInterns

        public function getInterns($id){

            $course = Course::find($id);

            if(!$course){
                return $this->sendError('Track does not exist', 404, []);
            }
            $interns = Course::where('id', $id)->with('interns');
            return $this->sendError('All Interns in a course', 200, $interns);
        }


}
