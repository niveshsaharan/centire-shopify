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
			$table->increments('id')->unsigned();
			$table->string('shopify_domain');
			$table->string('coupon_code')->nullable();
			$table->integer('amount');
			$table->string('discount_type')->default('FLAT');
			$table->integer('plan_id')->unsigned();

			if ($this->version() >= '5.7.8') {
				$table->json('metadata')->nullable(true)->default(null);
			} else {
				$table->text('metadata')->nullable(true)->default(null);
			}

			$table->timestamps();
			$table->softDeletes();

			// Relationships
			$table->foreign('plan_id')
				  ->references('id')
				  ->on('plans')
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
