<?php

namespace Tests;

use App\Models\User;
use App\Services\DataPrivacyConsentService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\URL;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        URL::forceRootUrl('http://localhost');
        config(['app.url' => 'http://localhost']);
    }

    public function actingAs(Authenticatable $user, $guard = null)
    {
        $result = parent::actingAs($user, $guard);

        if ($user instanceof User && ($guard === null || $guard === 'web')) {
            app(DataPrivacyConsentService::class)->grantForTesting($user);
        }

        return $result;
    }
}
