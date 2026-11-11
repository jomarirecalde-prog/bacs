<?php

namespace App\Support;

/**
 * Single source of truth for the RA 10173 employee data privacy notice (display text).
 * Update PRIVACY_NOTICE_VERSION when the notice changes materially so users must re-consent.
 */
final class PrivacyNotice
{
    public const PRIVACY_NOTICE_VERSION = '2026-11-01';

    public static function version(): string
    {
        return self::PRIVACY_NOTICE_VERSION;
    }

    public static function title(): string
    {
        return 'Data Privacy Notice — Republic Act No. 10173 (Data Privacy Act of 2012)';
    }

    public static function checkboxLabel(): string
    {
        return 'I have read and understood this Data Privacy Notice and I consent to the processing of my personal data.';
    }

    public static function dpoEmail(): string
    {
        return (string) config('privacy_notice.dpo_email');
    }

    public static function dpoPhone(): string
    {
        return (string) config('privacy_notice.dpo_phone');
    }

    public static function policyUrl(): ?string
    {
        $url = config('privacy_notice.policy_url');

        return is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * @return list<array{heading: string, paragraphs: list<string>, bullets?: list<string>}>
     */
    public static function sections(): array
    {
        return [
            [
                'heading' => 'Personal data we collect',
                'paragraphs' => [
                    'Through the BACS Daily Time Record and human-resources systems, we collect and process employee personal data including your name, employee number, work email and contact details, department and position, attendance and time records, leave and travel orders, payroll-related information, profile photograph (when provided), government identification numbers where required for statutory compliance, and technical data such as IP address and device/browser information when you sign in.',
                ],
            ],
            [
                'heading' => 'Purpose of processing',
                'paragraphs' => [
                    'We process your personal data to administer employment and HR records, compute and disburse payroll, monitor attendance and official daily time records, manage system access and security, fulfill legal and regulatory obligations, and support workforce planning and internal reporting.',
                ],
            ],
            [
                'heading' => 'Sharing with third parties',
                'paragraphs' => [
                    'We do not sell your personal data. We may share information only with service providers who host or support this system under confidentiality obligations, with government agencies when required by law (e.g., statutory remittances and reporting), or with professional advisers bound by duty of confidentiality. Any international transfer, if applicable, shall follow NPC requirements.',
                ],
            ],
            [
                'heading' => 'Retention and disposal',
                'paragraphs' => [
                    'Employment, attendance, and payroll records are retained for the period required by applicable labor, tax, and auditing laws, and otherwise only as long as necessary for the purposes stated above. When retention periods expire, personal data is securely deleted, anonymized, or disposed of in accordance with our records-management policy.',
                ],
            ],
            [
                'heading' => 'Your rights under RA 10173',
                'paragraphs' => [
                    'As a data subject, you have the right to be informed, the right to access, the right to rectification, the right to erasure or blocking, the right to object, the right to data portability, and the right to file a complaint with the National Privacy Commission (NPC).',
                ],
                'bullets' => [
                    'Right to be informed about how your data is collected and used',
                    'Right to access your personal data held by the organization',
                    'Right to rectification of inaccurate or incomplete data',
                    'Right to erasure or blocking, subject to legal exceptions',
                    'Right to object to processing in qualifying circumstances',
                    'Right to data portability, where technically feasible',
                    'Right to lodge a complaint with the NPC (www.privacy.gov.ph)',
                ],
            ],
            [
                'heading' => 'Withdrawal of consent',
                'paragraphs' => [
                    'You may withdraw consent to processing that is based on consent by contacting our Data Protection Officer. Withdrawal may limit your ability to use systems that require processing of employment data; where processing is required by law or for legitimate employment purposes, the organization may continue limited processing as permitted under RA 10173.',
                ],
            ],
        ];
    }

    public static function bodyPlainText(): string
    {
        $parts = [self::title()];

        foreach (self::sections() as $section) {
            $parts[] = $section['heading'];
            foreach ($section['paragraphs'] as $paragraph) {
                $parts[] = $paragraph;
            }
            if (! empty($section['bullets'])) {
                foreach ($section['bullets'] as $bullet) {
                    $parts[] = '- '.$bullet;
                }
            }
        }

        return implode("\n", $parts);
    }

    public static function textHash(): string
    {
        return hash('sha256', self::bodyPlainText());
    }
}
