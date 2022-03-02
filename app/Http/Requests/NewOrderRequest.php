<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NewOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'id_allfood'    =>'required|integer',
            'id_cafe'       =>'required|integer',
            'cafe_name'     =>'required|string',
            'phone'         =>'required|string',
            'name'          =>'required|string',
            'blob'          =>'required|string',
            'from_geo'      =>'required|string',
            'from_address'  =>'required|string',
            'to_geo'        =>'required|string',
            'to_address'    =>'required|string',
            'summ_order'    =>'required|numeric',
            'price_delivery'=>'required|numeric',
            'type'          =>'required|integer',
            'id_city'       =>'required|integer',
            'arrive_minute' =>'required|integer',
            'key'           =>'required|string'
        ];


    }
}
