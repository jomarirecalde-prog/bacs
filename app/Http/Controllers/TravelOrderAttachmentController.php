<?php

namespace App\Http\Controllers;

use App\Models\TravelOrder;
use App\Models\TravelOrderAttachment;
use App\Services\SensitiveAccessLogger;
use App\Support\PrivateStorage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TravelOrderAttachmentController extends Controller
{
    public function __construct(private readonly SensitiveAccessLogger $sensitiveAccess) {}

    public function show(Request $request, TravelOrder $travelOrder, TravelOrderAttachment $attachment): StreamedResponse
    {
        $this->authorize('view', $travelOrder);
        abort_unless((int) $attachment->travel_order_id === (int) $travelOrder->id, 404);
        abort_unless(PrivateStorage::exists($attachment->file_path), 404);

        $this->sensitiveAccess->fileDownload(
            $request->user(),
            'TravelOrders',
            $travelOrder->id,
            "Travel order attachment downloaded ({$travelOrder->travel_order_number}: {$attachment->file_name}).",
            $request,
            ['attachment_id' => $attachment->id, 'file_name' => $attachment->file_name],
        );

        return PrivateStorage::downloadResponse(
            $attachment->file_path,
            $attachment->file_name ?: 'attachment'
        );
    }
}
