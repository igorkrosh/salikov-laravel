<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateModuleJobsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('module_jobs', function (Blueprint $table) {
            $table->id();
            $table->integer('block_id');
            $table->integer('index');
            $table->string('authors');
            $table->string('title');
            $table->longText('text');
            $table->dateTime('deadline', $precision = 0);
            $table->dateTime('check_date', $precision = 0);
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
        Schema::dropIfExists('module_jobs');
    }
}
