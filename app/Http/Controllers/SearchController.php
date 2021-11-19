<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    const MAX_FOOT_DRIVER = 1500;
    const MAX_VELO_DRIVER = 3000;
    const MAX_MOPED_DRIVER = 5000;
    const MAX_AUTO_DRIVER = 50000;


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
    public function searchNewOrder()
    {
        $result = array();
        $newOrders = DB::table("orders")
            ->select("id", "id_city", "distance","from_geo")
            ->where("status", 1)
            ->whereRaw("TIMESTAMPDIFF(MINUTE, NOW(), arrive_time) < 10")
            ->get();
        foreach ($newOrders as $newOrder) {
            $result['courier'][] = $this->searchCourier($newOrder);
            $result['order'][] = $newOrder;
        }

        return response()->json($result);
    }


    //! Поиск курьеров к заказу 1 стадия
    public function searchCourier($order)
    {

    $drivers = array();

        do {
            //Поиск пеших курьеров
            if($order->distance < self::MAX_FOOT_DRIVER){
                $c = $this->searchCourierSql("1", "1000", $order);
                if ($c){
                    $drivers[] = $c;
                    $this->offerToCourier($c->id_user, $order->id);
                    break;
                }
            }

            //Поиск велосипедных курьеров
            if($order->distance < self::MAX_VELO_DRIVER) {
                $c = $this->searchCourierSql("2", "2000", $order);
                if ($c){
                    $drivers[] = $c;
                    $this->offerToCourier($c->id_user, $order->id);
                    break;
                }
            }
            //Поиск мопедных курьеров
            if($order->distance < self::MAX_MOPED_DRIVER) {
                $c = $this->searchCourierSql("3", "4000", $order);
                if ($c){
                    $drivers[] = $c;
                    $this->offerToCourier($c->id_user, $order->id);
                    break;
                }
            }
            //Поиск авто курьеров
            if($order->distance < self::MAX_AUTO_DRIVER) {
                $c = $this->searchCourierSql("4", "10000", $order);
                if ($c){
                    $drivers[] = $c;
                    $this->offerToCourier($c->id_user, $order->id);
                    break;
                }
            }

        } while (false);

        return $drivers;
    }

    function offerToCourier($id_user, $id_order){

        OrderController::changeOrderCourierStatus($id_order, $id_user, 2);

        PushController::newOrderPush($id_user, $id_order);

        return true;

    }

    function searchCourierSql($type, $distance, $order){
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

        return DB::table("users_geo")
            ->selectRaw(" id_user, users_geo.type, users.state, ".$geo_sql)
            ->join("users", "users_geo.id_user", "=","users.id")
            ->where("users_geo.type",$type)
            ->where("users_geo.updated_at",">", date("Y-m-d H:i:s",time()-3600))
            ->where("users.state" ,2)
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
