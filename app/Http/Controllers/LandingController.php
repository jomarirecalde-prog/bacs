<?php

namespace App\Http\Controllers;

use App\Enums\CalendarEventType;
use App\Enums\EventAudience;
use App\Models\CalendarEvent;
use App\Models\Setting;
use App\Services\CalendarService;
use App\Support\PrivacyNotice;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function __construct(private readonly CalendarService $calendar) {}

    public function index(): View|\Illuminate\Http\RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('home');
        }

        return view('landing.index', $this->viewData());
    }

    /**
     * @return array<string, mixed>
     */
    public function viewData(): array
    {
        $announcementQuery = CalendarEvent::query()
            ->published()
            ->where('audience_type', EventAudience::All)
            ->whereIn('event_type', [
                CalendarEventType::Announcement,
                CalendarEventType::CompanyEvent,
            ]);

        $dpoEmail = PrivacyNotice::dpoEmail();

        return [
            'companyName' => Setting::get('company_name', config('app.name', 'BACS DTR')),
            'companyAddress' => Setting::get('company_address', ''),
            'contactEmail' => Setting::get('mail_from_address', $dpoEmail),
            'announcements' => $this->calendar->upcoming($announcementQuery, days: 90, limit: 6),
            'dpoEmail' => $dpoEmail,
            'dpoPhone' => PrivacyNotice::dpoPhone(),
            'privacyPolicyUrl' => PrivacyNotice::policyUrl(),
            'privacyTitle' => PrivacyNotice::title(),
        ];
    }
}
