<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Aws\S3\S3Client;
use Illuminate\Filesystem\AwsS3V3Adapter as LaravelAwsS3V3Adapter;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter as S3Adapter;
use League\Flysystem\AwsS3V3\PortableVisibilityConverter;
use League\Flysystem\Filesystem;
use League\Flysystem\Visibility;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Fix for MySQL key length issue
        Schema::defaultStringLength(191);

        // Customize S3 client configuration
        Storage::extend('s3', function ($app, $config) {
            $s3Config = [
                'credentials' => [
                    'key' => $config['key'] ?? env('AWS_ACCESS_KEY_ID'),
                    'secret' => $config['secret'] ?? env('AWS_SECRET_ACCESS_KEY'),
                ],
                'version' => 'latest',
                'region' => $config['region'] ?? env('AWS_DEFAULT_REGION', 'us-east-1'),
                'http' => ['verify' => false],
            ];

            // Add endpoint if configured
            if (!empty($config['endpoint'])) {
                $s3Config['endpoint'] = $config['endpoint'];
            }

            // Add use_path_style_endpoint if configured
            if (isset($config['use_path_style_endpoint'])) {
                $s3Config['use_path_style_endpoint'] = $config['use_path_style_endpoint'];
            }

            $root = (string) ($config['root'] ?? '');
            $visibility = new PortableVisibilityConverter(
                $config['visibility'] ?? Visibility::PUBLIC
            );
            $streamReads = $config['stream_reads'] ?? false;

            $client = new S3Client($s3Config);

            $adapter = new S3Adapter(
                $client,
                $config['bucket'] ?? env('AWS_BUCKET'),
                $root,
                $visibility,
                null,
                $config['options'] ?? [],
                $streamReads
            );

            $filesystem = new Filesystem($adapter, $config);

            return new LaravelAwsS3V3Adapter($filesystem, $adapter, $config, $client);
        });
    }
}

