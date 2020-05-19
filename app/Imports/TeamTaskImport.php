<?php

namespace App\Imports;

use App\User;
use Maatwebsite\Excel\Concerns\ToModel;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use App\Course;
use App\CourseUser;
use App\TaskSubmission;


class TeamTaskImport implements ToCollection, WithHeadingRow
{

    public function collection(Collection $rows)
    {

        foreach ($rows as $row) {
            $email = $row['email'];

            $user = User::where('email', $email)->where('stage', 5)->first();
            if($user){
                $res = new TaskSubmission();
                $res->user_id = $user->id;
                $res->task_id = 152;
                $res->submission_link = ' ';
                $res->grade_score = 2;
                $res->comment = ' ';
                $res->is_submitted = true;
                $res->is_graded = true;
                $res->graded_by = 714;
                $res->save();
            }else{
                continue;
            }
        }
            
    }
}
