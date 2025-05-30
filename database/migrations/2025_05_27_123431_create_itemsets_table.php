<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('itemsets', function (Blueprint $table) {
            $table->id();
            $table->json('items'); // Menyimpan array kode produk
            $table->string('items_hash'); // Dibuat non-unique di sini dulu
            $table->tinyInteger('item_count')->unsigned(); // Jumlah item (1, 2, atau 3)
            $table->integer('support_count')->unsigned()->nullable();
            $table->decimal('support_value', 8, 4)->nullable(); // Presisi 8 digit, 4 di belakang koma
            $table->uuid('apriori_batch_id');
            $table->timestamps();
            $table->unique(['items_hash', 'apriori_batch_id'], 'itemsets_hash_batch_id_unique_constraint');
        });
    }

    public function down(): void
    {
        Schema::table('itemsets', function (Blueprint $table) {
        });
        Schema::dropIfExists('itemsets'); // Ini akan menghapus tabel beserta semua constraint/indexnya
    }
};