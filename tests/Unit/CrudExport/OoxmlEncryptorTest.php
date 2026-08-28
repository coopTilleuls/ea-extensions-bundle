<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\Tests\Unit\CrudExport;

use CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Xlsx\OoxmlEncryptor;
use PHPUnit\Framework\TestCase;

/**
 * Round-trip test for OoxmlEncryptor's hand-rolled ECMA-376 §3.3.3 Agile Encryption
 * writer. Deliberately does NOT call any OoxmlEncryptor internals: it re-derives the
 * key from scratch from the produced EncryptionInfo XML and MS-CFB layout, the same way
 * a real OOXML reader (Office, LibreOffice) would — so this exercises the actual on-disk
 * format, not just "encrypt() ran without throwing".
 */
final class OoxmlEncryptorTest extends TestCase
{
    private const string PASSWORD = 'S3cr3t!Pass';

    private const array BLOCK_KEY_KEY = [0x14, 0x6E, 0x0B, 0xE7, 0xAB, 0xAC, 0xD0, 0xD6];
    private const array BLOCK_KEY_VERIFIER_INPUT = [0xFE, 0xA7, 0xD2, 0x76, 0x3B, 0x4B, 0x9E, 0x79];
    private const array BLOCK_KEY_VERIFIER_VALUE = [0xD7, 0xAA, 0x0F, 0x6D, 0x30, 0x61, 0x34, 0x4E];
    private const int SPIN_COUNT = 100_000;

    public function testEncryptedFileDecryptsBackToTheOriginalBytes(): void
    {
        $original = "id,name\n1,Alice\n2,\"Bob, Jr.\"\n3,Charlie\n".str_repeat('x', 10_000);
        $inputPath = tempnam(sys_get_temp_dir(), 'ooxml_test_in_');
        $outputPath = tempnam(sys_get_temp_dir(), 'ooxml_test_out_');
        file_put_contents($inputPath, $original);

        try {
            OoxmlEncryptor::encrypt($inputPath, self::PASSWORD, $outputPath);

            $encrypted = file_get_contents($outputPath);
            self::assertIsString($encrypted);
            $decrypted = $this->decrypt($encrypted, self::PASSWORD);

            self::assertSame($original, $decrypted, 'decrypted payload must match the original bytes exactly');
        } finally {
            @unlink($inputPath);
            @unlink($outputPath);
        }
    }

    public function testWrongPasswordFailsTheVerifierCheck(): void
    {
        $inputPath = tempnam(sys_get_temp_dir(), 'ooxml_test_in_');
        $outputPath = tempnam(sys_get_temp_dir(), 'ooxml_test_out_');
        file_put_contents($inputPath, 'irrelevant content');

        try {
            OoxmlEncryptor::encrypt($inputPath, self::PASSWORD, $outputPath);

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('password verifier mismatch');

            $encrypted = file_get_contents($outputPath);
            self::assertIsString($encrypted);
            $this->decrypt($encrypted, 'wrong-password');
        } finally {
            @unlink($inputPath);
            @unlink($outputPath);
        }
    }

    /**
     * Minimal from-spec MS-CFB reader + ECMA-376 KDF, just enough to recover the
     * package key and decrypt — independent of (doesn't call into) OoxmlEncryptor.
     */
    private function decrypt(string $file, string $password): string
    {
        self::assertSame("\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1", substr($file, 0, 8), 'not a CFB file');

        $nFat = $this->u32($file, 44);
        $nDifat = $this->u32($file, 72);
        $nMiniFat = $this->u32($file, 64);

        $dir = substr($file, 512, 512);
        $rootStart = $this->u32($dir, 116);
        $infoSize = $this->u32($dir, 248);
        $pkgStart = $this->u32($dir, 372);
        $pkgSize = $this->u32($dir, 376);

        $miniStreamOffset = 512 + 512 + ($nFat + $nDifat + $nMiniFat) * 512;
        self::assertSame($miniStreamOffset, 512 + $rootStart * 512, 'mini stream offset mismatch');

        $infoBlob = substr($file, $miniStreamOffset, $infoSize);
        self::assertSame("\x04\x00\x04\x00\x40\x00\x00\x00", substr($infoBlob, 0, 8), 'bad EncryptionInfo header');
        $xml = new \SimpleXMLElement(substr($infoBlob, 8));
        $xml->registerXPathNamespace('e', 'http://schemas.microsoft.com/office/2006/encryption');
        $xml->registerXPathNamespace('p', 'http://schemas.microsoft.com/office/2006/keyEncryptor/password');

        $keyDataElements = $xml->xpath('//e:keyData');
        self::assertIsArray($keyDataElements);
        self::assertNotEmpty($keyDataElements);
        $packageSalt = $this->b64($keyDataElements[0]['saltValue']);

        $encryptedKeyElements = $xml->xpath('//p:encryptedKey');
        self::assertIsArray($encryptedKeyElements);
        self::assertNotEmpty($encryptedKeyElements);
        $encryptedKey = $encryptedKeyElements[0];
        $keySalt = $this->b64($encryptedKey['saltValue']);
        self::assertSame(self::SPIN_COUNT, (int) $encryptedKey['spinCount']);

        $verifierInputKey = $this->deriveKey($password, $keySalt, self::BLOCK_KEY_VERIFIER_INPUT);
        $verifierInput = $this->aesDecrypt($verifierInputKey, $keySalt, $this->b64($encryptedKey['encryptedVerifierHashInput']));
        $verifierValueKey = $this->deriveKey($password, $keySalt, self::BLOCK_KEY_VERIFIER_VALUE);
        $verifierValue = $this->aesDecrypt($verifierValueKey, $keySalt, $this->b64($encryptedKey['encryptedVerifierHashValue']));

        if (hash('sha512', $verifierInput, true) !== $verifierValue) {
            throw new \RuntimeException('password verifier mismatch');
        }

        $keyKey = $this->deriveKey($password, $keySalt, self::BLOCK_KEY_KEY);
        $packageKey = $this->aesDecrypt($keyKey, $keySalt, $this->b64($encryptedKey['encryptedKeyValue']));

        $pkgBlob = substr($file, 512 + $pkgStart * 512, $pkgSize);
        $originalSize = $this->u32($pkgBlob, 0);
        $ciphertext = substr($pkgBlob, 8);

        $plaintext = '';
        $chunkIndex = 0;
        foreach (str_split($ciphertext, 4096) as $chunk) {
            $iv = substr(hash('sha512', $packageSalt.pack('V', $chunkIndex), true), 0, 16);
            $plaintext .= $this->aesDecrypt($packageKey, $iv, $chunk);
            ++$chunkIndex;
        }

        return substr($plaintext, 0, $originalSize);
    }

    /**
     * @param array<int, int> $blockKey
     */
    private function deriveKey(string $password, string $salt, array $blockKey): string
    {
        $key = hash('sha512', $salt.mb_convert_encoding($password, 'UTF-16LE', 'UTF-8'), true);
        for ($i = 0; $i < self::SPIN_COUNT; ++$i) {
            $key = hash('sha512', pack('V', $i).$key, true);
        }

        return substr(hash('sha512', $key.pack('C*', ...$blockKey), true), 0, 32);
    }

    private function aesDecrypt(string $key, string $iv, string $data): string
    {
        $result = openssl_decrypt($data, 'aes-256-cbc', $key, \OPENSSL_RAW_DATA | \OPENSSL_ZERO_PADDING, $iv);
        self::assertIsString($result, 'AES-CBC decryption failed');

        return $result;
    }

    private function u32(string $data, int $offset): int
    {
        $unpacked = unpack('V', substr($data, $offset, 4));
        self::assertNotFalse($unpacked);

        return $unpacked[1];
    }

    private function b64(mixed $value): string
    {
        $decoded = base64_decode((string) $value, true);
        self::assertIsString($decoded);

        return $decoded;
    }
}
