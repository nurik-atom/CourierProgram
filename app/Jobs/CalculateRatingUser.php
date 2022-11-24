<?php

namespace App\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class CalculateRatingUser implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */

    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $id_courier = $this->data;

        $avg_star = DB::table("rating")
            ->where("id_courier", $id_courier)
            ->where("created_at", ">", Carbon::now()->subDays(15))
            ->avg("star");


        $count_all_offer = DB::table("order_user")
            ->where("id_user", $id_courier)
            ->where("status", 2)
            ->where("created_at", ">", Carbon::now()->subDays(15))
            ->count();

        $count_propusk = DB::table("order_user")
            ->where("id_user", $id_courier)
            ->where("created_at", ">", Carbon::now()->subDays(15))
            ->whereIn("status", [11,12])
            ->count();

        $count_orders = DB::table("orders")
            ->where("id_courier", $id_courier)
            ->where("created_at", ">", Carbon::now()->subDays(15))
            ->count();

        $count_during_orders = DB::table("orders")
            ->where("id_courier", $id_courier)
            ->where("needed_sec", ">=", "duration_sec")
            ->where("created_at", ">", Carbon::now()->subDays(15))
            ->count();

        $count_today = DB::table("order_user")
            ->where("id_user", $id_courier)
            ->where("created_at", ">", Carbon::now()->subHour(5))
            ->where("status", "=", 7)->count();


        $percent_success = 1 - $count_propusk/ ($count_all_offer ?: 1);
        $percent_during  = $count_during_orders / ($count_orders ?: 1);

        $rating = $avg_star * $percent_success * $percent_during;

        $sort_rating = ($rating * 10) - $count_today;

        if ($sort_rating == 0) $sort_rating = 50;


        $result['rating'] = round($rating, 1);
        $result['percent_success'] = (int) 100 * $percent_success;
        $result['percent_during_orders'] = (int) 100 * $percent_during;
        $result['all_orders'] =  (int) $count_orders;
        $result['days'] =  '15 дней';

        DB::table("users")
            ->where("id", $id_courier)
            ->update(["rating"=>round($rating,1), "sort_rating"=>$sort_rating]);

    }
}
