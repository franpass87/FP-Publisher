# Migration Guide: v0.2.0 → v0.2.1

**Release Date**: 2025-10-13  
**Migration Complexity**: ⭐ Easy (No Breaking Changes)  
**Estimated Time**: < 5 minutes  
**Downtime Required**: None

---

## Overview

Version 0.2.1 is a **100% backward compatible** bug fix and security hardening release. No code changes are required in your existing integrations, themes, or custom extensions.

---

## Pre-Migration Checklist

- [ ] Backup your database
- [ ] Backup the plugin directory
- [ ] Test in staging environment (recommended)
- [ ] Review CHANGELOG.md for all changes
- [ ] Verify PHP 8.1+ is installed
- [ ] Verify WordPress 6.4+ is active

---

## Migration Steps

### Option 1: Automatic Update (Recommended)

1. **Via WordPress Admin**
   ```
   Dashboard → Plugins → FP Digital Publisher → Update Now
   ```

2. **Verify Update**
   - Check version number shows `0.2.1`
   - Visit Settings page to confirm no errors
   - Test a simple post publication

3. **Clear Caches**
   ```bash
   # WordPress object cache (if using Redis/Memcached)
   wp cache flush
   
   # Browser cache
   Hard refresh: Ctrl+Shift+R (Windows/Linux) or Cmd+Shift+R (Mac)
   ```

### Option 2: Manual Update

1. **Download v0.2.1**
   ```bash
   cd /path/to/wp-content/plugins
   mv fp-digital-publisher fp-digital-publisher-backup
   # Upload/extract new version
   ```

2. **Activate**
   ```bash
   wp plugin activate fp-digital-publisher
   ```

3. **Verify**
   ```bash
   wp plugin list | grep fp-digital-publisher
   # Should show version 0.2.1 and status 'active'
   ```

### Option 3: Composer Update

```bash
cd /path/to/wordpress
composer update fp/digital-publisher
wp plugin activate fp-digital-publisher
```

---

## Post-Migration Verification

### 1. Visual Check
- [ ] Visit `Dashboard → FP Publisher`
- [ ] Check version in footer shows `0.2.1`
- [ ] No PHP errors/warnings displayed
- [ ] All menu items accessible

### 2. Functional Tests

**Quick Smoke Test** (3 minutes):
```bash
# Test queue
wp fp-publisher queue list

# Test settings
curl -I http://yoursite.com/wp-admin/admin.php?page=fp-publisher-settings

# Test health endpoint
curl http://yoursite.com/wp-json/fp-publisher/v1/health
```

**Full Functionality Test** (10 minutes):
- [ ] Create a new post in Composer
- [ ] Schedule for future date
- [ ] Add media attachment
- [ ] Select multiple channels
- [ ] Publish/Schedule successfully
- [ ] View in Calendar
- [ ] Check job in Jobs list

### 3. Browser Console Check
1. Open browser DevTools (F12)
2. Navigate to Composer page
3. Verify:
   - [ ] No JavaScript errors
   - [ ] No 404s for assets
   - [ ] No CORS warnings

### 4. Error Log Review
```bash
# Check PHP error log
tail -n 50 /var/log/php-errors.log

# Check WordPress debug log (if WP_DEBUG_LOG enabled)
tail -n 50 wp-content/debug.log
```

---

## What's Changed

### Security Improvements ✅
- **No action required** - All fixes are automatic
- Input validation strengthened across all REST endpoints
- File upload validation now client-side and server-side
- localStorage operations protected with try-catch

### UI/UX Enhancements ✅
- **No action required** - Fully backward compatible
- Improved accessibility (WCAG 2.1 Level AA)
- Better error messages
- Proper form field labels

### Performance Optimizations ✅
- **No action required** - Automatic improvements
- Memory leaks eliminated
- React hooks optimized
- Redundant operations removed

---

## Breaking Changes

**None!** 🎉

This release has:
- ✅ Zero breaking changes
- ✅ Zero API changes
- ✅ Zero database schema changes
- ✅ Zero configuration changes required

---

## Configuration Changes

### No Changes Required

All existing configuration remains valid:
- Settings → Queue configuration
- Settings → Channel credentials
- Settings → Blackout windows
- Settings → Alert recipients

### Optional: Review New Features

While not required, you may want to review:

1. **Enhanced Input Validation**
   - File uploads now limited to 50MB
   - Only image/* and video/* types accepted
   - Automatic validation with user feedback

2. **Improved Error Handling**
   - More descriptive error messages
   - Better network error recovery
   - Proper HTTP status code handling

---

## Troubleshooting

### Issue: Plugin shows as inactive after update
**Solution**:
```bash
wp plugin activate fp-digital-publisher
# Or via Dashboard → Plugins → Activate
```

### Issue: CSS/JS not loading correctly
**Solution**: Clear all caches
```bash
wp cache flush
# Also clear CDN cache if using one
# Hard refresh browser: Ctrl+Shift+R
```

### Issue: "Memory exhausted" error
**Solution**: Increase PHP memory limit
```php
// wp-config.php
define('WP_MEMORY_LIMIT', '256M');
```

### Issue: 404 on admin pages
**Solution**: Flush rewrite rules
```bash
wp rewrite flush
```

### Issue: Jobs not processing
**Solution**: Verify cron is running
```bash
wp cron event list
# Should show 'fp_pub_tick' event

# Manually run if needed
wp cron event run fp_pub_tick
```

---

## Rollback Procedure

If you encounter critical issues:

### Quick Rollback (< 2 minutes)

```bash
# 1. Deactivate plugin
wp plugin deactivate fp-digital-publisher

# 2. Restore backup
cd /path/to/wp-content/plugins
rm -rf fp-digital-publisher
mv fp-digital-publisher-backup fp-digital-publisher

# 3. Reactivate
wp plugin activate fp-digital-publisher
```

### Via WordPress Admin

1. Plugins → Deactivate "FP Digital Publisher"
2. Delete plugin
3. Upload v0.2.0 ZIP
4. Activate plugin

---

## Database Migration

### No Database Changes

Version 0.2.1 includes:
- ✅ No new tables
- ✅ No schema modifications
- ✅ No data migrations
- ✅ No index changes

Your existing database remains unchanged.

---

## API Compatibility

### REST API

All endpoints remain unchanged:
- ✅ Same URL structure
- ✅ Same request/response formats
- ✅ Same authentication
- ✅ Enhanced validation (stricter, but backward compatible)

### WP-CLI Commands

All commands remain unchanged:
- ✅ `wp fp-publisher queue`
- ✅ `wp fp-publisher diagnostics`
- ✅ `wp fp-publisher metrics`
- ✅ All other commands

### Hooks & Filters

All hooks remain unchanged:
- ✅ `fp_publisher_process_job`
- ✅ `fp_pub_payload_pre_send`
- ✅ `fp_pub_retry_decision`
- ✅ `fp_pub_published`
- ✅ All other hooks

---

## Testing Checklist

After migration, verify:

### Core Functionality
- [ ] Dashboard loads without errors
- [ ] Settings can be saved
- [ ] Posts can be created
- [ ] Scheduling works correctly
- [ ] Media uploads accepted
- [ ] Jobs processed successfully

### Integrations
- [ ] OAuth connections still valid
- [ ] API credentials still work
- [ ] Webhooks still functioning
- [ ] Custom hooks still firing

### Performance
- [ ] Page load times acceptable
- [ ] No memory leaks (check DevTools)
- [ ] Queue processing at normal rate
- [ ] No excessive API calls

### Accessibility
- [ ] Screen readers work correctly
- [ ] Keyboard navigation functional
- [ ] Form labels properly associated
- [ ] ARIA attributes present

---

## Support

### If You Need Help

1. **Check Documentation**
   - BUGFIX_REPORT.md - Detailed technical info
   - CHANGELOG.md - All changes listed
   - README.md - Updated feature list

2. **Review Logs**
   ```bash
   # WordPress debug log
   tail -f wp-content/debug.log
   
   # PHP error log
   tail -f /var/log/php-errors.log
   
   # Web server error log
   tail -f /var/log/apache2/error.log
   # or
   tail -f /var/log/nginx/error.log
   ```

3. **Contact Support**
   - Email: info@francescopasseri.com
   - Website: https://francescopasseri.com

---

## Post-Migration Monitoring

### First 24 Hours

Monitor these metrics:
- [ ] Error rate (should not increase)
- [ ] Job success rate (should improve or stay same)
- [ ] Page load times (should improve slightly)
- [ ] Memory usage (should decrease slightly)
- [ ] User reports (should be positive)

### Tools to Use

```bash
# Check error logs
watch -n 60 'tail -n 20 wp-content/debug.log'

# Monitor queue
watch -n 300 'wp fp-publisher queue list'

# Check health
watch -n 60 'curl -s http://yoursite.com/wp-json/fp-publisher/v1/health | jq'
```

---

## Success Criteria

Migration is successful when:
- ✅ Plugin shows version 0.2.1
- ✅ All pages load without errors
- ✅ Jobs process normally
- ✅ No increase in error logs
- ✅ All features functional
- ✅ Performance same or better
- ✅ No user complaints

---

## Recommended Next Steps

After successful migration:

1. **Update Documentation**
   - Update internal wiki/docs
   - Notify team of changes
   - Share CHANGELOG with stakeholders

2. **Performance Baseline**
   - Run performance benchmarks
   - Document baseline metrics
   - Set up monitoring alerts

3. **Security Review**
   - Review new validation rules
   - Test with penetration testing tools
   - Update security policies if needed

4. **Training**
   - Brief team on accessibility improvements
   - Review new error messages
   - Document any workflow changes

---

## Timeline Example

**Total Time: ~30 minutes** (including testing)

```
00:00 - Backup database and files (5 min)
00:05 - Update plugin via admin (2 min)
00:07 - Clear all caches (1 min)
00:08 - Visual verification (3 min)
00:11 - Run smoke tests (5 min)
00:16 - Full functional test (10 min)
00:26 - Review error logs (2 min)
00:28 - Document completion (2 min)
00:30 - Done! ✅
```

---

## FAQ

**Q: Do I need to update my code?**  
A: No, all changes are internal. Your code remains compatible.

**Q: Will my scheduled posts be affected?**  
A: No, all scheduled jobs continue as normal.

**Q: Do I need to reconnect OAuth?**  
A: No, all credentials remain valid.

**Q: Will this fix my existing issues?**  
A: If your issues match the 49 bugs fixed, yes! Check BUGFIX_REPORT.md for specifics.

**Q: How long until I see improvements?**  
A: Immediately after cache clear. Most improvements are instant.

**Q: Can I skip this update?**  
A: Not recommended. This release fixes 15 security vulnerabilities and 7 memory leaks.

---

**Migration Complete!** 🎉

If you've followed all steps and passed all checks, you're ready to enjoy the improved stability, security, and quality of v0.2.1.

---

**Document Version**: 1.0  
**Last Updated**: 2025-10-13  
**Applies To**: FP Digital Publisher v0.2.1
