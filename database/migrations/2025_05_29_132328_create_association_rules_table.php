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
        Schema::create('association_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('apriori_batch_id')->index(); // Untuk mengelompokkan aturan per proses analisis
            $table->json('antecedent'); // Item/itemset di sisi kiri aturan (IF part)
            $table->json('consequent'); // Item/itemset di sisi kanan aturan (THEN part)
            $table->decimal('confidence', 8, 4); // Nilai confidence aturan
            $table->decimal('lift', 8, 4)->nullable();       // Nilai lift aturan
            $table->decimal('support_value_rule', 8, 4); // Support dari itemset gabungan (Antecedent U Consequent)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('association_rules');
    }
};