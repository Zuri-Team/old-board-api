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

Route::post('login', 'AuthController@login');
Route::post('register', 'AuthController@register');

Route::post('/password/forgot', 'AuthController@requestReset');
Route::get('/password/reset/{token}', 'AuthController@findResetToken');
Route::post('/password/reset', 'AuthController@resetPassword');

Route::group(['middleware' => 'auth:api'], function () {
    
    Route::post('/password/update', 'AuthController@updatePassword');
    Route::post('/logout', 'AuthController@logout');
    Route::get('/clear_session', 'AuthController@clear_session');
    
    Route::resource('categories', 'CategoriesController');

    Route::post('track/create', 'TrackController@create_track');
    Route::put('track/edit', 'TrackController@edit_track');
    Route::delete('track/delete', 'TrackController@delete_track');
    Route::post('track/join', 'TrackController@join_track');
    Route::post('track/users/add', 'TrackController@add_user_to_track');
    Route::delete('track/users/remove', 'TrackController@remove_user_from_track');
    
    Route::get('track/list', 'TrackController@get_all_tracks');
    Route::get('track/{track}', 'TrackController@get_track_by_id');
    Route::get('users/track/{id}/list', 'TrackController@get_all_users_in_track');
    // Route::get('user/track/{id}', 'TrackController@get_track_by_user');
    // Route::get('track/user/{id}', 'TrackController@get_user_by_track');
});

