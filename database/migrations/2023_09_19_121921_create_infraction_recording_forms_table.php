<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('infraction_recording_forms', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('mobile');
            $table->string('email');
            $table->string('holding_no');
            $table->string('street_address');
            $table->string('street_address_2')->nullable();
            $table->string('city');
            $table->string('region');
            $table->string('postal_code');
            $table->string('country');
            $table->integer('violation_id');
            $table->integer('violation_section_id');
            $table->integer('penalty_amount');
            $table->boolean('previous_violation_offence')->nullable();
            $table->string('photo')->nullable();
            $table->string('video_audio')->nullable();
            $table->string('pdf')->nullable();
            $table->integer('version_no')->default(0);      //common for all table
            $table->smallInteger('status')->default(1);     //1-Active, 2-Not Active
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('infraction_recording_forms');
    }
};
