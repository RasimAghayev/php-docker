<?php

use App\Cache\TranslationCache;
use App\Repository\TranslationRepository;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class TranslationCacheTest extends \PHPUnit\Framework\TestCase
{
    public function testCacheMissDelegatesToRepositoryAndReturnsTranslation(): void
    {
        $adapter = new ArrayAdapter();
        $repository = $this->createMock(TranslationRepository::class);
        $repository->method('findForLanguage')->willReturn('bonjour');

        $cache = new TranslationCache($adapter, $repository);

        $result = $cache->findForLanguage(1, 'hello');

        $this->assertSame('bonjour', $result);
    }

    public function testCacheHitDoesNotCallRepository(): void
    {
        $adapter = new ArrayAdapter();
        $repository1 = $this->createMock(TranslationRepository::class);
        $repository1->method('findForLanguage')->willReturn('bonjour');
        $cache = new TranslationCache($adapter, $repository1);
        $cache->findForLanguage(1, 'hello');

        $repository2 = $this->createMock(TranslationRepository::class);
        $repository2->expects($this->never())->method('findForLanguage');
        $cache2 = new TranslationCache($adapter, $repository2);

        $result = $cache2->findForLanguage(1, 'hello');
        $this->assertSame('bonjour', $result);
    }

    public function testDifferentKeysAreCachedIndependently(): void
    {
        $adapter = new ArrayAdapter();
        $repository = $this->createMock(TranslationRepository::class);
        $repository->method('findForLanguage')->willReturnCallback(
            static fn(int $languageId, string $phrase) => match ($phrase) {
                'hello' => 'bonjour',
                'goodbye' => 'au revoir',
                default => null,
            }
        );
        $cache = new TranslationCache($adapter, $repository);

        $this->assertSame('bonjour', $cache->findForLanguage(1, 'hello'));
        $this->assertSame('au revoir', $cache->findForLanguage(1, 'goodbye'));
        $this->assertSame('bonjour', $cache->findForLanguage(1, 'hello'));
    }

    public function testNullTranslationIsCachedAsNull(): void
    {
        $adapter = new ArrayAdapter();
        $repository = $this->createMock(TranslationRepository::class);
        $repository->expects($this->once())->method('findForLanguage')->willReturn(null);
        $cache = new TranslationCache($adapter, $repository);

        $this->assertNull($cache->findForLanguage(99, 'missing'));
    }
}
