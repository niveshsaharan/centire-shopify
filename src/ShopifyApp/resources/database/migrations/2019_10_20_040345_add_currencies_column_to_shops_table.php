<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCurrenciesColumnToShopsTable extends Migration
{
    use \Centire\Utilities\MysqlVersion;

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('shops', function (Blueprint $table) {
            if ($this->version() >= '5.7.8') {
                $table->json('currencies')->nullable(true)->default(null);
            }else {
                $table->text('currencies')->nullable(true)->default(null);
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn('currencies');
        });
    }
}
