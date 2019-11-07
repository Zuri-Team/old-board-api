<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Exports\InternsExport;
use App\Exports\AdminsExport;
use App\Exports\StageExport;
use App\Exports\TeamExport;
use App\Exports\TrackExport;
use App\Exports\TaskSubmissionExport;
use App\Exports\ActiveInternsExport;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
    
    public function interns()
    {
        return Excel::download(new InternsExport, 'all_interns.xlsx');
    }

    public function admins()
    {
        return Excel::download(new AdminsExport, 'all_admins.xlsx');
    }

    public function active_interns()
    {
        return Excel::download(new ActiveInternsExport, 'all_interns.xlsx');
    }

    public function stage($stage)
    {
        return Excel::download(new StageExport($stage), 'Stage_'. $stage . 'intenrs.xlsx');
    }

    public function track($id)
    {
        return Excel::download(new TrackExport($id), 'Stage_'. $stage . 'intenrs.xlsx');
    }

    public function team($id)
    {
        return Excel::download(new TeamExport($id), 'Stage_'. $stage . 'intenrs.xlsx');
    }

    public function task_submission($id)
    {
        return Excel::download(new TaskSubmissionExport($id), 'Stage_'. $stage . 'intenrs.xlsx');
    }

    
}
