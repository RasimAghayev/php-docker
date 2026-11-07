<?php

use App\Repository\TranslationRepository;
use App\Database\Connection;

class TranslationLookupTest extends \PHPUnit\Framework\TestCase
{
    private static $pdo = null;

    public static function setUpBeforeClass(): void
    {
        // Characterization test: exercises the real PDO-backed repository
        // against a MySQL container started by `make up` / `make verify`.
        // Skipped when no database is available (e.g. CI without Docker).
        $dsn = $_ENV['MYSQL_DSN'] ?? 'mysql:host=127.0.0.1;port=3306;dbname=docker-php';
        $user = $_ENV['MYSQL_USER'] ?? 'user';
        $pass = $_ENV['MYSQL_PASSWORD'] ?? 'secret';
        try {
            self::$pdo = new \PDO($dsn, $user, $pass, [
                \PDO::ATTR_ERRMODE => \PDO::ATTR_ERRMODE_EXCEPTION,
            ]);
        } catch (\Throwable $e) {
            self::$pdo = null;
        }
    }

    public function testRepositoryResolvesKnownTranslation(): void
    {
        $this->assertNotNull(self::$pdo, 'MySQL not available — characterization test skipped');
        $repo = new TranslationRepository();
        // Reflection to inject our test PDO into the private singleton.
        $conn = new \ReflectionClass(Connection::class);
        $inst = $conn->getProperty('instance');
        $inst->setAccessible(true);
        $inst->setValue(null, null); // reset singleton
        $pdoProp = $conn->getProperty('pdo');
        $pdoProp->setAccessible(true);
        $instance = Connection::getInstance();
        $pdoProp->setValue($instance, self::$pdo);

        $result = $repo->findForLanguage(1, 'hello');
        $this->assertSame('bonjour', $result);
    }
}
