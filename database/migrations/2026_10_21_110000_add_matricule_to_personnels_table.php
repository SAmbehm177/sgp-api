<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personnels', function (Blueprint $table) {
            $table->string('matricule', 50)->nullable()->after('prenom');
        });

        // Les personnels existants reçoivent un matricule provisoire (MAT-00001, MAT-00002, ...)
        DB::table('personnels')->whereNull('matricule')->orderBy('id')->each(function ($personnel) {
            DB::table('personnels')
                ->where('id', $personnel->id)
                ->update(['matricule' => sprintf('MAT-%05d', $personnel->id)]);
        });

        Schema::table('personnels', function (Blueprint $table) {
            $table->string('matricule', 50)->nullable(false)->change();
        });

        Schema::table('personnels', function (Blueprint $table) {
            $table->unique('matricule');
        });
    }

    public function down(): void
    {
        Schema::table('personnels', function (Blueprint $table) {
            $table->dropUnique(['matricule']);
        });

        Schema::table('personnels', function (Blueprint $table) {
            $table->dropColumn('matricule');
        });
    }
};
