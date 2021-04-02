<?php

namespace JoeDixon\Translation\Drivers;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use JoeDixon\Translation\Exceptions\LanguageExistsException;
use JoeDixon\Translation\Language;
use JoeDixon\Translation\Scanner;
use JoeDixon\Translation\Translation as TranslationModel;

class Database extends Translation implements DriverInterface, DatabaseDriverInterface
{
    protected string $sourceLanguage;
    protected Scanner $scanner;

    private CacheRepository $cacheRepository;

    public function __construct(string $sourceLanguage, Scanner $scanner, CacheFactory $cacheRepository)
    {
        $this->sourceLanguage = $sourceLanguage;
        $this->scanner = $scanner;
        $this->cacheRepository = $cacheRepository->store('array');
    }

    public function allLanguages(): Collection
    {
        return Language::all()->mapWithKeys(function ($language) {
            return [$language->language => $language->name ?: $language->language];
        });
    }

    public function allGroup(string $language): Collection
    {
        return TranslationModel::getGroupsForLanguage($language)
            ->map(function (TranslationModel $translation) {
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
        if (!$this->languageExists($language)) {
            $this->addLanguage($language);
        }

        $this->getLanguage($language)
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
        if (!$this->languageExists($language)) {
            $this->addLanguage($language);
        }

        $this->getLanguage($language)
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
        if ($this->cacheRepository->has("language.{$language}.single")) {
            return $this->cacheRepository->get("language.{$language}.single");
        }

        $translationArray = [];

        $this->getBaseSelectTranslateQuery()
            ->where('language', $language)
            ->where('group', 'like', '%single')
            ->cursor()
            ->each(function ($translation) use (&$translationArray) {
                Arr::set($translationArray, "{$translation->group}.{$translation->key}", $translation->value);
            });

        $this->cacheRepository->set("language.{$language}.single", collect($translationArray));

        return $this->cacheRepository->get("language.{$language}.single", collect());
    }

    public function getGroupTranslationsFor(string $language): Collection
    {
        if ($this->cacheRepository->has("language.{$language}.group")) {
            return $this->cacheRepository->get("language.{$language}.group");
        }

        $translationArray = [];

        $this->getBaseSelectTranslateQuery()
            ->where('language', $language)
            ->whereNotNull('group')
            ->where('group', 'not like', '%single')
            ->cursor()
            ->each(function ($translation) use (&$translationArray) {
                Arr::set($translationArray, "{$translation->group}.{$translation->key}", $translation->value);
            });

        $this->cacheRepository->set("language.{$language}.group", collect($translationArray));

        return $this->cacheRepository->get("language.{$language}.group", collect());
    }

    public function languageExists(string $language): bool
    {
        return (bool) $this->getLanguage($language);
    }

    public function getGroupsFor(string $language): Collection
    {
        return $this->allGroup($language);
    }

    private function getBaseSelectTranslateQuery(): Builder
    {
        $languageModel = new Language();
        $translationModel = new TranslationModel();

        return DB::query()
            ->select(['group', 'key', 'value'])
            ->from($translationModel->getTable())
            ->leftJoin($languageModel->getTable(), $languageModel->getTable().'.id', '=', 'language_id')
            ->whereNotNull('group');
    }

    private function getLanguage(string $language): ?Language
    {
        return Language::where('language', $language)->first();
    }
}
