<?php

namespace ConfigCat\Tests;

use ConfigCat\Tests\Helpers\Utils;
use ConfigCat\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testConstructEmptyIdentifier()
    {
        $user = new User('');
        $this->assertEquals('', $user->getIdentifier());
    }

    public function testGetAttributeEmptyKey()
    {
        $user = new User('id');
        $this->assertEquals('', $user->getAttribute(''));
    }

    public function testCreateUserWithIdAndEmailAndCountryAllAttributesShouldContainsPassedValues()
    {
        // Arrange

        $user = new User('id', 'id@example.com', 'US');

        // Act

        $actualAttributes = $user->getAllAttributes();

        // Assert

        $this->assertIsArray($actualAttributes);
        $this->assertEquals(3, count($actualAttributes));

        $this->assertArrayHasKey(User::IDENTIFIER_ATTRIBUTE, $actualAttributes);
        $this->assertSame('id', $actualAttributes[User::IDENTIFIER_ATTRIBUTE]);
        $this->assertSame('id', $user->getAttribute(User::IDENTIFIER_ATTRIBUTE));
        $this->assertSame('id', $user->getIdentifier());

        $this->assertArrayHasKey(User::EMAIL_ATTRIBUTE, $actualAttributes);
        $this->assertSame('id@example.com', $actualAttributes[User::EMAIL_ATTRIBUTE]);
        $this->assertSame('id@example.com', $user->getAttribute(User::EMAIL_ATTRIBUTE));

        $this->assertArrayHasKey(User::COUNTRY_ATTRIBUTE, $actualAttributes);
        $this->assertSame('US', $actualAttributes[User::COUNTRY_ATTRIBUTE]);
        $this->assertSame('US', $user->getAttribute(User::COUNTRY_ATTRIBUTE));
    }

    public function testUseWellKnownAttributesAsCustomPropertiesShouldNotAppendAllAttributes()
    {
        // Arrange

        $user = new User('id', 'id@example.com', 'US', [
            'myCustomAttribute' => 'myCustomAttributeValue',
            User::IDENTIFIER_ATTRIBUTE => 'myIdentifier',
            User::COUNTRY_ATTRIBUTE => 'United States',
            User::EMAIL_ATTRIBUTE => 'otherEmail@example.com',
        ]);

        // Act

        $actualAttributes = $user->getAllAttributes();

        // Assert

        $this->assertIsArray($actualAttributes);
        $this->assertEquals(4, count($actualAttributes));

        $this->assertArrayHasKey(User::IDENTIFIER_ATTRIBUTE, $actualAttributes);
        $this->assertSame('id', $actualAttributes[User::IDENTIFIER_ATTRIBUTE]);
        $this->assertSame('id', $user->getAttribute(User::IDENTIFIER_ATTRIBUTE));
        $this->assertSame('id', $user->getIdentifier());

        $this->assertArrayHasKey(User::EMAIL_ATTRIBUTE, $actualAttributes);
        $this->assertSame('id@example.com', $actualAttributes[User::EMAIL_ATTRIBUTE]);
        $this->assertSame('id@example.com', $user->getAttribute(User::EMAIL_ATTRIBUTE));

        $this->assertArrayHasKey(User::COUNTRY_ATTRIBUTE, $actualAttributes);
        $this->assertSame('US', $actualAttributes[User::COUNTRY_ATTRIBUTE]);
        $this->assertSame('US', $user->getAttribute(User::COUNTRY_ATTRIBUTE));

        $this->assertArrayHasKey('myCustomAttribute', $actualAttributes);
        $this->assertSame('myCustomAttributeValue', $actualAttributes['myCustomAttribute']);
        $this->assertSame('myCustomAttributeValue', $user->getAttribute('myCustomAttribute'));
    }

    /**
     * @dataProvider provideTestDataForUseWellKnownAttributesAsCustomPropertiesWithDifferentNames_ShouldAppendAllAttributes
     */
    public function testUseWellKnownAttributesAsCustomPropertiesWithDifferentNamesShouldAppendAllAttributes(string $attributeName, string $attributeValue)
    {
        // Arrange

        $user = new User('id', 'id@example.com', 'US', [
            $attributeName => $attributeValue,
        ]);

        // Act

        $actualAttributes = $user->getAllAttributes();

        // Assert

        $this->assertIsArray($actualAttributes);
        $this->assertEquals(4, count($actualAttributes));

        $this->assertArrayHasKey($attributeName, $actualAttributes);
        $this->assertSame($attributeValue, $actualAttributes[$attributeName]);
        $this->assertSame($attributeValue, $user->getAttribute($attributeName));
    }

    public function provideTestDataForUseWellKnownAttributesAsCustomPropertiesWithDifferentNames_ShouldAppendAllAttributes(): array
    {
        return Utils::withDescription([
            ['identifier', 'myId'],
            ['IDENTIFIER', 'myId'],
            ['email', 'theBoss@example.com'],
            ['EMAIL', 'theBoss@example.com'],
            ['eMail', 'theBoss@example.com'],
            ['country', 'myHome'],
            ['COUNTRY', 'myHome'],
        ], function ($testCase) {
            return "attributeName: {$testCase[0]} | attributeValue: {$testCase[1]}";
        });
    }

    /**
     * @dataProvider provideTestDataForCreateUser_ShouldSetIdentifier
     */
    public function testCreateUserShouldSetIdentifier(string $identifier, string $expectedValue)
    {
        // Arrange

        $user = new User($identifier);

        // Act

        $actualAttributes = $user->getAllAttributes();

        // Assert

        $this->assertArrayHasKey(User::IDENTIFIER_ATTRIBUTE, $actualAttributes);
        $this->assertSame($expectedValue, $actualAttributes[User::IDENTIFIER_ATTRIBUTE]);
        $this->assertSame($expectedValue, $user->getAttribute(User::IDENTIFIER_ATTRIBUTE));
        $this->assertSame($expectedValue, $user->getIdentifier());
    }

    public function provideTestDataForCreateUser_ShouldSetIdentifier()
    {
        return Utils::withDescription([
            [null, ''],
            ['', ''],
            ['id', 'id'],
            ["\t", "\t"],
            ["\u{1F60}0", "\u{1F60}0"],
        ], function ($testCase) {
            return "identifier: {$testCase[0]}";
        });
    }
}
