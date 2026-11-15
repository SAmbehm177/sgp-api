<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_suppressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('projet_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('message_id');
            $table->timestamps();
            $table->index(['projet_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_suppressions');
    }
};
