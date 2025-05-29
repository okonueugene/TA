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
            // Add lateness_minutes column
            $table->integer('lateness_minutes')->default(0)->after('notes');

            // Add separate overtime columns for 1.5x and 2.0x rates
            // Using decimal for precision, 8 total digits, 1 after decimal (e.g., 1234567.8)
            $table->decimal('overtime_hours_1_5x', 8, 1)->default(0.0)->after('lateness_minutes');
            $table->decimal('overtime_hours_2_0x', 8, 1)->default(0.0)->after('overtime_hours_1_5x');
            $table->boolean('is_weekend')->default(false)->after('is_holiday');
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
            // Drop the columns in reverse order of creation
            $table->dropColumn([
                'overtime_hours',
                'lateness_minutes',
                'overtime_hours_1_5x',
                'overtime_hours_2_0x',
                'is_weekend',
            ]);
        });
    }
};