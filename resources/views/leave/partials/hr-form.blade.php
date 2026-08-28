<form method="POST" action="{{ route('admin.leave.hr', $application) }}" class="card card-accent-warn" x-data="{ decision: 'approved' }">
    @csrf
    <div class="card-header"><h3 class="card-title">HR processing</h3></div>
    <div class="card-body space-y-4">
        <p class="text-xs text-muted">Restricted to authorized HR/Admin personnel. Leave balance is deducted only when the application is approved with pay.</p>
        <label class="flex items-center gap-2">
            <input type="radio" name="decision" value="approved" class="radio" x-model="decision">
            <span class="font-semibold">APPROVED</span>
        </label>
        <label class="flex items-center gap-2">
            <input type="radio" name="decision" value="denied" class="radio" x-model="decision">
            <span class="font-semibold text-critical-700">DENIED</span>
        </label>
        <div>
            <div class="label">Leave payment classification</div>
            <label class="mt-1 flex items-center gap-2 text-sm">
                <input type="radio" name="payment_type" value="with_pay" class="radio" checked> LEAVE WITH PAY
            </label>
            <label class="mt-1 flex items-center gap-2 text-sm">
                <input type="radio" name="payment_type" value="without_pay" class="radio"> LEAVE W/O PAY
            </label>
        </div>
        <div>
            <label class="label" for="hr_sil_as_of">SIL balance as of</label>
            <input id="hr_sil_as_of" type="date" name="hr_sil_as_of" class="input" value="{{ now('Asia/Manila')->toDateString() }}">
        </div>
        <div>
            <label class="label" for="hr_remarks">HR remarks</label>
            <textarea id="hr_remarks" name="hr_remarks" rows="2" class="textarea"></textarea>
        </div>
        <div>
            <label class="label" for="hr-reason">Reason @if (true)<span class="text-xs text-muted">(required if denied)</span>@endif</label>
            <textarea id="hr-reason" name="reason" rows="2" class="textarea" :required="decision === 'denied'"></textarea>
            @error('reason') <p class="error-text">{{ $message }}</p> @enderror
        </div>
        <div x-data="signaturePad()">
            <div class="label">HR officer signature</div>
            <canvas x-ref="canvas" class="h-28 w-full cursor-crosshair rounded-xl border border-line bg-white" width="480" height="120"></canvas>
            <input type="hidden" name="signature" x-ref="input">
        </div>
        <button class="btn-warning btn-block" type="submit">Complete HR processing</button>
    </div>
</form>
