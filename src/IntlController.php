<?php

namespace Guym4c\PhpS3Intl;

class IntlController {

    private AbstractS3JsonClient $store;
    /** @var string[] */
    private array $languages;
    private ?string $language = null;
    private string $fallback;
    private bool $showKeysWhereMissing;

    public function __construct(
        AbstractS3JsonClient $store,
        array $languages,
        ?string $fallback = null,
        bool $showKeysWhereMissing = true
    ) {
        $this->store = $store;
        $this->languages = $languages;
        $this->fallback = $fallback ?? $languages[0];
        $this->showKeysWhereMissing = $showKeysWhereMissing;
    }

    /**
     * @param string $langpack
     * @param string $key
     * @param string[] $values
     * @param bool $addMissing
     * @return string
     */
    public function getText(string $langpack, string $key, array $values = [], bool $addMissing = false): string {
        return $this->getTextByLanguage($langpack, $key, $this->language, $values, $addMissing);
    }

    /**
     * @param string $langpackName
     * @param string $key
     * @param string $language
     * @param string[] $values
     * @param bool $addMissing
     * @return string
     */
    private function getTextByLanguage(string $langpackName, string $key, string $language, array $values, bool $addMissing) {
        $language = $language ?? $this->fallback;

        $langpack = $this->getLangpack($langpackName, $language);

        // check langpack exists
        if ($langpack === null) {
            $this->saveLangpack($langpackName, $language);

            if (!$this->isFallback($language)) {
                return $this->getTextByLanguage($langpackName, $key, $this->fallback, $values, $addMissing);
            }

            return $this->default($langpackName, $key);
        }

        // check key exists
        if (!isset($langpack[$key])) {
            if ($addMissing) {
                $langpack[$key] = '';
                $this->saveLangpack($langpackName, $language, $langpack);
            }

            if (!$this->isFallback($language)) {
                return $this->getTextByLanguage($langpackName, $key, $this->fallback, $values, $addMissing);
            }

            return $this->default($langpackName, $key);
        }

        $value = $langpack[$key];

        // check key is populated
        if (
            $value === ''
            && !$this->isFallback($language)
        ) {
            return $this->getTextByLanguage($langpackName, $key, $this->fallback, $values, $addMissing);
        }

        return preg_replace_callback(
            '/{ *([a-zA-Z0-9\-_.]+) *}/',
            fn(array $matches): string => $values[$matches[1]] ?? $matches[1],
            $value
        );
    }

    public function getLangpack(string $langpackName, string $language, bool $forceFetch = false): array {
        return $this->store->get(self::getLangpackFileKey($langpackName, $language), $forceFetch);
    }

    public function saveLangpack(string $langpackName, string $language, array $data = []): void {
        $this->store->save(self::getLangpackFileKey($langpackName, $language), $data);
    }

    public static function getLangpackFileKey(string $langpackName, string $language): string {
        return "{$langpackName}/{$language}.json";
    }

    private function default(string $langpackName, string $key) {
        if ($this->showKeysWhereMissing) {
            return "{$langpackName}.{$key}";
        }
        return '';
    }

    public function getStore(): AbstractS3JsonClient {
        return $this->store;
    }

    /**
     * @return string[]
     */
    public function getLanguages(): array {
        return $this->languages;
    }

    public function getLanguage(): string {
        return $this->language;
    }

    public function setLanguage(string $language): void {
        $this->language = $language;
    }

    public function getFallback(): string {
        return $this->fallback;
    }

    public function isFallback(?string $language = null): bool {
        $languageToCompare = $language ?? $this->language;
        return $languageToCompare === $this->fallback;
    }
}