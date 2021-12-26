<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class NotifResource extends JsonResource
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
            "name"=>$this->name,
            "short_text"=>$this->short_text,
            "full_text"=>$this->full_text,
            "date"=>Carbon::parse($this->created_at)->locale("ru_RU")->diffForHumans()
        ];
    }
}
