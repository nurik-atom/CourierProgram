<?php

namespace App\Http\Controllers;

use App\Http\Requests\checkOrderUserRequest;
use App\Http\Resources\OrderResource;
use Carbon\Carbon;
use http\Client\Curl\User;
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


    public static function getTextStatus($key)
    {
        $status[1] = 'Новый заказ';
        $status[2] = 'Ищем курьера';
        $status[3] = 'Курьер назначен';
        $status[4] = 'Курьер в кафе ждет заказа';
        $status[5] = 'Заказ доставляется';
        $status[6] = 'Курьер у клиента';
        $status[7] = 'Заказ успешно доставлен';
        $status[9] = 'Заказ отменен';

        return $status[$key];
    }

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
            if ($order->status === 2) {
                $time_offer = DB::table("order_user")
                    ->where("id_order", $id_order)
                    ->where("status", 2)
                    ->pluck("created_at")
                    ->last();

                $result['time_to_success'] = time() - strtotime($time_offer);
            }

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

    public function checkOrderUser(checkOrderUserRequest $request)
    {
        $password = $request->input("password");
        $user = UserController::getUser($password);
        $result['success'] = false;
        $result['have_order'] = false;
        do {
            if (!$user) {
                $result['message'] = 'Пользователь не найден';
                break;
            }
            $result['user_state'] = $user->state;
            $order_user = DB::table("order_user")->where("id_user", $user->id)->orderByDesc("id")->first();
            if (!$order_user) {
                break;
            }
            if ($order_user->status > 7) {
                break;
            }
            $order = DB::table("orders")
                ->where("id", $order_user->id_order)
                ->orderByDesc("id")
                ->get();

            if ($order[0]->status > 6) {
                break;
            }

            if ($order) {
                $result['have_order'] = true;

                if ($order[0]->status == 2){
                    $result['seconds'] = time() - strtotime($order_user->created_at);
                }

                if ($order[0]->status <= 3) {
                    $result['seconds'] = strtotime($order[0]->arrive_time) - time();
                }

                if ($order[0]->status == 5) {
                    $start_time = DB::table("order_user")->where("id_order", $order[0]->id)->where("status", 5)->select("created_at")->first();

                    $result['seconds'] = $order[0]->needed_sec + strtotime($start_time->created_at) - time();
                }

                $result['order'] = OrderResource::collection($order)[0];
            }
            $result['success'] = true;
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

            if (!$checkedDataResult['success']) {
                $result['message'] = $checkedDataResult['message'];
                break;
            }
            $user = $checkedDataResult['user'];
            $order = $checkedDataResult['order'];

            self::changeOrderCourierStatus($order->id, $user->id, 3);

            //Curl to allfood kz
            $result['allfood'] = PushController::takedOrderAllfood($order, $user, "5");
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

            if (!$checkedDataResult['success']) {
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
            $result['allfood'] = PushController::courierInCafe($order, $user);
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
        $result['success'] = false;
        do {
            $checkedDataResult = $this->checkUserAndOrder($password, $id_order, 4);

            if (!$checkedDataResult['success']) {
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
            $result['allfood'] = PushController::startDeliveryOrder($order, $user, $order->needed_sec);
            $result['success'] = true;
        } while (false);

        return response()->json($result);
    }

    public function courierAtTheClient(Request $request)
    {
        $password = $request->input("password");
        $id_order = $request->input('id_order');
        $result['success'] = false;
        do {
            $checkedDataResult = $this->checkUserAndOrder($password, $id_order, 5);

            if (!$checkedDataResult['success']) {
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
            $result['allfood'] = PushController::courierAtTheClient($order, $user);
            $result['success'] = true;
        } while (false);
        return response()->json($result);
    }

    public function finishDeliveryOrder(Request $request)
    {
        $password = $request->input("password");
        $id_order = $request->input("id_order");
        $result['success'] = false;
        do {
            $checkedDataResult = $this->checkUserAndOrder($password, $id_order, 6);

            if (!$checkedDataResult['success']) {
                $result['message'] = $checkedDataResult['message'];
                break;
            }
            $user = $checkedDataResult['user'];
            $order = $checkedDataResult['order'];

            $description = "Заказ №" . $order->id;
            MoneyController::addAmount($user->id, $order->id, $order->price_delivery, $description);

            $result['price'] = $order->price_delivery;
            self::changeOrderCourierStatus($order->id, $user->id, 7);

            //Curl to allfood kz
            $result['allfood'] = PushController::finishDeliveryOrder($order, $user);

            $result['success'] = true;
        } while (false);
        return response()->json($result);
    }

    public function refusingOrderReq(Request $request)
    {
        $pass = $request['password'];
        $id_order = $request['id_order'];
        $cause = $request["prichina"];
        $result['success'] = false;

        $user = UserController::getUser($pass);

        do {
            if (!$user) {
                $result['message'] = 'Пользователь не найден';
                break;
            }
            $order = DB::table("orders")->where("id", $id_order)->select("status", "id_courier")->first();

            if (!$order->status) {
                $result['message'] = 'Заказ не найден';
                break;
            }
            DB::table("orders")->where('id', $id_order)->update(['status' => 1]);
            self::refusingOrder($user->id, $id_order, 12, $cause);
            UserController::insertStateUserFunc($user->id, 1);
            $result['success'] = true;
        } while (false);

        return response()->json($result);
    }

    public static function refusingOrder($id_user, $id_order, $status, $cause){
        $prev_time = DB::table("order_user")
            ->where("id_user", $id_user)
            ->where("id_order", $id_order)
            ->where("status", "!=", $status)
            ->pluck("created_at")->last();

        if (!$prev_time) $prev_time = Carbon::now();

        DB::table("order_user")->updateOrInsert([
            "id_user" => $id_user,
            "id_order" => $id_order,
            "status" => $status,
            "refuse_text" => $cause
        ],["seconds" => Carbon::now()->diffInSeconds($prev_time),
            "created_at" => Carbon::now(),
            "updated_at" => Carbon::now()]);
    }

    public function cancelOrder(Request $request)
    {
        $pass = $request['password'];
        $id_order = $request['id_order'];
        $cause = $request["prichina"];
        $result['success'] = false;
        do {
            $user = UserController::getUser($pass);

            if (!$user) {
                $result['message'] = 'Пользователь не найден';
                break;
            }

            $order = DB::table("orders")->where("id", $id_order)->select("status", "id_courier")->first();


            if (!$order->status) {
                $result['message'] = 'Заказ не найден';
                break;
            }

            if ($user->id != $order->id_courier) {
                $result['message'] = 'Заказ не принадлежит Вам';
                break;
            }

            if ($order->status == 9) {
                $result['message'] = 'Заказ уже отменен';
                break;
            }

            $cancelSql = DB::table("orders")->where('id', $id_order)
                ->update(['status' => 9]);
            self::addCauseToCancelled($id_order, $user->id, 1, $cause);

            UserController::insertStateUserFunc($user->id, 1);

            if (!$cancelSql) {
                $result['message'] = 'Произошло ошибка';
            } else
                $result['success'] = true;

        } while (false);
        return response()->json($result);
    }

    public static function addCauseToCancelled($id_order, $id_who, $who, $cause)
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

            if (!$password) {
                $result['message'] = 'Пароль нет';
                break;
            }

            if (!$id_order) {
                $result['message'] = 'id заказа нет';
                break;
            }

            $user = DB::table("users")->where("password", $password)->first();
            if (!$user) {
                $result['message'] = 'Пользователь не найден';
                break;
            }

            $order = DB::table("orders")->where("id", $id_order)->first();

            if (!$order) {
                $result['message'] = 'Заказ не найден';
                break;
            }

            if ($status !== $order->status) {
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
