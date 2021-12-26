<?php

use App\Http\Controllers\HelpController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\PushController;
use App\Http\Controllers\MoneyController;

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

Route::get("test_curl", [PushController::class, "testCurl"]);
Route::get("test_money", [MoneyController::class, "test_money"]);

Route::get('/testCourier',[OrderController::class,'testCourier']);
Route::get('/prichinyOtmeny',[OrderController::class,'prichinyOtmeny']);
Route::get('/getAllHelpPages',[HelpController::class,'getAllHelpPages']);
Route::post('/getNotifications',[NotificationController::class,'getNotifications']);
Route::post('/getCountNewNotifs',[NotificationController::class,'getCountNewNotifs']);
Route::post('/openNotification',[NotificationController::class,'openNotification']);

Route::post('/getStatusTimeOrder', [OrderController::class, 'getStatusTimeOrder']);
Route::post('/newOrder', [OrderController::class, 'newOrder']);

Route::post('/imReady',[UserController::class,'imReady']);
Route::post('/takeOrder',[OrderController::class,'takeOrder']);
Route::post('/courierInCafe',[OrderController::class,'courierInCafe']);
Route::post('/startDeliveryOrder',[OrderController::class,'startDeliveryOrder']);
Route::post('/courierAtTheClient',[OrderController::class,'courierAtTheClient']);
Route::post('/finishDeliveryOrder',[OrderController::class,'finishDeliveryOrder']);
Route::post('/cancelOrder',[OrderController::class,'cancelOrder']);


Route::post('/register', [UserController::class, 'register']);
Route::post('/signIn', [UserController::class, 'signIn']);
Route::post('/profile', [UserController::class, 'profile']);
Route::post('/getMoneyAndOrdersUser', [UserController::class, 'getMoneyAndOrdersUser']);
//sendSms
Route::post('/signStepOne',[UserController::class,'signStepOne']);
//checkSms
Route::post('/signStepTwo',[UserController::class,'signStepTwo']);
Route::post('/checkUser',[UserController::class,'checkUser']);
Route::post('/checkOrderUser',[OrderController::class,'checkOrderUser']);


Route::post('/editDataUser',[UserController::class,'editDataUser']);

Route::post('/getStatusUser',[UserController::class,'getStatusUser']);
Route::post('/insertStateUser',[UserController::class,'insertStateUser']);
Route::post('/getStateUser',[UserController::class,'getStateUser']);
Route::post('/getDataUser',[UserController::class,'getDataUser']);
Route::post('/editTokenUser',[UserController::class,'editTokenUser']);
Route::post('/changePhone',[UserController::class,'changePhone']);
Route::post('/changeType',[UserController::class,'changeType']);
Route::post('/changeNames',[UserController::class,'changeNames']);
//TEST ROUTES
Route::post('/setStatusUser',[UserController::class,'setStatusUser']);
Route::post('/deleteUser',[UserController::class,'deleteUser']);

Route::post('/checkSMS',[UserController::class,'inputSmsCode']);
Route::post('/setUserGeoPosition',[UserController::class,'setUserGeoPosition']);

Route::get("/searchStart",[SearchController::class, 'searchNewOrder']);
Route::get("/insertTestGeoPositon",[SearchController::class, 'insertTestGeoPositon']);

Route::post("/addComment",[RatingController::class, "addComment"]);
Route::post("/getRatingUser",[RatingController::class, "getRatingUser"]);

