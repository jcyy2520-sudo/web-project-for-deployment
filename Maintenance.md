# Migrations already applied
php artisan migrate

# Start server
php artisan serve

# Test health check (public)
curl http://localhost:8000/api/health

# View error logs (admin only)
curl http://localhost:8000/api/admin/error-logs \
  -H "Authorization: Bearer YOUR_TOKEN"

# View metrics dashboard (admin only)
curl http://localhost:8000/api/admin/metrics/dashboard \
  -H "Authorization: Bearer YOUR_TOKEN"

  GET    /api/admin/alerts/dashboard          # Overview with summary
GET    /api/admin/alerts                    # View all alerts with filtering
GET    /api/admin/alerts/{id}               # Alert details
POST   /api/admin/alerts/{id}/acknowledge   # Mark as acknowledged

POST   /api/admin/alerts/rules              # Create alert rule
GET    /api/admin/alerts/rules              # View all rules
PUT    /api/admin/alerts/rules/{id}         # Update rule
DELETE /api/admin/alerts/rules/{id}         # Delete rule

# Create an alert rule
curl -X POST http://localhost:8000/api/admin/alerts/rules \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "High Error Rate",
    "type": "error_level",
    "condition": "==",
    "threshold": "error",
    "channel": "slack",
    "slack_webhook": "YOUR_ACTUAL_WEBHOOK_URL",
    "enabled": true,
    "cooldown_minutes": 5
  }'

# NOTE: Keep slack_webhook URLs in .env file only, never commit them to git

# View alerts dashboard
curl http://localhost:8000/api/admin/alerts/dashboard \
  -H "Authorization: Bearer TOKEN"



















Improve my AI chatbot Smart Understanding. 

Improve NLU, intent recognition, and fuzzy matching so it can understand incomplete messages, spelling mistakes, shortcuts, or informal text. 

Interpret user questions even if phrased differently. 

Provide the closest correct action or answer based on system context.