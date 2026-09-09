<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class MigrateSqliteToSupabase extends Command
{
    protected $signature = 'nutria:migrate-sqlite-to-supabase
                            {--source=database/database.sqlite : Caminho do SQLite de origem}
                            {--dry-run : Apenas valida conexões e exibe as quantidades}';

    protected $description = 'Copia os dados persistentes do SQLite local para o PostgreSQL/Supabase configurado no ambiente';

    /** @var list<string> */
    private const TABLES = [
        'sources',
        'users',
        'patients',
        'foods',
        'plans',
        'recipes',
    ];

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->error('Configure DB_CONNECTION=pgsql e DB_URL do Supabase antes de executar este comando.');

            return self::FAILURE;
        }

        $source = $this->sourcePath();

        if (! is_file($source)) {
            $this->error("SQLite de origem não encontrado: {$source}");

            return self::FAILURE;
        }

        config(['database.connections.nutria_legacy_sqlite' => [
            'driver' => 'sqlite',
            'database' => $source,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]]);

        DB::purge('nutria_legacy_sqlite');
        $legacy = DB::connection('nutria_legacy_sqlite');

        try {
            $legacy->getPdo();
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            $this->error('Não foi possível abrir uma das conexões: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Origem: '.$source);
        $this->info('Destino: PostgreSQL / schema '.config('database.connections.pgsql.search_path'));

        foreach (self::TABLES as $table) {
            $count = $legacy->getSchemaBuilder()->hasTable($table)
                ? $legacy->table($table)->count()
                : 0;
            $this->line(sprintf('  %-10s %d registro(s)', $table, $count));
        }

        if ($this->option('dry-run')) {
            $this->info('Dry-run concluído; nenhum dado foi alterado.');

            return self::SUCCESS;
        }

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("A tabela {$table} não existe no destino. Execute php artisan migrate --force primeiro.");
            }
        }

        DB::transaction(function () use ($legacy): void {
            foreach (self::TABLES as $table) {
                if (! $legacy->getSchemaBuilder()->hasTable($table)) {
                    continue;
                }

                $rows = $legacy->table($table)
                    ->orderBy('id')
                    ->get()
                    ->map(static fn (object $row): array => (array) $row)
                    ->all();

                if ($rows === []) {
                    continue;
                }

                $columns = array_keys($rows[0]);
                $updateColumns = array_values(array_filter($columns, static fn (string $column): bool => $column !== 'id'));

                foreach (array_chunk($rows, 200) as $chunk) {
                    DB::table($table)->upsert($chunk, ['id'], $updateColumns);
                }

                $this->resetSequence($table);
                $this->info("{$table}: ".count($rows).' registro(s) sincronizado(s).');
            }
        });

        $this->newLine();
        $this->info('Migração do SQLite para o Supabase concluída.');

        return self::SUCCESS;
    }

    private function sourcePath(): string
    {
        $source = (string) $this->option('source');

        if (str_starts_with($source, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $source) === 1) {
            return $source;
        }

        return base_path($source);
    }

    private function resetSequence(string $table): void
    {
        DB::statement(sprintf(
            <<<'SQL'
SELECT setval(
    pg_get_serial_sequence('%1$s', 'id'),
    COALESCE((SELECT MAX(id) FROM "%1$s"), 1),
    EXISTS (SELECT 1 FROM "%1$s")
)
SQL,
            $table,
        ));
    }
}
