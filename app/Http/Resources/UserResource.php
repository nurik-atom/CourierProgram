<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */

    public function toArray($request)
    {
        $types = array(1=>'Пешый курьер', 2=>'Вело курьер', 3=>'Мото курьер', 4=>'Авто курьер');
        return [
            'id'=>$this->id,
            'id_city'=>$this->id_city,
            'name'=>$this->name,
            'surname'=>$this->surname,
            'birthday'=>$this->birthday,
            'mini_photo'=>$this->photo ? 'https://courier.qala.kz'.Storage::url($this->photo) : null,
            'photo'=>$this->photo ? 'https://courier.qala.kz'.Storage::url($this->photo) : null,
            'phone'=>"+".$this->phone,
            'status'=>$this->status,
            'type'=>$this->type,
            'type_text'=>($this->type ? $types[$this->type] : ""),
            'state'=>$this->state,
            'rating'=>$this->rating
        ];
    }
}
