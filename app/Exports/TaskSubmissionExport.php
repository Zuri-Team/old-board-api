<?php

namespace App\Exports;

use App\TaskSumission;
use Maatwebsite\Excel\Concerns\FromCollection;

class TaskSubmissionExport implements FromCollection
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return TaskSumission::all();
    }
}
