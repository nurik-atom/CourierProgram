<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function newOrder(Request $request)
    {
        $id_allfood = $request->input('id_allfood');
        $phone = $request->input('phone');
        $name = $request->input('name');
        $blob = $request->input('blob');
        $status = 1;
        $id_courier = 0;

        $result['success'] = false;

        do {

            $order = DB::table("orders")->select("id")->where('id_allfood', $id_allfood)->first();
            if ($order) {
                $result['message'] = 'Заказ уже добавлен';
                break;
            }

            if (!$id_allfood) {
                $result['message'] = 'id allfood не Найден';
                break;
            }
            if (!$phone) {
                $result['message'] = 'Номер телефона клиента не найден';
                break;
            }
            if (!$name) {
                $result['message'] = 'Имя пользователя не найден';
                break;
            }
            if (!$blob) {
                $result['message'] = 'Описание заказа не найден';
                break;
            }

            $new_order = DB::table("orders")->insertGetId([
                'id_allfood' => $id_allfood,
                'phone' => $phone,
                'name' => $name,
                'blob' => $blob,
                'status' => $status,
                'id_courier' => $id_courier,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            $result['success'] = true;


        } while (false);
        return response()->json($result);
    }

}
