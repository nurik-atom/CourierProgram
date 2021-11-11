<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\RatingController;
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

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});


Route::post('/newOrder', [OrderController::class, 'newOrder']);
Route::get('/testCourier',[OrderController::class,'testCourier']);
Route::post('/cancelOrder',[OrderController::class,'cancelOrder']);
Route::post('/takeOrder',[OrderController::class,'takeOrder']);


Route::post('/register', [UserController::class, 'register']);
Route::post('/signIn', [UserController::class, 'signIn']);
Route::post('/profile', [UserController::class, 'profile']);
//sendSms
Route::post('/signStepOne',[UserController::class,'signStepOne']);
//checkSms
Route::post('/signStepTwo',[UserController::class,'signStepTwo']);
Route::post('/editDataUser',[UserController::class,'editDataUser']);
Route::post('/getStatusUser',[UserController::class,'getStatusUser']);
Route::post('/insertStateUser',[UserController::class,'insertStateUser']);
Route::post('/getStateUser',[UserController::class,'getStateUser']);
Route::post('/getDataUser',[UserController::class,'getDataUser']);
Route::post('/editTokenUser',[UserController::class,'editTokenUser']);
//TEST ROUTES
Route::post('/setStatusUser',[UserController::class,'setStatusUser']);
Route::post('/deleteUser',[UserController::class,'deleteUser']);

Route::post('/checkSMS',[UserController::class,'inputSmsCode']);
Route::post('/setUserGeoPosition',[UserController::class,'setUserGeoPosition']);

Route::get("/searchStart",[SearchController::class, 'searchNewOrder']);
Route::get("/insertTestGeoPositon",[SearchController::class, 'insertTestGeoPositon']);

Route::post("/addComment",[RatingController::class, "addComment"]);
Route::post("/getRatingUser",[RatingController::class, "getRatingUser"]);

