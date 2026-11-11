<?php

use App\Support\PrivacyNotice;

return [

  'version' => PrivacyNotice::PRIVACY_NOTICE_VERSION,

  'dpo_email' => env('PRIVACY_DPO_EMAIL', 'dpo@bacsconstruction.ph'),

  'dpo_phone' => env('PRIVACY_DPO_PHONE', '+63 917 000 0000'),

  'policy_url' => env('PRIVACY_POLICY_URL'),

];
