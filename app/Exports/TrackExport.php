<?php

namespace App\Exports;

use App\Track;
use Maatwebsite\Excel\Concerns\FromCollection;

class TrackExport implements FromCollection
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return Track::all();
    }
}
