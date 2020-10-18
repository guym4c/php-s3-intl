<?php

namespace Guym4c\PhpS3Intl;

use Aws\S3\S3Client;

abstract class AbstractS3JsonClient {

    protected S3Client $s3;
    protected string $bucket;
    protected string $baseKey;

    /**
     * @param S3Client $s3
     * @param string $bucket
     * @param string $baseKey
     */
    public function __construct(S3Client $s3, string $bucket, string $baseKey = '') {
        $this->s3 = $s3;
        $this->bucket = $bucket;
        $this->baseKey = $baseKey;
    }

    /**
     * Retrieve a file
     *
     * @param string $key The S3 key
     * @param bool $forceFetch Whether to fetch the file from S3 (if caching is implemented)
     * @return array Decoded JSON data
     */
    abstract public function get(string $key, bool $forceFetch = false): array;

    /**
     * Save an array as JSON
     *
     * @param string $key The S3 key
     * @param array $data The data to encode
     */
    abstract public function save(string $key, array $data = []): void;

    /**
     * Purge all cached data, if used
     *
     * @return bool Success
     */
    public function flush(): bool {
        return true;
    }
}