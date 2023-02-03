<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();

        $schedule->call("\App\Http\Controllers\SearchController::fallBehindOrders")->everyMinute();
        $schedule->call("\App\Http\Controllers\SearchController::searchNewOrder")->everyMinute();
        $schedule->call("\App\Http\Controllers\SearchController::push_new_orders")->everyMinute();
        $schedule->call("\App\Http\Controllers\SearchController::pushToLatedDrivers")->everyFiveMinutes();
        $schedule->call("\App\Http\Controllers\PushController::eyTyTamOtvechai")->everyFiveMinutes();
        $schedule->call("\App\Http\Controllers\UserController::updateStateIn0000Hour")->dailyAt('23:59');
        $schedule->call("\App\Http\Controllers\UserController::addBonusZaProstoi")->dailyAt('00:01');
        $schedule->call("\App\Http\Controllers\UserController::addBonusBenzin")->dailyAt('00:02');
//        $schedule->call("\App\Http\Controllers\UserController::raschetDriverIn0400Hour")->dailyAt('04:00');



    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
