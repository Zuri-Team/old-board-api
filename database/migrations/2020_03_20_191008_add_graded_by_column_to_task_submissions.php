<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGradedByColumnToTaskSubmissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('task_submissions', function (Blueprint $table) {
            $table->bigInteger('graded_by')->unsigned()->nullable();
            $table->foreign('graded_by')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('task_submissions', function (Blueprint $table) {
            $table->dropColumn('graded_by');
        });
    }
}
