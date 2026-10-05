<?php

namespace Tests\Unit\Setting {

use App\Http\Controllers\Setting\DatabaseBackupController;
use Illuminate\Container\Container;
use Illuminate\Encryption\Encrypter;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use ZipArchive;

/**
 * تحذير: نسخة سابقة من هذا الاختبار حذفت مجلد storage الحقيقي للمشروع، لأن storage_path()
 * كانت تشير إليه. الآن كل الملفات المؤقتة داخل مجلد معزول في sys_get_temp_dir()، و storage_path()
 * موجّهة إليه، والحذف لا يتم إلا بعد فحص assertSafeToDelete().
 */
class DatabaseBackupControllerTest extends TestCase
{
    private const TEMP_DIRECTORY_PREFIX = 'saham-backup-test-';

    private ?string $isolatedDirectory = null;
    private ?Container $previousContainer = null;
    private $previousFacadeApplication = null;
    private $previousCryptInstance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousContainer = Container::getInstance();
        $this->previousFacadeApplication = Facade::getFacadeApplication();

        $this->isolatedDirectory = $this->createIsolatedDirectory();

        // Application جديد (يصبح Container::getInstance) بحيث storage_path() يعيد المجلد المعزول فقط
        $app = new Application($this->isolatedDirectory);
        $app->useStoragePath($this->isolatedDirectory.DIRECTORY_SEPARATOR.'storage');
        $app->singleton('files', fn () => new Filesystem());
        Facade::setFacadeApplication($app);

        $resolvedStorage = storage_path();
        if (! str_starts_with($resolvedStorage, $this->isolatedDirectory.DIRECTORY_SEPARATOR)) {
            $this->fail("storage_path() is not isolated (resolved to {$resolvedStorage}); aborting before touching the filesystem.");
        }

        $key = random_bytes(32);
        $encrypter = new Encrypter($key, 'AES-256-CBC');
        $this->previousCryptInstance = Crypt::swap($encrypter);
    }

    #[DataProvider('basicSplitProvider')]
    public function testSplitSqlStatements(string $sql, array $expected): void
    {
        $this->assertSame($expected, $this->splitStatements($sql));
    }

    public function testSqlExtensionIsAcceptedRegardlessOfCase(): void
    {
        $file = $this->fakeUploadedFile('example.SQL');

        $this->assertTrue($this->isValidBackupExtension($file));
    }

    public function testZipExtensionIsAccepted(): void
    {
        $file = $this->fakeUploadedFile('archive.zip', 'application/zip');

        $this->assertTrue($this->isValidBackupExtension($file));
    }

    public function testEncryptedExtensionIsAccepted(): void
    {
        $file = $this->fakeUploadedFile('database-backup.sql.enc', 'text/plain');

        $this->assertTrue($this->isValidBackupExtension($file));
    }

    public function testEncryptedSqlDumpIsDecryptedDuringExtraction(): void
    {
        $sql = "SELECT 1;\n";
        $encrypted = Crypt::encryptString($sql);

        $path = $this->createTemporaryFile($encrypted);

        $this->assertSame($sql, $this->extractSqlFromUpload($path, 'enc'));
    }

    public function testEncryptedArchiveIsDecryptedBeforeExtraction(): void
    {
        $sql = "SELECT 42;\n";
        $archivePath = $this->temporaryPath('db-backup-zip-');

        $zip = new ZipArchive();
        $opened = $zip->open($archivePath, ZipArchive::OVERWRITE);

        if ($opened !== true) {
            $zip->open($archivePath, ZipArchive::CREATE);
        }

        $zip->addFromString('backup.sql', $sql);
        $zip->close();

        $archiveContents = file_get_contents($archivePath);

        $encryptedArchive = Crypt::encryptString($archiveContents);
        $encryptedPath = $this->createTemporaryFile($encryptedArchive);

        $this->assertSame($sql, $this->extractSqlFromUpload($encryptedPath, 'enc'));
    }

    public function testArchiveWithPathTraversalIsRejected(): void
    {
        $archivePath = $this->temporaryPath('db-backup-zip-slip-');

        $zip = new ZipArchive();
        $zip->open($archivePath, ZipArchive::OVERWRITE);
        $zip->addFromString('../escaped.sql', "SELECT 1;\n");
        $zip->close();

        $this->expectException(RuntimeException::class);

        try {
            $this->extractSqlFromUpload($archivePath, 'zip');
        } finally {
            $this->assertFileDoesNotExist(dirname(storage_path('app')).DIRECTORY_SEPARATOR.'escaped.sql');
        }
    }

    public function testUnexpectedExtensionIsRejected(): void
    {
        $file = $this->fakeUploadedFile('notes.txt');

        $this->assertFalse($this->isValidBackupExtension($file));
    }

    protected function tearDown(): void
    {
        if ($this->previousCryptInstance) {
            Crypt::swap($this->previousCryptInstance);
        } else {
            Facade::clearResolvedInstance('encrypter');
        }

        Facade::setFacadeApplication($this->previousFacadeApplication);
        Container::setInstance($this->previousContainer);

        // نحذف فقط المجلد المؤقت الذي أنشأناه بأنفسنا، وليس storage_path() أبدًا
        if ($this->isolatedDirectory !== null) {
            $this->deleteDirectory($this->isolatedDirectory);
        }

        $this->isolatedDirectory = null;
        $this->previousContainer = null;
        $this->previousFacadeApplication = null;
        $this->previousCryptInstance = null;

        parent::tearDown();
    }

    public static function basicSplitProvider(): iterable
    {
        yield 'custom delimiter with reset' => [
            <<<'SQL'
DELIMITER $$
CREATE PROCEDURE test()
BEGIN
    SELECT 'DELIMITER $$ inside string';
END$$
DELIMITER ;
SELECT 2;
SQL,
            [
                "CREATE PROCEDURE test()\nBEGIN\n    SELECT 'DELIMITER $$ inside string';\nEND",
                'SELECT 2',
            ],
        ];

        yield 'versioned delimiter comment' => [
            <<<'SQL'
DELIMITER //
CREATE TRIGGER sample BEFORE INSERT ON `demo`
FOR EACH ROW
BEGIN
    SET NEW.`created_at` = NOW();
END//
/*!50003 DELIMITER ; */
INSERT INTO `demo` (`name`) VALUES ('example');
SQL,
            [
                "CREATE TRIGGER sample BEFORE INSERT ON `demo`\nFOR EACH ROW\nBEGIN\n    SET NEW.`created_at` = NOW();\nEND",
                "INSERT INTO `demo` (`name`) VALUES ('example')",
            ],
        ];

        yield 'byte order mark is ignored' => [
            "\xEF\xBB\xBFSELECT 1;",
            ['SELECT 1'],
        ];
    }

    private function splitStatements(string $sql): array
    {
        $controller = new DatabaseBackupController();
        $reflection = new ReflectionClass(DatabaseBackupController::class);
        $method = $reflection->getMethod('splitSqlStatements');
        $method->setAccessible(true);

        return $method->invoke($controller, $sql);
    }

    private function isValidBackupExtension(UploadedFile $file): bool
    {
        $controller = new DatabaseBackupController();
        $reflection = new ReflectionClass(DatabaseBackupController::class);
        $method = $reflection->getMethod('isValidBackupExtension');
        $method->setAccessible(true);

        return (bool) $method->invoke($controller, $file);
    }

    private function extractSqlFromUpload(string $path, string $extension): string
    {
        $controller = new DatabaseBackupController();
        $reflection = new ReflectionClass(DatabaseBackupController::class);
        $method = $reflection->getMethod('extractSqlFromUpload');
        $method->setAccessible(true);

        return (string) $method->invoke($controller, $path, $extension);
    }

    private function fakeUploadedFile(string $name, ?string $mimeType = null): UploadedFile
    {
        $path = $this->temporaryPath('db-backup-test-');
        file_put_contents($path, 'dummy');

        return new UploadedFile($path, $name, $mimeType, null, true);
    }

    private function createTemporaryFile(string $contents): string
    {
        $path = $this->temporaryPath('db-backup-encrypted-');
        file_put_contents($path, $contents);

        return $path;
    }

    private function createIsolatedDirectory(): string
    {
        $tempRoot = realpath(sys_get_temp_dir());

        if ($tempRoot === false) {
            throw new RuntimeException('System temp directory is not available.');
        }

        $directory = $tempRoot.DIRECTORY_SEPARATOR.self::TEMP_DIRECTORY_PREFIX.bin2hex(random_bytes(8));

        if (! mkdir($directory, 0700) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create isolated test directory {$directory}.");
        }

        $this->assertSafeToDelete($directory);

        return $directory;
    }

    private function temporaryPath(string $prefix): string
    {
        $path = tempnam((string) $this->isolatedDirectory, $prefix);

        if ($path === false || ! str_starts_with($path, $this->isolatedDirectory.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Unable to create a temporary file inside the isolated directory.');
        }

        return $path;
    }

    /**
     * يرفض أي حذف خارج المجلد المؤقت المعزول: لا مسار المشروع، ولا storage الحقيقي، ولا أي شيء خارج sys_get_temp_dir().
     */
    private function assertSafeToDelete(string $directory): void
    {
        $target = realpath($directory);
        $tempRoot = realpath(sys_get_temp_dir());

        if ($target === false || $tempRoot === false) {
            throw new RuntimeException("Refusing to delete unresolved path [{$directory}].");
        }

        $forbidden = [realpath(dirname(__DIR__, 3))];

        if ($this->previousContainer instanceof Application) {
            $forbidden[] = realpath($this->previousContainer->basePath());
            $forbidden[] = realpath($this->previousContainer->storagePath());
        }

        foreach (array_filter($forbidden) as $protected) {
            if ($target === $protected
                || str_starts_with($target.DIRECTORY_SEPARATOR, $protected.DIRECTORY_SEPARATOR)
                || str_starts_with($protected.DIRECTORY_SEPARATOR, $target.DIRECTORY_SEPARATOR)) {
                throw new RuntimeException("Refusing to delete [{$target}]: it overlaps protected path [{$protected}].");
            }
        }

        if (! str_starts_with($target, $tempRoot.DIRECTORY_SEPARATOR)
            || ! str_starts_with(basename($target), self::TEMP_DIRECTORY_PREFIX)) {
            throw new RuntimeException("Refusing to delete [{$target}]: not an isolated test directory.");
        }
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $this->assertSafeToDelete($directory);

        $this->deleteTree($directory);
    }

    private function deleteTree(string $directory): void
    {
        $items = scandir($directory);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$item;

            if (is_dir($path) && ! is_link($path)) {
                $this->deleteTree($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}

}
