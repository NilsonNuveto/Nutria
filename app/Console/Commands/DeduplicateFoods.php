<?php

namespace App\Console\Commands;

use App\Services\FoodDeduplicator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('foods:deduplicate')]
#[Description('Remove cópias da planilha quando existe o mesmo alimento na TACO oficial.')]
class DeduplicateFoods extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(FoodDeduplicator $deduplicator): int
    {
        $deleted = $deduplicator->run();
        $this->info("{$deleted} alimentos duplicados removidos; referências dos planos e receitas atualizadas.");

        return self::SUCCESS;
    }
}
