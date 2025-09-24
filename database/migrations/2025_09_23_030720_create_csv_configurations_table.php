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
        \DB::statement('CREATE TABLE csv_configurations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            json_data_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT NULL,
            field_mappings JSON NOT NULL,
            column_order JSON NULL,
            filters JSON NULL,
            transformations JSON NULL,
            include_headers TINYINT(1) NOT NULL DEFAULT 1,
            delimiter VARCHAR(255) NOT NULL DEFAULT ",",
            enclosure VARCHAR(255) NOT NULL DEFAULT "\"",
            escape VARCHAR(255) NOT NULL DEFAULT "\\\\",
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (json_data_id) REFERENCES json_data(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('csv_configurations');
    }
};
