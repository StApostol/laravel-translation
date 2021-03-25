<?php

namespace JoeDixon\Translation\Drivers;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

abstract class Translation
{
    public function findMissingTranslations(string $language): array
    {
        return array_diff_assoc_recursive(
            $this->scanner->findTranslations(),
            $this->allTranslationsFor($language)
        );
    }

    public function saveMissingTranslations(?string $language = null): void
    {
        $languages = $language ? [$language => $language] : $this->allLanguages();

        foreach ($languages as $language => $name) {
            $missingTranslations = $this->findMissingTranslations($language);

            foreach ($missingTranslations as $type => $groups) {
                foreach ($groups as $group => $translations) {
                    foreach ($translations as $key => $value) {
                        if (Str::contains($group, 'single')) {
                            $this->addSingleTranslation($language, $group, $key);
                        } else {
                            $this->addGroupTranslation($language, $group, $key);
                        }
                    }
                }
            }
        }
    }

    public function getSourceLanguageTranslationsWith(string $language): Collection
    {
        $sourceTranslations = $this->allTranslationsFor($this->sourceLanguage);
        $languageTranslations = $this->allTranslationsFor($language);

        return $sourceTranslations->map(function ($groups, $type) use ($language, $languageTranslations) {
            return $groups->map(function ($translations, $group) use ($type, $language, $languageTranslations) {
                $translations = $translations instanceof Collection ? $translations->toArray() : $translations;

                $translations = self::flatternToOneKey($translations);
                $languageTranslationsByTypeAndGroup = self::flatternToOneKey($languageTranslations->get($type, collect())->get($group, []));

                array_walk($translations, function (&$value, &$key) use ($type, $language, $languageTranslationsByTypeAndGroup) {
                    $value = [
                        $this->sourceLanguage => $value,
                        $language => Arr::get($languageTranslationsByTypeAndGroup, $key),
                    ];
                });

                return $translations;
            });
        });
    }

    public function filterTranslationsFor(string $language, ?string $filter = null): Collection
    {
        $allTranslations = $this->getSourceLanguageTranslationsWith(($language));

        if (! $filter) {
            return $allTranslations;
        }

        return $allTranslations->map(function ($groups, $type) use ($language, $filter) {
            return $groups->map(function ($keys, $group) use ($language, $filter, $type) {
                return collect($keys)->filter(function ($translations, $key) use ($group, $language, $filter, $type) {
                    return strs_contain([$group, $key, $translations[$language], $translations[$this->sourceLanguage]], $filter);
                });
            })->filter(function ($keys) {
                return $keys->isNotEmpty();
            });
        });
    }

    private static function flatternToOneKey(iterable $array, string $prefix = ''): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            if (!is_array($value)) {
                $result[$prefix.$key] = $value;

                continue;
            }

            $result = array_merge($result, self::flatternToOneKey($value, $prefix.$key.'.'));
        }

        return $result;
    }
}
