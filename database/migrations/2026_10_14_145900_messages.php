<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projet_id')->constrained()->cascadeOnDelete();
            // user_id et non personnel_id : l'admin n'a pas de fiche personnel mais peut écrire
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('contenu');
            $table->timestamps();

            // Lecture du fil d'un projet, du plus récent au plus ancien
            $table->index(['projet_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
