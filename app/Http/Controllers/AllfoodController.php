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
        $key = $request->input("key");

        if (!$key || $key != env("ALLFOOD_KEY")) exit("Error key");

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

    public function cancelOrder(Request $request)
    {
        $key = $request->input("key");
        if (!$key || $key != env("ALLFOOD_KEY")) exit("Error key");

        $id_allfood = $request->input("id_allfood");
        $who = $request->input("who");
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
                ->update(['status' => 9, "price_delivery" => "0"]);

            if (!$cancelSql) {
                $result['message'] = 'Ошибка при отмене';
                break;
            }
            OrderController::addCauseToCancelled($order->id, 0, $who, $prichina);

            if ($order->id_courier) {
                UserController::insertStateUserFunc($order->id_courier, 1);
                PushController::cancelFromCafeClient($order->id, $order->id_courier, $prichina);
            }

            $result['message'] = true;
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
                ->where("type", "$type")->first();

            if (!$order){
                $result['message'] = 'Заказ не найден';
                break;
            }

            $result['order'] = $order;
            $result['success'] = true;
        } while (false);

        return $result;
    }

}
