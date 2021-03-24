<?php

namespace JoeDixon\Translation\Drivers;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use JoeDixon\Translation\Exceptions\LanguageExistsException;
use JoeDixon\Translation\Language;
use JoeDixon\Translation\Scanner;
use JoeDixon\Translation\Translation as TranslationModel;

class Database extends Translation implements DriverInterface
{
    protected string $sourceLanguage;

    protected Scanner $scanner;

    public function __construct(string $sourceLanguage, Scanner$scanner)
    {
        $this->sourceLanguage = $sourceLanguage;
        $this->scanner = $scanner;
    }

    public function allLanguages(): Collection
    {
        return Language::all()->mapWithKeys(function ($language) {
            return [$language->language => $language->name ?: $language->language];
        });
    }

    public function allGroup(string $language): Collection
    {
        $groups = TranslationModel::getGroupsForLanguage($language);

        return $groups->map(function ($translation) {
            return $translation->group;
        });
    }

    public function allTranslations(): Collection
    {
        return $this->allLanguages()->mapWithKeys(function ($name, $language) {
            return [$language => $this->allTranslationsFor($language)];
        });
    }

    public function allTranslationsFor(string $language): Collection
    {
        return Collection::make([
            'group' => $this->getGroupTranslationsFor($language),
            'single' => $this->getSingleTranslationsFor($language),
        ]);
    }

    public function addLanguage(string $language, ?string $name = null): void
    {
        if ($this->languageExists($language)) {
            throw new LanguageExistsException(__('translation::errors.language_exists', ['language' => $language]));
        }

        Language::create([
            'language' => $language,
            'name' => $name,
        ]);
    }

    public function addGroupTranslation(string $language, string $group, string $key, string $value = ''): void
    {
        if (! $this->languageExists($language)) {
            $this->addLanguage($language);
        }

        Language::where('language', $language)
            ->first()
            ->translations()
            ->updateOrCreate([
                'group' => $group,
                'key' => $key,
            ], [
                'group' => $group,
                'key' => $key,
                'value' => $value,
            ]);
    }

    public function addSingleTranslation(string $language, string $vendor, string $key, string $value = ''): void
    {
        if (! $this->languageExists($language)) {
            $this->addLanguage($language);
        }

        Language::where('language', $language)
            ->first()
            ->translations()
            ->updateOrCreate([
                'group' => $vendor,
                'key' => $key,
            ], [
                'key' => $key,
                'value' => $value,
            ]);
    }

    public function getSingleTranslationsFor(string $language): Collection
    {
        $translations = $this->getLanguage($language)
            ->translations()
            ->where('group', 'like', '%single')
            ->orWhereNull('group')
            ->get()
            ->groupBy('group');

        // if there is no group, this is a legacy translation so we need to
        // update to 'single'. We do this here so it only happens once.
        if ($this->hasLegacyGroups($translations->keys())) {
            TranslationModel::whereNull('group')->update(['group' => 'single']);
            // if any legacy groups exist, rerun the method so we get the
            // updated keys.
            return $this->getSingleTranslationsFor($language);
        }

        $translationArray = [];

        $translations->map(function ($translations) use (&$translationArray) {
            $translations->map(function ($translation) use (&$translationArray) {
                Arr::set($translationArray, "{$translation->group}.{$translation->key}", $translation->value);
            });
        });

        return collect($translationArray);
    }

    public function getGroupTranslationsFor(string $language): Collection
    {
        $translations = $this->getLanguage($language)
            ->translations()
            ->whereNotNull('group')
            ->where('group', 'not like', '%single')
            ->get()
            ->groupBy('group');

        $translationArray = [];

        $translations->map(function ($translations) use (&$translationArray) {
            $translations->map(function ($translation) use (&$translationArray) {
                Arr::set($translationArray, "{$translation->group}.{$translation->key}", $translation->value);
            });
        });

        return collect($translationArray);
    }

    public function languageExists(string $language): bool
    {
        return $this->getLanguage($language) ? true : false;
    }

    public function getGroupsFor(string $language): Collection
    {
        return $this->allGroup($language);
    }

    /**
     * Get a language from the database.
     *
     * @param string $language
     * @return Language
     */
    private function getLanguage($language)
    {
        return Language::where('language', $language)->first();
    }

    /**
     * Determine if a set of single translations contains any legacy groups.
     * Previously, this was handled by setting the group value to NULL, now
     * we use 'single' to cater for vendor JSON language files.
     *
     * @param Collection $groups
     * @return bool
     */
    private function hasLegacyGroups($groups)
    {
        return $groups->filter(function ($key) {
            return $key === '';
        })->count() > 0;
    }
}
