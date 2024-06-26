<?php

namespace App\Http\Resources;

use App\Http\Controllers\OrderController;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */

    public function blobToArray($blob)
    {
        $blob = explode("\n", $blob);
        $result = array();
        foreach ($blob as $key => $b) {
            $f = explode("$$", $b);
            $result[$key]["count"] = @$f[0];
            $result[$key]["name"] = @$f[1];
            $result[$key]["price"] = @$f[2];
        }
        return $result;

    }

    public function prichinaOtmeny($id_order){
        $pricina = DB::table("orders_cancelled")->select("cause")
            ->where("id_order", $id_order)->orderByDesc("id")->first();

        if ($pricina) {
            return $pricina->cause;
        }
        else {
            return "";
        }
    }

    public static function getTextSposopOplaty($key){
        $res = array(
            1=>'Наличными',
            2=>'Банковской картой',
            3=>'KASPI GOLD',
            4=>'KASPI RED',
            5=>'Halyk Pay'
        );

        return $res[$key];
    }

    public static function getSeconds($order){
        $result = 0;

        if ($order->status == 2){
            $order_user = DB::table("order_user")->where("id_user", $order->id_courier)->orderByDesc("id")->first();

            $result = time() - strtotime($order_user->created_at);
        }

        if ($order->status <= 3) {
            $result = strtotime($order->arrive_time) - time();
        }

        if ($order->status == 4) {
            $result = $order->needed_sec;
        }

        if ($order->status == 5) {
            $start_time = DB::table("order_user")->where("id_order", $order->id)->where("status", 5)->select("created_at")->first();

            $result = $order->needed_sec + strtotime($start_time->created_at) - time();
        }

        return $result;
    }

    public function toArray($request)
    {
        // Comment ADD
        // Cafe Phone
        // price_delivery tekseru kerek

//        $date =  Carbon::parse($this->created_at,'Asia/Aqtau');
        return [
            "id" => $this->id,
            "id_city" => $this->id_city,
            "id_allfood" => $this->id_allfood,
            "type" => $this->type,
            "cafe_name" => $this->cafe_name,
            "cafe_phone" => $this->cafe_phone,
            "sposob_oplaty" => $this->getTextSposopOplaty($this->sposob_oplaty),
            "user_phone" => $this->phone,
            "user_name" => $this->name,
            "blob" => ($this->type == 1 ? $this->blobToArray($this->blob) : ""),
            "status" => $this->status,
            "comments" => $this->comments,
            "date"=>Carbon::parse($this->created_at)->locale("ru_RU")->isoFormat('LLLL'),
            "date_short"=>Carbon::parse($this->created_at)->locale("ru_RU")->isoFormat('LL'),
            "time"=>Carbon::parse($this->created_at)->locale("ru_RU")->isoFormat('LT'),
            "arrive_time"=>Carbon::parse($this->arrive_time)->locale("ru_RU")->isoFormat('LT'),
            "datetime"=>Carbon::parse($this->created_at)->locale("ru_RU")->isoFormat('LLL'),
            "created_at" => $this->created_at,
            "from_geo" => $this->from_geo,
            "from_address" => $this->from_address,
            "to_geo" => $this->to_geo,
            "prichina_otmeny" => ($this->status == 9 ? $this->prichinaOtmeny($this->id) : ""),
            "to_address" => $this->to_address,
            "summ_order" => $this->summ_order,
            "pay_to_cafe" => $this->pay_to_cafe,
            "price_delivery" => $this->price_delivery,
            "distance" => $this->distance,
            "routing_points" => json_decode($this->routing_points,true),
         //   "add_time" => Carbon::createFromFormat('Y-m-d H:i:s', $this->created_at)->timezone("Asia/Almaty")
            "add_time" =>Carbon::createFromFormat('Y-m-d H:i:s',$this->created_at),
            'seconds' => $this->getSeconds($this),
            "title_text" => $this->title_text,
            "button_text" => $this->button_text,
            "button_active" => $this->button_active,
        ];
    }
}
