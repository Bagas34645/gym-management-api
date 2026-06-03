<?php

namespace App\Services\Face;

use App\Contracts\FaceRecognitionInterface;
use App\Enums\ErrorCode;
use App\Exceptions\ApiException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class FaceRecognitionClient implements FaceRecognitionInterface
{
    public function register(UploadedFile $image): array
    {
        $response = $this->request('/v1/faces/register', $image);

        if (! ($response['success'] ?? false)) {
            throw new ApiException(
                $response['message'] ?? 'Gambar wajah tidak dapat diproses',
                ErrorCode::FaceBadQuality,
                400,
            );
        }

        return [
            'embedding' => $response['data']['embedding'],
            'quality_score' => $response['data']['quality_score'] ?? 1.0,
        ];
    }

    public function verify(UploadedFile $image, array $storedEmbedding): array
    {
        $response = Http::timeout(10)
            ->withHeaders(['X-Internal-Api-Key' => config('gym.face_api_key')])
            ->attach('face_image', file_get_contents($image->getRealPath()), $image->getClientOriginalName())
            ->post(config('gym.face_api_url').'/v1/faces/verify', [
                'embedding' => json_encode($storedEmbedding),
            ]);

        if (! $response->successful()) {
            throw new ApiException('Layanan face recognition tidak tersedia', ErrorCode::FaceBadQuality, 503);
        }

        $body = $response->json();

        return [
            'matched' => (bool) ($body['data']['matched'] ?? false),
            'confidence' => (float) ($body['data']['confidence'] ?? 0),
        ];
    }

    private function request(string $path, UploadedFile $image): array
    {
        $response = Http::timeout(10)
            ->withHeaders(['X-Internal-Api-Key' => config('gym.face_api_key')])
            ->attach('face_image', file_get_contents($image->getRealPath()), $image->getClientOriginalName())
            ->post(config('gym.face_api_url').$path);

        if (! $response->successful()) {
            throw new ApiException('Layanan face recognition tidak tersedia', ErrorCode::FaceBadQuality, 503);
        }

        return $response->json();
    }
}
