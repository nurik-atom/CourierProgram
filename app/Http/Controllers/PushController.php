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

    public static function newOrderPush($user,$id_order){

        $order = DB::table("orders")->where("id", $id_order)->get();
        $data = array();
        $data['order'] = OrderResource::collection($order);
        $message['title'] = "Новый заказ";
        $message['body'] = "Заказ на сумму ".$order[0]->price_delivery.' тенге';

        self::sendDataPush($user, $data, $message);
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
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data_notif));
        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }


    public static function sendAsynchPush(){
        $curl = new Client();
        $request = $curl->postAsync("https://fcm.googleapis.com/fcm/send",[
            "to"=>"c1EFZ6d8QAqJt6avg4Qc3a:APA91bE4EVnF1MZgbmNJXBs7CcjBLpDQUMdBkt-L8zd3u0Rof0gBhQ_36UPh8KIqQX6oagzovcpK0NEXFvX9vrMxleuNvUua_XXRuk2jwE27lnXMfWbsfXfFWGjI_-G1gYVDGaXiiSg4",
            "notification"=>[
                "title"=>"Title Async",
                "body"=>"Body Asynch"
            ]
        ]);
        // Create a PSR-7 request object to send
        $headers = ['X-Foo' => 'Bar'];
        $body = 'Hello!';
        $request = new Request('HEAD', 'http://httpbin.org/head', $headers, $body);
        $promise = $client->sendAsync($request);

// Or, if you don't need to pass in a request instance:
        $promise = $client->requestAsync('GET', 'http://httpbin.org/get');



    }

    public static function sendReqToAllfood($url, $post){



        $headers = array('Content-Type: application/json');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
        $result = curl_exec($ch);
        curl_close($ch);

        return $result;

    }
}
