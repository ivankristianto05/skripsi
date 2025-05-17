<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AprioriService;

class AprioriRun extends Command
{
    protected $signature = 'apriori:run {--min=0 : Minimum support}';
    protected $description = 'Menjalankan algoritma Apriori dan menampilkan frequent itemsets';

    public function handle()
    {
        $minSupport = (int) $this->option('min');

        $this->info("🔍 Menjalankan Apriori dengan minimum support: $minSupport\n");

        $frequentItemsets = AprioriService::getFrequentItemsets($minSupport);

        foreach ($frequentItemsets as $k => $itemsets) {
            $this->line("✅ Frequent {$k}-itemsets:");
            foreach ($itemsets as $itemset => $count) {
                $this->line(" - [{$itemset}] ({$count} transaksi)");
            }
            $this->line('');
        }

        $this->info("✅ Selesai.");
    }
}
