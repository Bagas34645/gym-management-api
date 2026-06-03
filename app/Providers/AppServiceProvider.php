<?php

namespace App\Providers;

use App\Contracts\FaceRecognitionInterface;
use App\Services\Face\FaceRecognitionClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FaceRecognitionInterface::class, FaceRecognitionClient::class);
    }

    public function boot(): void
    {
        //
    }
}
