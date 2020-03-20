<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */
use App\User;
use App\TaskSubmission;
use Faker\Generator as Faker;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| This directory should contain each of the model factory definitions for
| your application. Factories provide a convenient way to generate new
| model instances for testing / seeding your application's database.
|
*/

$factory->define(User::class, function (Faker $faker) {

    return [
            'firstname' => $faker->name,
            'lastname' => $faker->name . ' Full',
            'username' => $faker->userName,
            'email' => $faker->unique()->safeEmail,
            'stack' => '',
            'email_verified_at' => now(),
            'location' => '',
	        'slack_id' => '',
	        'gender' => 'Male',
            'password' => bcrypt('[password]'),
            'role' => 'intern',
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ];
    // return 
});

$autoIncrement = autoIncrement();

$factory->define(TaskSubmission::class, function (Faker $faker) use ($autoIncrement) {
    // $autoIncrement->next();

    static $number = 5;
    return [
        'user_id' => $number++, 
        'task_id' => 7,
        'submission_link' => 'hhtps://jude.com', 
        'comment' => 'test', 
        'grade_score' => 30, 
        'is_submitted' => 1, 
        'is_graded' => 0
    ];
});

function autoIncrement()
{
    for ($i = 4; $i < 1000; $i++) {
        yield $i;
    }
}
