<?php

namespace Guym4c\PhpS3Intl;

use Aws\S3\S3Client;
use Doctrine\Common\Cache\CacheProvider;
use GuzzleHttp\Client as HttpClient;
use stdClass;
use Teapot\StatusCode;
use voku\helper\UTF8;

class CachingS3JsonClient extends AbstractS3JsonClient {

    private const DEFAULT_CACHE_LIFETIME = 0;
    private const UPLOAD_URL_EXPIRES = '+5 minutes';

    private CacheProvider $cache;
    private int $cacheLifetime;
    private string $baseFetchUrl;

    public function __construct(
        S3Client $s3,
        string $bucket,
        CacheProvider $cache,
        string $baseKey = '',
        string $baseFetchUrl = '',
        int $cacheLifetime = self::DEFAULT_CACHE_LIFETIME
    ) {
        parent::__construct($s3, $bucket, $baseKey);
        $this->cache = $cache;
        $this->cacheLifetime = $cacheLifetime;
        $this->baseFetchUrl = $baseFetchUrl;
    }

    /**
     * @param string $key
     * @param bool $fromOrigin
     * @return false|mixed
     * @throws IntlNetworkError
     */
    public function get(string $key, bool $fromOrigin = false): array {
        $combinedKey = "{$this->baseKey}{$key}";
        if (
            !$fromOrigin
            && $this->cache->contains($key)
        ) {
            return $this->cache->fetch($key);
        }

        if (
            !$fromOrigin
            && $this->baseFetchUrl !== ''
        ) {
            $response = (new HttpClient(['base_uri' => $this->baseFetchUrl]))
                ->get($combinedKey);
            $responseBody = (string) $response->getBody();
            $responseCode = $response->getStatusCode();

            if ($responseCode !== StatusCode::OK) {
                throw IntlNetworkError::fromErrorResponse($responseCode, $responseBody);
            }

            $json = json_decode($responseBody, true);

            $this->cache->save($key, $json, $this->cacheLifetime);

            return $json;
        }

        $data = $this->s3->getObject([
            'Bucket' => $this->bucket,
            'Key' => $combinedKey,
        ])->toArray()['Body'];

        $json = json_decode($data, true);

        $this->cache->save($key, $json, $this->cacheLifetime);

        return $json;
    }

    public function save(string $key, array $data = []): void {
        if ($data = []) {
            $data = new stdClass();
        }

        $this->s3->putObject(array_merge(
            $this->getPutArgs($key),
            ['Body' => json_encode($data)],
        ));
        $this->cache->save($key, $data, $this->cacheLifetime);
    }

    public function flush(): bool {
        return $this->cache->flushAll();
    }

    public function list(): array {
        $objects = $this->s3->listObjects([
            'Bucket' => $this->bucket,
            'Prefix' => $this->baseKey,
        ])->toArray()['Contents'];

        $files = [];
        foreach ($objects as $file) {
            if (UTF8::str_contains($file['Key'], 'json')) {
                $files[] = str_replace($this->baseKey, '', $file['Key']);
            }
        }

        return $files;
    }

    public function getDownloadUrl(string $key, bool $fromOrigin = false): string {
        $combinedKey = "{$this->baseKey}{$key}";

        if (
            !$fromOrigin
            && $this->baseFetchUrl !== ''
        ) {
            return "{$this->baseFetchUrl}/{$combinedKey}";
        }

        return $this->s3->getObjectUrl($this->bucket, $combinedKey);
    }

    public function getUploadUrl(string $key): string {
        $this->cache->delete($key);

        return (string) $this->s3->createPresignedRequest(
            $this->s3->getCommand('PutObject', $this->getPutArgs($key)),
            self::UPLOAD_URL_EXPIRES,
        )->getUri();
    }

    private function getPutArgs(string $key): array {
        return [
            'Bucket' => $this->bucket,
            'Key' => "{$this->baseKey}{$key}",
            'ACL' => 'public-read',
            'ContentType' => 'application/json',
        ];
    }
}