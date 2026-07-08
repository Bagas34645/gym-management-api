<?php

namespace App\Services\Auth;

use Kreait\Firebase\Contract\Auth as FirebaseAuth;
use Kreait\Firebase\Factory;

class FirebaseService
{
    private FirebaseAuth $auth;

    public function __construct()
    {
        $factory = (new Factory)->withServiceAccount(
            base_path(config('firebase.credentials'))
        );
        $this->auth = $factory->createAuth();
    }

    public function verifyIdToken(string $idToken): array
    {
        $verifiedToken = $this->auth->verifyIdToken($idToken);

        return [
            'uid' => $verifiedToken->claims()->get('sub'),
            'email' => $verifiedToken->claims()->get('email'),
            'name' => $verifiedToken->claims()->get('name'),
            'email_verified' => $verifiedToken->claims()->get('email_verified', false),
        ];
    }
}
