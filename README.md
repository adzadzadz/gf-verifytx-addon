# GF VerifyTX Addon

Gravity Forms addon for real-time insurance verification through VerifyTX API.

## Overview

Integrates [VerifyTX](https://www.verifytx.com/) insurance verification service directly into Gravity Forms, enabling automated eligibility checks during form submission.

## Requirements

- WordPress 5.0+
- PHP 7.4+
- Gravity Forms 2.5+
- VerifyTX API account

## Installation

1. Download and extract to `/wp-content/plugins/gf-verifytx-addon/`
2. Activate via WordPress admin
3. Configure API credentials at Forms → Settings → VerifyTX
4. Enable on individual forms via Form Settings

## Quick Start

### 1. Global Configuration
```
Forms → Settings → VerifyTX
- API Client ID: [Your VerifyTX Client ID]
- API Secret Key: [Your VerifyTX Secret]
- Test Mode: Enable for sandbox testing
```

### 2. Form Setup
```
Form Settings → VerifyTX
- Enable VerifyTX: ✓
- Verification Timing: Real-time
- Map required fields
```

### 3. Required Fields
- Patient First/Last Name
- Date of Birth
- Insurance Company/Payer ID
- Member/Subscriber ID

## Features

### Verification Modes
- **Real-time**: During form submission
- **AJAX Pre-submission**: Before submit
- **Background**: After submission

### Data Management
- Verification history tracking
- Configurable data retention
- Smart caching system
- Audit logging

### Security
- Encrypted credential storage
- HIPAA-compliant handling
- Secure API communication
- Role-based access control

## Developer Reference

### Hooks

#### Actions
```php
do_action('gf_verifytx_before_verification', $entry, $form);
do_action('gf_verifytx_after_verification', $entry, $form, $result);
do_action('gf_verifytx_verification_failed', $entry, $form, $error);
do_action('gf_verifytx_verification_success', $entry, $form, $data);
```

#### Filters
```php
apply_filters('gf_verifytx_api_request_data', $data, $entry, $form);
apply_filters('gf_verifytx_api_response_data', $response, $entry, $form);
apply_filters('gf_verifytx_validation_message', $message, $field);
```

### Database Tables

#### `{prefix}_gf_verifytx_verifications`
Stores verification history and results.

#### `{prefix}_gf_verifytx_cache`
Caches successful verifications for performance.

### JavaScript Events

```javascript
// Verification completed
jQuery(document).on('verifytx:verified', function(e, data) {
    console.log('Verified:', data);
});

// Verification failed
jQuery(document).on('verifytx:error', function(e, error) {
    console.log('Error:', error);
});
```

## API Response Format

### Success Response
```json
{
    "status": "active",
    "coverage": {
        "effective_date": "2024-01-01",
        "copay": 25,
        "deductible": {
            "amount": 1000,
            "met": 500
        },
        "out_of_pocket": {
            "max": 5000,
            "met": 1500
        }
    }
}
```

### Error Response
```json
{
    "error": true,
    "message": "Invalid member ID",
    "code": "INVALID_MEMBER"
}
```

## Troubleshooting

| Issue | Solution |
|-------|----------|
| API connection failed | Verify credentials and network access |
| Verification not running | Check form settings and field mapping |
| Slow performance | Enable caching, use background mode |
| Missing data | Ensure all required fields are mapped |

### Debug Mode
Enable logging at Forms → Settings → VerifyTX → Logging → All Activity

### Error Codes
- `API_AUTH_FAILED` - Invalid credentials
- `INVALID_MEMBER` - Member ID not found
- `EXPIRED_COVERAGE` - Insurance inactive
- `NETWORK_ERROR` - Connection issue

## Performance

### Caching
- Default: 24-hour cache
- Configurable per-form
- Automatic invalidation

### Optimization Tips
1. Use background verification for high-volume forms
2. Enable caching for repeat submissions
3. Implement conditional logic to skip unnecessary checks
4. Regular database cleanup via data retention settings

## Compliance

### HIPAA Considerations
- PHI encryption at rest
- Secure transmission (TLS 1.2+)
- Audit trail maintenance
- Access logging

### Data Retention
- Configurable retention period
- Automatic cleanup
- Export capabilities
- Right to deletion support

## Support

- Documentation: `/wp-content/plugins/gf-verifytx-addon/PLANNING.md`
- Issues: Contact your administrator
- VerifyTX API: [https://www.verifytx.com/](https://www.verifytx.com/)

## Version History

### 1.0.0 (Current)
- Initial release
- Core verification functionality
- Admin interface
- Field mapping
- Caching system

## License

GPL v2 or later