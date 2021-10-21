<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
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
        $distance = SearchController::getDistance($from_geo,$to_geo);
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
                'distance'=>$distance,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            $result['success'] = true;
            $result['id'] = $new_order_id;



        } while (false);
        return response()->json($result);
    }

    public function cancelOrder(Request $request)
    {
        $pass = $request['pass'];
        $id_order = $request['id_order'];
        $result['success'] = false;
        do {
            if ($pass != 'ALLFOOD123'){
                $result['message'] = 'Пароль неверный';
                break;
            }
            $status = DB::table("orders")->where("id",$id_order)->pluck("status")->first();

            if($status == 9){
                $result['message'] = 'Заказ уже отменен';
                break;
            }

            if(!$status){
                $result['message'] = 'Заказ не найден';
                break;
            }

            $cancelSql = DB::table("orders")->where('id',$id_order)->update(['status' =>9]);
            if (!$cancelSql){
                $result['message'] = 'Произошло ошибка';
            }else
                $result['success'] = true;

        } while (false);
        return response()->json($result);
    }

}
