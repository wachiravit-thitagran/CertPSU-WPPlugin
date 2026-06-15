# CertPSU Connector

Connector plugin for async certificate issuance through cert.psu.ac.th.

Targets the cert.psu.ac.th **API v2**. Authentication uses `X-API-Key`; the
organization is resolved from the API key server-side. The public PHP API below
is unchanged — `organization_id` is accepted for backward compatibility but is no
longer sent to the server.

## Requirements
- PHP 8.2+
- WordPress 6.5+

## Basic usage
```php
$result = certpsu()->create_issuance([
    'external_source' => 'training-plugin',
    'external_id' => 'course-123',
    'certpsu' => [
        'organization_id' => 'org-1', // optional under v2 (org comes from the API key)
        'certificate_email_template' => 'email-1',
        'endorser_required_endorsement_email_template' => 'email-2',
        'endorser_without_endorsement_email_template' => 'email-3',
    ],
    'class' => [
        'name' => 'Training Class',
        'printed_name' => 'Training Class',
        'started_date' => '2026-06-01',
        'ended_date' => '2026-06-01',
        'issued_date' => '2026-06-01',
    ],
    'certificate_template' => [
        'name' => 'Participant Certificate',
        'group' => 'participant',
        'template' => 'template-1',
    ],
    'participants' => [
        ['name' => 'Alice', 'email' => 'alice@example.com'],
    ],
]);
```
