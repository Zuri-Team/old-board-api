<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
 */

Route::get('slacks/test/{prev}/{next}', 'SlackController@show');
Route::post('slacks/verify','SlackController@verify_user');
Route::post('slacks/profile','SlackController@slack_user_profile');

Route::post('login', 'AuthController@login');
Route::post('register', 'AuthController@register');
Route::get('/verify/{token}', 'AuthController@verify')->name('verify');

Route::post('/password/forgot', 'AuthController@requestReset');
Route::post('/password/reset', 'AuthController@resetPassword');
Route::get('/test_mail', 'AuthController@testMail');
//testMail

Route::get('profile/{user}', 'ProfileController@index');

//Status
Route::get('status', 'StatusController@status');


//get all tracks without signing in
Route::get('track/all', 'TrackController@all');

Route::get('/stats/summary', 'StatsController@summary');
Route::group(['middleware' => 'auth:api', 'throttle:60,1'], function () {

    //User Profile Routes
    Route::group(['prefix' => 'user-profile'], function () {
        Route::get('/{user}', 'UserProfileController@index');
        Route::get('/{user}/track', 'UserProfileController@user_tracks');
        Route::put('/promote/{user}', 'UserProfileController@promote');
        Route::put('/demote/{user}', 'UserProfileController@demote');
        Route::put('/update-stage/{user}', 'UserProfileController@update_stage');
        Route::put('/deactivate/{user}', 'UserProfileController@deactivate');
        Route::put('/activate/{user}', 'UserProfileController@activate');
        Route::put('/make-admin/{user}', 'UserProfileController@make_admin');
        Route::put('/remove-admin/{user}', 'UserProfileController@remove_admin');
        Route::delete('/delete/{user}', 'UserProfileController@destroy');
    });

    Route::resources('/courses', 'CoursesController');
    Route::group(['prefix' => 'course-registration'], function(){
        Route::post('/{user}/{track}', 'CourseregistrationsController@store');
        Route::put('/{user}/{track}/{id}', 'CourseregistrationsController@update');
        Route::post('/{user_id}/{track}/{id}/course-reg-status', 'CourseregistrationsController@course_reg_status');
        Route::get('/{user}/{track}/{id}', 'Courseregistrations@show');
    });

        
    //Activity Routes
    Route::group(['prefix' => 'activity'], function () {
        Route::get('/all', 'ActivityController@get_all_activities');
        Route::get('/interns', 'ActivityController@get_all_intern_activities');
        Route::get('/admins', 'ActivityController@get_all_admin_activities');
        Route::get('/search/{query}', 'ActivityController@search_all_logs');


        // Route::get('/search/interns/{query}', 'ActivityController@search_all_intern_logs');
        // Route::get('/search/admins/{query}', 'ActivityController@search_all_admin_logs');
    });

    Route::group(['prefix' => 'post-comment'], function() {
        Route::get('/{id}/comments', 'PostCommentController@retrieve_post_comments');
        Route::post('/{id}/comment', 'PostCommentController@user_post_comment');
        Route::put('/comment/{id}', 'PostCommentController@update_user_comment');
        Route::delete('/comment/{id}', 'PostCommentController@delete_user_comment');
    });

    //Exports Routes
    Route::group(['prefix' => 'exports'], function() {
        Route::get('/interns', 'ExportController@interns');
        Route::get('/admins', 'ExportController@admins');
        Route::get('/active', 'ExportController@active_interns');
        Route::get('/stage/{stage}', 'ExportController@stage');
        Route::get('/track/{id}', 'ExportController@track');
        Route::get('/team/{id}', 'ExportController@team');
        Route::get('/submissions/{id}', 'ExportController@task_submission');
    });

    //Track Request routes
    Route::group(['prefix' => 'track-requests'], function() {
    Route::get('/all', 'TrackRequestController@all');
    Route::get('/request-count', 'TrackRequestController@get_request_count');
    Route::post('/send-request', 'TrackRequestController@request');
    Route::put('/accept/{id}', 'TrackRequestController@accept');
    Route::delete('/reject/{id}', 'TrackRequestController@reject');
    });

    
//stat
Route::get('/stats/dashboard', 'StatsController@dashboard');
Route::get('/interns', 'InternsController@get_all_interns');
Route::get('/admins', 'InternsController@get_all_admins');
Route::get('/user/search/{query}', 'InternsController@search_users');
Route::delete('intern/delete/{id}', 'InternsController@destroy');

    Route::post('/password/update', 'AuthController@updatePassword');
    Route::post('/logout', 'AuthController@logout');
    Route::get('/clear_session', 'AuthController@clear_session');

    Route::resource('categories', 'CategoriesController');
    Route::post('categories/update/{id}', 'CategoriesController@updateCategory');

    Route::resource('submissions', 'TaskSubmissionController');
    Route::post('submit', 'TaskSubmissionController@submit');
    Route::post('task/{id}/submissions/grade', 'TaskSubmissionController@grade_task_for_interns');
    Route::post('user/task/{id}/', 'TaskSubmissionController@grade_intern_task');
    Route::get('user/{user}/task/{id}/', 'TaskSubmissionController@intern_view_task_grade');
    Route::get('task/{id}/intern/grades', 'TaskSubmissionController@intern_view_task_grades');
    Route::get('task/{id}/grades', 'TaskSubmissionController@view_all_intern_grades');
    Route::get('task/{id}/submissions', 'TaskSubmissionController@admin_retrieve_interns_submission');

    Route::get('task/{id}/intern_submissions', 'TaskSubmissionController@retrieve_interns_submission');
    Route::delete('submissions/task/{taskId}', 'TaskSubmissionController@delete_interns_submissions');
    Route::delete('task/{taskId}', 'TaskSubmissionController@delete_interns_submissions');
    Route::delete('submission', 'TaskSubmissionController@delete_all_submission');

    Route::post('track/create', 'TrackController@create_track');
    Route::put('track/edit', 'TrackController@edit_track');
    Route::delete('track/delete', 'TrackController@delete_track');
    Route::post('track/join', 'TrackController@join_track');
    Route::post('track/users/add', 'TrackController@add_user_to_track');
    Route::delete('track/users/remove', 'TrackController@remove_user_from_track');

    Route::get('track/list', 'TrackController@get_all_tracks');
    Route::get('track/{track}', 'TrackController@get_track_by_id');
    Route::get('users/track/{id}/list', 'TrackController@get_all_users_in_track');

    Route::get('tasks/active/', 'TasksController@getActiveTasks');
    Route::resource('tasks', 'TasksController'); #URL for tasks

    Route::get('track/{id}/tasks', 'TasksController@view_track_task');
    Route::get('task/{id}', 'TasksController@view_task');
    Route::get('user/task/', 'TasksController@intern_view_track_task');

//    Route::get('track/{id}/tasks', 'TasksController@viewTracktask');
//    Route::get('tasks/{id}', 'TasksController@viewTask');

    Route::put('tasks/changestatus/{id}', 'TasksController@changeTaskStatus');

    Route::resource('teams', 'TeamController');
    Route::post('teams/add-member', 'TeamController@addMember');
    Route::post('teams/remove-member', 'TeamController@removeMember');
    Route::get('teams/members/{id}', 'TeamController@viewMembers');


    Route::resource('posts', 'PostsController');
    Route::get('posts/view/{post}', 'PostsController@view_a_post');
    Route::get('categories/posts/{id}', 'PostsController@view_posts_in_category');

    Route::post('profile/{user}/edit', 'ProfileController@update');
    Route::post('profile/{user}/upload', 'ProfileController@upload');

    // Probation Routes
    Route::post('user/probate', 'ProbationController@probate');
    Route::delete('user/unprobate', 'ProbationController@unprobate_by_admin');
    Route::get('probation/status/{user}', 'ProbationController@is_on_onprobation');
    Route::get('probation/all', 'ProbationController@list_probations');
    
    // NOTIFICATION
    Route::get('notifications', 'NotificationController@index');
    Route::delete('notifications', 'NotificationController@destroy');
    Route::post('notifications/markasread', 'NotificationController@markAsRead');
    Route::post('notifications/read', 'NotificationController@markOneAsRead');
    Route::get('notifications/notification_count', 'NotificationController@notification_count');
    
});


Route::get('leaderboard/{week}', 'LeaderboardController@viewAll');