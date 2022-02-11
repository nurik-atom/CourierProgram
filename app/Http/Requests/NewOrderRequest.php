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
            'cafe_name'     =>'required',
            'phone'         =>'required',
            'name'          =>'required',
            'blob'          =>'required',
            'from_geo'      =>'required|numeric',
            'from_address'  =>'required',
            'to_geo'        =>'required|numeric',
            'to_address'    =>'required',
            'summ_order'    =>'required|numeric',
            'price_delivery'=>'required|numeric',
            'type'          =>'required|integer',
            'id_city'       =>'required|integer',
            'arrive_minute' =>'required|integer',
            'key'           =>'required|integer'
        ];
    }
}
