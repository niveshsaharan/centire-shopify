<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateShopsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->increments('id');
            $table->string('analytics_id')->nullable()->default(null);
            $table->string('shopify_id')->nullable(true)->default(null);
            $table->string('name')->nullable(true)->default(null);
            $table->string('email')->nullable(true)->default(null);
            $table->string('customer_email')->nullable(true)->default(null);
            $table->string('shop_owner')->nullable(true)->default(null);
            $table->string('shopify_domain')->nullable(true)->default(null);
            $table->string('domain')->nullable(true)->default(null);
            $table->string('primary_locale', 25)->nullable(true)->default(null);
            $table->string('address_1', 500)->nullable(true)->default(null);
            $table->string('city')->nullable(true)->default(null);
            $table->string('phone', 25)->nullable(true)->default(null);
            $table->string('province')->nullable(true)->default(null);
            $table->string('province_code')->nullable(true)->default(null);
            $table->string('country')->nullable(true)->default(null);
            $table->string('country_name')->nullable(true)->default(null);
            $table->string('country_code')->nullable(true)->default(null);
            $table->string('zip', 10)->nullable(true)->default(null);
            $table->string('latitude')->nullable(true)->default(null);
            $table->string('longitude')->nullable(true)->default(null);
            $table->string('currency', 3)->nullable(true)->default(null);
            $table->string('money_format')->nullable(true)->default(null);
            $table->string('timezone')->nullable(true)->default(null);
            $table->string('iana_timezone')->nullable(true)->default(null);
            $table->string('money_with_currency_format')->nullable(true)->default(null);
            $table->string('shopify_plan_name')->nullable(true)->default(null);
            $table->string('shopify_plan_display_name')->nullable(true)->default(null);
            $table->integer('status')->default(0);
            $table->string('shopify_token')->nullable(true)->default(null);

            if ($this->version() >= '5.7.8') {
                $table->json('shopify_scopes')->nullable(true)->default(json_encode([]));
            } else {
                $table->text('shopify_scopes')->nullable(true)->default(json_encode([]));
            }


            $table->bigInteger('charge_id')->nullable(true)->default(null);
            $table->boolean('grandfathered')->default(false);
            $table->string('api_token', 60)->unique()->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('shops');
    }
}
