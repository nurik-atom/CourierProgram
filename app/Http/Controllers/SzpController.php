<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
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

        $active_order = DB::table('orders')->select('id_allfood', 'from_address', 'to_address', 'cafe_phone', 'cafe_name', 'phone', 'name', 'type', 'status', 'created_at', 'distance', 'duration_sec', 'needed_sec', 'mode')->whereNotIn('status', ['7', '9'])->where('id_courier', $user->id)->orderByDesc('id')->get();

        $orders = DB::table('orders')->select('id_allfood', 'type', 'cafe_name', 'to_address', 'status', 'created_at', 'distance', 'duration_sec', 'needed_sec', 'mode')->where('id_courier', $user->id)->orderByDesc('id')->limit(10)->get();

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
                ->whereNotIn('users.state', [0, 3, 4])
                ->having("distance", "<", $distance)
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

            $update_order = DB::table('orders')->where('id_allfood', $id_allfood)->where('type', $type)->update(['id_courier' => $new_driver->id, 'status' => 3]);


            // ! Если заказ не переназначен
            if (!$update_order){
                $result['message'] = 'Ошибка назначение заказа';
                break;
            }

            //ADD to ORDER_USER table
            DB::table("order_user")
                ->insert([
                    "id_user" => $new_driver->id,
                    "id_order" => $order->id,
                    "status" => 3,
                    "created_at" => Carbon::now(),
                    "updated_at" => Carbon::now()]);

            // * Проверяем есть ли курьер который получил этот заказ
            if ($order->id_courier){
                UserController::insertStateUserFunc($order->id_courier, 0);
                PushController::sendDataPush($order->id_courier,
                    array('type' => 'order', 'status' => 'other_driver'),
                    array('title'=>'Заказ #'.$order->id.' переназначен',
                        'body' => 'Оператор переназначил заказ другому курьеру.'));
            }

            // ! Если новый курьер свободен поменяем State на 3
            if ($new_driver->state == 1){
                UserController::insertStateUserFunc($new_driver->id, 3);
            }

            PushController::sendDataPush($id_driver,
                array('type' => 'order', 'status' => 'new_order'),
                array('title'=>'Новый заказ',
                    'body'=>'Заказ на сумму '.$order->price_delivery.' тенге'));

            PushController::takedOrderAllfood($order, $new_driver, "5");

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
}
