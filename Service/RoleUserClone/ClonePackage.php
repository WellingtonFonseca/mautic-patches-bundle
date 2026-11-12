<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Service\RoleUserClone;

use MauticPlugin\MauticPatchesBundle\Exception\ClonePackageException;

/**
 * Encrypts the roles / users data of a clone with AES-256-GCM. The key is random text shown once at the export: the package alone
 * (it holds password hashes) opens nothing. The messages of ClonePackageException are translation keys.
 */
class ClonePackage
{
    public const FORMAT  = 'roles-users-clone';
    public const VERSION = 1;

    private const KEY_LENGTH = 100;
    private const ALPHABET   = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    private const CIPHER     = 'aes-256-gcm';
    private const INFO       = 'roles-users-clone';

    public function generateKey(): string
    {
        $key = '';
        for ($i = 0; $i < self::KEY_LENGTH; ++$i) {
            $key .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $key;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function encrypt(array $data, string $key): string
    {
        $salt  = random_bytes(16);
        $nonce = random_bytes(12);
        $tag   = '';
        $plain = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $cipher = openssl_encrypt($plain, self::CIPHER, $this->derive($key, $salt), OPENSSL_RAW_DATA, $nonce, $tag);
        if (false === $cipher) {
            throw new ClonePackageException('mautic.patches.role_user_clone.error.encrypt');
        }

        return (string) json_encode([
            'format'  => self::FORMAT,
            'version' => self::VERSION,
            'salt'    => base64_encode($salt),
            'nonce'   => base64_encode($nonce),
            'data'    => base64_encode($cipher.$tag),
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    public function decrypt(string $text, string $key): array
    {
        $package = $this->parse($text);
        if ('' === trim($key)) {
            throw new ClonePackageException('mautic.patches.role_user_clone.error.empty_key');
        }

        $raw = $package['data'];
        if (strlen($raw) < 16) {
            throw new ClonePackageException('mautic.patches.role_user_clone.error.format');
        }
        $plain = openssl_decrypt(substr($raw, 0, -16), self::CIPHER, $this->derive(trim($key), $package['salt']), OPENSSL_RAW_DATA, $package['nonce'], substr($raw, -16));
        if (false === $plain) {
            // GCM authenticates: a wrong key and a changed text both end here, and nothing is read
            throw new ClonePackageException('mautic.patches.role_user_clone.error.wrong_key');
        }

        $data = json_decode($plain, true);
        if (!is_array($data)) {
            throw new ClonePackageException('mautic.patches.role_user_clone.error.format');
        }

        return $data;
    }

    /**
     * @return array{salt: string, nonce: string, data: string}
     */
    private function parse(string $text): array
    {
        $decoded = json_decode($text, true);
        $error   = new ClonePackageException('mautic.patches.role_user_clone.error.format');
        if (!is_array($decoded) || self::FORMAT !== ($decoded['format'] ?? null) || self::VERSION !== ($decoded['version'] ?? null)) {
            throw $error;
        }

        $parts = [];
        foreach (['salt', 'nonce', 'data'] as $name) {
            $value = is_string($decoded[$name] ?? null) ? base64_decode($decoded[$name], true) : false;
            if (false === $value || '' === $value) {
                throw $error;
            }
            $parts[$name] = $value;
        }

        return $parts;
    }

    private function derive(string $key, string $salt): string
    {
        return hash_hkdf('sha256', $key, 32, self::INFO, $salt);
    }
}
