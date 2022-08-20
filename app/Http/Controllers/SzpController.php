<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SzpController extends Controller
{
    public function getAllDrivers (Request $request){
        $pass = $request->input('pass');
        if ($pass != sha1('ALL'.date('Ymd').'FOOD_2201')){
            exit('Error Key');
        }

        $users = DB::table('users')
            ->select('id', 'name', 'surname', 'id_city', 'phone', 'status', 'rating','state')
            ->get();
        $result['users']   = $users;
        $result['success'] = true;
        return response()->json($result);
    }
}
