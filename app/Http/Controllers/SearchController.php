<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Slots\SlotsBackController;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    const MAX_FOOT_DRIVER  = 1500;
    const MAX_VELO_DRIVER  = 3000;
    const MAX_MOPED_DRIVER = 5000;
    const MAX_AUTO_DRIVER  = 50000;


    function insertTestGeoPositon(){
//        $test_user = DB::table("users")->where("phone", "77089222820")->first();
        $coordinates = array();
        $coordinates[] = array("lat"=>'43.336915', 'lon'=>'52.859859', 'type'=>1, "id_user" =>1);
        $coordinates[] = array("lat"=>'43.333543', 'lon'=>'52.850418', 'type'=>2, "id_user" =>2);
        $coordinates[] = array("lat"=>'43.340535', 'lon'=>'52.857799', 'type'=>3, "id_user" =>3);
        $coordinates[] = array("lat"=>'43.340161', 'lon'=>'52.843723', 'type'=>4, "id_user" =>4);
        foreach ($coordinates as $c) {
            $insert = DB::table("users_geo")
                ->insert(["id_user"=>$c['id_user'],
                    "lan"=>$c['lat'],
                    "lon"=>$c['lon'],
                    "type"=>$c['type'],
                    "created_at"=>Carbon::now(),
                    "updated_at"=>Carbon::now()
                ]);
        }

    }

    //* Поиск новых заказов Нулевой стадия
    public static function searchNewOrder()
    {
        $result = array();
        $newOrders = DB::table("orders")
            ->select("id", "id_cafe", "id_city","id_allfood","type", "distance_matrix","from_geo", "to_geo", "price_delivery", "kef")
            ->where("status", 1)
            ->whereRaw("TIMESTAMPDIFF(MINUTE, NOW(), arrive_time) < 20")
            ->get();
        foreach ($newOrders as $newOrder) {
//            $result['courier'][] = self::searchCourier($newOrder);
            $founded = self::searchCourierV2($newOrder);
            $result['courier'][] = $founded;
            $result['order'][] = $newOrder;

            if (!$founded){
                $mes['mess'] = '🔎 ❌ Не можем найти курьера для заказа  # '.$newOrder->id;
                $mes['id_cafe'] = $newOrder->id_cafe;
                PushController::sendReqToAllfood("PushNewOrders", $mes);
            }

        }

        return response()->json($result);
    }

    public static function push_new_orders(){

        $newOrders = DB::table("orders")
            ->select("id", "id_city", "id_cafe", "id_allfood","type", "cafe_name")
            ->whereIn("status", [1,2])
            ->whereRaw("TIMESTAMPDIFF(MINUTE, NOW(), arrive_time) < 10")
            ->get();
        foreach ($newOrders as $newOrder) {
            $mes['mess'] = 'Новый '.($newOrder->type == 1 ? 'Заказ ALLFOOD' : 'Заявка').' в DRIVER # '.$newOrder->id_allfood;
            $mes['id_cafe'] = $newOrder->id_cafe;
            PushController::sendReqToAllfood("PushNewOrders", $mes);
        }

        //$mes['mess'] = 'Пока заказов нет '.date('H:i:s');
        //$mes['id_cafe'] = 7;
        //PushController::sendReqToAllfood("PushNewOrders", $mes);
        return $newOrders;

    }

    public static function fallBehindOrders(){
        $result = array();
//        $fallBehindOrders = DB::table("orders")
//            ->select("id", "id_courier", "id_city", "distance","from_geo", "to_geo")
//            ->where("status", 2)
//            ->whereRaw("TIMESTAMPDIFF(SECOND, created_at, NOW()) > 50")
//            ->get();
//
//        foreach ($fallBehindOrders as $o) {
//            OrderController::refusingOrder($o->id_courier, $o->id, 11, null);
//            PushController::refusingFallBehindOrder($o->id,$o->id_courier);
//            DB::table("orders")->where("id", $o->id)
//                ->update(['status' => 1, 'id_courier' => 0]);
//
//            UserController::insertStateUserFunc($o->id_courier, 1);
//
//            $result['courier'][] = self::searchCourier($o);
//            $result['order'][] = $o;
//        }

        return response()->json($result);
    }

    public static function searchCourierSql($type, $distance, $order){
        $from_lat = explode("\n", $order->from_geo)[0];
        $from_lon = explode("\n", $order->from_geo)[1];

        $not_users_id = DB::table("order_user")
            ->where("id_order", $order->id)
            ->pluck("id_user")
            ->toArray();

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

        return DB::table("users_geo")
            ->selectRaw("users.id as id, users.name as name,users.photo as photo, users.phone as phone,  id_user, users_geo.type, users.state, ".$geo_sql)
            ->join("users", "users_geo.id_user", "=","users.id")
            ->where("users_geo.type", $type)
            ->where("users_geo.updated_at",">", date("Y-m-d H:i:s",time()-3600))
            ->where("users.state" ,1)
            //->whereNotIn("users.id" ,$not_users_id)
            ->having("distance", "<",$distance)
//            ->orderByDesc("users.sort_rating")
            ->orderBy('distance')
            ->first();

    }

    public static function offerToCourier($user, $order){

        $matrix = PushController::getPointsRoutinAndTime($order->from_geo, $order->to_geo, $user->type);
        if ($order->type == 1){
            $price_delivery = MoneyController::costDelivery($matrix['distance'], $user->type, $order->kef, SlotsBackController::driverSlotKefForCost($user->id));
        }else{
            $price_delivery = $order->price_delivery;
        }


        //$price_delivery = MoneyController::costDelivery($order->distance, $user->type);

        //$matrix = PushController::getPointsRoutinAndTime($order->from_geo, $order->to_geo, $user->type);

        DB::table("orders")->where("id", $order->id)
            ->update([
                "needed_sec" => $matrix['time'] > 450 ? $matrix['time'] : 450,
                "distance_matrix" => $matrix['distance'],
                "routing_points" => $matrix['route_points'],
                "mode" => $user->type,
                "price_delivery" => $price_delivery,
                'distance_to_cafe' => $user->distance
            ]);

        OrderController::changeOrderCourierStatus($order->id, $user->id, 2);

        PushController::newOrderPush($user->id, $order->id);

        return true;

    }

    public static function pushToLatedDrivers(){
        $lated_orders = DB::table('orders', 'o')
            ->leftJoin('users as u', 'o.id_courier', '=', 'u.id')
            ->select('o.id', 'o.id_courier', 'o.id_cafe', 'o.cafe_name', 'u.name')
            ->where('o.status', 3)
            ->where('o.arrive_time', '<', Carbon::now())
            ->get();

        if ($lated_orders){
            foreach ($lated_orders as $key => $o){

                $mes['mess'] = '🕔 '.$o->name.' опаздывает на заказ # '.$o->id;
                $mes['id_cafe'] = $o->id_cafe;
                PushController::sendReqToAllfood("PushNewOrders", $mes);
            }
        }
    }

    //! New Search Drivers
    public static function searchCourierV2($order)
    {
        $c = array();
        do {
            //Поиск пеших курьеров
            if($order->distance_matrix < self::MAX_FOOT_DRIVER){
                $c = self::searchCourierSql("1", "1000", $order);
                if ($c){
                    self::offerToCourier($c, $order);
                    break;
                }
            }

            //Поиск велосипедных курьеров
            if($order->distance_matrix < self::MAX_VELO_DRIVER) {
                $c = self::searchCourierSql("2", "1500", $order);
                if ($c){
                    self::offerToCourier($c, $order);
                    break;
                }
            }
            //Поиск мопедных курьеров
            if($order->distance_matrix < self::MAX_MOPED_DRIVER) {
                if ($c = self::searchFreeDriverInRadius("3", "2000", $order)){
                    self::offerToCourier($c, $order);
                }elseif($c = self::searchStatus_3_NearDriver("3", "3000", $order)){
                    self::assignToCourier($c, $order, '2 шаг мото');
                }elseif($c = self::searchStatus_5_NearDriver("3", "2000", $order)){
                    self::assignToCourier($c, $order, '3 шаг мото');
                }elseif ($c = self::searchFreeDriverInRadius("3", "6000", $order)){
                    self::offerToCourier($c, $order);
                }
                if ($c) break;
            }
            //Поиск авто курьеров
            if($order->distance_matrix < self::MAX_AUTO_DRIVER) {

                if ($c = self::searchFreeDriverInRadius("4", "2000", $order)){
                    self::offerToCourier($c, $order);
                }elseif($c = self::searchStatus_3_NearDriver("4", "5000", $order)){
                    self::assignToCourier($c, $order, '2 шаг авто');
                }elseif($c = self::searchStatus_5_NearDriver("4", "3000", $order)){
                    self::assignToCourier($c, $order, '3 шаг авто');
                }elseif ($c = self::searchFreeDriverInRadius("4", "12000", $order)){
                    self::offerToCourier($c, $order);
                }
                if ($c) break;
            }

        } while (false);

        return $c;
    }

    public static function assignToCourier($user, $order, $shag = ''){

        $matrix = PushController::getPointsRoutinAndTime($order->from_geo, $order->to_geo, $user->type);
        if ($order->type == 1){
            $price_delivery = MoneyController::costDelivery($matrix['distance'], $user->type, $order->kef, SlotsBackController::driverSlotKefForCost($user->id));
        }else{
            $price_delivery = $order->price_delivery;
        }

        DB::table("orders")->where("id", $order->id)
            ->update([
                "needed_sec" => $matrix['time'] > 450 ? $matrix['time'] : 450,
                "distance_matrix" => $matrix['distance'],
                "routing_points" => $matrix['route_points'],
                "mode" => $user->type,
                "price_delivery" => $price_delivery,
                'distance_to_cafe' => 0
            ]);

        OrderController::changeOrderCourierStatus($order->id, $user->id, 3);

        PushController::sendDataPush($user->id,
            array('type' => 'order', 'status' => 'new_order'),
            array('title'=>'Новый заказ',
                'body'=>'Заказ на сумму '.$order->price_delivery.' тенге'));

        PushController::takedOrderAllfood($order, $user, "5");

        $mes['mess'] = '🤖 Авто-назначение заказа #'.$order->id.' на '.$user->name.' ('.$shag.') ';
        $mes['id_cafe'] = $order->id_cafe;
        PushController::sendReqToAllfood("PushNewOrders", $mes);

        return true;
    }

    public static function searchFreeDriverInRadius($type, $distance, $order){
        $from_lat = explode("\n", $order->from_geo)[0];
        $from_lon = explode("\n", $order->from_geo)[1];

        $not_users_id = DB::table("order_user")
            ->where("id_order", $order->id)
            ->pluck("id_user")
            ->toArray();

        $geo_sql = "( 6371000 *
                    ACOS(
                        COS( RADIANS( {$from_lat} ) ) *
                        COS( RADIANS( g.lan ) ) *
                        COS( RADIANS( g.lon ) -
                        RADIANS( {$from_lon} ) ) +
                        SIN( RADIANS( {$from_lat} ) ) *
                        SIN( RADIANS( g.lan) )
                    )
                )
                AS distance";

        $find = DB::table('users' , 'u')->leftJoin('users_geo as g', 'u.id', '=', 'g.id_user')
            ->selectRaw('u.id, g.type, u.state, '.$geo_sql)
            ->where('u.state', 1)
            ->where("u.type", $type)
            ->whereNotIn("u.id" ,$not_users_id)
            ->where("g.updated_at",">", date("Y-m-d H:i:s",time()-3600))
            ->having("distance", "<",$distance)
            ->orderBy('distance')
            ->first();

        if ($find){
            $find->searchFreeDriverInRadius_distance = $distance;
        }


        return $find;

    }

    public static function searchStatus_3_NearDriver($type, $distance, $order){
        $to_lat = explode("\n", $order->to_geo)[0];
        $to_lon = explode("\n", $order->to_geo)[1];

        $not_users_id = DB::table("order_user")
            ->where("id_order", $order->id)
            ->pluck("id_user")
            ->toArray();

        $not_users_id_2 = DB::table('orders')
            ->whereNotIn('status', [7,9])
            ->groupBy('id_courier')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('id_courier')
            ->toArray();

        $geo_sql = "( 6371000 *
                    ACOS(
                        COS( RADIANS( {$to_lat} ) ) *
                        COS( RADIANS( SUBSTRING_INDEX(o.to_geo, '\n', 1) ) ) *
                        COS( RADIANS( SUBSTRING_INDEX(o.to_geo, '\n', -1) ) -
                        RADIANS( {$to_lon} ) ) +
                        SIN( RADIANS( {$to_lat} ) ) *
                        SIN( RADIANS( SUBSTRING_INDEX(to_geo, '\n', 1)) )
                    )
                )
                AS distance";

        $find = DB::table('users', 'u')
            ->leftJoin('orders as o', 'u.id', '=', 'o.id_courier')
            ->leftJoin('users_geo as g', 'u.id', '=', 'g.id_user')
            ->selectRaw('u.id, u.type, u.state, u.name, u.photo, u.phone, '.$geo_sql)
            ->where('u.state', 3)
            ->where('u.type', $type)
            ->whereNotIn("u.id" ,$not_users_id)
            ->whereNotIn("u.id" ,$not_users_id_2)
            ->where("g.updated_at",">", date("Y-m-d H:i:s",time()-3600))
            ->where('o.status', '=', 3)
            ->where('o.id_cafe', '=', $order->id_cafe)
            ->having("distance", "<",$distance)
            ->orderBy('distance')
            ->first();

        if ($find){
            $find->searchStatus_3_NearDriver = $distance;
        }

        return $find;

    }

    public static function searchStatus_5_NearDriver($type, $distance, $order){
        $from_lat = explode("\n", $order->from_geo)[0];
        $from_lon = explode("\n", $order->from_geo)[1];

        $not_users_id = DB::table("order_user")
            ->where("id_order", $order->id)
            ->pluck("id_user")
            ->toArray();

        $not_users_id_2 = DB::table('orders')
            ->whereNotIn('status', [7,9])
            ->groupBy('id_courier')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('id_courier')
            ->toArray();

        $geo_sql = "( 6371000 *
                    ACOS(
                        COS( RADIANS( {$from_lat} ) ) *
                        COS( RADIANS( SUBSTRING_INDEX(o.to_geo, '\n', 1) ) ) *
                        COS( RADIANS( SUBSTRING_INDEX(o.to_geo, '\n', -1) ) -
                        RADIANS( {$from_lon} ) ) +
                        SIN( RADIANS( {$from_lat} ) ) *
                        SIN( RADIANS( SUBSTRING_INDEX(to_geo, '\n', 1)) )
                    )
                )
                AS distance";

        $find = DB::table('users', 'u')
            ->leftJoin('orders as o', 'u.id', '=', 'o.id_courier')
            ->leftJoin('users_geo as g', 'u.id', '=', 'g.id_user')
            ->selectRaw('u.id, u.type, u.state, '.$geo_sql)
            ->whereIn('u.state', [4,5,6])
            ->where('u.type', $type)
            ->whereNotIn("u.id" ,$not_users_id)
            ->whereNotIn("u.id" ,$not_users_id_2)
            ->where("g.updated_at",">", date("Y-m-d H:i:s",time()-3600))
            ->whereIn('o.status', [4,5,6])
            ->having("distance", "<",$distance)
            ->orderBy('distance')
            ->first();

        if ($find){
            $find->searchStatus_5_NearDriver = $distance;
        }

        return $find;

    }

    public static function getDistance($from, $to)
    {
        $earthRadius = 6371000;
        // convert from degrees to radians
        $latFrom = deg2rad(explode("\n", $from)[0]);
        $lonFrom = deg2rad(explode("\n", $from)[1]);
        $latTo = deg2rad(explode("\n", $to)[0]);
        $lonTo = deg2rad(explode("\n", $to)[1]);


        $lonDelta = $lonTo - $lonFrom;
        $a = pow(cos($latTo) * sin($lonDelta), 2) +
            pow(cos($latFrom) * sin($latTo) - sin($latFrom) * cos($latTo) * cos($lonDelta), 2);
        $b = sin($latFrom) * sin($latTo) + cos($latFrom) * cos($latTo) * cos($lonDelta);

        $angle = atan2(sqrt($a), $b);
        return (int)($angle * $earthRadius);
    }
}
