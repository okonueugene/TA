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
        // Check if the column exists before trying to drop it
        // This makes the migration more robust in case it's run on a fresh DB
        // where this column might never have existed.
        if (Schema::hasColumn('employee_shifts', 'overtime_hours')) {
            Schema::table('employee_shifts', function (Blueprint $table) {
                $table->dropColumn('overtime_hours');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('employee_shifts', function (Blueprint $table) {
            // Re-add the column if rolling back (for development/testing purposes)
            // Note: Data in this column will be lost if you run 'down' and then 'up' again.
            // If you had historical data, you'd need a more complex strategy to re-populate.
            $table->float('overtime_hours')->nullable()->after('hours_worked');
        });
    }
};