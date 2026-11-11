<?php

namespace App\Http\Middleware;

use App\Services\DataPrivacyConsentService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDataPrivacyConsent
{
    public function __construct(private readonly DataPrivacyConsentService $consentService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $this->consentService->hasCurrentConsent($user) && ! $request->routeIs(
            'privacy.consent.show',
            'privacy.consent.store',
            'logout',
            'server-time',
            'session.heartbeat',
            'session.extend',
        )) {
            if ($request->expectsJson() || $request->ajax() || $request->headers->get('X-BACS-Partial') === '1') {
                return response()->json([
                    'message' => 'Data Privacy Notice consent is required before using this system.',
                    'code' => 'PRIVACY_CONSENT_REQUIRED',
                    'redirect' => route('privacy.consent.show'),
                ], 403);
            }

            return redirect()->route('privacy.consent.show');
        }

        return $next($request);
    }
}
