<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Plus d'inscription libre : l'administrateur crée d'abord le personnel, puis lui accorde un accès (compte utilisateur).
    // Un personnel peut donc exister sans compte (ETRE : personnel 0,1). Supprimer un compte ne supprime plus la fiche.
    public function up(): void
    {
        Schema::table('personnels', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('personnels', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Échoue s'il existe des personnels sans compte : leur donner un accès (ou les supprimer) avant de revenir en arrière
        Schema::table('personnels', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('personnels', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
