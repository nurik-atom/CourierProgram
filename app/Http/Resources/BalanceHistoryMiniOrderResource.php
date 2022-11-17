<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class BalanceHistoryMiniOrderResource extends JsonResource
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
            "id"=>$this->id_order,
            "id_driver"=>$this->id_user,
            "cafe_name"=>($this->id_order == 0 ? 'ALLFOOD' : $this->cafe_name),
            "date"=>Carbon::parse($this->created_at)->locale("ru_RU")->isoFormat('LLL'),
            "status"=>$this->status,
            "price_delivery"=>$this->amount,
            "sposob_oplaty"=>$this->sposob_oplaty,
            "summ_order"=>$this->summ_order
        ];
    }
}
