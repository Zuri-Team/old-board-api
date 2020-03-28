<?php

namespace App\Imports;

use App\User;
use Maatwebsite\Excel\Concerns\ToModel;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use App\Course;
use App\CourseUser;


class CourseUserImports implements ToCollection, WithHeadingRow
{
    protected $course_id;
    public function __construct($course_id)
    {
        $this->course_id = $course_id;
    }

    public function collection(Collection $rows)
    {

        /**
         * Row 1: slack Username
         * Row 2: StartNg Email
         * Row 3: Unique Username
         * Row 4: Unique Email *
         */

        foreach ($rows as $row) {
            $email = $row['uniquemail'];
            $user = User::where('email', $email)->first();
            if($user){
                CourseUser::createOrUpdate([
                    'user_id' => $user->id,
                    'course_id' => $this->course_id
                ]);
            }else{
                continue;
            }
        }
            
    }
}
