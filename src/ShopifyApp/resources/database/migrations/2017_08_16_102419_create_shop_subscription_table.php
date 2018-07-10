<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateShopSubscriptionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shop_subscription', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('shop_id', false, true);
            $table->integer('subscription_id', false, true);
            $table->integer('charge_id', false, true)->nullable();
            $table->integer('trial_days', false, true);
            $table->double('discount');
            $table->boolean('is_active')->default(false);
            $table->timestamp('billing_on')->nullable()->default(null);
            $table->timestamp('activated_on')->nullable()->default(null);
            $table->timestamp('trial_ends_on')->nullable()->default(null);
            $table->timestamp('cancelled_on')->nullable()->default(null);
            $table->timestamp('end_at')->nullable()->default(null);
            $table->timestamps();

            // Relationships
            $table->foreign('shop_id')
                ->references('id')
                ->on('shops')
                ->onDelete('CASCADE');

            $table->foreign('subscription_id')
                ->references('id')
                ->on('subscriptions')
                ->onDelete('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('shop_subscription');
    }
}
