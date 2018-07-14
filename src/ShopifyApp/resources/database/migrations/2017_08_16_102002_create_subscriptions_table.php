<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSubscriptionsTable extends Migration
{
    use \Centire\Utilities\MysqlVersion;

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('plan_id', false, true);
            $table->string('display_name');
            $table->text('description');
            $table->integer('duration');
            $table->integer('trial_days');
            $table->double('price');
            $table->string('billing_type')->default("recurring");
            $table->integer('priority');

            if ($this->version() >= '5.7.8') {
                $table->json('metadata')->nullable(true)->default(null);
            } else {
                $table->text('metadata')->nullable(true)->default(null);
            }

            $table->boolean('is_active')->default(false);
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
        Schema::dropIfExists('subscriptions');
    }
}
