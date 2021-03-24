<?php

namespace JoeDixon\Translation\Drivers;

use Illuminate\Support\Collection;

interface DriverInterface
{
    /**
     * Get all languages from the application.
     */
    public function allLanguages(): Collection;

    /**
     * Get all group translations from the application.
     */
    public function allGroup(string $language): Collection;

    /**
     * Get all the translations from the application.
     */
    public function allTranslations(): Collection;

    /**
     * Get all translations for a particular language.
     */
    public function allTranslationsFor(string $language): Collection;

    /**
     * Add a new language to the application.
     */
    public function addLanguage(string $language, ?string $name = null): void;

    /**
     * Add a new group type translation.
     */
    public function addGroupTranslation(string $language, string $group, string $key, string $value = ''): void;

    /**
     * Add a new single type translation.
     */
    public function addSingleTranslation(string $language, string $vendor, string $key, string $value = ''): void;

    /**
     * Get all of the single translations for a given language.
     */
    public function getSingleTranslationsFor(string $language): Collection;

    /**
     * Get all of the group translations for a given language.
     */
    public function getGroupTranslationsFor(string $language): Collection;

    /**
     * Determine whether or not a language exists.
     */
    public function languageExists(string $language): bool;

    /**
     * Find all of the translations in the app without translation for a given language.
     */
    public function findMissingTranslations(string $language): array;

    /**
     * Save all of the translations in the app without translation for a given language.
     */
    public function saveMissingTranslations(?string $language = null): void;

    /**
     * Get a collection of group names for a given language.
     */
    public function getGroupsFor(string $language): Collection;

    /**
     * Get all translations for a given language merged with the source language.
     */
    public function getSourceLanguageTranslationsWith(string $language): Collection;

    /**
     * Filter all keys and translations for a given language and string.
     */
    public function filterTranslationsFor(string $language, ?string $filter = null): Collection;
}
