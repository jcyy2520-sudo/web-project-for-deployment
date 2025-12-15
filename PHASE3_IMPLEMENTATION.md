# Phase 3: Intelligence at Scale - Implementation Guide

## Overview

Phase 3 implements intelligent monitoring, security hardening, and disaster recovery for your Laravel + React application. This ensures system reliability, data safety, and proactive threat detection.

## 1. Analytics Dashboard

### Features
- **Real-time System Metrics**: CPU, memory, disk usage tracking
- **Performance Trends**: Historical data analysis and trend detection
- **Health Status**: Comprehensive system health overview
- **Database Monitoring**: Connection tracking and size monitoring

### Database Schema
```sql
system_metrics
├── timestamp
├── cpu_usage (%)
├── memory_usage (bytes)
├── disk_usage (bytes)
├── load_average_*min
├── database_connections
├── pending_jobs
├── failed_jobs
└── metadata
```

### API Endpoints
```
GET  /api/analytics/dashboard?hours=24
GET  /api/analytics/cpu?hours=24
GET  /api/analytics/memory?hours=24
GET  /api/analytics/disk?hours=24
GET  /api/analytics/health
GET  /api/analytics/trends?hours=24
```

### Frontend Components
- `AnalyticsDashboard.jsx` - Main dashboard with all metrics
- Auto-refreshes every 30 seconds
- Customizable time ranges (1h, 6h, 24h, 7d)
- Health status indicators (healthy, warning, critical)

### Health Status Thresholds
- **CPU**: Warning ≥ 70%, Critical ≥ 85%
- **Memory**: Warning ≥ 75%, Critical ≥ 90%
- **Disk**: Warning ≥ 80%, Critical ≥ 95%

### Usage Example
```php
// Collect metrics
php artisan metrics:collect

// In code
$metricsService = new SystemMetricsService();
$metrics = $metricsService->collectMetrics();
$latest = $metricsService->getLatestMetrics();
```

---

## 2. Rate Limiting & DDoS Protection

### Features
- **IP-based Rate Limiting**: 100 requests/60 seconds (default)
- **Suspicious Activity Detection**: Pattern-based threat detection
- **Automatic IP Blocking**: High-risk IPs blocked for 1 hour
- **Risk Scoring**: Dynamic scoring based on behavior patterns
- **Configurable Rules**: Per-IP customization available

### Security Event Tracking
```sql
security_events
├── event_type (rate_limit_exceeded, ip_blocked, etc.)
├── ip_address
├── risk_score (0-100)
├── is_suspicious (boolean)
├── action_taken (blocked, logged, alerted)
├── blocked_until (nullable timestamp)
└── details (JSON metadata)
```

### Risk Score Calculation
- Base on request count exceeding threshold: 40 points
- Repeat offenses (per hour): 30 points
- Previous blocks (per week): 30 points
- **Block threshold**: 70+ points

### API Endpoints
```
GET  /api/security/events?minutes=60
GET  /api/security/blocked-ips
POST /api/security/ip/block
POST /api/security/ip/unblock
GET  /api/security/summary?minutes=60
GET  /api/security/rate-limit/{ip}
POST /api/security/rate-limit/update
```

### Frontend Components
- `SecurityMonitor.jsx` - Real-time security monitoring
- View all suspicious events
- Manually block/unblock IPs
- Adjust rate limits per IP
- Auto-refresh support (10-second intervals)

### Usage Example
```php
// In controller
$ddosService = new DDoSProtectionService($limiter);

// Check if request allowed
if (!$ddosService->shouldAllowRequest($ip, $endpoint)) {
    return response()->json(['blocked' => true], 429);
}

// Get security summary
$summary = $ddosService->getSecuritySummary(60); // Last 60 minutes
```

### Configuration
Edit in `DDoSProtectionService.php`:
```php
private const DEFAULT_RATE_LIMIT = 100;        // requests
private const DEFAULT_TIME_WINDOW = 60;        // seconds
private const SUSPICIOUS_THRESHOLD = 150;      // requests/minute
private const RISK_SCORE_THRESHOLD = 70;       // score to block
private const BLOCK_DURATION = 3600;           // 1 hour
```

---

## 3. Automated Cleanup Tasks

### Features
- **Log Rotation**: Files > 50MB archived and compressed
- **Cache Clearing**: Framework cache and compiled views cleared
- **Old Backup Removal**: Backups older than 30 days deleted
- **Temp File Cleanup**: 7+ day old temporary files removed
- **Session Cleanup**: Expired sessions removed (120-min default)
- **Failed Job Archival**: Jobs archived after 30 days
- **Metrics Archival**: Old metrics removed after 90 days

### Scheduled Tasks (in Kernel.php)
```
✓ Metrics Collection    - Every 5 minutes
✓ Log Rotation          - Daily at 1 AM
✓ Cache Clearing        - Daily at 2 AM
✓ Database Backup       - Daily at 3 AM
✓ Session Cleanup       - Every hour
✓ Temp File Cleanup     - Daily at 4 AM
✓ Old Backup Archival   - Weekly (Sunday 5 AM)
✓ Metrics Archival      - Weekly (Sunday 6 AM)
✓ Failed Jobs Archival  - Daily at 7 AM
```

### Artisan Commands
```bash
# Run all cleanup tasks
php artisan cleanup:run

# Run specific component
php artisan cleanup:run --component=logs
php artisan cleanup:run --component=cache
php artisan cleanup:run --component=backups
php artisan cleanup:run --component=temp
php artisan cleanup:run --component=sessions
php artisan cleanup:run --component=jobs
php artisan cleanup:run --component=metrics
```

### API Endpoints
```
POST /api/maintenance/cleanup                    # Run all
POST /api/maintenance/cleanup/logs               # Rotate logs
POST /api/maintenance/cleanup/cache              # Clear cache
POST /api/maintenance/cleanup/old-backups        # Remove old backups
POST /api/maintenance/cleanup/temp-files         # Clean temp
POST /api/maintenance/cleanup/sessions           # Clean sessions
GET  /api/maintenance/tasks/status               # View schedule
```

### Frontend Component
- `MaintenanceCenter.jsx` - Manage cleanup tasks
- Run any task manually
- View scheduled tasks
- Monitor last run results

### Usage Example
```php
// Run all cleanup
$cleanupService = new CleanupService();
$results = $cleanupService->performAllCleanup();

// Run specific cleanup
$cleanupService->rotateLogs();
$cleanupService->clearCache();
$cleanupService->removeOldBackups(30); // 30 days
```

---

## 4. Disaster Recovery Plan

### Features
- **Automated Backups**: Daily database backups
- **Backup Verification**: Integrity checking for all backups
- **Point-in-Time Recovery**: Restore from any backup
- **Restore Testing**: Dry-run restore procedures
- **Recovery Documentation**: Step-by-step recovery plans
- **Schedule Management**: Flexible backup scheduling

### Backup Process
1. Create backup with unique timestamp
2. Store metadata in database
3. Verify file integrity
4. Log backup completion
5. Auto-cleanup old backups (30+ days)

### Database Schema
```sql
database_backups
├── filename
├── path
├── size (bytes)
├── status (pending, completed, failed)
├── is_verified (boolean)
├── backup_type (manual, automatic)
├── created_by (user_id)
├── started_at
├── completed_at
├── verified_at
└── last_restored_at
```

### API Endpoints
```
GET  /api/backups                              # List all
POST /api/backups/create                       # Create new
GET  /api/backups/{id}/verify                  # Verify backup
POST /api/backups/{id}/restore                 # Restore database
POST /api/backups/{id}/test-restore            # Test restore
GET  /api/backups/{id}/recovery-plan           # Get recovery steps
GET  /api/backups/schedule/status              # View schedule
POST /api/backups/schedule/update              # Update schedule
GET  /api/backups/statistics                   # Get stats
```

### Frontend Component
- `DisasterRecovery.jsx` - Comprehensive backup management
- Create backups manually
- Verify backup integrity
- Test restore procedures
- View recovery plans
- Monitor backup schedule

### Artisan Commands
```bash
# Create backup
php artisan backup:database

# Create with verification
php artisan backup:database --verify

# Verify specific backup
$backupService->verifyBackup($backup);

# Test restore
$result = $backupService->testRestore($backup);

# Perform restore
$backupService->restore($backup);
```

### Recovery Procedure
1. **Pre-Recovery**:
   - Take backup of current database
   - Stop application processes
   - Verify database credentials
   - Check disk space

2. **Recovery**:
   - Call `$backupService->restore($backup)`
   - Verify data integrity
   - Test application functionality

3. **Post-Recovery**:
   - Check application logs
   - Restart services
   - Notify stakeholders
   - Document recovery

### Usage Example
```php
// Create backup
$backup = $backupService->backup('manual', auth()->id());

// Verify before using
if ($backupService->verifyBackup($backup)) {
    // Safe to use
}

// Test restore (dry run)
$result = $backupService->testRestore($backup);
if ($result['success']) {
    // Ready for actual restore
    $backupService->restore($backup);
}

// Get recovery plan
$plan = $backupService->getRecoveryProcedure($backup);
```

### Schedule Configuration
```bash
# Update backup frequency
POST /api/backups/schedule/update
{ "frequency": "daily" }  # or: hourly, weekly, monthly
```

### Retention Policy
- **Automatic**: Delete backups older than 30 days
- **Manual**: Can override retention via cleanup task
- **Minimum**: Keep at least 1 backup always

---

## 5. Safety Considerations

### Access Control
All Phase 3 endpoints require:
- Authentication (`auth:sanctum`)
- Admin role (`admin` middleware)

### Data Security
- Backups stored in `/storage/backups`
- Only accessible via authenticated API
- Verify checksums before restore
- Log all recovery operations

### Rate Limiting Safeguards
- Conservative thresholds (100 requests/min default)
- Multiple detection methods (request count, patterns)
- Automatic 1-hour blocking (adjustable)
- Manual override via admin panel

### Backup Safety
- Verify integrity before restore
- Test restore before actual recovery
- Keep 30 days of backups
- Monitor backup success rate
- Alert on backup failures

### Monitoring Best Practices
1. Review security events weekly
2. Test backups monthly
3. Monitor disk usage trends
4. Check failed job queue daily
5. Verify metrics collection running

---

## 6. Database Migrations

Run migrations to create required tables:
```bash
php artisan migrate

# Tables created:
# - system_metrics
# - security_events
```

---

## 7. Configuration

### Environment Variables
No additional env variables required. Uses existing database configuration.

### Laravel Queue (for scheduled tasks)
Ensure queue is running:
```bash
php artisan queue:work
# OR: php artisan queue:daemon
```

---

## 8. Troubleshooting

### Metrics Not Collecting
```bash
# Check if command runs
php artisan metrics:collect

# Check logs
tail -f storage/logs/laravel.log

# Ensure scheduler is running
php artisan schedule:run
```

### Backups Failing
- Verify disk space: `df -h`
- Check MySQL credentials in `.env`
- Verify `mysqldump` is installed
- Check file permissions on `/storage/backups`

### DDoS Protection Not Working
- Verify cache is configured (Redis or Memcached)
- Check IP detection (use `request()->ip()`)
- Verify middleware is applied to routes

---

## 9. Monitoring Checklist

Daily:
- [ ] Check failed jobs count
- [ ] Verify latest backup completed
- [ ] Review security events

Weekly:
- [ ] Run backup verification test
- [ ] Test restore procedure
- [ ] Review disk usage trends
- [ ] Check security event patterns

Monthly:
- [ ] Full disaster recovery drill
- [ ] Audit access logs
- [ ] Review metrics trends
- [ ] Update rate limit thresholds if needed

---

## 10. Support & Next Steps

### Additional Resources
- [Laravel Scheduling](https://laravel.com/docs/scheduling)
- [Laravel Database Backups](https://spatie.be/docs/laravel-backup/v8/introduction)
- [DDoS Protection Best Practices](https://owasp.org/www-community/attacks/DoS_attack)

### Future Enhancements
- External backup storage (S3, Azure)
- Slack/Email alerts for critical events
- Advanced analytics (Prometheus + Grafana)
- Automated recovery with failover
- Global rate limiting across servers

---

**Last Updated**: 2025-12-15
**Version**: 1.0
**Status**: Production Ready ✓
