<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateShopSettingTable extends Migration
{
    use \Centire\Utilities\MysqlVersion;

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('shop_setting', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('shop_id', false, true)->unsigned();
            $table->integer('setting_id', false, true)->unsigned();
            $table->text('value')->nullable();
            $table->timestamps();

            // Indexes
            $table->unique(['shop_id', 'setting_id']);

            // Relationships
            $table->foreign('shop_id')
                ->references('id')
                ->on('shops')
                ->onDelete('CASCADE');

            $table->foreign('setting_id')
                ->references('id')
                ->on('settings')
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
        Schema::dropIfExists('shop_setting');
    }
}
