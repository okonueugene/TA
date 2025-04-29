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
        Schema::create('employee_shifts', function (Blueprint $table) {
            $table->id();
            // Indexed for frequent querying and joining
            $table->string('employee_pin')->index();
            $table->date('shift_date')->index(); // for querying by day
            $table->unsignedBigInteger('clock_in_attendance_id')->nullable()->index();
            $table->unsignedBigInteger('clock_out_attendance_id')->nullable()->index();
            $table->timestamp('clock_in_time')->nullable();
            $table->timestamp('clock_out_time')->nullable();
            $table->float('hours_worked')->nullable();
            $table->string('shift_type')->nullable(); // day, night, missing_clockout, etc.
            $table->boolean('is_complete')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Foreign key constraints
            $table->foreign('clock_in_attendance_id')
                  ->references('id')->on('attendances')->nullOnDelete();
            $table->foreign('clock_out_attendance_id')
                  ->references('id')->on('attendances')->nullOnDelete();
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('employee_shifts');
    }
};
