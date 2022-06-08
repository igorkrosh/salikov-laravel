<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateModuleTestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('module_tests', function (Blueprint $table) {
            $table->id();
            $table->integer('block_id');
            $table->integer('index');
            $table->string('authors');
            $table->string('title');
            $table->json('test');
            $table->dateTime('deadline', $precision = 0);
            $table->dateTime('check_date', $precision = 0);
            $table->string('file')->default('');
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
        Schema::dropIfExists('module_tests');
    }
}
