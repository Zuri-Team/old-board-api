<?php

namespace App\Exports;

use App\User;
// use Maatwebsite\Excel\Concerns\FromCollection;
// use Maatwebsite\Excel\Concerns\FromArray;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class StartNGFinalExports implements FromView
{
    public function view(): View
    {
        return view('exports.final', [
            'users' => User::where('stage', 5)->where('role', 'intern')->get()
        ]);
    }
}