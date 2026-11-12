<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Tests\Unit\Service\RoleUserClone;

use MauticPlugin\MauticPatchesBundle\Exception\ClonePackageException;
use MauticPlugin\MauticPatchesBundle\Service\RoleUserClone\ClonePackage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ClonePackageTest extends TestCase
{
    public function testTheKeyHasAboutOneHundredLetterAndDigitCharactersAndIsNeverRepeated(): void
    {
        $package = new ClonePackage();
        $key     = $package->generateKey();

        $this->assertSame(100, strlen($key));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{100}$/', $key);
        $this->assertNotSame($key, $package->generateKey());
    }

    public function testTheDataComesBackWithTheSameKey(): void
    {
        $package = new ClonePackage();
        $key     = $package->generateKey();
        $data    = ['roles' => [['name' => 'Gestão ñ']], 'users' => []];

        $this->assertSame($data, $package->decrypt($package->encrypt($data, $key), $key));
    }

    public function testThePackageIsJsonWithTheFormatAndNoReadableData(): void
    {
        $package = new ClonePackage();
        $text    = $package->encrypt(['secret' => 'bcrypt-hash-value'], $package->generateKey());
        $decoded = json_decode($text, true);

        $this->assertSame('roles-users-clone', $decoded['format']);
        $this->assertSame(1, $decoded['version']);
        $this->assertStringNotContainsString('bcrypt-hash-value', $text);
    }

    public function testTwoEncryptionsOfTheSameDataDiffer(): void
    {
        $package = new ClonePackage();
        $key     = $package->generateKey();

        $this->assertNotSame($package->encrypt(['a' => 1], $key), $package->encrypt(['a' => 1], $key));
    }

    public function testAWrongKeyIsAClearErrorAndGivesNoData(): void
    {
        $package = new ClonePackage();
        $text    = $package->encrypt(['a' => 1], $package->generateKey());

        $this->expectException(ClonePackageException::class);
        $this->expectExceptionMessage('key');
        $package->decrypt($text, $package->generateKey());
    }

    public function testADamagedCipherTextIsRefused(): void
    {
        $package = new ClonePackage();
        $key     = $package->generateKey();
        $decoded = json_decode($package->encrypt(['a' => 1], $key), true);
        $decoded['data'] = base64_encode('x'.base64_decode($decoded['data']));

        $this->expectException(ClonePackageException::class);
        $package->decrypt((string) json_encode($decoded), $key);
    }

    #[DataProvider('badTexts')]
    public function testATextThatIsNotAPackageIsRefused(string $text): void
    {
        $this->expectException(ClonePackageException::class);
        $this->expectExceptionMessage('format');
        (new ClonePackage())->decrypt($text, 'k');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function badTexts(): array
    {
        return [
            'not json'      => ['abc'],
            'not an object' => ['5'],
            'other format'  => ['{"format":"x","version":1}'],
            'other version' => ['{"format":"roles-users-clone","version":2}'],
            'missing parts' => ['{"format":"roles-users-clone","version":1}'],
        ];
    }

    public function testAnEmptyKeyIsRefused(): void
    {
        $package = new ClonePackage();
        $text    = $package->encrypt(['a' => 1], $package->generateKey());

        $this->expectException(ClonePackageException::class);
        $this->expectExceptionMessage('key');
        $package->decrypt($text, '   ');
    }

    public function testThePackageOpensWithAnIndependentDecryptionOfTheDocumentedFormat(): void
    {
        $package = new ClonePackage();
        $key     = $package->generateKey();
        $decoded = json_decode($package->encrypt(['a' => 1], $key), true);
        $salt    = base64_decode($decoded['salt']);
        $nonce   = base64_decode($decoded['nonce']);
        $raw     = base64_decode($decoded['data']);

        $this->assertSame(16, strlen($salt));
        $this->assertSame(12, strlen($nonce));
        $plain = openssl_decrypt(substr($raw, 0, -16), 'aes-256-gcm', hash_hkdf('sha256', $key, 32, 'roles-users-clone', $salt), OPENSSL_RAW_DATA, $nonce, substr($raw, -16));
        $this->assertSame('{"a":1}', $plain);
    }

    public function testSpacesAroundThePastedKeyAreIgnored(): void
    {
        $package = new ClonePackage();
        $key     = $package->generateKey();

        $this->assertSame(['a' => 1], $package->decrypt($package->encrypt(['a' => 1], $key), "  \n".$key." \n"));
    }

    public function testADataPartOfOnlyTheTagIsAWrongKeyNotAFormatError(): void
    {
        $package = new ClonePackage();
        $decoded = json_decode($package->encrypt(['a' => 1], $package->generateKey()), true);
        $decoded['data'] = base64_encode(str_repeat('x', 16));

        $this->expectExceptionMessage('wrong_key');
        $package->decrypt((string) json_encode($decoded), 'any-key');
    }

    public function testADataPartShorterThanTheTagIsAFormatError(): void
    {
        $package = new ClonePackage();
        $decoded = json_decode($package->encrypt(['a' => 1], $package->generateKey()), true);
        $decoded['data'] = base64_encode(str_repeat('x', 15));

        $this->expectExceptionMessage('error.format');
        $package->decrypt((string) json_encode($decoded), 'any-key');
    }

    /**
     * @param array<string, mixed> $override
     */
    #[DataProvider('wrongHeaders')]
    public function testAGoodBodyUnderAnotherFormatOrVersionIsRefused(array $override): void
    {
        $package = new ClonePackage();
        $key     = $package->generateKey();
        $decoded = array_merge(json_decode($package->encrypt(['a' => 1], $key), true), $override);

        $this->expectExceptionMessage('error.format');
        $package->decrypt((string) json_encode($decoded), $key);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function wrongHeaders(): array
    {
        return ['format' => [['format' => 'other']], 'version' => [['version' => 2]], 'version text' => [['version' => '1']]];
    }
}
