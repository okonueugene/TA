<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('employee_shifts', function (Blueprint $table) {
            $table->float('overtime_hours')->default(0.0)->after('hours_worked');
            $table->boolean('is_holiday')->default(false)->after('overtime_hours');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('employee_shifts', function (Blueprint $table) {
            $table->dropColumn(['overtime_hours', 'is_holiday']);
        });
    }
};
