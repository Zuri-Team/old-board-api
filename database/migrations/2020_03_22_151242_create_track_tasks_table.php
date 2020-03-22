<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTrackTasksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('track_tasks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('task_id')->nullable()->unsigned();
            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade')->onUpdate('cascade');            
            $table->bigInteger('track_id')->unsigned();
            $table->foreign('track_id')->references('id')->on('tracks');
            $table->bigInteger('course_id')->nullable()->unsigned();
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade')->onUpdate('cascade');            
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
        Schema::dropIfExists('track_task');
    }
}
