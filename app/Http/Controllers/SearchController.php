<?php

namespace App\Http\Controllers;

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
            ->select("id", "id_city","id_allfood","type", "distance","from_geo", "to_geo", "price_delivery", "kef")
            ->where("status", 1)
            ->whereRaw("TIMESTAMPDIFF(MINUTE, NOW(), arrive_time) < 30")
            ->get();
        foreach ($newOrders as $newOrder) {
            $result['courier'][] = self::searchCourier($newOrder);
            $result['order'][] = $newOrder;


//            $mes['mess'] = 'Новый заказ в DRIVER # '.$newOrder->id_allfood. ' type = '.$newOrder->type;
//            PushController::sendReqToAllfood("test_search", $mes);
        }

        return response()->json($result);
    }

    public static function push_new_orders(){

        $newOrders = DB::table("orders")
            ->select("id", "id_city", "id_cafe", "id_allfood","type", "cafe_name")
            ->whereIn("status", [1,2])
            ->whereRaw("TIMESTAMPDIFF(MINUTE, NOW(), arrive_time) < 120")
            ->get();
        foreach ($newOrders as $newOrder) {
            $mes['mess'] = 'Новый '.($newOrder->type == 1 ? 'Заказ ALLFOOD' : 'Заявка').' в DRIVER # '.$newOrder->id_allfood;
            $mes['id_cafe'] = $newOrder->id_cafe;
            PushController::sendReqToAllfood("PushNewOrders", $mes);
        }
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

    //! Поиск курьеров к заказу 1 стадия
    public static function searchCourier($order)
    {
        $drivers = array();
        do {
            //Поиск пеших курьеров
            if($order->distance < self::MAX_FOOT_DRIVER){
                $c = self::searchCourierSql("1", "1000", $order);
                if ($c){
                    $drivers[] = $c;
                    self::offerToCourier($c, $order);
                    break;
                }
            }

            //Поиск велосипедных курьеров
            if($order->distance < self::MAX_VELO_DRIVER) {
                $c = self::searchCourierSql("2", "2000", $order);
                if ($c){
                    $drivers[] = $c;
                    self::offerToCourier($c, $order);
                    break;
                }
            }
            //Поиск мопедных курьеров
            if($order->distance < self::MAX_MOPED_DRIVER) {
                $c = self::searchCourierSql("3", "4000", $order);
                if ($c){
                    $drivers[] = $c;
                    self::offerToCourier($c, $order);
                    break;
                }
            }
            //Поиск авто курьеров
            if($order->distance < self::MAX_AUTO_DRIVER) {
                $c = self::searchCourierSql("4", "10000", $order);
                if ($c){
                    $drivers[] = $c;
                    self::offerToCourier($c, $order);
                    break;
                }
            }

        } while (false);

        return $drivers;
    }

    public static function offerToCourier($user, $order){

        $matrix = PushController::getPointsRoutinAndTime($order->from_geo, $order->to_geo, $user->type);
        if ($order->type == 1){
            $price_delivery = MoneyController::costDelivery($matrix['distance'], $user->type);
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
                "price_delivery" => $price_delivery * $order->kef,
                'distance_to_cafe' => $user->distance
            ]);

        OrderController::changeOrderCourierStatus($order->id, $user->id, 2);

        PushController::newOrderPush($user->id, $order->id);

        return true;

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
            ->selectRaw("users.id as id, id_user, users_geo.type, users.state, ".$geo_sql)
            ->join("users", "users_geo.id_user", "=","users.id")
            ->where("users_geo.type",$type)
            ->where("users_geo.updated_at",">", date("Y-m-d H:i:s",time()-14400))
            ->where("users.state" ,1)
            //->whereNotIn("users.id" ,$not_users_id)
            ->having("distance", "<",$distance)
            ->orderByDesc("users.rating")
            ->first();

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
