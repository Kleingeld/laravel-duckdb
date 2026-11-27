<?php

namespace Harish\LaravelDuckdb\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class DownloadDuckDBCliCommand extends Command
{
    protected $signature = 'laravel-duckdb:download-cli {--ver=}';

    protected $description = 'Download DuckDB Cli';

    public function handle(): void
    {
        $this->raiseMemoryLimit('512M');

        $os =strtolower(php_uname('s'));
        $arch = strtolower(php_uname('m'));

        $version = $this->option('ver') ? ltrim($this->option('ver'), 'vV') : null;

        $this->info("OS: $os, Architecture: $arch");
        $this->newLine();

        if ($os === 'linux') {
            $platform = match ($arch) {
                'x86_64', 'amd64' => 'amd64',
                'aarch64', 'arm64' => 'arm64',
                default => $arch,
            };

            if ($this->isMusl()) {
                $platform .= '-musl';
            }

            $this->downloadCli('linux', $platform, $version);
        } elseif ($os === 'darwin') {
            $this->downloadCli('osx', 'universal', $version);
        } else {
            throw new \Exception('Not Supported! Currently Only linux, mac supported. Try manually downloading cli from: https://duckdb.org/docs/installation/');
        }
    }

    /**
     * The CLI archive is downloaded and extracted in memory, so make sure there is
     * enough room. Only raises the limit, never lowers it (and leaves -1 alone).
     */
    private function raiseMemoryLimit(string $limit): void
    {
        $current = ini_get('memory_limit');

        if ($current === false || $current === '-1') {
            return;
        }

        if ($this->toBytes($current) < $this->toBytes($limit)) {
            ini_set('memory_limit', $limit);
        }
    }

    private function toBytes(string $value): int
    {
        $value = trim($value);
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private function isMusl(): bool
    {
        return file_exists('/etc/alpine-release');
    }

    private function downloadCli(string $os, string $platform, ?string $version = null): void
    {
        // Since DuckDB v2.0.0 the CLI is released as `duckdb-cli-<os>-<platform>.tar.gz`,
        // older releases used `duckdb_cli-<os>-<platform>.zip`.
        $useTarball = $version === null || version_compare($version, '2.0.0', '>=');

        $fileName = $useTarball
            ? "duckdb-cli-$os-$platform.tar.gz"
            : "duckdb_cli-$os-$platform.zip";

        $url = $version
            ? "https://github.com/duckdb/duckdb/releases/download/v$version/$fileName"
            : "https://github.com/duckdb/duckdb/releases/latest/download/$fileName";

        $this->info("Downloading cli($url)...");
        $this->newLine();

        $res = Http::timeout(10 * 60)
            ->retry(2, 100)
            ->get($url);

        if (! $res->successful()) {
            throw new \Exception("Failed to download DuckDB CLI from $url (HTTP {$res->status()})");
        }

        $tmpDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'duckdb_cli_'.uniqid();
        mkdir($tmpDir, 0755, true);
        $archivePath = $tmpDir.DIRECTORY_SEPARATOR.$fileName;
        file_put_contents($archivePath, $res->body());

        $this->info('Extracting cli...');
        $this->newLine();

        $targetDir = base_path('vendor/bin');
        if (! is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        try {
            if ($useTarball) {
                $this->extractTarball($archivePath, $targetDir);
            } else {
                $this->extractZip($archivePath, $targetDir);
            }
        } finally {
            @unlink($archivePath);
            @rmdir($tmpDir);
        }

        $binary = $targetDir.DIRECTORY_SEPARATOR.'duckdb';
        if (! file_exists($binary)) {
            throw new \Exception("Extraction finished but `$binary` was not found.");
        }

        chmod($binary, 0755);
        $this->info('Done! cli located at `'.$binary.'`');
    }

    private function extractTarball(string $archivePath, string $targetDir): void
    {
        $phar = new \PharData($archivePath);
        $phar->extractTo($targetDir, 'duckdb', true);

        // Release the archive handle, otherwise the temp file cannot be removed.
        unset($phar);
        \Phar::unlinkArchive($archivePath);
    }

    private function extractZip(string $archivePath, string $targetDir): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($archivePath) !== true) {
            throw new \Exception("Could not open zip archive `$archivePath`.");
        }

        $zip->extractTo($targetDir);
        $zip->close();
    }
}
