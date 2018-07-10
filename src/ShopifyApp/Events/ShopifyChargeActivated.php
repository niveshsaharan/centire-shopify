<?php

namespace Centire\ShopifyApp\Events;

use App\Shop;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ShopifyChargeActivated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var Shop $shop
     */
    public $shop;

    /**
     * @var array
     */
    public $metadata;

    /**
     * Create a new event instance.
     *
     * @param                   $shop
     * @param                   $metadata
     *
     * @return void
     */
    public function __construct($shop, $metadata = [])
    {
        $this->shop = $shop;
        $this->metadata = $metadata;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
