<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChargesTable extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		// Thanks to @ncpope of Github.com
		Schema::create('charges', function (Blueprint $table) {
			$table->bigIncrements('id')->unsigned();
			$table->bigInteger('charge_id')->unsigned()->unique();
			$table->integer('shop_id')->unsigned();
			$table->integer('plan_id')->unsigned();
			$table->boolean('test');
			$table->string('status')->nullable();
			$table->string('name')->nullable();
			$table->string('terms')->nullable();
			$table->integer('type');
			$table->integer('price');
			$table->integer('discount_amount')->nullable();
			$table->integer('capped_amount')->nullable();
			$table->integer('trial_days')->nullable();
			$table->timestamp('billing_on')->nullable();
			$table->timestamp('activated_on')->nullable();
			$table->timestamp('trial_ends_on')->nullable();
			$table->timestamp('cancelled_on')->nullable();
			$table->timestamps();
			$table->softDeletes();

			$table->foreign('shop_id')->references('id')->on('shops')->onDelete('cascade');
			$table->foreign('plan_id')->references('id')->on('plans')->onDelete('cascade');
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('charges');
	}
}
