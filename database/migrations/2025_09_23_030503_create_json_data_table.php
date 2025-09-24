<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('json_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name')->unique();
            $table->string('filename')->unique();
            $table->string('original_filename');
            $table->integer('file_size');
            $table->longText('raw_data');
            $table->json('parsed_structure');
            $table->integer('record_count')->nullable();
            $table->string('status')->default('uploaded');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('json_data');
    }
};
