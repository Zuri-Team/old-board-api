<?php

use Illuminate\Database\Seeder;

class SubmissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory('App\TaskSubmission', 1000)->create();
    }
}
