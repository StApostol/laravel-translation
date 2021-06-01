<?php

namespace JoeDixon\Translation\Drivers;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

abstract class Translation
{
    public function findMissingTranslations(string $language): array
    {
        $languageTranslations = $this->allTranslationsFor($language)->map(fn ($item, $key) => $item->toArray())->toArray();

        return array_diff_assoc_recursive($this->scanner->findTranslations(), $languageTranslations);
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

    public function getLanguageTranslationsWith(string $language, string $sourceLanguage): Collection
    {
        $sourceTranslations = $this->allTranslationsFor($sourceLanguage);
        $languageTranslations = $this->allTranslationsFor($language);
        $defaultSourceTranslations = $sourceLanguage === $this->sourceLanguage ? clone $sourceTranslations : $this->allTranslationsFor($this->sourceLanguage);

        return $sourceTranslations->map(function ($groups, $type) use ($language, $languageTranslations, $sourceLanguage, $defaultSourceTranslations) {
            return $groups->map(function ($translations, $group) use ($type, $language, $languageTranslations, $sourceLanguage, $defaultSourceTranslations) {
                $translations = $translations instanceof Collection ? $translations->toArray() : $translations;

                $translations = self::flatternToOneKey($translations);
                $languageTranslationsByTypeAndGroup = self::flatternToOneKey($languageTranslations->get($type, collect())->get($group, []));
                $defaultLanguageTranslationsByTypeAndGroup = self::flatternToOneKey($defaultSourceTranslations->get($type, collect())->get($group, []));

                array_walk($translations, function (&$value, $key) use ($type, $language, $languageTranslationsByTypeAndGroup, $sourceLanguage, $defaultLanguageTranslationsByTypeAndGroup) {
                    $value = [
                        $sourceLanguage => $value,
                        $language => Arr::get($languageTranslationsByTypeAndGroup, $key),
                        $this->sourceLanguage => Arr::get($defaultLanguageTranslationsByTypeAndGroup, $key),
                    ];
                });

                return $translations;
            });
        });
    }

    public function getSourceLanguageTranslationsWith(string $language): Collection
    {
        return $this->getLanguageTranslationsWith($language, $this->sourceLanguage);
    }

    public function filterTranslationsFor(string $language, string $sourcelanguage, ?string $filter = null): Collection
    {
        $allTranslations = $this->getLanguageTranslationsWith($language, $sourcelanguage);

        if (!$filter) {
            return $allTranslations;
        }

        return $allTranslations->map(
            fn ($groups, $type) => $groups
                ->map(
                    fn ($keys, $group) => collect($keys)
                        ->filter(
                            fn ($translations, $key) => strs_contain([$group, $key, $translations[$language], $translations[$this->sourceLanguage]], $filter)
                        )
                )
                ->filter(fn ($keys) => $keys->isNotEmpty())
        );
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
