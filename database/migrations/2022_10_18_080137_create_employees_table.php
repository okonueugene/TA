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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('pin')->unique();
            $table->string('empname');
            $table->enum('empgender', ['male', 'female'])->nullable();
            $table->string('empoccupation')->nullable();
            $table->string('empphone')->nullable();
            $table->string('empresidence')->nullable();
            $table->string('team')->nullable();
            $table->string('status')->nullable();
            $table->string('acc_no')->nullable();
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
        Schema::dropIfExists('employees');
    }
};
