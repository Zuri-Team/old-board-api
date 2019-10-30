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

// Route::routes();

Route::get('/fix', function(){
    DB::table('users')->update(['gender' => 'Male']);

});


// Route::resource('slacks', 'SlackController');
Route::post('slacks/verify','SlackController@verify_user');
Route::post('slacks/profile','SlackController@slack_user_profile');

Route::post('login', 'AuthController@login');
Route::post('register', 'AuthController@register');
Route::get('/verify/{token}', 'AuthController@verify')->name('verify');
Route::post('/password/forgot', 'AuthController@requestReset');
Route::get('/password/reset/{token}', 'AuthController@findResetToken');
Route::post('/password/reset', 'AuthController@resetPassword');

Route::get('profile/{user}', 'ProfileController@index');

//Status
Route::get('status', 'StatusController@status');


//get all tracks without signing in
Route::get('track/all', 'TrackController@all');

Route::get('/stats/summary', 'StatsController@summary');
Route::group(['middleware' => 'auth:api'], function () {

    //User Profile Routes
    Route::group(['prefix' => 'user-profile'], function () {
        Route::get('/{user}', 'UserProfileController@index');
        Route::put('/promote/{user}', 'UserProfileController@promote');
        Route::put('/demote/{user}', 'UserProfileController@demote');
        Route::put('/update-stage/{user}', 'UserProfileController@update_stage');
        Route::put('/deactivate/{user}', 'UserProfileController@deactivate');
        Route::put('/activate/{user}', 'UserProfileController@activate');
        Route::put('/make-admin/{user}', 'UserProfileController@make_admin');
        Route::put('/remove-admin/{user}', 'UserProfileController@remove_admin');
        Route::delete('/delete/{user}', 'UserProfileController@destroy');
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
        Route::get('/post/{id}/comments', 'PostCommentController@retrieve_post_comments');
        Route::post('/post/{id}/comment', 'PostCommentController@user_post_comment');
        Route::put('/post/comment/{id}', 'PostCommentController@update_user_comment');
        Route::delete('/post/comment/{id}', 'PostCommentController@delete_user_comment');
    });

    
//stat
Route::get('/stats/dashboard', 'StatsController@dashboard');
Route::get('/interns', 'InternsController@get_all_interns');
Route::delete('intern/delete/{id}', 'InternsController@destroy');

    Route::post('/password/update', 'AuthController@updatePassword');
    Route::post('/logout', 'AuthController@logout');
    Route::get('/clear_session', 'AuthController@clear_session');

    Route::resource('categories', 'CategoriesController');
    Route::post('categories/update/{id}', 'CategoriesController@updateCategory');

    Route::resource('submissions', 'TaskSubmissionController');
    Route::post('task/{id}/submissions', 'TaskSubmissionController@grade_task_for_interns');
    Route::post('user/task/{id}/', 'TaskSubmissionController@grade_intern_task');
    Route::get('user/{user}/task/{id}/', 'TaskSubmissionController@intern_view_task_grade');
    Route::get('task/{id}/grades', 'TaskSubmissionController@intern_view_task_grades');
    Route::get('task/{id}/submissions/grades', 'TaskSubmissionController@view_all_intern_grades');

    Route::post('track/create', 'TrackController@create_track');
    Route::put('track/edit', 'TrackController@edit_track');
    Route::delete('track/delete', 'TrackController@delete_track');
    Route::post('track/join', 'TrackController@join_track');
    Route::post('track/users/add', 'TrackController@add_user_to_track');
    Route::delete('track/users/remove', 'TrackController@remove_user_from_track');

    Route::get('track/list', 'TrackController@get_all_tracks');
    Route::get('track/{track}', 'TrackController@get_track_by_id');
    Route::get('users/track/{id}/list', 'TrackController@get_all_users_in_track');

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
    Route::get('categories/posts/{id}', 'PostsController@view_posts_in_category');

    Route::post('profile/{user}/edit', 'ProfileController@update');
    Route::post('profile/{user}/upload', 'ProfileController@upload');

    // Probation Routes
    Route::post('user/probate', 'ProbationController@probate');
    Route::delete('user/unprobate', 'ProbationController@unprobate_by_admin');
    Route::get('probation/status/{user}', 'ProbationController@is_on_onprobation');
    Route::get('probations/all', 'ProbationController@list_probations');
    
    // NOTIFICATION
    Route::get('notifications', 'NotificationController@index');
    Route::delete('notifications', 'NotificationController@destroy');
    Route::post('notifications/markasread', 'NotificationController@markAsRead');
    Route::post('notifications/read', 'NotificationController@markOneAsRead');
    Route::get('notifications/notification_count', 'NotificationController@notification_count');
    
});
