<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateShopSubscriptionLogsTable extends Migration
{
    use \Centire\Utilities\MysqlVersion;

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shop_subscription_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('shop_id', false, true);
            $table->integer('shop_subscription_id', false, true)->nullable();
            $table->string('event')->nullable();

            if ($this->version() >= '5.7.8') {
                $table->json('metadata')->nullable(true)->default(null);
            } else {
                $table->text('metadata')->nullable(true)->default(null);
            }

            $table->boolean('is_active')->default(false);
            $table->timestamps();

            // Relationships
            $table->foreign('shop_id')
                  ->references('id')
                  ->on('shops')
                  ->onDelete('CASCADE');

            $table->foreign('shop_subscription_id')
                  ->references('id')
                  ->on('shop_subscription')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('shop_subscription_logs');
    }
}
