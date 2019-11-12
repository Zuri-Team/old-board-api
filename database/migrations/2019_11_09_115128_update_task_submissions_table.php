<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateTaskSubmissionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('task_submissions', function (Blueprint $table) {
            //$table->integer('grade_score')->change();
            $table->integer('is_submitted')->default(0);
            $table->integer('is_graded')->default(0);
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
            //
        });
    }
}
