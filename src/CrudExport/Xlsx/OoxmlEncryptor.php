<?php

declare(strict_types=1);

namespace CoopTilleuls\Bundle\EAExtensionsBundle\CrudExport\Xlsx;

/**
 * OOXML Agile Encryption — ECMA-376 Part 4, §3.3.3.
 *
 * Encrypts an XLSX file with a password, producing a Compound File Binary (CFB)
 * container compatible with Microsoft Office and LibreOffice.
 *
 * Requires only standard PHP extensions: openssl, mbstring, hash.
 */
final class OoxmlEncryptor
{
    // CFB format
    private const int SECTOR_SIZE = 512;
    private const int MINI_SECTOR_SIZE = 64;
    private const int ENTRIES_PER_FAT_SECTOR = self::SECTOR_SIZE / 4; // 128
    private const int MINI_STREAM_CUTOFF = 4096;

    // Package encryption
    private const int PACKAGE_CHUNK_SIZE = 4096;
    private const string HASH_ALGO = 'sha512';
    private const string CIPHER = 'aes-256-cbc';
    private const int KEY_BITS = 256;
    private const int SPIN_COUNT = 100_000;

    // ECMA-376 §3.3.3 fixed block key constants
    private const array BLOCK_KEY_HMAC_KEY = [0x5F, 0xB2, 0xAD, 0x01, 0x0C, 0xB9, 0xE1, 0xF6];
    private const array BLOCK_KEY_HMAC_VALUE = [0xA0, 0x67, 0x7F, 0x02, 0xB2, 0x2C, 0x84, 0x33];
    private const array BLOCK_KEY_KEY = [0x14, 0x6E, 0x0B, 0xE7, 0xAB, 0xAC, 0xD0, 0xD6];
    private const array BLOCK_KEY_VERIFIER_INPUT = [0xFE, 0xA7, 0xD2, 0x76, 0x3B, 0x4B, 0x9E, 0x79];
    private const array BLOCK_KEY_VERIFIER_VALUE = [0xD7, 0xAA, 0x0F, 0x6D, 0x30, 0x61, 0x34, 0x4E];

    // CFB special sector values
    private const int FREESECT = 0xFFFFFFFF;
    private const int ENDOFCHAIN = 0xFFFFFFFE;
    private const int FATSECT = 0xFFFFFFFD;
    private const int DIFSECT = 0xFFFFFFFC;
    private const int NOSTREAM = 0xFFFFFFFF;

    /**
     * Encrypt an XLSX file with a password, writing the result to $outputPath.
     */
    public static function encrypt(string $inputPath, string $password, string $outputPath): void
    {
        $packageKey = random_bytes(32);
        $packageSalt = random_bytes(16);
        $keySalt = random_bytes(16);

        $tmpPkg = self::createTempPath('pkg');
        try {
            self::encryptPackage($inputPath, $packageKey, $packageSalt, $tmpPkg);
            $encryptionInfo = self::buildEncryptionInfo($password, $packageKey, $packageSalt, $keySalt, $tmpPkg);
            self::writeCfb($encryptionInfo, $tmpPkg, $outputPath);
        } finally {
            if (file_exists($tmpPkg)) {
                @unlink($tmpPkg);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Package encryption
    // -------------------------------------------------------------------------

    private static function encryptPackage(
        string $inputPath,
        string $packageKey,
        string $packageSalt,
        string $outputPath,
    ): void {
        $originalSize = filesize($inputPath);
        if (false === $originalSize) {
            throw new \RuntimeException(sprintf('Cannot stat input file: %s', $inputPath));
        }

        $fp = fopen($inputPath, 'rb');
        if (false === $fp) {
            throw new \RuntimeException(sprintf('Cannot open input file: %s', $inputPath));
        }

        $out = fopen($outputPath, 'wb');
        if (false === $out) {
            fclose($fp);
            throw new \RuntimeException(sprintf('Cannot create temp file: %s', $outputPath));
        }

        try {
            // uint32 LE original size + 4 zero padding bytes (ECMA-376 uint64 LE, files < 4 GB)
            fwrite($out, pack('V', $originalSize)."\x00\x00\x00\x00");

            $chunkIndex = 0;
            while (!feof($fp)) {
                $chunk = fread($fp, self::PACKAGE_CHUNK_SIZE);
                if (false === $chunk || '' === $chunk) {
                    break;
                }

                // Pad to AES block boundary (16 bytes)
                $rem = strlen($chunk) % 16;
                if (0 !== $rem) {
                    $chunk = str_pad($chunk, strlen($chunk) + (16 - $rem), "\0");
                }

                $iv = self::packageChunkIv($packageSalt, $chunkIndex);
                // ECMA-376 §3.3.3: IV is deterministically derived from the random package salt + chunk index.
                // The randomness comes from $packageSalt (random_bytes), not from the IV itself — this is by spec.
                // noinspection PhpCryptographicWeakIVInspection
                $enc = openssl_encrypt($chunk, self::CIPHER, $packageKey, \OPENSSL_NO_PADDING, $iv);
                if (false === $enc) {
                    throw new \RuntimeException('Package chunk encryption failed.');
                }

                fwrite($out, $enc);
                ++$chunkIndex;
            }
        } finally {
            fclose($fp);
            fclose($out);
        }
    }

    // -------------------------------------------------------------------------
    // EncryptionInfo XML
    // -------------------------------------------------------------------------

    private static function buildEncryptionInfo(
        string $password,
        string $packageKey,
        string $packageSalt,
        string $keySalt,
        string $encryptedPackagePath,
    ): string {
        // Data integrity: HMAC of the encrypted package, itself encrypted with the package key
        $hmacKey = random_bytes(64);
        $encHmacKey = self::aesEncrypt($packageKey, self::deriveIv($packageSalt, self::BLOCK_KEY_HMAC_KEY), $hmacKey);
        $hmacValue = hash_hmac_file(self::HASH_ALGO, $encryptedPackagePath, $hmacKey, true);
        if (false === $hmacValue) {
            throw new \RuntimeException('HMAC computation failed.');
        }
        $encHmacValue = self::aesEncrypt($packageKey, self::deriveIv($packageSalt, self::BLOCK_KEY_HMAC_VALUE), $hmacValue);

        // Verifier hash: allows Office to confirm the password without decrypting the package
        $verifierInput = random_bytes(16);
        $encVerifierInput = self::aesEncrypt(
            self::deriveKey($password, $keySalt, self::BLOCK_KEY_VERIFIER_INPUT),
            $keySalt,
            $verifierInput,
        );
        $verifierValue = hash(self::HASH_ALGO, $verifierInput, true);
        $encVerifierValue = self::aesEncrypt(
            self::deriveKey($password, $keySalt, self::BLOCK_KEY_VERIFIER_VALUE),
            $keySalt,
            $verifierValue,
        );

        // Encrypted package key
        $encKeyValue = self::aesEncrypt(
            self::deriveKey($password, $keySalt, self::BLOCK_KEY_KEY),
            $keySalt,
            $packageKey,
        );

        $xml = self::encryptionXml(
            $packageSalt,
            $keySalt,
            $encHmacKey,
            $encHmacValue,
            $encVerifierInput,
            $encVerifierValue,
            $encKeyValue,
        );

        // Fixed 8-byte prefix required by the ECMA-376 spec
        return "\x04\x00\x04\x00\x40\x00\x00\x00".$xml;
    }

    private static function encryptionXml(
        string $packageSalt,
        string $keySalt,
        string $encHmacKey,
        string $encHmacValue,
        string $encVerifierInput,
        string $encVerifierValue,
        string $encKeyValue,
    ): string {
        $b64 = static fn (string $s): string => base64_encode($s);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<encryption'
            .' xmlns="http://schemas.microsoft.com/office/2006/encryption"'
            .' xmlns:p="http://schemas.microsoft.com/office/2006/keyEncryptor/password"'
            .' xmlns:c="http://schemas.microsoft.com/office/2006/keyEncryptor/certificate">'
            .'<keyData saltSize="16" blockSize="16" keyBits="256" hashSize="64"'
            .' cipherAlgorithm="AES" cipherChaining="ChainingModeCBC" hashAlgorithm="SHA512"'
            .' saltValue="'.$b64($packageSalt).'"/>'
            .'<dataIntegrity'
            .' encryptedHmacKey="'.$b64($encHmacKey).'"'
            .' encryptedHmacValue="'.$b64($encHmacValue).'"/>'
            .'<keyEncryptors>'
            .'<keyEncryptor uri="http://schemas.microsoft.com/office/2006/keyEncryptor/password">'
            .'<p:encryptedKey'
            .' spinCount="'.self::SPIN_COUNT.'"'
            .' saltSize="16" blockSize="16" keyBits="256" hashSize="64"'
            .' cipherAlgorithm="AES" cipherChaining="ChainingModeCBC" hashAlgorithm="SHA512"'
            .' saltValue="'.$b64($keySalt).'"'
            .' encryptedVerifierHashInput="'.$b64($encVerifierInput).'"'
            .' encryptedVerifierHashValue="'.$b64($encVerifierValue).'"'
            .' encryptedKeyValue="'.$b64($encKeyValue).'"/>'
            .'</keyEncryptor>'
            .'</keyEncryptors>'
            .'</encryption>';
    }

    // -------------------------------------------------------------------------
    // Compound File Binary (MS-CFB) writer
    // -------------------------------------------------------------------------

    private static function writeCfb(string $encryptionInfo, string $encryptedPackagePath, string $outputPath): void
    {
        $pkgSize = filesize($encryptedPackagePath);
        if (false === $pkgSize) {
            throw new \RuntimeException('Cannot stat encrypted package file.');
        }

        $infoSize = strlen($encryptionInfo);

        // EncryptionInfo is always < 4096 bytes → must go in the mini stream (MS-CFB §3.3).
        // EncryptedPackage is always > 4096 bytes → stored in regular FAT sectors.
        $nMiniSectors = (int) ceil($infoSize / self::MINI_SECTOR_SIZE);
        $nMiniStreamSecs = (int) ceil($nMiniSectors * self::MINI_SECTOR_SIZE / self::SECTOR_SIZE);
        $nMiniFatSecs = (int) ceil($nMiniSectors / self::ENTRIES_PER_FAT_SECTOR);
        $nPackageSectors = (int) ceil($pkgSize / self::SECTOR_SIZE);

        [$nFatSectors, $nDifatSectors] = self::calcFatSectors($nMiniFatSecs, $nMiniStreamSecs, $nPackageSectors);

        // Sector layout (all consecutive):
        //   0                                   : directory (1 sector)
        //   1 .. nFat                           : FAT sectors
        //   nFat+1 .. nFat+nDifat               : DIFAT sectors (if any)
        //   .. +nMiniFat                         : MiniFAT sectors
        //   .. +nMiniStream                      : mini stream container (root entry data)
        //   .. end                               : EncryptedPackage data
        $firstFatSector = 1;
        $firstDifatSector = 1 + $nFatSectors;
        $firstMiniFatSector = 1 + $nFatSectors + $nDifatSectors;
        $firstMiniStreamSector = 1 + $nFatSectors + $nDifatSectors + $nMiniFatSecs;
        $firstPkgSector = 1 + $nFatSectors + $nDifatSectors + $nMiniFatSecs + $nMiniStreamSecs;
        $totalSectors = 1 + $nFatSectors + $nDifatSectors + $nMiniFatSecs + $nMiniStreamSecs + $nPackageSectors;

        // Build FAT
        $fat = array_fill(0, $totalSectors, self::FREESECT);
        $fat[0] = self::ENDOFCHAIN; // directory sector
        for ($i = 0; $i < $nFatSectors; ++$i) {
            $fat[$firstFatSector + $i] = self::FATSECT;
        }
        for ($i = 0; $i < $nDifatSectors; ++$i) {
            $fat[$firstDifatSector + $i] = self::DIFSECT;
        }
        for ($i = 0; $i < $nMiniFatSecs; ++$i) {
            $fat[$firstMiniFatSector + $i] = ($i < $nMiniFatSecs - 1) ? $firstMiniFatSector + $i + 1 : self::ENDOFCHAIN;
        }
        for ($i = 0; $i < $nMiniStreamSecs; ++$i) {
            $fat[$firstMiniStreamSector + $i] = ($i < $nMiniStreamSecs - 1) ? $firstMiniStreamSector + $i + 1 : self::ENDOFCHAIN;
        }
        for ($i = 0; $i < $nPackageSectors; ++$i) {
            $s = $firstPkgSector + $i;
            $fat[$s] = ($i < $nPackageSectors - 1) ? $s + 1 : self::ENDOFCHAIN;
        }

        // Build MiniFAT: chain for EncryptionInfo mini sectors
        $miniFat = array_fill(0, max($nMiniSectors, 1), self::FREESECT);
        for ($i = 0; $i < $nMiniSectors; ++$i) {
            $miniFat[$i] = ($i < $nMiniSectors - 1) ? $i + 1 : self::ENDOFCHAIN;
        }

        $out = fopen($outputPath, 'wb');
        if (false === $out) {
            throw new \RuntimeException(sprintf('Cannot create output file: %s', $outputPath));
        }

        try {
            // Header
            fwrite($out, self::cfbHeader(
                0,
                $nFatSectors,
                $nDifatSectors,
                $firstFatSector,
                $firstDifatSector,
                $nMiniFatSecs,
                $firstMiniFatSector,
            ));

            // Directory sector
            // Root entry: startSector = mini stream container, size = logical mini stream size
            // EncryptionInfo: startSector = mini sector 0, size = actual stream size
            // EncryptedPackage: startSector = regular FAT sector, size = actual stream size
            fwrite($out, self::dirSector(
                0,
                $infoSize,
                $firstPkgSector,
                $pkgSize,
                $firstMiniStreamSector,
                $nMiniSectors * self::MINI_SECTOR_SIZE,
            ));

            // FAT sectors
            $fatBin = '';
            foreach ($fat as $entry) {
                $fatBin .= pack('V', $entry);
            }
            fwrite($out, str_pad($fatBin, $nFatSectors * self::SECTOR_SIZE, "\xFF\xFF\xFF\xFF"));

            // DIFAT sectors (only if nFatSectors > 109)
            if ($nDifatSectors > 0) {
                fwrite($out, self::buildDifatSectors($nFatSectors, $nDifatSectors, $firstFatSector, $firstDifatSector));
            }

            // MiniFAT sectors
            $miniFatBin = '';
            foreach ($miniFat as $entry) {
                $miniFatBin .= pack('V', $entry);
            }
            fwrite($out, str_pad($miniFatBin, $nMiniFatSecs * self::SECTOR_SIZE, "\xFF\xFF\xFF\xFF"));

            // Mini stream container (root entry data): EncryptionInfo in mini sectors, padded to sector boundary
            $miniStreamData = str_pad($encryptionInfo, $nMiniStreamSecs * self::SECTOR_SIZE, "\0");
            fwrite($out, $miniStreamData);

            // EncryptedPackage stream (streamed to avoid large memory usage)
            $pkgFp = fopen($encryptedPackagePath, 'rb');
            if (false === $pkgFp) {
                throw new \RuntimeException('Cannot open encrypted package file.');
            }
            try {
                $written = 0;
                while (!feof($pkgFp)) {
                    $buf = fread($pkgFp, 65536);
                    if (false === $buf || '' === $buf) {
                        break;
                    }
                    fwrite($out, $buf);
                    $written += strlen($buf);
                }
                $rem = $written % self::SECTOR_SIZE;
                if (0 !== $rem) {
                    fwrite($out, str_repeat("\0", self::SECTOR_SIZE - $rem));
                }
            } finally {
                fclose($pkgFp);
            }
        } finally {
            fclose($out);
        }
    }

    /**
     * Iteratively determine the number of FAT and DIFAT sectors needed.
     *
     * @return array{0: int, 1: int} [nFatSectors, nDifatSectors]
     */
    private static function calcFatSectors(int $nMiniFatSecs, int $nMiniStreamSecs, int $nPackageSectors): array
    {
        $nDirSectors = 1;
        $nFatSectors = 0;
        $nDifatSectors = 0;

        for ($i = 0; $i < 20; ++$i) {
            $total = $nDirSectors + $nFatSectors + $nDifatSectors + $nMiniFatSecs + $nMiniStreamSecs + $nPackageSectors;
            $newFat = (int) ceil($total / self::ENTRIES_PER_FAT_SECTOR);
            $newDifat = $newFat > 109 ? (int) ceil(($newFat - 109) / 127) : 0;

            if ($newFat === $nFatSectors && $newDifat === $nDifatSectors) {
                break;
            }

            $nFatSectors = $newFat;
            $nDifatSectors = $newDifat;
        }

        return [$nFatSectors, $nDifatSectors];
    }

    private static function cfbHeader(
        int $dirSector,
        int $nFatSectors,
        int $nDifatSectors,
        int $firstFatSector,
        int $firstDifatSector,
        int $nMiniFatSectors,
        int $firstMiniFatSector,
    ): string {
        $h = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"; // Magic
        $h .= str_repeat("\x00", 16);               // CLSID
        $h .= pack('v', 0x003E);                    // Minor version
        $h .= pack('v', 0x0003);                    // Major version (v3, 512-byte sectors)
        $h .= "\xFE\xFF";                           // Byte order mark (little-endian)
        $h .= pack('v', 9);                         // Sector size = 2^9 = 512
        $h .= pack('v', 6);                         // Mini sector size = 2^6 = 64
        $h .= str_repeat("\x00", 6);                // Reserved
        $h .= pack('V', 0);                         // Directory FAT sectors (v3: always 0)
        $h .= pack('V', $nFatSectors);              // FAT sector count
        $h .= pack('V', $dirSector);                // First directory sector
        $h .= pack('V', 0);                         // Transaction signature
        $h .= pack('V', self::MINI_STREAM_CUTOFF);  // Mini stream cutoff (4096)
        $h .= pack('V', $firstMiniFatSector);       // First mini FAT sector
        $h .= pack('V', $nMiniFatSectors);          // Mini FAT sector count
        $h .= pack('V', $nDifatSectors > 0 ? $firstDifatSector : self::ENDOFCHAIN);
        $h .= pack('V', $nDifatSectors);            // DIFAT sector count

        // Inline DIFAT array: up to 109 FAT sector locations
        for ($i = 0; $i < 109; ++$i) {
            $h .= pack('V', $i < $nFatSectors ? $firstFatSector + $i : self::FREESECT);
        }

        return $h; // exactly 512 bytes
    }

    private static function buildDifatSectors(
        int $nFatSectors,
        int $nDifatSectors,
        int $firstFatSector,
        int $firstDifatSector,
    ): string {
        $result = '';
        $fatIdx = 109; // first 109 FAT sectors are already referenced in the header

        for ($d = 0; $d < $nDifatSectors; ++$d) {
            $sector = '';
            for ($e = 0; $e < 127; ++$e) { // 127 FAT sector entries per DIFAT sector
                $sector .= pack('V', $fatIdx < $nFatSectors ? $firstFatSector + $fatIdx : self::FREESECT);
                if ($fatIdx < $nFatSectors) {
                    ++$fatIdx;
                }
            }
            $next = ($d < $nDifatSectors - 1) ? $firstDifatSector + $d + 1 : self::ENDOFCHAIN;
            $sector .= pack('V', $next);
            $result .= $sector;
        }

        return $result;
    }

    /** Build the directory sector (512 bytes = 4 entries × 128 bytes). */
    private static function dirSector(
        int $infoStart,
        int $infoSize,
        int $pkgStart,
        int $pkgSize,
        int $rootStart,
        int $rootSize,
    ): string {
        // Root Entry: owns the mini stream container (startSector = first mini stream sector, size = mini stream logical size)
        // EncryptionInfo: stored in the mini stream (startSector = mini sector 0, size = actual stream size)
        // EncryptedPackage: stored in regular FAT sectors
        return self::dirEntry('Root Entry', 5, self::NOSTREAM, self::NOSTREAM, 1, $rootStart, $rootSize)
              .self::dirEntry('EncryptionInfo', 2, self::NOSTREAM, 2, self::NOSTREAM, $infoStart, $infoSize)
              .self::dirEntry('EncryptedPackage', 2, self::NOSTREAM, self::NOSTREAM, self::NOSTREAM, $pkgStart, $pkgSize)
              .self::dirEntry('', 0, self::NOSTREAM, self::NOSTREAM, self::NOSTREAM, self::ENDOFCHAIN, 0);
    }

    /** Build a 128-byte directory entry. */
    private static function dirEntry(
        string $name,
        int $type,
        int $left,
        int $right,
        int $child,
        int $startSector,
        int $size,
    ): string {
        $nameUtf16 = mb_convert_encoding($name, 'UTF-16LE', 'UTF-8');
        $nameSizeBytes = strlen($nameUtf16) > 0 ? strlen($nameUtf16) + 2 : 0; // +2 for null terminator

        $e = str_pad($nameUtf16, 64, "\0");  // Name field (64 bytes)
        $e .= pack('v', $nameSizeBytes);       // Name size including null terminator
        $e .= pack('C', $type);                // Object type
        $e .= pack('C', 1);                    // Color flag: black
        $e .= pack('V', $left);                // Left sibling SID
        $e .= pack('V', $right);               // Right sibling SID
        $e .= pack('V', $child);               // Child SID
        $e .= str_repeat("\x00", 16);          // CLSID
        $e .= pack('V', 0);                    // State bits
        $e .= str_repeat("\x00", 8);           // Created time
        $e .= str_repeat("\x00", 8);           // Modified time
        $e .= pack('V', $startSector);         // Starting sector
        $e .= pack('V', $size);                // Stream size (low 32 bits, v3)
        $e .= pack('V', 0);                    // Stream size (high 32 bits, v3: 0)

        return $e; // 128 bytes
    }

    // -------------------------------------------------------------------------
    // Cryptographic helpers
    // -------------------------------------------------------------------------

    /**
     * ECMA-376 §3.3.3.1 password-based key derivation.
     *
     * @param array<int, int> $blockKey
     */
    private static function deriveKey(string $password, string $salt, array $blockKey): string
    {
        $h = hash_init(self::HASH_ALGO);
        hash_update($h, $salt);
        hash_update($h, mb_convert_encoding($password, 'UTF-16LE', 'UTF-8'));
        $key = hash_final($h, true);

        for ($i = 0; $i < self::SPIN_COUNT; ++$i) {
            $key = hash(self::HASH_ALGO, pack('V', $i).$key, true);
        }

        $key = hash(self::HASH_ALGO, $key.pack('C*', ...$blockKey), true);

        $len = self::KEY_BITS / 8;

        return strlen($key) >= $len
            ? substr($key, 0, $len)
            : str_pad($key, $len, "\x36");
    }

    /** IV for encrypting package chunk at index $i. */
    private static function packageChunkIv(string $salt, int $chunkIndex): string
    {
        return substr(hash(self::HASH_ALGO, $salt.pack('V', $chunkIndex), true), 0, 16);
    }

    /** IV derived from salt and a fixed block key constant.
     *
     * @param array<int, int> $blockKey
     */
    private static function deriveIv(string $salt, array $blockKey): string
    {
        return substr(hash(self::HASH_ALGO, $salt.pack('C*', ...$blockKey), true), 0, 16);
    }

    /**
     * AES-256-CBC encrypt without padding (caller must pre-pad data to 16-byte boundary).
     *
     * The $iv is intentionally deterministic (derived from a random salt + a fixed block key per ECMA-376 §3.3.3).
     * Randomness is provided by the salt generated in {@see encrypt()}, not by the IV itself.
     */
    // noinspection PhpCryptographicWeakIVInspection
    private static function aesEncrypt(string $key, string $iv, string $data): string
    {
        // Ensure key is exactly 32 bytes
        $key = strlen($key) >= 32 ? substr($key, 0, 32) : str_pad($key, 32, "\x36");

        // Pad data to 16-byte boundary
        $rem = strlen($data) % 16;
        if (0 !== $rem) {
            $data = str_pad($data, strlen($data) + (16 - $rem), "\0");
        }

        $result = openssl_encrypt($data, self::CIPHER, $key, \OPENSSL_NO_PADDING, $iv);
        if (false === $result) {
            throw new \RuntimeException('AES-CBC encryption failed: '.openssl_error_string());
        }

        return $result;
    }

    private static function createTempPath(string $prefix): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ooxml_'.$prefix.'_');
        if (false === $tmp) {
            throw new \RuntimeException('Could not create temporary file.');
        }

        return $tmp;
    }
}
