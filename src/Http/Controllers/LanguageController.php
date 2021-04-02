<?php

namespace JoeDixon\Translation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use JoeDixon\Translation\Drivers\Translation;
use JoeDixon\Translation\Http\Requests\LanguageRequest;

class LanguageController extends Controller
{
    private Translation $translation;

    public function __construct(Translation $translation)
    {
        $this->translation = $translation;
    }

    public function index(): View
    {
        $languages = $this->translation->allLanguages();

        return view('translation::languages.index', compact('languages'));
    }

    public function create(): View
    {
        return view('translation::languages.create');
    }

    public function store(LanguageRequest $request): RedirectResponse
    {
        $this->translation->addLanguage($request->locale, $request->name);

        return redirect()
            ->route('languages.index')
            ->with('success', __('translation::translation.language_added'));
    }
}
