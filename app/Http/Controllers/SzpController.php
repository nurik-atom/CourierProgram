<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderMiniResource;
use App\Http\Resources\OrderResource;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SzpController extends Controller
{

    public function __construct()
    {
        $this->key_szp_allfood = sha1('ALL' . date('Ymd') . 'FOOD_2201');
    }

    public function getAllDrivers(Request $request)
    {
        $pass = $request->input('pass');
        if ($pass != $this->key_szp_allfood) {
            exit('Error Key');
        }

        $users = DB::table('users')
            ->select('id', 'name', 'surname', 'id_city', 'birthday', 'phone', 'type', 'status', 'rating', 'state', 'created_at')
            ->orderByDesc('state')
            ->latest()
            ->get();
        $result['users'] = $users;
        $result['success'] = true;
        return response()->json($result);
    }

    public function getOneDriverDetails(Request $request)
    {
        $pass = $request->input('pass');
        $id_driver = $request->input('id_driver');
        if ($pass != $this->key_szp_allfood) {
            exit('Error Key');
        }

        $user = DB::table('users')
            ->select('id', 'name', 'surname', 'id_city', 'photo', 'birthday', 'phone', 'type', 'status', 'rating', 'state', 'created_at')
            ->where('id', $id_driver)->first();

        $active_order = DB::table('orders')
            ->select('id_allfood', 'from_address', 'to_address', 'cafe_phone', 'cafe_name', 'phone', 'name', 'type', 'status', 'created_at', 'distance', 'duration_sec', 'distance_matrix', 'price_delivery', 'kef',  'needed_sec', 'mode')
            ->whereNotIn('status', ['7', '9'])
            ->where('id_courier', $user->id)
            ->orderByDesc('id')->get();

        $orders = DB::table('orders')
            ->select('id_allfood', 'type', 'cafe_name', 'to_address', 'status', 'created_at', 'distance','distance_matrix', 'price_delivery', 'kef', 'duration_sec', 'needed_sec', 'mode')
            ->where('id_courier', $user->id)
            ->orderByDesc('id')
            ->limit(100)->get();

        $result['user'] = $user;
        $result['active_order'] = $active_order;
        $result['orders'] = $orders;

        $result['success'] = true;

        return response()->json($result);
    }

    public function changeDriverStatusSzp(Request $request)
    {
        $pass = $request->input('pass');
        $id_driver = $request->input('id_driver');
        $status = $request->input('status');
        $result['success'] = false;

        if ($pass != $this->key_szp_allfood) {
            exit('Error Key');
        }

        $user_update = DB::table('users')->where('id', $id_driver)->update(['status' => $status]);

        if ($user_update) $result['success'] = true;

        return response()->json($result);

    }

    public function changeDriverStateSzp(Request $request)
    {
        $pass = $request->input('pass');
        $id_driver = $request->input('id_driver');
        $state = $request->input('state');
        $result['success'] = false;

        if ($pass != $this->key_szp_allfood) {
            exit('Error Key');
        }

        $user_update = DB::table('users')->where('id', $id_driver)->update(['state' => $state]);

        if ($state == 0 || $state == 1){
            UserController::startStopWork($id_driver,$state);
        }

        if ($user_update) $result['success'] = true;

        return response()->json($result);

    }

    public function getDriversGeo(Request $request)
    {
        $pass = $request->input('pass');
        $id_city = $request->input('id_city');
        if ($pass != $this->key_szp_allfood) {
            exit('Error Key');
        }

        $users_geo = DB::table('users_geo')->join('users', 'users_geo.id_user', '=', 'users.id')
            ->select('users_geo.id', 'users_geo.id_user', 'users_geo.lan', 'users_geo.lon', 'users_geo.type', 'users_geo.updated_at', 'users.name', 'users.surname', 'users.state', 'users.rating')
            ->where('users.status', 3)
            ->where('users.state', '!=', 0)
            ->orderByDesc('users_geo.id');

        if ($id_city != 0) {
            $users_geo = $users_geo->where('users.id_city', $id_city)->get()->unique('id_user');
        } else {
            $users_geo = $users_geo->get()->unique('id_user');
        }

        $result['users_geo'] = $users_geo;
        $result['success'] = true;

        return response()->json($result);
    }

    public function getOneDriverGeo(Request $request)
    {
        $pass = $request->input('pass');
        $id_driver = $request->input('id_driver');
        if ($pass != $this->key_szp_allfood) {
            exit('Error Key');
        }

        $data = DB::table('users_geo')->select('lan', 'lon', 'updated_at')->orderByDesc('id')->first();
        if ($data) {
            $result['geo'] = $data;
        }
        $result['success'] = true;
        return response()->json($result);
    }

    public function getDriversForNaznachenieZakaza(Request $request)
    {
        $pass = $request->input('pass');
        $id_allfood = $request->input('id_allfood');
        $type = $request->input('type');
        $result['success'] = false;
        if ($pass != $this->key_szp_allfood) {
            exit('Error Key');
        }
        do {
            $order = DB::table('orders')->where('id_allfood', $id_allfood)->where('type', $type)->first();

            if (!$order) {
                $result['message'] = 'Заказ не найден';
                break;
            }

            $from_lat = explode("\n", $order->from_geo)[0];
            $from_lon = explode("\n", $order->from_geo)[1];

            $geo_sql = "( 6371000 *
                    ACOS(
                        COS( RADIANS( {$from_lat} ) ) *
                        COS( RADIANS( lan ) ) *
                        COS( RADIANS( lon ) -
                        RADIANS( {$from_lon} ) ) +
                        SIN( RADIANS( {$from_lat} ) ) *
                        SIN( RADIANS( lan) )
                    )
                )
                AS distance";

            $distance = 10000;

            $drivers = DB::table("users_geo")
                ->selectRaw("users.id as id, id_user, users_geo.type, users.name, users.surname, users.state, " . $geo_sql)
                ->join("users", "users_geo.id_user", "=", "users.id")
                ->where("users.status", 3)
                ->where("users_geo.updated_at", ">", date("Y-m-d H:i:s", time() - 3600))
                ->whereNotIn('users.state', [0, 2])
                //->having("distance", "<", $distance)
                ->orderByDesc("users.rating")
                ->get()->unique('id_user');

            $result['drivers'] = $drivers;
            $result['success'] = true;
        } while (false);

        return response()->json($result);
    }

    public function naznachitZakaz(Request $request)
    {
        $pass = $request->input('pass');
        $id_driver = $request->input('id_driver');
        $id_allfood = $request->input('id_allfood');
        $type = $request->input('type');
        $result['success'] = false;
        /* TODO
            1. Проверяем заказ есть нет
            2. Статус заказ ищем текущего курьера
            3. Если есть текущий курьер отменяем для Него заказ. Отправляем пуш уведомление
            4. Назначаем заказ курьеру отправляем пуш уведомление. Если у него есть активный заказ, state не меняем.
            5. Отправляем результат
        */

        if ($pass != $this->key_szp_allfood) {
            exit('Error Key');
        }

        do {
            $order = DB::table('orders')
                ->where('id_allfood', $id_allfood)
                ->where('type', $type)
                ->first();

            $old_driver_id = $order->id_courier;

            $new_driver = DB::table('users')
                ->select('id', 'token', 'state', 'name', 'photo', 'phone', 'type')
                ->where('id', $id_driver)
                ->first();

            if (!$order){
                $result['message'] = 'Заказ не найден';
                break;
            }
            if (!$new_driver){
                $result['message'] = 'Курьер не найден';
                break;
            }

            if ($new_driver->state == 0){
                $result['message'] = 'Курьер не активен';
                break;
            }

            $geo_new_user = DB::table('users_geo')->where('id_user', $new_driver->id)->first();
            $cafe_geo = explode("\n", $order->from_geo);

            $distance_to_cafe = SearchController::getDistance($geo_new_user->lan."\n".$geo_new_user->lon, $order->from_geo);

            $matrix = PushController::getPointsRoutinAndTime($order->from_geo, $order->to_geo, $new_driver->type);

            if ($order->type == 1) {
                $price_delivery = MoneyController::costDelivery($matrix['distance'], $new_driver->type) * $order->kef;
            }else{
                $price_delivery = $order->price_delivery;
            }

            $update_order = DB::table("orders")->where("id", $order->id)
                ->update([
                    "needed_sec" => $matrix['time'] > 450 ? $matrix['time'] : 450,
                    "distance_matrix" => $matrix['distance'],
                    "routing_points" => $matrix['route_points'],
                    "mode" => $new_driver->type,
                    "price_delivery" => $price_delivery,
                    'id_courier' => $new_driver->id,
                    'status' => 3,
                    'distance_to_cafe'=>$distance_to_cafe
                ]);


            // ! Если заказ не переназначен
            if (!$update_order){
                $result['message'] = 'Ошибка назначение заказа';
                break;
            }

            OrderController::changeOrderCourierStatus($order->id, $new_driver->id, 3);


            //ADD to ORDER_USER table
//            DB::table("order_user")
//                ->insert([
//                    "id_user" => $new_driver->id,
//                    "id_order" => $order->id,
//                    "status" => 3,
//                    "created_at" => Carbon::now(),
//                    "updated_at" => Carbon::now()]);

//            // * Проверяем есть ли курьер который получил этот заказ

            if ($old_driver_id != 0){

                $old_driver = DB::table('users')
                    ->select( 'name')
                    ->where('id', $old_driver_id)
                    ->first();

                $active_order = DB::table('orders')->select('id','status')->where('id_courier', $order->id_courier)->whereNotIn('status', [1,7,9])->orderByDesc('id')->first();

                if ($active_order){
                    $state = $active_order->status;
                }else{
                    $state = 1;
                }

                UserController::insertStateUserFunc($order->id_courier, $state);
                PushController::sendDataPush($order->id_courier,
                    array('type' => 'order', 'status' => 'other_driver'),
                    array('title'=>'Заказ #'.$order->id.' переназначен',
                        'body' => 'Оператор переназначил заказ другому курьеру.'));
            }



            // ! Если новый курьер свободен поменяем State на 3
            if ($new_driver->state == 1){
                UserController::insertStateUserFunc($new_driver->id, 3);
            }


            if ($old_driver_id != 0) {
                $mes['mess'] = '🔄️ Переназначение. ' . $old_driver->name.' ➡ '.$new_driver->name;
            }else{
                $mes['mess'] = '➡ Назначение заказа на '.$new_driver->name;
            }
            $mes['id_cafe'] = $order->id_cafe;
            PushController::sendReqToAllfood("PushNewOrders", $mes);


            PushController::sendDataPush($id_driver,
                array('type' => 'order', 'status' => 'new_order'),
                array('title'=>'Новый заказ',
                    'body'=>'Заказ на сумму '.$order->price_delivery.' тенге'));

            PushController::takedOrderAllfood($order, $new_driver, "5");

            $result['driver']['id']     = $new_driver->id;
            $result['driver']['name']   = $new_driver->name;
            $result['driver']['photo']  = $new_driver->photo;
            $result['driver']['phone']  = $new_driver->phone;
            $result['driver']['type']   = $new_driver->type;

            $result['success'] = true;
        } while (false);

        return response()->json($result);
    }

    public function getCommentsForSzp(Request $request)
    {
        $pass = $request->input('pass');
        $result['success'] = false;

        if ($pass != $this->key_szp_allfood) {
            exit('Error Key');
        }
        $result['comments'] = array();
        $comments = DB::table('rating')
            ->select('rating.id', 'rating.id_allfood', 'rating.id_courier', 'rating.star', 'rating.comment', 'rating.user_tel','rating.created_at', 'orders.cafe_name', 'orders.name', 'orders.type')
            ->leftJoin('orders', 'rating.id_allfood', '=','orders.id_allfood')
            ->where('rating.created_at', '>=', Carbon::yesterday())->get();

        if ($comments){
            $result['comments'] = $comments;
        }

        $result['success'] = true;
        return response()->json($result);
    }

    public function sendTestPushSzp(Request $request){
        $id_driver = $request->input('id_driver');

        PushController::sendDataPush($id_driver,
            array('type' => 'order', 'status' => 'other_driver'),
            array('title'=>'Новый заказ',
                'body'=>'Заказ на сумму 500 тенге'));
    }

    public function driverReturnCash(Request $request){
        $pass      = $request->input('pass');
        $id_driver = $request->input('id_driver');
        $summa     = $request->input('summa');
        $pass = $request->input('pass');

        if ($pass != $this->key_szp_allfood) {
            exit('Error Key');
        }

        $result['success'] = (bool) (new CashOnHandController)->minusSumma($id_driver, $summa);

        return response()->json($result);

    }

    public function izmenitSposobOplaty(Request $request){
        $pass   = $request->input('pass');
        $id     = $request->input('id_order');
        $type   = $request->input('type');
        $new_sposob_oplaty = $request->input('new_sposob_oplaty');
        $result['success'] = false;

        do{
            if ($pass != $this->key_szp_allfood) {
                exit('Error Key');
            }

            $order = DB::table('orders')
                ->where('id_allfood', $id)
                ->where('type', $type)
                ->first();

            if (!$order){
                $result['message'] = 'Заказ не найден';
                break;
            }

            $update_order = DB::table('orders')->where('id_allfood', $id)->where('type', $type)->update(['sposob_oplaty' => $new_sposob_oplaty]);

            $result['success'] = true;

        }while(false);

        return response()->json($result);
    }

    public function getDriverCashHistoryForSzp(Request $request){
        $pass   = $request->input('pass');
        $result['success'] = false;
        $result['history'] = array();
        do{
            if ($pass != $this->key_szp_allfood) {
                exit('Error Key');
            }

            $history_cash_of_hand = DB::table('cash_driver_history as h')
                ->selectRaw('h.id, h.summa, h.id_driver, h.id_order, h.created_at, users.name, users.surname, orders.id_allfood, orders.type')
                ->leftJoin('users', 'h.id_driver', '=', 'users.id')
                ->leftJoin('orders', 'h.id_order', '=', 'orders.id')
                ->orderByDesc('id')
                ->limit(100)
                ->get();

            if ($history_cash_of_hand){
                $result['history_cash_of_hand'] = $history_cash_of_hand;
            }

            $history_balance = DB::table('balance_history', 'h')
                ->select('h.id','h.id_user', 'h.amount', 'h.created_at','h.description', 'o.cafe_name', 'u.name', 'o.id_allfood', 'o.type', 'o.distance_matrix')
                ->leftJoin('users as u', 'h.id_user', '=', 'u.id')
                ->leftJoin('orders as o', 'h.id_order', '=', 'o.id')
                ->orderByDesc('id')
                ->limit(100)
                ->get();

            if ($history_balance){
                $result['history_balance'] = $history_balance;
            }
            $result['success'] = true;

        }while(false);

        return response()->json($result);
    }

    public function getDriverCashTotal(Request $request){
        $pass   = $request->input('pass');
        $result['success'] = false;
        $result['driver_total'] = array();
        do{
            if ($pass != $this->key_szp_allfood) {
                exit('Error Key');
            }

            $cash_on_hand = DB::table('users')->select('name', 'surname', 'id', 'cash_on_hand', 'phone')
                ->where('status', 3)
                ->get();



            $balance = DB::table('balance', 'b')
                ->select('b.id_user','b.amount', 'u.name', 'u.surname', 'u.cash_on_hand')
                ->leftJoin('users as u', 'b.id_user', '=', 'u.id')
                ->where('u.status', 3)
                ->get();

            $balance_id_user = $balance->pluck('id_user');

            if ($cash_on_hand){
                $result['cash_on_hand'] = $cash_on_hand;
            }

//            $result['new'] = DB::table('users', 'u')
//                ->leftJoin('balance as b', 'u.id', '=', 'b.id_user')
//                ->select('u.name', 'u.surname', 'u.id', 'u.cash_on_hand', 'u.phone', 'u.id_city', 'b.amount as balance')
//                ->where('status', 3)
//                ->get();
// TODO
//            $balance_today = DB::table('balance_history')
//                ->selectRaw("")

            if ($balance){
                $result['balance_user'] = $balance;
            }

            $result['success'] = true;

        }while(false);

        return response()->json($result);
    }

    public function getDriverCashTotalNew(Request $request){
        $pass   = $request->input('pass');
        $result['success'] = false;
        $result['driver_total'] = array();
        do{
            if ($pass != $this->key_szp_allfood) {
                exit('Error Key');
            }

            $driver_total = DB::table('users', 'u')
                ->leftJoin('balance as b', 'u.id', '=', 'b.id_user')
                ->select('u.name', 'u.surname', 'u.id', 'u.cash_on_hand', 'u.phone', 'u.id_city', 'b.amount as balance')
                ->where('status', 3)
                ->get();

            $result['driver_total'] = $driver_total;

            $result['success'] = true;

        }while(false);

        return response()->json($result);
    }

    public function addZapisTranzakciaDriver(Request $request){
        $id_driver = $request->input('id_driver');
        $id_order  = $request->input('id_order');
        $summa     = $request->input('summa');
        $comment   = $request->input('comment');
        $pass   = $request->input('pass');
        $result['success'] = false;
        do {
            if ($pass != $this->key_szp_allfood) {
                exit('Error Key');
            }

            $driver_check = DB::table('users')->where('id', $id_driver)->first();
            if (!$driver_check){
                $result['message'] = 'Пользователь не найден';
                break;
            }

            $insert = (new CashOnHandController)->plusSumma($id_driver, $summa, $id_order, $comment);

            $result['success'] = true;
        }while(false);

        return response()->json($result);
    }

    public function addZapisBalanceDriver(Request $request){
        $id_driver = $request->input('id_driver');
        $summa     = $request->input('summa');
        $comment   = $request->input('comment');
        $pass   = $request->input('pass');
        $result['success'] = false;

        do {
            if ($pass != $this->key_szp_allfood) {
                exit('Error Key');
            }

            $driver_check = DB::table('users')->where('id', $id_driver)->first();
            if (!$driver_check){
                $result['message'] = 'Пользователь не найден';
                break;
            }

            $insert = MoneyController::addAmount($id_driver,0,$summa,$comment, 4);

            $result['success'] = true;
        }while(false);

        return response()->json($result);
    }

    public function updateAllSummaDriverSZP(Request $request){
        $id_driver = $request->input('id_driver');
        $pass   = $request->input('pass');
        $result['success'] = false;
        do {
            if ($pass != $this->key_szp_allfood) {
                exit('Error Key');
            }

            $driver_check = DB::table('users')->where('id', $id_driver)->first();
            if (!$driver_check) {
                $result['message'] = 'Пользователь не найден';
                break;
            }
            (new CashOnHandController)->updateAllSummaDriver($id_driver);

            MoneyController::calculateBalance($id_driver);


            $result['success'] = true;
        }while(false);

        return response()->json($result);

    }

    public function getOrdersLast24Hour(Request $request){
        $pass   = $request->input('pass');
        $result['success'] = false;
        do {
            if ($pass != $this->key_szp_allfood) {
                exit('Error Key');
            }

            $orders = DB::table('orders')->where('created_at', '>=', Carbon::yesterday())->orderByDesc('id')->get();
            if ($orders){
                $result['orders'] = OrderMiniResource::collection($orders);
            }
            $result['success'] = true;
        }while(false);

        return response()->json($result);
    }

    public function getWhereOrderDriver(Request $request)
    {
        $pass       = $request->input('pass');
        $id_allfood = $request->input('id_allfood');
        $type       = $request->input('type');
        $result['success'] = false;

        do {
            if ($pass != $this->key_szp_allfood) {
                exit('Error Key');
            }

            $order = DB::table('orders')->where('id_allfood', $id_allfood)->where('type', $type)->first();

            if (!$order){
                $result['message'] = 'Заказ не найден';
                break;
            }

            if ($order->id_courier == 0){
                $result['message'] = 'Пока что курьер не найден';
                break;
            }

            $driver = DB::table('users')->where('id', $order->id_courier)->first();

            $result['driver_name'] = $driver->name;
            $result['driver_phone'] = $driver->phone;

            $result['routing_points'] = $order->routing_points;
            $result['distance_matrix'] = $order->distance_matrix;

            $users_geo = DB::table('users_geo')->where('id_user', $order->id_courier)->orderByDesc('id')->first();

            $result['driver_lat'] = $users_geo->lan;
            $result['driver_lon'] = $users_geo->lon;
            $result['driver_type'] = $users_geo->type;
            $result['driver_geo_update_time'] = $users_geo->updated_at;

            $result['success'] = true;

        }while(false);

        return response()->json($result);
    }

    public function getOrderStatusHistory(Request $request){
        $pass       = $request->input('pass');
        $id_allfood = $request->input('id_allfood');
        $type       = $request->input('type');

        $result['success'] = false;
        do {
            if ($pass != $this->key_szp_allfood) {
                exit('Error Key');
            }

            $order = DB::table('orders')
                ->select('id', 'price_delivery', 'status', 'id_courier', 'distance_matrix')
                ->where('id_allfood',$id_allfood)
                ->where('type', $type)->first();

            if (!$order){
                $result['message'] = 'Заказ не найден';
                break;
            }
            $result['order'] = $order;

            $order_user = DB::table('order_user', 'ou')
                ->leftJoin('users as u', 'ou.id_user', '=', 'u.id')
                ->select('ou.id', 'ou.id_order', 'ou.status', 'ou.created_at', 'u.name')
                ->where('ou.id_order', $order->id)
                ->get();


            $result['status_history'] = array();
            if ($order_user){
                $result['status_history'] = $order_user;
            }

            $result['success'] = true;
        }while(false);

        return response()->json($result);
    }

    public function izmenitStatusDriverOrder(Request $request){
        $pass       = $request->input('pass');
        $id_allfood = $request->input('id_allfood');
        $type       = $request->input('type');
        $new_status = $request->input('new_status');

        $result['success'] = false;
        do {
            if ($pass != $this->key_szp_allfood) {
                exit('Error Key');
            }

            $order = DB::table('orders')
                ->where('id_allfood',$id_allfood)
                ->where('type', $type)->first();

            if (!$order){
                $result['message'] = 'Заказ не найден';
                break;
            }

            OrderController::changeOrderCourierStatus($order->id, $order->id_courier, $new_status);

            $result['success'] = true;
        }while(false);

        return response()->json($result);
    }

    public function getAllActiveOrders(Request $request){
        $pass       = $request->input('pass');

        $result['success'] = false;
        do {
            if ($pass != $this->key_szp_allfood) {
                exit('Error Key');
            }
            $result['orders'] = array();

            $orders = DB::table('orders','o')
                ->leftJoin('users AS u', 'o.id_courier', '=', 'u.id')
                ->select('o.id', 'o.id_allfood', 'o.status','o.type', 'o.id_courier', 'o.created_at', 'o.id_city', 'o.id_cafe', 'o.cafe_name', 'u.name')
                ->whereNotIn('o.status',[7,9])
                ->orWhere('o.created_at', '>', date('Y-m-d H:i:s', time()-43200))
                ->orderByDesc('o.id')
                ->get();

            if ($orders){
                $result['orders'] = $orders;
            }
            $result['success'] = true;
        }while(false);

        return response()->json($result);
    }

    public function getCountActiveOrders(Request $request){
        $pass = $request->input('pass');

        $result['success'] = false;
        do {
            if ($pass != $this->key_szp_allfood) {
                exit('Error Key');
            }

            $result['count'] = DB::table('orders')->whereNotIn('status', [7,9])->count();

            $result['success'] = true;
        }while(false);

        return response()->json($result);
    }

    public function getDriverReportForDate(Request $request){
        $pass      = $request->input('pass');
        $id_driver = $request->input('id_driver');
        $date_from = $request->input('date_from');
        $date_to   = $request->input('date_to');

        $result['success'] = false;
        $itogo_active_time = 0;
        do {
            if ($pass != $this->key_szp_allfood) {
                exit('Error Key');
            }

            $driver_info = DB::table('users')
                ->select('id', 'name', 'surname', 'id_city', 'phone', 'cash_on_hand')
                ->where('id', $id_driver)
                ->first();

            if (!$driver_info){
                $result['message'] = 'Пользователь не найден';
                break;
            }

            $driver_info->balance = DB::table('balance')->where('id_user', $id_driver)->pluck('amount')->first();

            $result['driver_info'] = $driver_info;

            $orders_history['offered'] = DB::table('order_user')
                ->selectRaw("COUNT(*) as kol_order, DATE(created_at) as date_day")
                ->whereRaw("id_user = $id_driver AND status = 2 AND DATE(created_at) >='$date_from' AND DATE(created_at) <='$date_to'")
                ->groupBy('date_day')
                ->get()->toArray();

            $orders_history['ended'] = DB::table('orders')
                ->selectRaw("COUNT(*) as kol_order, DATE(created_at) as date_day")
                ->whereRaw("id_courier = $id_driver AND DATE(created_at) >='$date_from' AND DATE(created_at) <='$date_to'")
                ->groupBy('date_day')
                ->get()->toArray();

//            $order_ids = DB::table('orders')
//                ->where('type', 1)
//                ->whereRaw("id_courier = $id_driver AND DATE(created_at) >='$date_from' AND DATE(created_at) <='$date_to'")
//                ->pluck('id_allfood');
//
//            $zayavka_ids = DB::table('orders')
//                ->where('type', 2)
//                ->whereRaw("id_courier = $id_driver AND DATE(created_at) >='$date_from' AND DATE(created_at) <='$date_to'")
//                ->pluck('id_allfood');

            $balance_history['ended'] = DB::table('balance_history')
                ->selectRaw('SUM(amount) as summa, DATE(created_at) as date_day')
                ->whereRaw("id_user = $id_driver AND type = 1 AND amount > 0  AND DATE(created_at) >='$date_from' AND DATE(created_at) <='$date_to'")
                ->groupBy('date_day')
                ->get()->toArray();

            $balance_history['do_kafe'] = DB::table('balance_history')
                ->selectRaw('SUM(amount) as summa, DATE(created_at) as date_day')
                ->whereRaw("id_user = $id_driver AND type = 2 AND DATE(created_at) >='$date_from' AND DATE(created_at) <='$date_to'")
                ->groupBy('date_day')
                ->get()->toArray();

            $balance_history['doplata_hour'] = DB::table('balance_history')
                ->selectRaw('SUM(amount) as summa, DATE(DATE_ADD(created_at, INTERVAL -1 DAY)) as date_day')
                ->whereRaw("id_user = $id_driver AND type = 3 AND DATE(created_at) >=DATE_ADD('$date_from',INTERVAL +1 DAY) AND DATE(created_at) <=DATE_ADD('$date_to',INTERVAL +1 DAY)")
                ->groupBy('date_day')
                ->get()->toArray();

            $balance_history['other'] = DB::table('balance_history')
                ->selectRaw('SUM(amount) as summa, DATE(created_at) as date_day')
                ->whereRaw("id_user = $id_driver AND type NOT IN (1,2,3) AND amount > 0  AND DATE(created_at) >='$date_from' AND DATE(created_at) <='$date_to'")
                ->groupBy('date_day')
                ->get()->toArray();


            $active_time_history = DB::table('users_active_time')
                ->selectRaw('SUM(seconds) as seconds, DATE(created_at) as date_day')
                ->whereRaw("id_driver = $id_driver AND state = 0 AND DATE(created_at) >='$date_from' AND DATE(created_at) <='$date_to'")
                ->groupBy('date_day')
                ->get()->toArray();

            $cash_history = DB::table('cash_driver_history')
                ->selectRaw('SUM(summa) as summa, DATE(created_at) as date_day')
                ->whereRaw("id_driver = $id_driver AND DATE(created_at) >='$date_from' AND DATE(created_at) <='$date_to'")
                ->groupBy('date_day')
                ->get()->toArray();


            $startDate = Carbon::createFromFormat('Y-m-d', $date_from);
            $endDate = Carbon::createFromFormat('Y-m-d', $date_to);

            $dateRange = CarbonPeriod::create($startDate, $endDate);
            $result['$dateRange'] = array();

            foreach ($dateRange as $key => $d){
                $day = date("Y-m-d", strtotime($d));

                $order_day['ended'] = array_search($day, array_column($orders_history['ended'], 'date_day'), true);
                $order_day['offered'] = array_search($day, array_column($orders_history['offered'], 'date_day'), true);

                $balance_day['ended'] = array_search($day, array_column($balance_history['ended'], 'date_day'), true);
                $balance_day['do_kafe'] = array_search($day, array_column($balance_history['do_kafe'], 'date_day'), true);
                $balance_day['doplata_hour'] = array_search($day, array_column($balance_history['doplata_hour'], 'date_day'), true);
                $balance_day['other'] = array_search($day, array_column($balance_history['other'], 'date_day'), true);

                $active_time_day = array_search($day, array_column($active_time_history, 'date_day'), true);
                $cash_day = array_search($day, array_column($cash_history, 'date_day'), true);

                $result['result'][$key]['day'] = $day;
                //$result['result'][$key]['$order_day'] = $order_day;
                $itogo_active_time += $active_time_history[$active_time_day]->seconds;
                $active_t = $active_time_day !== false ? CarbonInterval::seconds($active_time_history[$active_time_day]->seconds)->cascade()->forHumans() : 0;

                $result['result'][$key]['orders']['ended'] = $order_day['ended'] !== false ? $orders_history['ended'][$order_day['ended']]->kol_order : 0;
                $result['result'][$key]['orders']['offered'] = $order_day['offered'] !== false ? $orders_history['offered'][$order_day['offered']]->kol_order : 0;

                $result['result'][$key]['balance']['ended'] = $balance_day['ended'] !== false ? $balance_history['ended'][$balance_day['ended']]->summa : 0;
                $result['result'][$key]['balance']['do_kafe'] = $balance_day['do_kafe'] !== false ? $balance_history['do_kafe'][$balance_day['do_kafe']]->summa : 0;
                $result['result'][$key]['balance']['doplata_hour'] = $balance_day['doplata_hour'] !== false ? $balance_history['doplata_hour'][$balance_day['doplata_hour']]->summa : 0;
                $result['result'][$key]['balance']['other'] = $balance_day['other'] !== false ? $balance_history['other'][$balance_day['other']]->summa : 0;

                $result['result'][$key]['cash'] = $cash_day !== false ? (int) $cash_history[$cash_day]->summa : 0;
                $result['result'][$key]['active_time'] = $active_t;
            }

            $result['itogo_active_time'] = $itogo_active_time;
            $result['$orders_history']  = $orders_history;
            $result['$balance_history'] = $balance_history;
//            $result['$order_ids']       = $order_ids;
//            $result['$zayavka_ids']     = $zayavka_ids;
            $result['sql'] = 'id_courier = '.$id_driver.' AND DATE(created_at) >='.$date_from.' AND DATE(created_at) <='.$date_to;

            $result['success'] = true;
        }while(false);

        return response()->json($result);
    }

    public function statisticaOrders(Request $request){
        $pass               = $request->input('pass');
        $start_date         = $request->input('start_date');
        $end_date           = $request->input('end_date');
        $compare_start_date = $request->input('compare_start_date');
        $compare_end_date   = $request->input('compare_end_date');

        $result['success'] = false;

        do{
            if ($pass != $this->key_szp_allfood) {
                exit('Error Key');
            }

            $result['orders'] = DB::table('orders')
                ->selectRaw("COUNT(*) as kol, SUM(price_delivery) as price_delivery")
                ->whereRaw("DATE(created_at) >= '$start_date' AND DATE(created_at) <= '$end_date'")
                ->first();

            $result['compare_orders'] = DB::table('orders')
                ->selectRaw("COUNT(*) as kol, SUM(price_delivery) as price_delivery")
                ->whereRaw("DATE(created_at) >= '$compare_start_date' AND DATE(created_at) <= '$compare_end_date'")
                ->first();

            $result['balance_plus'] = DB::table('balance_history')
                ->selectRaw("COUNT(*) as kol, SUM(amount) as summa, type")
                ->whereRaw("DATE(created_at) >= '$start_date' AND DATE(created_at) <= '$end_date' AND amount > 0")
                ->groupBy('type')
                ->get()
                ->pluck('summa', 'type');

            $result['compare_balance_plus'] = DB::table('balance_history')
                ->selectRaw("COUNT(*) as kol, SUM(amount) as summa, type")
                ->whereRaw("DATE(created_at) >= '$compare_start_date' AND DATE(created_at) <= '$compare_end_date' AND amount > 0")
                ->groupBy('type')
                ->get()
                ->pluck('summa', 'type');

            $result['cash_change'] = DB::table('cash_driver_history')
                ->selectRaw("COUNT(*) as kol, SUM(summa) as summa")
                ->whereRaw("DATE(created_at) >= '$start_date' AND DATE(created_at) <= '$end_date' AND id_order > 0")
                ->first();

            $result['compare_cash_change'] = DB::table('cash_driver_history')
                ->selectRaw("COUNT(*) as kol, SUM(summa) as summa")
                ->whereRaw("DATE(created_at) >= '$compare_start_date' AND DATE(created_at) <= '$compare_end_date' AND id_order > 0")
                ->first();

            $result['success'] = true;

        }while(false);

        return response()->json($result);
    }

    public function getStatussTelOrders(Request $request){
        $pass = $request->input('pass');
        $ids  = $request->input('ids');
        $result['success'] = false;
        do {
            if ($pass != $this->key_szp_allfood) {
                exit('Error Key');
            }
            $result['ids'] = $ids;
            $statuss = DB::table('orders')->select('id', 'status')->whereIn('id',  $ids)->pluck('status', 'id');

            if (!$statuss){
                $result['message'] = 'Ошибка при GET';
                break;
            }


            $result['statuss'] = $statuss;
            $result['success'] = true;
        }while(false);

        return response()->json($result);
    }
}
