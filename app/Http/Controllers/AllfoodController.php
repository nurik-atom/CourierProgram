<?php

namespace App\Http\Controllers;

use App\Http\Requests\NewOrderRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AllfoodController extends Controller
{
    public function newOrder(Request $request): \Illuminate\Http\JsonResponse
    {
//        $request = $request->validated();

        $key = $request->input("key");

        if (!$key || $key != env("ALLFOOD_KEY")) exit("Error key");

        $id_allfood = $request->input('id_allfood');
        $id_city = $request->input('id_city');
        $phone = $request->input('phone');
        $name = $request->input('name');
        $blob = $request->input('blob');
        $id_cafe = $request->input('id_cafe');
        $cafe_name = $request->input('cafe_name');
        $cafe_phone = $request->input('cafe_phone');
        $comments = $request->input('comments');
        $from_geo = $request->input('from_geo');
        $from_address = $request->input('from_address');
        $to_geo = $request->input('to_geo');
        $to_address = $request->input('to_address');
        $summ_order = $request->input('summ_order');
        $pay_to_cafe = $request->input('pay_to_cafe');
        $price_delivery = $request->input('price_delivery');
        $sposob_oplaty = $request->input('sposob_oplaty');
        $type = $request->input('type');
        $distance = SearchController::getDistance($from_geo, $to_geo);
        $arrive_minute = (int) $request->input("arrive_minute");
        $kef = $request->input("kef");
        $profit_driver_allfood = (int) $request->input("profit_driver_allfood");
        $status = 1;
        $id_courier = 0;
        $result['success'] = false;

        do {

            $result['distance'] = $distance;

            $order = DB::table("orders")->select("id")->where('id_allfood', $id_allfood)->where("type", $type)->first();
            if ($order) {
                $result['message'] = 'Заказ уже добавлен';
                break;
            }

            if (!$id_allfood || !$id_city || !$phone || !$id_cafe || !$from_geo || !$from_address || !$to_geo || !$to_address || !$type) {
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
                'sposob_oplaty' => $sposob_oplaty,
                'id_cafe' => $id_cafe,
                'cafe_name' => $cafe_name,
                'cafe_phone' => $cafe_phone,
                'comments' => $comments,
                'from_geo' => $from_geo,
                'from_address' => $from_address,
                'to_geo' => $to_geo,
                'to_address' => $to_address,
                'summ_order' => $summ_order,
                'pay_to_cafe' => $pay_to_cafe,
                'type' => $type,
                'kef' => $kef,
                'profit_driver_allfood' => $profit_driver_allfood,
                'distance' => $distance,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'arrive_time' => Carbon::now()->addMinutes($arrive_minute)
            ]);

            $result['success'] = true;
            $result['id'] = $new_order_id;

            if ($new_order_id){
                DB::table("order_user")
                    ->insert([
                        "id_user" => 0,
                        "id_order" => $new_order_id,
                        "status" => 1,
                        "created_at" => Carbon::now(),
                        "updated_at" => Carbon::now()]);
            }

        } while (false);

        return response()->json($result);
    }

    public function cancelTelOrderFromAllfood(Request $request){
        $id = $request->input("id");
        $id_allfood = $request->input("id_allfood");
        $who = $request->input("who");
        $key = $request->input("key");
        $prichina = $request->input("prichina");

        $result['success'] = false;

        if (!$key || $key != env("ALLFOOD_KEY")) exit("Error key");

        do{
            $check = $this->checkOrderType($id_allfood, 2);
            if (!$check['success']){
                $result['message'] = $check['message'];
                break;
            }

            $order = $check['order'];

            $cancelSql = DB::table("orders")->where('id', $order->id)
                ->update(['status' => 9]);
            if (!$cancelSql) {
                $result['message'] = 'Ошибка при отмене';
                break;
            }
            OrderController::addCauseToCancelled($order->id, 0, $who, $prichina);

            if ($order->id_courier) {
                UserController::defineStateAndUpdate($order->id_courier);
                PushController::cancelFromCafeClient($order->id, $order->id_courier, $prichina);
            }
            $result['success'] = true;
        }while(false);

        return response()->json($result);

    }

    public function cancelOrderFromAllfood(Request $request)
    {
        $key = $request->input("key");
        if (!$key || $key != env("ALLFOOD_KEY")) exit("Error key");

        $id_allfood = $request->input("id_allfood");
        $type = $request->input("type");
        $prichina = $request->input("prichina");
        $result['success'] = false;

        do {
            $check = $this->checkOrderType($id_allfood,$type);
            if (!$check['success']){
                $result['message'] = $check['message'];
                break;
            }
            $order = $check['order'];

            $cancelSql = DB::table("orders")->where('id', $order->id)
                ->update(['status' => 9]);

            if (!$cancelSql) {
                $result['message'] = 'Ошибка при отмене';
                break;
            }
            OrderController::addCauseToCancelled($order->id, 0, 0, $prichina);

            if ($order->id_courier) {
//                UserController::insertStateUserFunc($order->id_courier, 1);
                UserController::defineStateAndUpdate($order->id_courier);
                PushController::cancelFromCafeClient($order->id, $order->id_courier, $prichina);
            }

            $result['success'] = true;
        } while (false);
        return response()->json($result);
    }

    public function getStatusOrder(Request $request)
    {
        $key = $request->input("key");
        if (!$key || $key != env("ALLFOOD_KEY")) exit("Error key");

        $id_allfood = $request->input("id_allfood");
        $type = $request->input("type");
        $result['success'] = false;
        do {
            $check = $this->checkOrderType($id_allfood,$type);
            if (!$check['success']){
                $result['message'] = $check['message'];
                break;
            }
            $order = $check['order'];
            $result['status'] = $order->status;
            $result['status_text'] = OrderController::getTextStatus($order->status);


            $result['success'] = true;
        } while (false);

        return response()->json($result);
    }

    private function checkOrderType($id_allfood, $type)
    {
        $result['success'] = false;
        do {
            if (!$id_allfood) {
                $result['message'] = 'id Order неправильно';
                break;
            }
            if (!$type) {
                $result['message'] = 'Type неправильно';
                break;
            }

            $order = DB::table("orders")
                ->where("id_allfood", $id_allfood)
                ->where("type", $type)->first();

            if (!$order){
                $result['message'] = 'Заказ не найден';
                break;
            }

            $result['order'] = $order;
            $result['success'] = true;
        } while (false);

        return $result;
    }

    public function getOrderDriverPosition(Request $request){

        $id_allfood = $request->input("id_order");
        $type       = $request->input("type");
        $result['success'] = false;
        do{
            $order = DB::table('orders')->where('id_allfood', $id_allfood)->where('type', $type)->first();
            if(!$order){
                $result['message'] = 'Заказ не найден';
                break;
            }
            if(!$order->id_courier){
                $result['message'] = 'Курьер не определен';
                break;
            }
            $user_geo = DB::table('users_geo')->where('id_user', $order->id_courier)->orderByDesc('id')->first();

            $result['lat']      = $user_geo->lan;
            $result['lon']      = $user_geo->lon;
            $result['type']     = $user_geo->type;
            $result['date']     = $user_geo->created_at;
            $result['seconds']  = time()-strtotime($user_geo->created_at);
            $result['success'] = true;
        }while(false);
        return response()->json($result);
    }

    public function test_graphhopper (Request $request){
        $mode = $request->input("mode");

        $order = DB::table("orders")->first();

        $result = PushController::getPointsRoutinAndTime($order->from_geo, $order->to_geo, $mode);

        return response()->json($result);
    }

    public function whoIsDriver(Request $request){
        $key = $request->input("key");
        $id_allfood = $request->input("id_order");
        $type = $request->input("type");
        $result['success'] = false;

        if (!$key || $key != env("ALLFOOD_KEY")) exit("Error key");

        do{

            $order = DB::table('orders')
                ->where('id_allfood', $id_allfood)
                ->where('type', $type)
                ->first();

            if (!$order){
                $result['message'] = 'order not found';
                break;
            }

            if (!$order->id_courier){
                $result['message'] = 'driver не определен';
                break;
            }

            $driver = DB::table('users')
                ->where('id', $order->id_courier)
                ->first();

            if ($driver){
                $result['driver']['id'] = $driver->id;
                $result['driver']['name'] = $driver->name;
                $result['driver']['photo'] = $driver->photo;
                $result['driver']['phone'] = $driver->phone;
                $result['driver']['type'] = $driver->type;
                $result['success'] = true;

            }else{
                $result['message'] = 'driver не найден';
                break;
            }


        }while(false);

        return response()->json($result);

    }

}
