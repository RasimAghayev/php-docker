<?php

use App\Model\Language;

class LanguageTest extends \PHPUnit\Framework\TestCase
{
    public function testTableConstantIsLanguage(): void
    {
        $this->assertSame('language', Language::TABLE);
    }

    public function testGetIdReturnsSetId(): void
    {
        $language = new Language();
        $reflection = new \ReflectionClass($language);
        $idProp = $reflection->getProperty('id');
        $idProp->setAccessible(true);
        $idProp->setValue($language, 42);

        $this->assertSame(42, $language->getId());
    }

    public function testGetNameReturnsSetName(): void
    {
        $language = new Language();
        $reflection = new \ReflectionClass($language);
        $nameProp = $reflection->getProperty('name');
        $nameProp->setAccessible(true);
        $nameProp->setValue($language, 'French');

        $this->assertSame('French', $language->getName());
    }

    public function testPropertiesArePrivate(): void
    {
        $reflection = new \ReflectionClass(Language::class);
        $this->assertTrue($reflection->getProperty('id')->isPrivate());
        $this->assertTrue($reflection->getProperty('name')->isPrivate());
    }
}
