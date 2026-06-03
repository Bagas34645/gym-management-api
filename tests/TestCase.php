<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $private = config('jwt.private_key_path');
        $public = config('jwt.public_key_path');

        if (! file_exists($private) || ! file_exists($public)) {
            $dir = storage_path('app/jwt');
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            exec('openssl genrsa -out '.escapeshellarg($dir.'/private.pem').' 2048 2>/dev/null');
            exec('openssl rsa -in '.escapeshellarg($dir.'/private.pem').' -pubout -out '.escapeshellarg($dir.'/public.pem').' 2>/dev/null');
        }
    }
}
