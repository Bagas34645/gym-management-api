<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

interface FaceRecognitionInterface
{
    /**
     * @return array{embedding: array<float>, quality_score: float}
     */
    public function register(UploadedFile $image): array;

    /**
     * @return array{matched: bool, confidence: float}
     */
    public function verify(UploadedFile $image, array $storedEmbedding): array;

    /**
     * Identify a face against many candidates (1:N) for kiosk check-in.
     *
     * @param  array<int, array{user_id: string, embedding: array<float>}>  $candidates
     * @return array{matched: bool, user_id: ?string, confidence: float}
     */
    public function identify(UploadedFile $image, array $candidates): array;
}
