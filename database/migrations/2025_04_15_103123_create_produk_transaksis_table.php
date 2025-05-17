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
        Schema::create('produk_transaksis', function (Blueprint $table) {
            $table->id();

            $table->string('kode_transaksi');
            $table->foreign('kode_transaksi')
                ->references('kode_transaksi')
                ->on('transaksis')
                ->onDelete('cascade');

            $table->string('kode_produk');
            $table->foreign('kode_produk')
                ->references('kode_produk')
                ->on('produks')
                ->onDelete('cascade');

            $table->timestamps();

            // Optional: mencegah entri duplikat
            $table->unique(['kode_transaksi', 'kode_produk']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('produk_transaksis');
    }
};
