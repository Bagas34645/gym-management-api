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
}
