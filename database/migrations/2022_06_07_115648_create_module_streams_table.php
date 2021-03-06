<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateModuleStreamsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('module_streams', function (Blueprint $table) {
            $table->id();
            $table->integer('block_id');
            $table->integer('index');
            $table->string('authors')->default('');
            $table->string('title');
            $table->string('link');
            $table->dateTime('date_start', $precision = 0);
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
        Schema::dropIfExists('module_streams');
    }
}
