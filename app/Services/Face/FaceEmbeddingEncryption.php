<?php

namespace App\Services\Face;

class FaceEmbeddingEncryption
{
    public function encrypt(array $embedding): string
    {
        $key = $this->deriveKey();
        $iv = random_bytes(16);
        $payload = json_encode($embedding);
        $encrypted = openssl_encrypt($payload, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        return base64_encode($iv.$encrypted);
    }

    public function decrypt(string $encrypted): array
    {
        $key = $this->deriveKey();
        $raw = base64_decode($encrypted);
        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        $payload = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        return json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
    }

    private function deriveKey(): string
    {
        return hash('sha256', config('gym.face_encryption_key'), true);
    }
}
