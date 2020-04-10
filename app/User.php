<?php

namespace App;

use Cache;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use DB;
use Carbon\Carbon;


class User extends Authenticatable
{
    use HasApiTokens, Notifiable;
    use HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'firstname', 'lastname', 'username', 'email', 'gender', 'password', 'location', 'stack', 'token', 'slack_id'
    ];

    protected $guard_name = 'api';

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    protected static function boot()
    {
        //
        parent::boot();

        static::created(
            function ($user){
                $user->profile()->create([
                    'bio' => 'Welcome to Start.Ng.',
                ]);
            }
        );
    }

    public function tracks()
    {
        return $this->belongsToMany('App\Track', 'track_users')->withTimestamps();
    }

    /**
     * The teams that belong a user belongs to.
     */
    public function teams()
    {
        return $this->belongsToMany('App\Team')->withTimestamps();
    }

    public function courses()
    {
        return $this->belongsToMany('App\Course', 'course_user')->withTimestamps();
    }

    public function profile()
    {
        //
        return $this->hasOne('App\Profile');
    }

    public function status()
    {
        return Cache::has('online' . $this->id);

    }
     
    public function activities()
    {
        return $this->hasMany('App\Activity');
    }


    public function probation()
    {
        return $this->hasOne(Probation::class);
    }
    
    public function submissions()
    {
        return $this->hasMany('App\TaskSubmission');
    }

    public function totalScore(){
        $db = DB::table('task_submissions')->where('user_id', $this->id)->select('grade_score')->get();
        $score = 0;
        foreach($db as $s){
            $score += $s->grade_score;
        }
        return $score;
    }

    public function totalScoreForWeek($week = 0){
        // $internship_start_date = env('PROGRAMME_START_DATE', '2020-03-01 12:00:00');
        // $days = $week * 7; 
        // $formattedDate = $days == 0 ? $internship_start_date : Carbon::now()->addDay($days);

        $db = DB::table('task_submissions')
            ->where('user_id', $this->id)
            // ->whereDate('created_at', '>=', $formattedDate)
            ->select('grade_score')
            ->get();
            
        $score = 0;
        foreach($db as $s){
            $score += $s->grade_score;
        }
        return $score;
    }

}
