<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
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
Route::get('/', function () {
    return view('welcome');
});

Route::group([
    'middleware' => 'api',
    'namespace' => 'App\Http\Controllers',
    'prefix' => 'auth'
], function ($router) {
    Route::post('login', 'AuthController@login');
    Route::post('register/temp-user', 'AuthController@saveTempUser');
    Route::post('register/user', 'AuthController@saveUserToMain');
    Route::post('otp-verification', 'AuthController@otpVerificationCheck');
    Route::post('change-password', 'AuthController@changePassword');
    Route::post('forgot-password', 'AuthController@forgotPasswordOtp');
    Route::post('forgot-password-otp-verification', 'AuthController@forgotPassword');
    Route::post('resend-otp', 'AuthController@resendOTP');
    Route::post('logout', 'AuthController@logout');
    Route::get('refresh-token', 'AuthController@refresh');
    Route::get('user-profile', 'AuthController@userProfile');
    Route::get('get-user-profile/{user_uuid}', 'AuthController@getUserProfile');
    Route::post('update-profile', 'AuthController@updateProfile');

    Route::get('get-platforms', 'PlatformController@getPlatforms');
    Route::get('get-customised-platforms', 'PlatformController@getUserAddedPlatforms');
    Route::get('get-media_types', 'PlatformController@getMediaTypes');
    Route::get('get-all-platforms', 'PlatformController@getAllPlatforms');
    Route::post('add-platforms', 'PlatformController@addPlatforms');
    Route::post('edit-platform', 'PlatformController@editPlatform');
    Route::post('remove-platform', 'PlatformController@removePlatform');
    Route::post('remove-user-added-platform', 'PlatformController@removeUserAddedPlatform');
    Route::post('add-customised-platforms', 'PlatformController@addCustomisedPlatforms');
    Route::post('edit-customised-platform', 'PlatformController@editCustomisedPlatform');

    Route::post('on-platform-tap', 'PlatformController@postTapPlatform');

    Route::post('add-bundles', 'BundleController@addBundles');

    // Route::post('send-mail', 'AuthController@sendMail');
});

Route::fallback(function(){
    return response()->json([
        'message' => 'Page Not Found.'
    ], 404);
});
