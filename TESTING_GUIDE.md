# System Testing Guide

## Overview
Your system has:
- **Frontend**: React app deployed on Vercel
- **Backend**: Laravel API deployed on Render.com with MySQL database
- **Database**: MySQL database on Render.com (or connected via render.com)

---

## Step 1: Verify Your Backend on Render

### 1a. Get Your Render Backend URL
1. Go to https://dashboard.render.com
2. Find your backend service
3. Copy the URL (e.g., `https://your-service-xxxxx.onrender.com`)
4. Save this URL - you'll need it for testing

### 1b. Test Backend Endpoints

Use any of these methods to test:

#### Method 1: Using Browser
Simply visit these URLs in your browser:

```
https://your-service-xxxxx.onrender.com/api/health
https://your-service-xxxxx.onrender.com/sanctum/csrf-cookie
```

#### Method 2: Using curl (Terminal)
```bash
# Test if backend is running
curl https://your-service-xxxxx.onrender.com/api/health

# Test CSRF token endpoint
curl https://your-service-xxxxx.onrender.com/sanctum/csrf-cookie

# Test user endpoint (should get 401 without auth token)
curl https://your-service-xxxxx.onrender.com/api/user

# List appointments (should get 401 without auth)
curl https://your-service-xxxxx.onrender.com/api/appointments
```

#### Method 3: Using PowerShell (Windows)
```powershell
# Run the test script
.\test-system.ps1 "https://your-service-xxxxx.onrender.com" "https://your-frontend-url.vercel.app"
```

---

## Step 2: Verify Database Connection

Your backend needs to be able to access the database. Check these:

### Backend Environment Variables on Render
Go to your Render service settings and verify:
- `DB_HOST` = Your database host
- `DB_PORT` = 3306 (or your port)
- `DB_DATABASE` = Database name
- `DB_USERNAME` = Database user
- `DB_PASSWORD` = Database password

### Run Migrations
If migrations haven't run, your database tables won't exist. You might need to:

1. SSH into Render (if available)
2. Run: `php artisan migrate`
3. Or check Render's deployment logs for migration output

---

## Step 3: Update Frontend Configuration

### 3a. Update Vercel Environment Variables

1. Go to your Vercel project: https://vercel.com/dashboard
2. Go to **Settings → Environment Variables**
3. Add a new variable:
   - **Name**: `VITE_API_URL`
   - **Value**: `https://your-service-xxxxx.onrender.com`
   - **Environments**: Production (and Preview if testing)
4. **Redeploy** your frontend

### 3b. Verify Local Development
For local testing, your frontend already uses Vite proxy in `vite.config.js`:
```javascript
proxy: {
  '/api': {
    target: 'http://127.0.0.1:8000',  // Local Laravel dev server
  }
}
```

---

## Step 4: Test Frontend Connection

### 4a. Test Frontend Loads
Visit your Vercel frontend URL:
```
https://your-frontend-url.vercel.app
```

### 4b. Open Browser DevTools Console
Press `F12` and go to the **Console** tab.

Type and run:
```javascript
// Check API configuration
window.debugApiConfig()
```

You should see:
- Current URL
- Current Origin
- Axios configuration
- Test request to `/api/health`

### 4c. Check Network Tab
In DevTools **Network** tab, look for API requests to make sure they're going to your Render backend, not localhost.

---

## Step 5: Full System Test

### Test Authentication Flow
1. Go to your frontend (Vercel)
2. Try to **Register** or **Login**
3. Check if you can:
   - Create an account
   - Log in successfully
   - See your user data
   - Access protected pages

### Test Database Connection
1. After logging in, go to the **Admin Dashboard** (if you're admin)
2. Check if you can:
   - View appointments (should pull from database)
   - View users list (should pull from database)
   - Create/update records
   - Filter and search (should work if database is connected)

### Test API Endpoints Directly

#### Get CSRF Token
```bash
curl -X GET https://your-service-xxxxx.onrender.com/sanctum/csrf-cookie -v
```

#### Login (Replace email and password)
```bash
curl -X POST https://your-service-xxxxx.onrender.com/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password"}'
```

#### Get User (Replace token from login response)
```bash
curl -X GET https://your-service-xxxxx.onrender.com/api/user \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

#### Get Appointments (Replace token)
```bash
curl -X GET https://your-service-xxxxx.onrender.com/api/appointments \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

---

## Step 6: Database Verification

### Check Database Tables Exist
Connect to your database directly (using a tool like MySQL Workbench or terminal):

```sql
-- List all tables
SHOW TABLES;

-- Check specific tables
DESCRIBE users;
DESCRIBE appointments;
DESCRIBE messages;
```

### Verify Data
```sql
-- Check if users exist
SELECT COUNT(*) FROM users;

-- Check if appointments exist
SELECT COUNT(*) FROM appointments;

-- List recent users
SELECT id, name, email, role FROM users LIMIT 5;
```

---

## Troubleshooting

### Issue: Frontend shows "Cannot connect to backend"

**Solution:**
1. Verify Render backend URL is correct
2. Check `VITE_API_URL` environment variable in Vercel
3. Redeploy frontend after updating env vars
4. Clear browser cache (Ctrl+Shift+Delete)
5. Open DevTools → Network and check if requests are going to Render URL

### Issue: Database queries return empty

**Solution:**
1. Verify migrations have run: `php artisan migrate --list` (on your Render instance)
2. Check database connection in `.env` on Render
3. Verify tables exist: `SHOW TABLES;` in MySQL
4. Seed database if needed: `php artisan db:seed`

### Issue: Login fails with 401 or 422

**Solution:**
1. Check user exists in database: `SELECT * FROM users WHERE email='test@example.com';`
2. Verify CSRF token is being fetched
3. Check Laravel logs on Render for detailed error
4. Verify authentication guard is configured (should use `sanctum`)

### Issue: CORS errors

**Solution:**
1. Check your Laravel `config/cors.php` allows Vercel domain:
   ```php
   'allowed_origins' => [
       'https://your-frontend-url.vercel.app',
       'http://localhost:3000'
   ]
   ```
2. Verify `SANCTUM_STATEFUL_DOMAINS` includes Vercel domain:
   ```
   SANCTUM_STATEFUL_DOMAINS=your-frontend-url.vercel.app,localhost:3000
   ```
3. Redeploy backend after changes

### Issue: "502 Bad Gateway" from Render

**Solution:**
1. Your backend service is experiencing issues
2. Check Render logs: Dashboard → Your Service → Logs
3. Possible causes:
   - Database connection failed
   - Out of memory
   - PHP error in code
   - Missing environment variables

---

## Quick Verification Checklist

- [ ] Backend URL is accessible (try in browser)
- [ ] Backend returns 200 for `/api/health`
- [ ] Frontend is deployed and loads
- [ ] `VITE_API_URL` environment variable is set in Vercel
- [ ] Frontend has been redeployed after setting env var
- [ ] Database migrations have run
- [ ] Can login successfully
- [ ] Can see data from database
- [ ] Network requests in DevTools show Render URL

---

## Testing Scripts

### PowerShell (Windows)
```powershell
.\test-system.ps1 "https://your-backend.onrender.com" "https://your-frontend.vercel.app"
```

### Bash (macOS/Linux)
```bash
chmod +x ./test-system.sh
./test-system.sh "https://your-backend.onrender.com" "https://your-frontend.vercel.app"
```

---

## Support Resources

- **Render Documentation**: https://docs.render.com
- **Vercel Documentation**: https://vercel.com/docs
- **Laravel Deployment**: https://laravel.com/docs/deployment
- **React on Vercel**: https://vercel.com/guides/deploying-react-with-vercel
