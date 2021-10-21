<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    const MAX_FOOT_DRIVER = 1500;
    const MAX_VELO_DRIVER = 3000;
    const MAX_MOPED_DRIVER = 5000;
    const MAX_AUTO_DRIVER = 50000;


    public function searchNewOrder()
    {
        $result = array();
        $newOrders = DB::table("orders")->select("id", "id_city", "distance","from_geo")->where("status", 1)->get();
        foreach ($newOrders as $newOrder) {
            $result[] = $this->searchCourier($newOrder);
        }

        return response()->json($result);
    }

    public function searchCourier($order)
    {
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


        do {
            //Поиск пеших курьеров
            if($order->distance < self::MAX_FOOT_DRIVER){
                $drivers = DB::select("SELECT id_user, type, ".$geo_sql." FROM users_geo WHERE type=2 AND created_at>'".date("Y-m-d H:i:s",time()-120)."' HAVING distance < 5000 ");
            }

        } while (false);

        return $drivers;
    }

    public static function getDistance($from, $to, $earthRadius = 6371000)
    {
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
        return intval($angle * $earthRadius);
    }
}
