<?php

namespace App\Http\Controllers;

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
//4. Курьер в Кафе
//5. на доставке
//6 доставлен
//8 отмена курьер
//9 отмена

//Статусы предложение к курьера
//1. Новый
//2. Курьер принял
//3. Курьер не принял
//9. Курьер отменил


    public function getStatusTimeOrder(Request $request)
    {
        $password = $request->input("password");
        $id_order = $request->input("id_order");
        $result['success'] = false;
        do {
            $user = UserController::getUser($password);

            if (!$user) {
                $result['message'] = 'Пользователь не найден';
                break;
            }

            $order = DB::table("orders")->find($id_order);
            if (!$order) {
                $result['message'] = 'Заказ не найден';
                break;
            }

            $result['status'] = $order->status;
            if ($order->status === 3) {
                $result['time_to_cafe'] = strtotime($order->arrive_time) - time();
            }

            if ($order->status === 5) {
                $time_status = DB::table("order_user")
                    ->where("id_user", $user->id)
                    ->where("id_order", $id_order)
                    ->where("status", 5)->pluck("created_at")->last();
                $result['time_to_client'] = strtotime($time_status) + $order->needed_sec - time();
            }
            $result['success'] = true;
        } while (false);

        return response()->json($result);
    }

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
        $price_delivery = 0;
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

            if (!$id_allfood || !$id_city || !$phone || !$name || !$blob || !$id_cafe || !$cafe_name || !$from_geo || !$from_address || !$to_geo || !$to_address || !$summ_order || !$type) {
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
                'price_delivery' => $price_delivery,
                'id_cafe' => $id_cafe,
                'cafe_name' => $cafe_name,
                'from_geo' => $from_geo,
                'from_address' => $from_address,
                'to_geo' => $to_geo,
                'to_address' => $to_address,
                'summ_order' => $summ_order,
                'type' => $type,
                'distance' => $distance,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'arrive_time' => Carbon::now()->addMinute($arrive_minute)
            ]);

            $result['success'] = true;
            $result['id'] = $new_order_id;


        } while (false);
        return response()->json($result);
    }

    public static function changeOrderCourierStatus($id_order, $id_courier, $status)
    {
        //Update "ORDERS" table
        DB::table("orders")->where("id", $id_order)
            ->update(['status' => $status, 'id_courier' => $id_courier]);

        if ($status == 7)
            $user_state = 1;
        else
            $user_state = $status;
        //Update User State
        UserController::insertStateUserFunc($id_courier, $user_state);

        //ADD to ORDER_USER table
        DB::table("order_user")
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
            $checkedDataResult = $this->checkUserAndOrder($password, $id_order, 2);

            if (!$checkedDataResult['success']){
                $result['message'] = $checkedDataResult['message'];
                break;
            }
            $user = $checkedDataResult['user'];
            $order = $checkedDataResult['order'];

            self::changeOrderCourierStatus($order->id, $user->id, 3);

            $price_delivery = MoneyController::costDelivery($order->distance, $user->type);

            //Update needed_time and distance_matrix
            $matrix = PushController::getDistanceDurationGoogle($order->from_geo, $order->to_geo, $user->type);
            DB::table("orders")->where("id", $id_order)
                ->update([
                    "needed_sec" => $matrix['time_value'],
                    "distance_matrix" => $matrix['dist_value'],
                    "mode" => $user->type,
                    "price_delivery" => $price_delivery
                ]);

            $result['matrix'] = $matrix;
            //Curl to allfood kz
            $result['allfood'] = PushController::takedOrderAllfood($order->id, $user->id, "5");


            $result['success'] = true;

        } while (false);

        return response()->json($result);
    }

    public function courierInCafe(Request $request)
    {
        $password = $request->input("password");
        $id_order = $request->input("id_order");
        $lat = $request->input("lat");
        $lon = $request->input("lon");
        $result['success'] = false;
        do {
            $checkedDataResult = $this->checkUserAndOrder($password, $id_order, 3);

            if (!$checkedDataResult['success']){
                $result['message'] = $checkedDataResult['message'];
                break;
            }
            $user = $checkedDataResult['user'];
            $order = $checkedDataResult['order'];

            $distance_to_cafe = SearchController::getDistance($order->from_geo, $lat . "\n" . $lon);

            if ($distance_to_cafe > 100) {
                $result['message'] = 'Вы слишком далеко находитесь от кафе';
                break;
            }

            self::changeOrderCourierStatus($order->id, $user->id, 4);
            //Curl to allfood kz
            $result['allfood'] = PushController::courierInCafe($order->id, $user->id);

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
            $checkedDataResult = $this->checkUserAndOrder($password, $id_order,4);

            if (!$checkedDataResult['success']){
                $result['message'] = $checkedDataResult['message'];
                break;
            }
            $user = $checkedDataResult['user'];
            $order = $checkedDataResult['order'];

            $distance_to_cafe = SearchController::getDistance($order->from_geo, $lat . "\n" . $lon);

            if ($distance_to_cafe > 100) {
                $result['message'] = 'Вы слишком далеко находитесь от кафе';
                break;
            }

            self::changeOrderCourierStatus($order->id, $user->id, 5);
            //Curl to allfood kz
            $result['allfood'] = PushController::startDeliveryOrder($order->id, $user->id, $order->needed_sec);

        } while (false);

        return response()->json($result);
    }

    public function courierAtTheClient(Request $request)
    {
        $password = $request->input("password");
        $id_order = $request->input('id_order');
        do {
            $checkedDataResult = $this->checkUserAndOrder($password, $id_order,5);

            if (!$checkedDataResult['success']){
                $result['message'] = $checkedDataResult['message'];
                break;
            }
            $user = $checkedDataResult['user'];
            $order = $checkedDataResult['order'];

            $start_time = DB::table("order_user")
                ->where("id_user", $user->id)->where("id_order", $order->id)
                ->where("status", 4)
                ->pluck("created_at")->first();

            $duration_sec = time() - strtotime($start_time);
            //Insert Duration Second
            DB::table("orders")->where('id', $order->id)
                ->update(['duration_sec' => $duration_sec]);

            self::changeOrderCourierStatus($order->id, $user->id, 6);

            //Curl to allfood kz
            $result['allfood'] = PushController::courierAtTheClient($order->id, $user->id);

        } while (false);
        return response()->json($result);
    }

    public function finishDeliveryOrder(Request $request)
    {
        $password = $request->input("password");
        $id_order = $request->input("id_order");
        $result['success'] = false;
        do{
            $checkedDataResult = $this->checkUserAndOrder($password, $id_order,6);

            if (!$checkedDataResult['success']){
                $result['message'] = $checkedDataResult['message'];
                break;
            }
            $user = $checkedDataResult['user'];
            $order = $checkedDataResult['order'];

            $description = "Заказ №" . $order->id;
            MoneyController::addAmount($user->id, $order->id, $order->price_delivery, $description);

            $result['price'] = $order->price_delivery;
            self::changeOrderCourierStatus($order->id, $user->id,7);

            //Curl to allfood kz
            $result['allfood'] = PushController::finishDeliveryOrder($order->id, $user->id);

            $result['success'] = true;
        }while(false);
        return response()->json($result);
    }

    public function cancelOrder(Request $request)
    {
        $pass = $request['pass'];
        $id_order = $request['id_order'];
        $cause = $request["prichina"];
        $result['success'] = false;
        do {
            $user = UserController::getUser($pass);

            $status = DB::table("orders")->where("id", $id_order)->pluck("status")->first();

            if ($status == 9) {
                $result['message'] = 'Заказ уже отменен';
                break;
            }

            if (!$status) {
                $result['message'] = 'Заказ не найден';
                break;
            }

            $this->addCauseToCancelled($id_order, $user->id, 1, $cause);

            $cancelSql = DB::table("orders")->where('id', $id_order)
                ->update(['status' => 9, "price_delivery" => "0"]);

            UserController::insertStateUserFunc($user->id, 1);

            if (!$cancelSql) {
                $result['message'] = 'Произошло ошибка';
            } else
                $result['success'] = true;

        } while (false);
        return response()->json($result);
    }

    public function addCauseToCancelled($id_order, $id_who, $who, $cause)
    {
        // WHO
//        1. Курьер
//        2. Кафе
//        3. Клиент
//        4. Оператор
//        5. Программа


        $add = DB::table("orders_cancelled")->insert([
            "id_order" => $id_order,
            "id_who" => $id_who,
            "who" => $who,
            "cause" => $cause
        ]);

        if ($add)
            return true;
        else
            return false;

    }

    public function checkUserAndOrder($password, $id_order, $status)
    {
        do {
            $result['success'] = false;

            if (!$password){
                $result['message'] = 'Пароль нет';
                break;
            }

            if (!$id_order){
                $result['message'] = 'id заказа нет';
                break;
            }

            $user = DB::table("users")->where("password", $password)->first();
            if (!$user){
              $result['message'] = 'Пользователь не найден';
              break;
            }

            $order = DB::table("orders")->where("id",$id_order)->first();

            if (!$order){
                $result['message'] = 'Заказ не найден';
                break;
            }

            if ($status !== $order->status){
                $result['message'] = 'Неправильный статус';
                break;
            }

            $result['order'] = $order;
            $result['user'] = $user;

            $result['success'] = true;
        } while (false);

        return $result;
    }

    public function prichinyOtmeny()
    {
        $result = array();
        $result[] = 'Расстояние слишком большое';
        $result[] = 'Магазин не в моей отправной точке';
        $result[] = 'Я не хочу делать заказ';
        $result[] = 'Я не хочу идти в этот магазин';
        $result[] = 'У меня слишком много заказов';
        $result[] = 'У меня проблемы с телефоном или приложением';
        $result[] = 'Моя смена скоро закончится';
        $result[] = 'Мне нужен перерыв';
        $result[] = 'У меня чрезвычайная ситуация';
        $result[] = 'Магазин закрыт';

        return response()->json($result);
    }

}
