<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePlansTable extends Migration
{
    use \Centire\Utilities\MysqlVersion;

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->increments('id')->unsigned();
            $table->string('name');
            $table->string('display_name')->nullable();
            $table->string('terms')->nullable();
            $table->text('description');
            $table->integer('duration');
            $table->integer('trial_days');
            $table->integer('price');
            $table->string('plan_type')->default('recurring'); // recurring, single, usage, credit
            $table->integer('priority');

            if ($this->version() >= '5.7.8') {
                $table->json('metadata')->nullable(true)->default(null);
            } else {
                $table->text('metadata')->nullable(true)->default(null);
            }

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('plans');
    }
}
