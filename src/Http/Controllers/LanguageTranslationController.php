<?php

namespace JoeDixon\Translation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use JoeDixon\Translation\Drivers\Translation;
use JoeDixon\Translation\Http\Requests\TranslationRequest;

class LanguageTranslationController extends Controller
{
    protected Translation $translation;

    public function __construct(Translation $translation)
    {
        $this->translation = $translation;
    }

    /**
     * @return View|RedirectResponse
     */
    public function index(Request $request, string $language)
    {
        if ($request->has('language') && $request->get('language') !== $language) {
            return redirect()
                ->route('languages.translations.index', ['language' => $request->get('language'), 'group' => $request->get('group'), 'filter' => $request->get('filter')]);
        }

        $languages = $this->translation->allLanguages();
        $groups = $this->translation->getGroupsFor(config('app.locale'))->merge('single');

        $sourcelanguage = $request->get('sourceLanguage', config('app.locale'));

        $translations = $this->translation->filterTranslationsFor($language, $sourcelanguage, $request->get('filter'));

        if ($request->has('group') && $request->get('group')) {
            if ($request->get('group') === 'single') {
                $translations = $translations->get('single');
                $translations = new Collection(['single' => $translations]);
            } else {
                $translations = $translations->get('group')->filter(function ($values, $group) use ($request) {
                    return $group === $request->get('group');
                });

                $translations = new Collection(['group' => $translations]);
            }
        }

        return view('translation::languages.translations.index', compact('language', 'languages', 'groups', 'translations'));
    }

    public function create(string $language): View
    {
        return view('translation::languages.translations.create', compact('language'));
    }

    public function store(TranslationRequest $request, string $language): RedirectResponse
    {
        if ($request->filled('group')) {
            $namespace = $request->has('namespace') && $request->get('namespace') ? "{$request->get('namespace')}::" : '';
            $this->translation->addGroupTranslation($language, "{$namespace}{$request->get('group')}", $request->get('key'), $request->get('value') ?: '');
        } else {
            $this->translation->addSingleTranslation($language, 'single', $request->get('key'), $request->get('value') ?: '');
        }

        return redirect()
            ->route('languages.translations.index', $language)
            ->with('success', __('translation::translation.translation_added'));
    }

    public function update(Request $request, string $language): array
    {
        if (! Str::contains($request->get('group'), 'single')) {
            $this->translation->addGroupTranslation($language, $request->get('group'), $request->get('key'), $request->get('value') ?: '');
        } else {
            $this->translation->addSingleTranslation($language, $request->get('group'), $request->get('key'), $request->get('value') ?: '');
        }

        return ['success' => true];
    }
}
