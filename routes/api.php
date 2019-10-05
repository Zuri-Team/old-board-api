<?php

use Illuminate\Http\Request;

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

// Route::post('login', 'AuthController@login');
// Route::post('register', 'AuthController@register');

// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::group(['middleware' => ['api', 'cors'],'namespace'=>'JWT',], function () {
    
    // User Routes
    Route::post('user/login', 'UserController@login');
    Route::post('user/register', 'UserController@register');
    
    // Admin Routes
    Route::post('admin/login', 'AdminController@login');
    Route::post('admin/register', 'AdminController@register');
}); 