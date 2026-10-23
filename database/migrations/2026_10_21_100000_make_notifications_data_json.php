<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Colonne JSON : permet de filtrer en SQL (where('data->projet_id', ...)) au lieu de charger puis filtrer en PHP
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->json('data')->change();
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->text('data')->change();
        });
    }
};
