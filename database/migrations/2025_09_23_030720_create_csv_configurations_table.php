<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('csv_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('json_data_id')->constrained('json_data')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('field_mappings');
            $table->json('column_order')->nullable();
            $table->json('filters')->nullable();
            $table->json('transformations')->nullable();
            $table->boolean('include_headers')->default(true);
            $table->string('delimiter')->default(',');
            $table->string('enclosure')->default('"');
            $table->string('escape')->default('\\\\');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('csv_configurations');
    }
};
