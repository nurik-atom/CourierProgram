<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderResource;
//use Illuminate\Http\Request;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\RatingController;
use GuzzleHttp\Psr7\Request;

class PushController extends Controller
{

    public function testCurl(){
//        return self::takedOrderAllfood(555,222, 5);

        $from = "43.336142\n52.855964";
        $to = "43.339474\n52.885699";
        return $this->getDurationv2($from, $to, 4);
    }

    public function getDurationv2($from, $to, $mode){
        $modes[1] = "foot-walking";
        $modes[2] = "cycling-regular";
        $modes[3] = "cycling-electric";
        $modes[4] = "driving-car";

        $from = explode("\n", $from);
        $to   = explode("\n", $to);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://api.openrouteservice.org/v2/matrix/".$modes[$mode]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);

        curl_setopt($ch, CURLOPT_POST, TRUE);

        curl_setopt($ch, CURLOPT_POSTFIELDS, '{"locations":[['.$from[0].','.$from[1].'],['.$to[0].','.$to[1].']],"metrics":["duration","distance"],"units":"km"}');

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Accept: application/json, application/geo+json, application/gpx+xml, img/png; charset=utf-8",
            "Authorization: ".env("OPEN_ROUTE_SERVICE"),
            "Content-Type: application/json; charset=utf-8"
        ));

        $response = curl_exec($ch);
        curl_close($ch);
        $response = json_decode($response, true);
        return $response;


    }

    public static function getDistanceDurationGoogle($from, $to, $mode){

        $modes[1] = "walking";
        $modes[2] = "walking";
        $modes[3] = "walking";
        $modes[4] = "driving";

        $from = explode("\n", $from);
        $to   = explode("\n", $to);

        $url = "https://maps.googleapis.com/maps/api/distancematrix/json?origins=".$from[0].",".$from[1]."&destinations=".$to[0].",".$to[1]."&mode=".$modes[$mode]."&language=ru&key=".env("GOOGLE_MATRIX");
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_PROXYPORT, 3128);
//        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
//        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $response = curl_exec($ch);
        curl_close($ch);
        $response_a = json_decode($response, true);

        $res['dist'] = $response_a['rows'][0]['elements'][0]['distance']['text'];
        $res['time'] = $response_a['rows'][0]['elements'][0]['duration']['text'];
        $res['dist_value'] = $response_a['rows'][0]['elements'][0]['distance']['value'];
        $res['time_value'] = $response_a['rows'][0]['elements'][0]['duration']['value'];

        return $res;
    }

    public static function newOrderPush($user,$id_order){

        $order = DB::table("orders")->where("id", $id_order)->select("id", "price_delivery")->first();
        $data = array();
//        $data['order'] = OrderResource::collection($order);
        $data['type'] = 'newOrder';
        $message['title'] = "Новый заказ";
        $message['body'] = "Заказ на сумму ".$order->price_delivery.' тенге';

        self::sendDataPush($user, $data, $message);
        $mes['mess'] = 'Новый заказ '. $order->id.' Courier #'.$user;
        self::sendReqToAllfood("test_search", $mes);
    }

    public static function sendDataPush($user, $data, $message){
        if (is_numeric($user)){
            $token = DB::table("users")->where("id",$user)->pluck("token")->first();
        }else{
            $token = $user;
        }
        $data_notif = [
            "to"=>$token,
            "notification"=>[
                "title"=>$message['title'],
                "body"=>$message['body']
            ]
        ];
        if ($data){
            $data_notif['data'] =  (array)$data;
        }

        $headers = array(
            'Authorization: '.env("FIREBASE_AUTH"),
            'Content-Type: application/json'
        );
        $url = "https://fcm.googleapis.com/fcm/send";


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 100);
//        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data_notif));
        $result = curl_exec($ch);
        curl_close($ch);
//
        return $result;
    }

    public static function takedOrderAllfood($id_order, $id_courier, $time){
        $req['id_order']    = $id_order;
        $req['id_courier']  = $id_courier;
        $req['time']        = $time;

        return self::sendReqToAllfood("taked_order", $req);
    }

    public static function courierInCafe($id_order, $id_courier){
        $req['id_order']    = $id_order;
        $req['id_courier']  = $id_courier;

        return self::sendReqToAllfood("courierInCafe", $req);
    }

    public static function startDeliveryOrder($id_order, $id_courier, $time){
        $req['id_order']    = $id_order;
        $req['id_courier']  = $id_courier;
        $req['time']        = $time;

        return self::sendReqToAllfood("start_delivery", $req);
    }

    public static function courierAtTheClient($id_order, $id_courier){
        $req['id_order']    = $id_order;
        $req['id_courier']  = $id_courier;

        return self::sendReqToAllfood("end_delivery", $req);
    }

    public static function finishDeliveryOrder($id_order, $id_courier){
        $req['id_order']    = $id_order;
        $req['id_courier']  = $id_courier;

        return self::sendReqToAllfood("end_delivery", $req);
    }

    public static function cancelFromCafeClient($id_order, $id_user, $prichina){
        $data['type']  = 'cancelOrder';
        $mess['title'] = 'Заказ №'.$id_order. 'отменен';
        $mess['body']  = 'Причина: '.$prichina;
        self::sendDataPush($id_user, $data, $mess);
    }

    public static function sendReqToAllfood($url, $post){

        $url = "https://allfood.kz/need_courier/".$url;
        $post['key'] = md5("ALL".date("Ymd")."FOOD");

        $post_str = http_build_query($post);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_str);
        $result = curl_exec($ch);
        curl_close($ch);

        return $result;

    }

    public static function sendNotification($type, $to, $title, $body){
        $data_notif = [
            "notification"=>[
                "title"=>$title,
                "body"=>$body
            ],
            "data"=>[
                "type"=>'notification'
            ]
        ];

        if ($type == 1){
            $data_notif['registration_ids'] = $to;
        }elseif ($type == 2){
            $data_notif['to'] = $to;
        }

        $headers = array(
            'Authorization: '.env("FIREBASE_AUTH"),
            'Content-Type: application/json'
        );
        $url = "https://fcm.googleapis.com/fcm/send";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data_notif));
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
}
