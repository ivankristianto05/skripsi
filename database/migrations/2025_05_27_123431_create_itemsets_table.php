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
        Schema::create('itemsets', function (Blueprint $table) {
            $table->id();
            $table->json('items'); // Menyimpan array kode produk
            $table->string('items_hash'); // Dibuat non-unique di sini dulu
            $table->tinyInteger('item_count')->unsigned(); // Jumlah item (1, 2, atau 3)
            $table->integer('support_count')->unsigned()->nullable();
            $table->decimal('support_value', 8, 4)->nullable(); // Presisi 8 digit, 4 di belakang koma
            
            // apriori_batch_id sekarang kita buat tidak nullable, karena setiap itemset
            // akan selalu terkait dengan sebuah batch (baik interaktif maupun global).
            // Jika ada kasus di mana ia bisa null, Anda bisa kembalikan ->nullable().
            $table->uuid('apriori_batch_id');
            
            $table->timestamps();

            // Menambahkan composite unique key untuk kombinasi items_hash dan apriori_batch_id
            // Nama constraint 'itemsets_hash_batch_id_unique' adalah contoh, Anda bisa menamainya lain.
            $table->unique(['items_hash', 'apriori_batch_id'], 'itemsets_hash_batch_id_unique_constraint');
            
            // Anda tetap bisa menambahkan index terpisah pada apriori_batch_id jika sering query hanya berdasarkan kolom ini
            // $table->index('apriori_batch_id'); 
            // Namun, unique constraint di atas biasanya juga akan membuatkan index yang mencakup kedua kolom tersebut.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('itemsets', function (Blueprint $table) {
            // Hapus unique constraint jika ada saat rollback
            // Pastikan nama constraint sama dengan yang didefinisikan di method up()
            // $table->dropUnique('itemsets_hash_batch_id_unique_constraint'); // Baris ini bisa error jika tabel tidak ada
        });
        Schema::dropIfExists('itemsets'); // Ini akan menghapus tabel beserta semua constraint/indexnya
    }
};