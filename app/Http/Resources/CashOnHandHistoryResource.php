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
            'cafe_name' => (int) $this->cafe_name,
            'date_short'=>Carbon::parse($this->created_at)->locale("ru_RU")->isoFormat('LLL')
        ];
    }
}
