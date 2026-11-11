<?php

namespace App\Http\Controllers;

use App\Enums\DataPrivacyConsentAction;
use App\Services\DataPrivacyConsentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class DataPrivacyConsentController extends Controller
{
    public function __construct(private readonly DataPrivacyConsentService $consentService) {}

    public function show(Request $request)
    {
        if ($this->consentService->hasCurrentConsent($request->user())) {
            return redirect()->intended(route('home'));
        }

        return view('auth.data-privacy-consent');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['granted', 'declined'])],
            'acknowledged' => [Rule::requiredIf($request->input('action') === 'granted'), 'accepted'],
        ]);

        $user = $request->user();
        $action = DataPrivacyConsentAction::from($validated['action']);

        $this->consentService->record($user, $action, $request);

        if ($action === DataPrivacyConsentAction::Declined) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'redirect' => route('login'),
                    'message' => 'Consent is required to use the BACS system. You have been signed out.',
                ]);
            }

            return redirect()
                ->route('login')
                ->with('error', 'Consent is required to use the BACS system. You have been signed out.');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'redirect' => route('home'),
            ]);
        }

        return redirect()->intended(route('home'));
    }
}
