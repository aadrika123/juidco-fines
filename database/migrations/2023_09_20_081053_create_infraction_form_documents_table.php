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
        Schema::create('infraction_form_documents', function (Blueprint $table) {
            $table->id();
            $table->integer('irf_id');
            $table->string('unique_id');
            $table->string('reference_no');
            $table->decimal('latitude', 10, 6);
            $table->decimal('longitude', 10, 6);
            $table->string('document_verified_by');
            $table->string('document_verified_at');
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
        Schema::dropIfExists('infraction_form_documents');
    }
};
