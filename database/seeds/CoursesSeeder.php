<?php

use Illuminate\Database\Seeder;
use App\Course;

class CoursesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Course::create([
            'track_id' => 1,
            'name' => 'PHP',
            'description' => 'Backend'
        ]);
    }
}
