# Production Deployment Checklist

## Pre-Deployment

### Code Quality
- [x] All tests passing
- [x] No linting errors
- [x] Code review completed
- [x] Security audit performed
- [x] Performance benchmarks met

### Dependencies
- [x] All dependencies up to date
- [x] Security vulnerabilities checked (`composer audit`, `npm audit`)
- [x] Production dependencies only (no dev dependencies)
- [x] Autoloader optimized

### Build Process
- [x] Production assets built (`npm run build:prod`)
- [x] Assets minified (JS + CSS)
- [x] Source maps removed
- [x] Console logs removed (via esbuild `drop`)

### Configuration
- [x] Environment set to `production`
- [x] Debug mode disabled
- [x] Error logging configured
- [x] Cache settings optimized
- [x] Rate limiting configured
- [x] Security headers enabled

## Deployment

### Files & Directories
- [ ] Unnecessary files excluded (tests, docs, tools)
- [ ] `.htaccess` configured for security
- [ ] File permissions set correctly (755 for directories, 644 for files)
- [ ] Sensitive files protected

### WordPress Configuration
- [ ] WordPress requirements met (PHP 8.1+, WP 6.4+)
- [ ] Database tables created (run activation)
- [ ] Capabilities registered
- [ ] Options initialized

### Server Configuration
- [ ] PHP version: 8.1 or higher
- [ ] Required PHP extensions installed:
  - [x] mbstring
  - [x] xml
  - [x] curl
  - [x] intl
  - [x] zip
- [ ] Memory limit: at least 256MB
- [ ] Max execution time: at least 300 seconds
- [ ] Upload size limit: at least 10MB

### Security
- [ ] SSL/TLS enabled
- [ ] File permissions secure
- [ ] Database credentials secure
- [ ] API keys in environment variables
- [ ] Rate limiting active
- [ ] CSRF protection enabled
- [ ] Security headers configured

### Performance
- [ ] Object caching enabled (Redis/Memcached recommended)
- [ ] Database indexes created
- [ ] Query caching active
- [ ] Asset compression enabled
- [ ] Browser caching configured
- [ ] CDN configured (if applicable)

### Monitoring
- [ ] Error logging enabled
- [ ] Health check endpoint accessible
- [ ] Metrics collection enabled
- [ ] Alerting configured
- [ ] Log rotation configured

## Post-Deployment

### Testing
- [ ] Smoke tests passed
- [ ] Critical user journeys tested
- [ ] API endpoints responding
- [ ] Queue processing working
- [ ] Scheduled jobs running
- [ ] Dead letter queue monitored

### Verification
- [ ] Version number correct
- [ ] Plugin activated successfully
- [ ] No PHP errors in logs
- [ ] No JavaScript errors in console
- [ ] Database migrations completed
- [ ] All features functional

### Monitoring (First 24 Hours)
- [ ] Monitor error logs
- [ ] Check performance metrics
- [ ] Verify queue processing
- [ ] Monitor API rate limits
- [ ] Check circuit breaker status
- [ ] Review user feedback

### Documentation
- [ ] Deployment documented
- [ ] Changes logged in CHANGELOG.md
- [ ] Team notified
- [ ] Rollback plan prepared

## Rollback Plan

### If Issues Occur
1. **Immediate Actions**
   - Disable plugin via WordPress admin
   - Check error logs for issues
   - Verify database integrity

2. **Rollback Steps**
   - Restore previous plugin version from backup
   - Revert database changes if necessary
   - Clear all caches
   - Restart PHP-FPM/web server if needed

3. **Communication**
   - Notify team of rollback
   - Document issues encountered
   - Schedule post-mortem

## Environment-Specific Settings

### wp-config.php (Production)
```php
// Environment
define('WP_ENVIRONMENT_TYPE', 'production');
define('FP_PUBLISHER_ENV', 'production');

// Debug (DISABLED in production)
define('WP_DEBUG', false);
define('WP_DEBUG_DISPLAY', false);
define('WP_DEBUG_LOG', false);
define('SCRIPT_DEBUG', false);
define('FP_PUBLISHER_DEBUG', false);

// Performance
define('WP_CACHE', true);
define('CONCATENATE_SCRIPTS', true);
define('COMPRESS_SCRIPTS', true);
define('COMPRESS_CSS', true);

// Database
define('WP_POST_REVISIONS', 3);
define('AUTOSAVE_INTERVAL', 300);
define('EMPTY_TRASH_DAYS', 7);

// Security
define('DISALLOW_FILE_EDIT', true);
define('FORCE_SSL_ADMIN', true);
```

### Plugin Configuration
```php
// Include production config
require_once WP_PLUGIN_DIR . '/fp-digital-publisher/config-production.php';
```

## Support & Maintenance

### Regular Maintenance
- Weekly: Check error logs
- Weekly: Review performance metrics
- Monthly: Security audit
- Monthly: Dependency updates
- Quarterly: Full system review

### Contacts
- **Developer**: Francesco Passeri
- **Email**: info@francescopasseri.com
- **Website**: https://francescopasseri.com

## Useful Commands

### Build for Production
```bash
cd fp-digital-publisher
./deploy.sh --version=0.2.0
```

### Build Docker Image
```bash
./deploy.sh --version=0.2.0 --docker
```

### Create ZIP Package
```bash
cd fp-digital-publisher
bash build.sh --set-version=0.2.0
```

### Security Audit
```bash
composer audit
npm audit --omit=dev --audit-level=high
```

### Run Tests
```bash
composer test
composer test:integration
```

---

**Last Updated**: $(date)
**Plugin Version**: 0.2.0