<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDiscountsTable extends Migration
{
    use \Centire\Utilities\MysqlVersion;

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('discounts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('shopify_domain');
            $table->string('coupon_code')->nullable();
            $table->double('discount');
            $table->string('discount_type')->default("FLAT");
            $table->integer('subscription_id')->unsigned();


            if ($this->version() >= '5.7.8') {
                $table->json('metadata')->default(null);
            } else {
                $table->text('metadata')->default(null);
            }

            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->softDeletes();

            // Relationships
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
        Schema::dropIfExists('discounts');
    }
}
