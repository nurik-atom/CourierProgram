<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */

    public function blobToArray($blob){
        $blob = explode("\n", $blob);
        $result = array();
        foreach ($blob as $key => $b){
            $f = explode("$$", $b);
            $result[$key]["count"] = $f[0];
            $result[$key]["name"]  = $f[1];
            $result[$key]["price"] = $f[2];
        }
        return $result;

    }
    public function toArray($request)
    {
        return [
            "id"=>$this->id,
            "id_city"=>$this->id_city,
            "id_allfood"=>$this->id_allfood,
            "cafe_name"=>$this->cafe_name,
            "phone"=>$this->phone,
            "name"=>$this->name,
            "blob"=> ($this->type == 1 ? $this->blobToArray($this->blob) : ""),
            "status"=>$this->status,
            "created_at"=>$this->created_at,
            "from_geo"=>$this->from_geo,
            "from_address"=>$this->from_address,
            "to_geo"=>$this->to_geo,
            "to_address"=>$this->to_address,
            "summ_order"=>$this->summ_order,
            "price_delivery"=>$this->price_delivery,
            "type"=>$this->type,
            "distance"=>$this->distance
        ];
    }
}