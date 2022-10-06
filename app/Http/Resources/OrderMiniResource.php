<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderMiniResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            "id"=>$this->id,
            "id_driver"=>$this->id_courier,
            "cafe_name"=>$this->cafe_name,
            "date"=>Carbon::parse($this->created_at)->locale("ru_RU")->isoFormat('LLL'),
            "status"=>$this->status,
            "price_delivery"=>$this->price_delivery,
            "sposob_oplaty"=>$this->sposob_oplaty,
            "summ_order"=>$this->summ_order
        ];
    }
}
