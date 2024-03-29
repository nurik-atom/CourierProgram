<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class CashOnHandHistoryResource extends JsonResource
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
            'id'=>$this->id,
            'summa' => (int) $this->summa,
            'comment' => $this->comment,
            'cafe_name' => $this->id_order == 0 ? 'ALLFOOD' : $this->cafe_name,
            'date_short'=>Carbon::parse($this->created_at)->locale("ru_RU")->isoFormat('LLL')
        ];
    }
}
