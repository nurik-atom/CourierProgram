<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

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

    public function toArray($request)
    {
        // Comment ADD
        // Cafe Phone
        // price_delivery tekseru kerek


        $date =  Carbon::parse($this->created_at,'Asia/Aqtau');
        return [
            "id" => $this->id,
            "id_city" => $this->id_city,
            "id_allfood" => $this->id_allfood,
            "type" => $this->type,
            "cafe_name" => $this->cafe_name,
            "cafe_phone" => $this->cafe_phone,
            "user_phone" => $this->phone,
            "user_name" => $this->name,
            "blob" => ($this->type == 1 ? $this->blobToArray($this->blob) : ""),
            "status" => $this->status,
            "comments" => $this->comments,
            "created_at" => $this->created_at,
            "from_geo" => $this->from_geo,
            "from_address" => $this->from_address,
            "to_geo" => $this->to_geo,
            "to_address" => $this->to_address,
            "summ_order" => $this->summ_order,
            "price_delivery" => $this->price_delivery,
            "distance" => $this->distance,
            "routing_points" => json_decode($this->routing_points,true),
         //   "add_time" => Carbon::createFromFormat('Y-m-d H:i:s', $this->created_at)->timezone("Asia/Almaty")
            "add_time" =>Carbon::createFromFormat('Y-m-d H:i:s',$this->created_at),
        ];
    }
}
