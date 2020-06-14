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
use App\Slack;


class TeamTaskImport implements ToCollection, WithHeadingRow
{

    public function collection(Collection $rows)
    {

        foreach ($rows as $row) {
            $email = $row['email'];

            $email = str_replace(' ', '', $email);
            $user = User::where('email', $email)->first();

            if(!empty($user)){
                $slack_id =  $user->slack_id;
                // Slack::removeFromChannel($slack_id, 2);
                // Slack::addToChannel($slack_id, 3);
                $user->stage = 3;
                $user->save();
            }else{
                continue;
            }
        }
            
    }
}
