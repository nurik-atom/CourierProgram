<?php

use App\Http\Controllers\AllfoodController;
use App\Http\Controllers\CashOnHandController;
use App\Http\Controllers\HelpController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\Slots\SlotsAppController;
use App\Http\Controllers\Slots\SlotsSzpController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\PushController;
use App\Http\Controllers\MoneyController;
use App\Http\Controllers\SzpController;

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
Route::post('/sendNotification',[NotificationController::class,'sendNotification']);

Route::post('/getStatusTimeOrder', [OrderController::class, 'getStatusTimeOrder']);
Route::post('/newOrder', [AllfoodController::class, 'newOrder']);
Route::post("/whoIsDriver",[AllfoodController::class, "whoIsDriver"]);

Route::post("/allfood/cancelOrderFromAllfood", [AllfoodController::class, 'cancelOrderFromAllfood']);
Route::post("/allfood/cancelTelOrderFromAllfood", [AllfoodController::class, 'cancelTelOrderFromAllfood']);
Route::post("/allfood/getStatusOrder", [AllfoodController::class, 'getStatusOrder']);


Route::post('/imReady',[UserController::class,'imReady']);
Route::post('/takeOrder',[OrderController::class,'takeOrder']);
Route::post('/poluchilZakaz',[OrderController::class,'poluchilZakaz']);
//Route::post('/courierInCafe',[OrderController::class,'courierInCafe']);
//Route::post('/startDeliveryOrder',[OrderController::class,'startDeliveryOrder']);
//Route::post('/courierAtTheClient',[OrderController::class,'courierAtTheClient']);
Route::post('/finishDeliveryOrder',[OrderController::class,'finishDeliveryOrder']);
Route::post('/refusingOrderReq',[OrderController::class,'refusingOrderReq']);
Route::post('/cancelOrder',[OrderController::class,'cancelOrder']);

Route::post('/order/getQROplatyOrder',[OrderController::class,'getQROplatyOrder']);

Route::post('/not_found_driver_update',[OrderController::class,'managerNotFoundDriver']);

Route::post('/register', [UserController::class, 'register']);
Route::post('/signIn', [UserController::class, 'signIn']);
Route::post('/profile', [UserController::class, 'profile']);
Route::post("/getCashHistory",[CashOnHandController::class, "getCashHistory"]);
Route::post("/getCurrentCash",[CashOnHandController::class, "getCurrentCash"]);
Route::post("/driverReturnCash",[CashOnHandController::class, "driverReturnCash"]);
Route::post('/getMoneyAndOrdersUser', [UserController::class, 'getMoneyAndOrdersUser']);
Route::post('/getDateRangeOrders', [UserController::class, 'getDateRangeOrders']);
Route::post('/getDateRangeStatistica', [UserController::class, 'getDateRangeStatistica']);
Route::post('/getFullDetailsOrder', [UserController::class, 'getFullDetailsOrder']);
Route::post('/getHelpBalancePage', [HelpController::class, 'getHelpBalancePage']);
//sendSms
Route::post('/signStepOne',[UserController::class,'signStepOne']);
//checkSms
Route::post('/signStepTwo',[UserController::class,'signStepTwo']);
Route::post('/successRegistration',[UserController::class,'successRegistration']);
Route::post('/checkUser',[UserController::class,'checkUser']);
Route::post('/checkOrderUser',[OrderController::class,'checkOrderUser']);
Route::post('/checkOrderUser_2',[OrderController::class,'checkOrderUser_2']);

Route::post('/test_graphhopper',[AllfoodController::class,'test_graphhopper']);
Route::post('/getOrderDriverPosition',[AllfoodController::class,'getOrderDriverPosition']);



Route::post('/editDataUser',[UserController::class,'editDataUser']);
Route::post('/citiesForReg',[UserController::class,'citiesForReg']);

Route::post('/getStatusUser',[UserController::class,'getStatusUser']);
Route::post('/insertStateUser',[UserController::class,'insertStateUser']);
Route::post('/getStateUser',[UserController::class,'getStateUser']);
Route::post('/getDataUser',[UserController::class,'getDataUser']);
Route::post('/editTokenUser',[UserController::class,'editTokenUser']);
Route::post('/changePhone',[UserController::class,'changePhone']);
Route::post('/changePhoneStepTwo',[UserController::class,'changePhoneStepTwo']);
Route::post('/changeType',[UserController::class,'changeType']);
Route::post('/changeNames',[UserController::class,'changeNames']);
//TEST ROUTES
Route::post('/setStatusUser',[UserController::class,'setStatusUser']);
Route::post('/deleteUser',[UserController::class,'deleteUser']);

Route::post('/checkSMS',[UserController::class,'inputSmsCode']);
Route::post('/setUserGeoPosition',[UserController::class,'setUserGeoPosition']);
Route::post('/getVersionApp',[UserController::class,'getVersionApp']);

Route::get("/searchStart",[SearchController::class, 'searchNewOrder']);
Route::get("/fallBehindOrders",[SearchController::class, 'fallBehindOrders']);
Route::get("/insertTestGeoPositon",[SearchController::class, 'insertTestGeoPositon']);

Route::post("/addComment",[RatingController::class, "addComment"]);
Route::post("/getRatingUser",[RatingController::class, "getRatingUser"]);

//!New Functions version2
Route::post("/getBalanceHistoryUser",[UserController::class, "getBalanceHistoryUser"]);


//!SLOTS
Route::post("/slots/getAllSlotsFromApp",[SlotsAppController::class, "getAllSlotsFromApp"]);
Route::post("/slots/subsToSlotFromApp",[SlotsAppController::class, "subsToSlotFromApp"]);
Route::post("/slots/unSubsFromSlotFromApp",[SlotsAppController::class, "unSubsFromSlotFromApp"]);

//! Potom alyp tastau kerek
Route::post("/slots/sendNotifAboutSlotStartToDriver",[\App\Http\Controllers\Slots\SlotsBackController::class, "sendNotifAboutSlotStartToDriver"]);


//!
Route::post("/financy/getFinancyFirstEkranData",[\App\Http\Controllers\FinancyEkranController::class, "getFinancyFirstEkranData"]);
Route::post("/financy/getVyplatyDataById",[\App\Http\Controllers\FinancyEkranController::class, "getVyplatyDataById"]);
Route::post("/financy/getCashDetailsEkran",[\App\Http\Controllers\FinancyEkranController::class, "getCashDetailsEkran"]);
Route::post("/financy/getAllVyplaty",[\App\Http\Controllers\FinancyEkranController::class, "getAllVyplaty"]);

/**Tol'ko dlya Nurika */
Route::post("/financy/groupBalanceByIdOrder",[\App\Http\Controllers\FinancyEkranController::class, "groupBalanceByIdOrder"]);

/** Statistika User Controller*/
Route::post("/statistika/getStatistikaEkranFirstReq",[\App\Http\Controllers\StatistikaUserController::class, "getStatistikaEkranFirstReq"]);
Route::post("/statistika/getStatistikaFromTo",[\App\Http\Controllers\StatistikaUserController::class, "getStatistikaFromTo"]);

/** Notif Message Look Like*/
Route::post("/notif/getNotifMessage",[\App\Http\Controllers\NotificationController::class, "getNotifMessage"]);

Route::post("/szp/getAllDrivers",[SzpController::class, "getAllDrivers"]);
Route::post("/szp/getOneDriverDetails",[SzpController::class, "getOneDriverDetails"]);
Route::post("/szp/changeDriverStatusSzp",[SzpController::class, "changeDriverStatusSzp"]);
Route::post("/szp/getDriversGeo",[SzpController::class, "getDriversGeo"]);
Route::post("/szp/getOneDriverGeo",[SzpController::class, "getOneDriverGeo"]);
Route::post("/szp/getCommentsForSzp",[SzpController::class, "getCommentsForSzp"]);


Route::post("/szp/getDriversForNaznachenieZakaza",[SzpController::class, "getDriversForNaznachenieZakaza"]);
Route::post("/szp/naznachitZakaz",[SzpController::class, "naznachitZakaz"]);
Route::post("/szp/driverReturnCash",[SzpController::class, "driverReturnCash"]);
Route::post("/szp/izmenitSposobOplaty ",[SzpController::class, "izmenitSposobOplaty"]);
Route::post("/szp/getDriverCashHistoryForSzp",[SzpController::class, "getDriverCashHistoryForSzp"]);
Route::post("/szp/getDriverCashTotal",[SzpController::class, "getDriverCashTotal"]);
Route::post("/szp/getDriverCashTotalNew",[SzpController::class, "getDriverCashTotalNew"]);
Route::post("/szp/addZapisTranzakciaDriver",[SzpController::class, "addZapisTranzakciaDriver"]);
Route::post("/szp/addZapisBalanceDriver",[SzpController::class, "addZapisBalanceDriver"]);
Route::post("/szp/updateAllSummaDriverSZP",[SzpController::class, "updateAllSummaDriverSZP"]);
Route::post("/szp/getOrdersLast24Hour",[SzpController::class, "getOrdersLast24Hour"]);
Route::post("/szp/getWhereOrderDriver",[SzpController::class, "getWhereOrderDriver"]);
Route::post("/szp/getOrderStatusHistory",[SzpController::class, "getOrderStatusHistory"]);
Route::post("/szp/izmenitStatusDriverOrder",[SzpController::class, "izmenitStatusDriverOrder"]);
Route::post("/szp/getAllActiveOrders",[SzpController::class, "getAllActiveOrders"]);
Route::post("/szp/changeDriverStateSzp",[SzpController::class, "changeDriverStateSzp"]);
Route::post("/szp/getCountActiveOrders",[SzpController::class, "getCountActiveOrders"]);
Route::post("/szp/addTranzakciaBalance",[SzpController::class, "addTranzakciaBalance"]);
Route::post("/szp/getDriverReportForDate",[SzpController::class, "getDriverReportForDate"]);
Route::post("/szp/start_statistica_new",[SzpController::class, "statisticaOrders"]);
Route::post("/szp/getStatussTelOrders",[SzpController::class, "getStatussTelOrders"]);


Route::post("/szp/testStaticFunctions",[UserController::class, "testStaticFunctions"]);



Route::post("/szp/updateOrCreateSlotsFromSzp",[SlotsSzpController::class, "updateOrCreateSlotsFromSzp"]);
Route::post("/szp/getSlotsFromSzp",[SlotsSzpController::class, "getSlotsFromSzp"]);
