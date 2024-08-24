<?php

namespace App\Jobs;

use App\Http\Controllers\PushController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RequestStartDeliveryOrderToAllfood implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $order;
    protected $driver;
    protected $time;
    public function __construct($order, $driver, $time)
    {
        $this->order = $order;
        $this->driver = $driver;
        $this->time = $time;
    }

    public function handle(): void
    {
        if (!isset($this->order) || !isset($this->driver) || !isset($this->time)) {
            \Log::error('Order or driver is not set in RequestStartDeliveryOrderToAllfood job.');
            return;
        }

        PushController::startDeliveryOrder($this->order, $this->driver, $this->time);
    }
}
