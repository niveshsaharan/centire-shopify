<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSettingsTable extends Migration
{
    use \Centire\Utilities\MysqlVersion;

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->increments('id')->unsigned();
            $table->string('name');
            $table->string('label');
            $table->text('value');
            $table->text('description');
            $table->string('type');
            $table->string('placeholder');
            $table->string('identifier');

            if ($this->version() >= '5.7.8') {
                $table->json('metadata')->nullable(true)->default(null);
            } else {
                $table->text('metadata')->nullable(true)->default(null);
            }

            $table->integer('priority');
            $table->boolean('is_editable')->default(false);
            $table->boolean('is_autoload')->default(false);
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
        Schema::dropIfExists('settings');
    }
}
