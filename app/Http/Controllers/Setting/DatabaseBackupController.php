<?php

namespace App\Http\Controllers\Setting;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\DbDumper\Databases\PostgreSql;
use Throwable;
use ZipArchive;

class DatabaseBackupController extends Controller
{
    /**
     * Display the database backup and restore tools page.
     */
    public function index()
    {
        return view('settings.database-backups');
    }

    /**
     * Display the database restore page.
     */
    public function restore()
    {
        $this->ensureAdmin();

        return view('settings.database-restore');
    }

    /**
     * Trigger a database-only backup and return it as a download response.
     */
    public function export()
    {
        $this->ensureAdmin();

        $connectionName = config('database.default');
        $connectionConfig = config("database.connections.{$connectionName}");

        if (! is_array($connectionConfig)) {
            Log::error('Database backup export failed: missing connection configuration.', [
                'connection' => $connectionName,
            ]);

            return redirect()
                ->back()
                ->with('error', __('setting.Database Export Error'));
        }

        $driver = $connectionConfig['driver'] ?? $connectionName;
        $timestamp = now()->format('Y-m-d_H-i-s');
        $plainFileName = "database-backup-{$timestamp}.sql";
        $encryptedFileName = $plainFileName.'.enc';
        $exportDirectory = storage_path('app/database-exports');
        $plainFilePath = $exportDirectory.DIRECTORY_SEPARATOR.$plainFileName;
        $encryptedFilePath = $exportDirectory.DIRECTORY_SEPARATOR.$encryptedFileName;

        File::ensureDirectoryExists($exportDirectory);

        $connection = DB::connection($connectionName);

        try {
            $this->dumpDatabaseToFile($driver, $connectionConfig, $plainFilePath, $connection);
            $this->encryptBackupFile($plainFilePath, $encryptedFilePath);

            return response()->download($encryptedFilePath, $encryptedFileName)->deleteFileAfterSend(true);
        } catch (Throwable $e) {
            foreach ([$plainFilePath, $encryptedFilePath] as $path) {
                if (File::exists($path)) {
                    File::delete($path);
                }
            }

            Log::error('Database backup export failed.', [
                'exception' => $e,
                'connection' => $connectionName,
            ]);

            return redirect()
                ->back()
                ->with('error', __('setting.Database Export Error'));
        }
    }

    /**
     * Restore the database from an uploaded backup archive or SQL dump.
     */
    public function import(Request $request)
    {
        $this->ensureAdmin();

        $maxKilobytes = (int) config('backup.import.max_upload_kilobytes', 0);

        $rules = ['required', 'file'];

        if ($maxKilobytes > 0) {
            $rules[] = 'max:'.$maxKilobytes;
        }

        $validator = Validator::make($request->all(), [
            'backup_file' => $rules,
        ]);

        $validator->after(function ($validator) use ($request) {
            $file = $request->file('backup_file');

            if (! $file) {
                return;
            }

            if (! $this->isValidBackupExtension($file)) {
                $validator->errors()->add('backup_file', __('validation.mimes', [
                    'attribute' => __('validation.attributes.backup_file'),
                    'values' => 'zip, sql, enc',
                ]));
            }
        });

        $validator->validate();

        $file = $request->file('backup_file');

        $reenableOnFailure = null;

        try {
            $sql = $this->extractSqlFromUpload(
                $file->getRealPath(),
                (string) $file->getClientOriginalExtension()
            );

            $driver = DB::getDriverName();
            $disableCommand = null;
            $enableCommand = null;

            if ($driver === 'mysql') {
                $disableCommand = 'SET FOREIGN_KEY_CHECKS=0;';
                $enableCommand = 'SET FOREIGN_KEY_CHECKS=1;';
            } elseif ($driver === 'sqlite') {
                $disableCommand = 'PRAGMA foreign_keys = OFF;';
                $enableCommand = 'PRAGMA foreign_keys = ON;';
            } elseif ($driver === 'pgsql') {
                $disableCommand = 'SET session_replication_role = replica;';
                $enableCommand = 'SET session_replication_role = DEFAULT;';
            }

            $reenableOnFailure = $enableCommand;

            if ($disableCommand) {
                DB::statement($disableCommand);
            }

            $statements = $this->splitSqlStatements($sql);

            foreach ($statements as $statement) {
                DB::unprepared($statement);
            }

            if ($enableCommand) {
                DB::statement($enableCommand);
                $reenableOnFailure = null;
            }

            return redirect()
                ->back()
                ->with('success', __('setting.Database Import Success'));
        } catch (Throwable $e) {
            if (! empty($reenableOnFailure)) {
                try {
                    DB::statement($reenableOnFailure);
                } catch (Throwable $inner) {
                    Log::warning('Failed to re-enable database constraints after import error.', ['exception' => $inner]);
                }
            }

            Log::error('Database backup import failed.', ['exception' => $e]);

            return redirect()
                ->back()
                ->with('error', __('setting.Database Import Error'));
        }
    }

    /**
     * Defense in depth: export/import/restore expose or replace the whole database,
     * so they require the admin role even if the route middleware is ever changed.
     */
    private function ensureAdmin(): void
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);
    }

    /**
     * Determine if the uploaded backup file has an allowed extension.
     */
    private function isValidBackupExtension(UploadedFile $file): bool
    {
        $extension = Str::lower($file->getClientOriginalExtension());

        if ($extension === '') {
            $extension = Str::lower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        }

        return in_array($extension, ['zip', 'sql', 'enc'], true);
    }

    /**
     * Extract SQL content from the uploaded backup file.
     */
    private function extractSqlFromUpload(string $path, string $extension): string
    {
        $extension = strtolower($extension);

        if ($extension === 'enc') {
            $decrypted = $this->decryptBackupFile($path);

            if ($this->looksLikeZipArchive($decrypted)) {
                $temporaryArchive = storage_path('app/import-'.Str::uuid().'.zip');
                File::ensureDirectoryExists(dirname($temporaryArchive));
                File::put($temporaryArchive, $decrypted);

                try {
                    return $this->extractSqlFromArchive($temporaryArchive);
                } finally {
                    File::delete($temporaryArchive);
                }
            }

            return $decrypted;
        }

        if ($extension === 'sql') {
            return File::get($path);
        }

        return $this->extractSqlFromArchive($path);
    }

    /**
     * Determine whether the provided contents represent a ZIP archive.
     */
    private function looksLikeZipArchive(string $contents): bool
    {
        if (strlen($contents) < 4) {
            return false;
        }

        $signature = substr($contents, 0, 4);

        return in_array($signature, ["PK\x03\x04", "PK\x05\x06", "PK\x07\x08"], true);
    }

    /**
     * Extract SQL contents from the provided archive path.
     */
    private function extractSqlFromArchive(string $archivePath): string
    {
        $temporaryDirectory = storage_path('app/import-'.Str::uuid());
        File::makeDirectory($temporaryDirectory, 0755, true, true);

        try {
            $archive = new ZipArchive();
            if ($archive->open($archivePath) !== true) {
                throw new \RuntimeException('Unable to open the uploaded archive.');
            }

            $this->assertArchiveEntriesAreSafe($archive);

            if ($archive->extractTo($temporaryDirectory) === false) {
                throw new \RuntimeException('Unable to extract the uploaded archive.');
            }

            $archive->close();

            $sqlFile = collect(File::allFiles($temporaryDirectory))
                ->first(function ($file) {
                    return Str::endsWith($file->getFilename(), ['.sql', '.sql.enc']);
                });

            if (! $sqlFile) {
                throw new FileNotFoundException('No SQL dump found inside the archive.');
            }

            $contents = File::get($sqlFile->getRealPath());

            if (Str::endsWith($sqlFile->getFilename(), '.enc')) {
                $temporaryFile = $sqlFile->getRealPath();

                return $this->decryptBackupContents($contents, $temporaryFile);
            }

            return $contents;
        } finally {
            $this->deleteTemporaryImportDirectory($temporaryDirectory);
        }
    }

    /**
     * Reject archives whose entries could be written outside the extraction directory (Zip Slip).
     */
    private function assertArchiveEntriesAreSafe(ZipArchive $archive): void
    {
        for ($index = 0; $index < $archive->numFiles; $index++) {
            $name = (string) $archive->getNameIndex($index);
            $normalized = str_replace('\\', '/', $name);

            $isAbsolute = str_starts_with($normalized, '/') || preg_match('/^[A-Za-z]:/', $normalized) === 1;
            $hasTraversal = in_array('..', explode('/', $normalized), true);

            if ($name === '' || str_contains($name, "\0") || $isAbsolute || $hasTraversal) {
                throw new RuntimeException('The uploaded archive contains an unsafe file path.');
            }
        }
    }

    /**
     * Delete a temporary import directory, but only if it lives under storage/app/import-*.
     */
    private function deleteTemporaryImportDirectory(string $directory): void
    {
        $allowedParent = realpath(storage_path('app'));
        $resolved = realpath($directory);

        if ($allowedParent === false || $resolved === false) {
            return;
        }

        if (dirname($resolved) !== $allowedParent || ! str_starts_with(basename($resolved), 'import-')) {
            Log::warning('Refused to delete unexpected directory during backup import cleanup.', [
                'directory' => $resolved,
            ]);

            return;
        }

        File::deleteDirectory($resolved);
    }

    /**
     * Encrypt the generated backup and remove the plain-text dump.
     */
    private function encryptBackupFile(string $sourcePath, string $destinationPath): void
    {
        if (! File::exists($sourcePath)) {
            throw new FileNotFoundException("Backup source file not found at {$sourcePath}.");
        }

        $contents = File::get($sourcePath);
        $encrypted = Crypt::encryptString($contents);

        File::put($destinationPath, $encrypted);
        File::delete($sourcePath);
    }

    /**
     * Decrypt an encrypted backup file stored on disk.
     */
    private function decryptBackupFile(string $path): string
    {
        $contents = File::get($path);

        return $this->decryptBackupContents($contents, $path);
    }

    /**
     * Decrypt the provided backup contents and report detailed errors.
     */
    private function decryptBackupContents(string $contents, ?string $contextPath = null): string
    {
        try {
            return Crypt::decryptString($contents);
        } catch (DecryptException $e) {
            $message = 'Unable to decrypt the uploaded backup file.';

            if ($contextPath) {
                $message .= " ({$contextPath})";
            }

            throw new RuntimeException($message, previous: $e);
        }
    }

    /**
     * Dump the configured database connection to the provided file path.
     */
    private function dumpDatabaseToFile(string $driver, array $config, string $destination, ConnectionInterface $connection): void
    {
        if (Str::of($driver)->lower()->contains('sqlite')) {
            $databasePath = $config['database'] ?? null;

            if (! $databasePath || $databasePath === ':memory:') {
                throw new \RuntimeException('SQLite in-memory databases cannot be exported.');
            }

            if (! File::exists($databasePath)) {
                throw new FileNotFoundException("SQLite database file not found at {$databasePath}.");
            }

            if (! File::copy($databasePath, $destination)) {
                throw new \RuntimeException("Failed to copy SQLite database from {$databasePath}.");
            }

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->dumpMySqlDatabase($connection, $config, $destination);

            return;
        }

        $dumper = match ($driver) {
            'pgsql', 'postgresql', 'postgres' => PostgreSql::create(),
            default => null,
        };

        if ($dumper === null) {
            throw new \RuntimeException("Database driver [{$driver}] is not supported for export.");
        }

        $database = $config['database'] ?? '';
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';
        $host = $config['host'] ?? '127.0.0.1';
        $port = (string) ($config['port'] ?? ($driver === 'pgsql' ? 5432 : 3306));

        $dumper
            ->setDbName($database)
            ->setUserName($username)
            ->setPassword($password)
            ->setHost($host)
            ->setPort($port);

        if (! empty($config['unix_socket'])) {
            $dumper->setSocket($config['unix_socket']);
        }

        if (! empty($config['dump']['dump_binary_path'])) {
            $dumper->setDumpBinaryPath($config['dump']['dump_binary_path']);
        }

        if (! empty($config['dump']['timeout'])) {
            $dumper->setTimeout((int) $config['dump']['timeout']);
        }

        if (! empty($config['dump']['extra_options']) && is_array($config['dump']['extra_options'])) {
            foreach ($config['dump']['extra_options'] as $option) {
                if (is_string($option) && $option !== '') {
                    $dumper->addExtraOption($option);
                }
            }
        }

        if (method_exists($dumper, 'useSingleTransaction') && ($config['dump']['use_single_transaction'] ?? false)) {
            $dumper->useSingleTransaction();
        }

        if (! empty($config['dump']['exclude_tables']) && is_array($config['dump']['exclude_tables'])) {
            foreach ($config['dump']['exclude_tables'] as $table) {
                if (is_string($table) && $table !== '') {
                    $dumper->excludeTables($table);
                }
            }
        }

        $dumper->dumpToFile($destination);
    }

    /**
     * Dump a MySQL or MariaDB database without relying on external binaries.
     */
    private function dumpMySqlDatabase(ConnectionInterface $connection, array $config, string $destination): void
    {
        $database = $config['database'] ?? $connection->getDatabaseName();

        if ($database === null) {
            throw new RuntimeException('Unable to determine the database name for export.');
        }

        $handle = fopen($destination, 'wb');

        if ($handle === false) {
            throw new RuntimeException('Unable to open the export destination for writing.');
        }

        $pdo = $connection->getPdo();

        $write = static function (string $sql) use ($handle): void {
            if (fwrite($handle, $sql) === false) {
                throw new RuntimeException('Failed to write to the export file.');
            }
        };

        try {
            $write('-- Database Backup'.PHP_EOL);
            $write('-- Generated at '.now()->toDateTimeString().PHP_EOL.PHP_EOL);
            $write('SET NAMES utf8mb4;'.PHP_EOL);
            $write('SET FOREIGN_KEY_CHECKS=0;'.PHP_EOL);
            $write('USE `'.str_replace('`', '``', $database).'`;'.PHP_EOL.PHP_EOL);

            $tables = $connection->select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");

            foreach ($tables as $tableRow) {
                $tableData = (array) $tableRow;
                $table = array_values($tableData)[0] ?? null;

                if ($table === null) {
                    continue;
                }

                $write('-- --------------------------------------------------'.PHP_EOL);
                $write('-- Structure for table `'.$table.'`'.PHP_EOL);
                $write('-- --------------------------------------------------'.PHP_EOL);

                $write('DROP TABLE IF EXISTS `'.$table.'`;'.PHP_EOL);

                $create = (array) $connection->selectOne("SHOW CREATE TABLE `{$table}`");
                $createSql = $create['Create Table'] ?? $create['Create View'] ?? (array_values($create)[1] ?? null);

                if ($createSql === null) {
                    throw new RuntimeException("Unable to determine create statement for table {$table}.");
                }

                $write($createSql.';'.PHP_EOL.PHP_EOL);

                $rows = [];
                $columns = null;
                $batchSize = 500;

                $quote = static function ($value) use ($pdo) {
                    if ($value === null) {
                        return 'NULL';
                    }

                    if (is_bool($value)) {
                        return $value ? '1' : '0';
                    }

                    if (is_int($value) || is_float($value)) {
                        return (string) $value;
                    }

                    return $pdo->quote((string) $value);
                };

                foreach ($connection->table($table)->select('*')->cursor() as $record) {
                    $row = (array) $record;

                    if ($columns === null) {
                        $columns = array_keys($row);
                    }

                    $rows[] = $row;

                    if (count($rows) >= $batchSize) {
                        $this->writeInsertStatements($handle, $table, $columns, $rows, $quote);
                        $rows = [];
                    }
                }

                if (! empty($rows) && $columns !== null) {
                    $this->writeInsertStatements($handle, $table, $columns, $rows, $quote);
                }

                $write(PHP_EOL);
            }

            $write('SET FOREIGN_KEY_CHECKS=1;'.PHP_EOL);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Write INSERT statements for the provided rows.
     */
    private function writeInsertStatements($handle, string $table, array $columns, array $rows, callable $quote): void
    {
        $columnList = implode(', ', array_map(fn ($column) => '`'.str_replace('`', '``', $column).'`', $columns));

        $values = [];

        foreach ($rows as $row) {
            $ordered = [];

            foreach ($columns as $column) {
                $ordered[] = $quote($row[$column] ?? null);
            }

            $values[] = '('.implode(', ', $ordered).')';
        }

        $statement = 'INSERT INTO `'.$table.'` ('.$columnList.') VALUES'.PHP_EOL;
        $statement .= implode(','.PHP_EOL, $values).';'.PHP_EOL;

        if (fwrite($handle, $statement) === false) {
            throw new RuntimeException('Failed to write INSERT statements to the export file.');
        }
    }

    /**
     * Break a SQL dump into executable statements while handling comments and strings.
     */
    private function splitSqlStatements(string $sql): array
    {
        $sql = ltrim($sql, "\xEF\xBB\xBF");

        $statements = [];
        $current = '';
        $lineBuffer = '';
        $length = strlen($sql);
        $inString = false;
        $stringDelimiter = '';
        $inLineComment = false;
        $inBlockComment = false;
        $delimiter = ';';
        $delimiterLength = strlen($delimiter);

        $parseDelimiterDirective = static function (string $line) use (&$delimiter, &$delimiterLength, &$current, &$lineBuffer): bool {
            $trimmedLine = trim($line);

            if ($trimmedLine === '') {
                return false;
            }

            if (substr($trimmedLine, 0, 2) === '/*!') {
                $withoutPrefix = preg_replace('/^\/\*!\d+\s*/', '', $trimmedLine);

                if ($withoutPrefix !== null && $withoutPrefix !== '') {
                    $commentEnd = strpos($withoutPrefix, '*/');

                    if ($commentEnd !== false) {
                        $withoutPrefix = substr($withoutPrefix, 0, $commentEnd);
                    }

                    $candidate = trim($withoutPrefix);

                    if ($candidate !== '') {
                        $trimmedLine = $candidate;
                    }
                }
            }

            if (! preg_match('/^DELIMITER\s+(\S+)/i', $trimmedLine, $matches)) {
                return false;
            }

            $newDelimiter = $matches[1] !== '' ? $matches[1] : ';';

            if ($newDelimiter === '') {
                $newDelimiter = ';';
            }

            $delimiter = $newDelimiter;
            $delimiterLength = strlen($delimiter);

            $lineLength = strlen($lineBuffer);

            if ($lineLength > 0 && $lineLength <= strlen($current)) {
                $current = substr($current, 0, -$lineLength);
            }

            $lineBuffer = '';
            $current = rtrim($current, "\r\n");

            return true;
        };

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $sql[$i + 1] ?? '';

            if ($inLineComment) {
                if ($char === "\n" || ($char === "\r" && $next !== "\n")) {
                    $inLineComment = false;
                    $lineBuffer = '';
                }

                continue;
            }

            if ($inBlockComment) {
                if ($char === '*' && $next === '/') {
                    $inBlockComment = false;
                    $i++;
                }

                if ($char === "\n" || ($char === "\r" && $next !== "\n")) {
                    $lineBuffer = '';
                }

                continue;
            }

            if ($inString) {
                $current .= $char;
                $lineBuffer .= $char;

                if ($char === $stringDelimiter) {
                    $escaped = false;
                    $offset = 1;

                    while ($i - $offset >= 0 && ($sql[$i - $offset] ?? null) === '\\') {
                        $escaped = ! $escaped;
                        $offset++;
                    }

                    if (! $escaped) {
                        $inString = false;
                        $stringDelimiter = '';
                    }
                }

                if ($char === "\n" || ($char === "\r" && $next !== "\n")) {
                    $lineBuffer = '';
                }

                continue;
            }

            if ($char === '-' && $next === '-') {
                $third = $sql[$i + 2] ?? '';

                if ($third === ' ' || $third === "\t" || $third === '-' || $third === "\r" || $third === "\n" || $third === '') {
                    $inLineComment = true;
                    $i++;
                    continue;
                }
            }

            if ($char === '#') {
                $inLineComment = true;
                continue;
            }

            if ($char === '/' && $next === '*') {
                $inBlockComment = true;
                $i++;
                continue;
            }

            if ($delimiterLength > 0 && substr($sql, $i, $delimiterLength) === $delimiter) {
                $trimmed = trim($current);

                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }

                $current = '';
                $lineBuffer = '';

                $i += $delimiterLength - 1;

                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $inString = true;
                $stringDelimiter = $char;
                $current .= $char;
                $lineBuffer .= $char;

                continue;
            }

            $current .= $char;
            $lineBuffer .= $char;

            if ($char === "\n" || ($char === "\r" && $next !== "\n")) {
                if ($parseDelimiterDirective($lineBuffer)) {
                    continue;
                }

                $lineBuffer = '';
            }
        }

        if ($lineBuffer !== '') {
            $parseDelimiterDirective($lineBuffer);
        }

        $trimmed = trim($current);

        if ($trimmed !== '') {
            $statements[] = $trimmed;
        }

        return $statements;
    }
}
