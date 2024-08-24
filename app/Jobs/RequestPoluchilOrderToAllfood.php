<?php

namespace App\Jobs;

use App\Http\Controllers\PushController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RequestPoluchilOrderToAllfood implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $order;
    protected $driver;
    public function __construct($order, $driver)
    {
        $this->order = $order;
        $this->driver = $driver;
    }

    public function handle(): void
    {
        if (!isset($this->order) || !isset($this->driver)) {
            \Log::error('Order or driver is not set in RequestStartDeliveryOrderToAllfood job.');
            return;
        }

        PushController::courierInCafe($this->order, $this->driver);
    }
}
