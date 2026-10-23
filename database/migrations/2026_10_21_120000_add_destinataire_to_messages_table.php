<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Association RECEVOIR (MCD) : un message peut viser un membre précis (0,1).
    // Sans destinataire, il s'adresse à tout le projet ; tous les membres voient tous les messages.
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('destinataire_id')->nullable()->after('user_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('destinataire_id');
        });
    }
};
