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
Route::resource('submissions', 'TaskSubmissionController');

Route::group(['middleware' => 'auth:api'], function () {
    Route::resource('categories', 'CategoriesController');
    Route::resource('teams', 'TeamController');
    Route::post('teams/add-member', 'TeamController@addMember');
    Route::post('teams/remove-member', 'TeamController@removeMember');
    Route::get('teams/members/{id}', 'TeamController@viewMembers');
});