<?php

namespace App\Http\Controllers;

use App\Http\Controllers\UserController;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
//use App\Http\Controllers\UserController;

class OrderController extends Controller
{

//Статусы заказа
//1. новый
//2. назначается курьер
//3. назначен курьер
//4. на доставке
//5 доставлен
//8 отмена курьер
//9 отмена

//Статусы предложение к курьера
//1. Новый
//2. Курьер принял
//3. Курьер не принял
//9. Курьер отменил


    public function newOrder(Request $request)
    {
        $id_allfood = $request->input('id_allfood');
        $id_city = $request->input('id_city');
        $phone = $request->input('phone');
        $name = $request->input('name');
        $blob = $request->input('blob');
        $id_cafe = $request->input('id_cafe');
        $cafe_name = $request->input('cafe_name');
        $from_geo = $request->input('from_geo');
        $from_address = $request->input('from_address');
        $to_geo = $request->input('to_geo');
        $to_address = $request->input('to_address');
        $summ_order = $request->input('summ_order');
        $price_delivery = $request->input('price_delivery');
        $type = $request->input('type');
        $distance = SearchController::getDistance($from_geo, $to_geo);
        $arrive_minute = $request->input("arrive_minute");
        $status = 1;
        $id_courier = 0;
        $result['success'] = false;

        do {
            $result['distance'] = $distance;

            $order = DB::table("orders")->select("id")->where('id_allfood', $id_allfood)->first();
            if ($order) {
                $result['message'] = 'Заказ уже добавлен';
                break;
            }

            if (!$id_allfood || !$id_city || !$phone || !$name || !$blob || !$id_cafe || !$cafe_name || !$from_geo || !$from_address || !$to_geo || !$to_address || !$summ_order || !$price_delivery || !$type) {
                $result['message'] = 'Данные не полные. Заказ не добавлен';
                break;
            }

            $new_order_id = DB::table("orders")->insertGetId([
                'id_allfood' => $id_allfood,
                'id_city' => $id_city,
                'id_courier' => $id_courier,
                'phone' => $phone,
                'name' => $name,
                'blob' => $blob,
                'status' => $status,
                'id_cafe' => $id_cafe,
                'cafe_name' => $cafe_name,
                'from_geo' => $from_geo,
                'from_address' => $from_address,
                'to_geo' => $to_geo,
                'to_address' => $to_address,
                'summ_order' => $summ_order,
                'price_delivery' => $price_delivery,
                'type' => $type,
                'distance' => $distance,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'arrive_time'=>Carbon::now()->addMinute($arrive_minute)
            ]);

            $result['success'] = true;
            $result['id'] = $new_order_id;


        } while (false);
        return response()->json($result);
    }

    public static function changeOrderCourierStatus($id_order,$id_courier, $status){
        //Update "ORDERS" table
        DB::table("orders")->where("id", $id_order)
            ->update(['status' => $status, 'id_courier' => $id_courier]);

        if ($status == 5)
            $user_state = 1;
        else
            $user_state = $status;
        //Update User State
        UserController::insertStateUserFunc($id_courier, $user_state);

        //ADD to ORDER_USER table
        $add_offer = DB::table("order_user")
            ->insert([
                "id_user" => $id_courier,
                "id_order" => $id_order,
                "status" => $status,
                "created_at" => Carbon::now(),
                "updated_at" => Carbon::now()]);

    }

    public function takeOrder(Request $request)
    {
        $password = $request->input("password");
        $id_order = $request->input('id_order');

        $result['success'] = false;

        do {
            $user = DB::table("users")->where("password", $password)->first();
            if (!$user) {
                $result['message'] = 'user not found';
                break;
            }

            $order = DB::table("orders")->find($id_order);

            if (!$order) {
                $result['message'] = "Order Not Found";
                break;
            }
            if ($order->status != 1 && $order->status != 2) {
                $result['message'] = 'Курьер уже назначен или заказ отменен';
                break;
            }




            self::changeOrderCourierStatus($order->id, $user->id, 3);

            //Update needed_time and distance_matrix
            $matrix = PushController::getDistanceDurationGoogle($order->from_geo, $order->to_geo,$user->type);
            DB::table("orders")->where("id", $id_order)
                ->update([
                    "needed_sec"=>$matrix['time_value'],
                    "distance_matrix"=>$matrix['dist_value'],
                    "mode"=>$user->type]);

            $result['matrix'] = $matrix;
            //Curl to allfood kz
            $result['allfood'] = PushController::takedOrderAllfood($order->id, $user->id, "5");


            $result['success'] = true;

        } while (false);

        return response()->json($result);
    }

    public function startDeliveryOrder(Request $request)
    {
        $password = $request->input("password");
        $id_order = $request->input('id_order');
        $lat = $request->input("lat");
        $lon = $request->input("lon");

        do {
            $user = DB::table("users")->where("password", $password)->first();
            if (!$user) {
                $result['message'] = 'user not found';
                break;
            }

            $order = DB::table("orders")->find($id_order);

            if (!$order) {
                $result['message'] = "Order Not Found";
                break;
            }

            if ($order->status == 4){
                $result['message'] = 'Заказ уже на доставке';
                break;
            }

            $distance_to_cafe = SearchController::getDistance($order->from_geo, $lat."\n".$lon);

            if ($distance_to_cafe > 100){
                $result['message'] = 'Вы слишком далеко находитесь от кафе';
                break;
            }

            self::changeOrderCourierStatus($order->id, $user->id, 4);
            //Curl to allfood kz
            $result['allfood'] = PushController::startDeliveryOrder($order->id, $user->id, $order->needed_sec);

        } while (false);

    }

    public function deliveredOrder(Request $request)
    {
        $password = $request->input("password");
        $id_order = $request->input('id_order');
        do {
            $user = DB::table("users")->where("password", $password)->first();
            if (!$user) {
                $result['message'] = 'user not found';
                break;
            }

            $order = DB::table("orders")->find($id_order);

            if (!$order) {
                $result['message'] = "Order Not Found";
                break;
            }

            if ($order->status == 5){
                $result['message'] = 'Заказ уже доставлен';
                break;
            }

            self::changeOrderCourierStatus($order->id, $user->id, 5);
            //Curl to allfood kz
            $result['allfood'] = PushController::endDeliveryOrder($order->id, $user->id);

        } while (false);
    }

    public function cancelOrder(Request $request)
    {
        $pass = $request['pass'];
        $id_order = $request['id_order'];
        $cause = $request["prichina"];
        $result['success'] = false;
        do {
            $user = DB::table("users")->where("password", $pass);

            if ($pass != 'ALLFOOD123') {
                $result['message'] = 'Пароль неверный';
                break;
            }
            $status = DB::table("orders")->where("id", $id_order)->pluck("status")->first();

            if ($status == 9) {
                $result['message'] = 'Заказ уже отменен';
                break;
            }

            if (!$status) {
                $result['message'] = 'Заказ не найден';
                break;
            }

            $this->addCauseToCancelled($id_order,$user->id, 1, $cause);

            $cancelSql = DB::table("orders")->where('id', $id_order)->update(['status' => 9]);
            UserController::insertStateUserFunc($user->id, 1);

            if (!$cancelSql) {
                $result['message'] = 'Произошло ошибка';
            } else
                $result['success'] = true;

        } while (false);
        return response()->json($result);
    }

    public function addCauseToCancelled($id_order, $id_who = null, $who, $cause){
        // WHO
//        1. Курьер
//        2. Кафе
//        3. Клиент
//        4. Оператор
//        5. Программа


        $add = DB::table("orders_cancelled")->insert([
            "id_order"=>$id_order,
            "id_who"=>$id_who,
            "who"=>$who,
            "cause"=>$cause
        ]);

        if ($add)
            return true;
        else
            return false;

    }

}
