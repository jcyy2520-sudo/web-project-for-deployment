# Deployment Checklist

## 🎯 Current Status

- [ ] Backend deployed on Render.com
- [ ] Backend has MySQL database
- [ ] Frontend deployed on Vercel
- [ ] Migrations run on database

---

## 📋 Pre-Testing Checklist

### Backend (Render.com)

- [ ] Get your Render backend URL
  - Location: Dashboard → Your Service → URL
  - URL format: `https://your-service-xxxxx.onrender.com`

- [ ] Verify database is accessible
  - Check environment variables in Render settings
  - Verify: `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`

- [ ] Run migrations (if not auto-run)
  ```bash
  php artisan migrate
  php artisan db:seed  # Optional: for test data
  ```

- [ ] Check backend logs
  - Render Dashboard → Logs
  - Look for any errors or warnings

### Frontend (Vercel)

- [ ] Get your Vercel frontend URL
  - Location: Vercel Dashboard → Project
  - URL format: `https://your-project-xxxxx.vercel.app`

- [ ] Set environment variable in Vercel
  - Go to Settings → Environment Variables
  - Add: `VITE_API_URL` = `https://your-backend-url.onrender.com`
  - Apply to: Production environment

- [ ] **Redeploy frontend** after setting env vars
  - Go to Deployments → Redeploy latest version

---

## ✅ Testing Checklist

### Step 1: Backend Health Check

- [ ] Backend responds to health endpoint
  ```bash
  curl https://YOUR-BACKEND-URL/api/health
  ```
  Expected: Status 200 or 404 (if endpoint not created)

- [ ] Database connection works
  ```bash
  curl https://YOUR-BACKEND-URL/api/test-db  # If endpoint exists
  ```

### Step 2: Frontend Loads

- [ ] Frontend page loads without errors
  - Visit: `https://YOUR-FRONTEND-URL`
  - Check for JavaScript errors in console (F12)

- [ ] API configuration debug
  ```javascript
  // Run in console (F12)
  window.debugApiConfig()
  ```

### Step 3: Authentication

- [ ] User can register
  - Go to signup/register page
  - Create test account
  - Check database: `SELECT * FROM users;`

- [ ] User can login
  - Use created account
  - Check if token is saved in localStorage
  - Check if user data displays correctly

### Step 4: Data from Database

- [ ] Can view appointments
  - Login as admin
  - Navigate to appointments list
  - Verify appointments load from database

- [ ] Can view users
  - Navigate to users list
  - Should show all users from database

- [ ] Can perform CRUD operations
  - Create new appointment
  - Update existing record
  - Delete record (if allowed)

### Step 5: API Integration

- [ ] Requests go to Render backend
  - Open DevTools (F12)
  - Go to Network tab
  - Filter for "XHR/Fetch"
  - Verify requests show Render URL: `https://YOUR-BACKEND-URL/api/*`

- [ ] Responses have correct status codes
  - 200: Success
  - 401: Unauthorized (without token)
  - 404: Endpoint not found
  - 500: Server error

---

## 🐛 Troubleshooting Quick Fixes

### Frontend shows loading spinner but never loads
- **Fix**: Update `VITE_API_URL` in Vercel environment variables
- **Fix**: Redeploy frontend after env var change
- **Fix**: Clear browser cache (Ctrl+Shift+Delete)

### Backend returns "Connection refused"
- **Fix**: Verify Render service is running (check Render dashboard)
- **Fix**: Verify backend URL is correct
- **Fix**: Check Render logs for service startup errors

### Database queries fail
- **Fix**: Run migrations: `php artisan migrate`
- **Fix**: Seed database: `php artisan db:seed`
- **Fix**: Check DB credentials in Render environment variables

### CORS errors in console
- **Fix**: Add Vercel domain to `config/cors.php`
- **Fix**: Add to `SANCTUM_STATEFUL_DOMAINS` in .env
- **Fix**: Redeploy backend after config changes

### Login shows "Network Error"
- **Fix**: Check if backend is responding: `curl https://YOUR-BACKEND-URL/sanctum/csrf-cookie`
- **Fix**: Check CORS configuration
- **Fix**: Verify API URL is correct in Vercel env vars

---

## 📊 What to Test

### Core Functionality
- [ ] User Registration
- [ ] User Login
- [ ] User Logout
- [ ] Password Reset (if applicable)

### Database Operations
- [ ] Create new records
- [ ] Read/List records
- [ ] Update existing records
- [ ] Delete records
- [ ] Filter/Search records

### Admin Features
- [ ] View dashboard stats
- [ ] Manage users
- [ ] Manage appointments
- [ ] View messages
- [ ] Generate reports

### Edge Cases
- [ ] Login with wrong password (should fail)
- [ ] Create duplicate email (should fail)
- [ ] Access protected routes without login (should redirect)
- [ ] Network interruption (should show error)

---

## 📝 How to Share Results

If something isn't working, share:

1. **Your Render backend URL**: `https://your-service-xxxxx.onrender.com`
2. **Your Vercel frontend URL**: `https://your-project-xxxxx.vercel.app`
3. **Browser console errors** (F12 → Console tab)
4. **Network tab screenshot** (F12 → Network tab)
5. **Render logs** (Dashboard → Logs)
6. **Backend .env settings** (environment variables)

---

## ✨ Success Indicators

When everything is working, you should see:

✅ Frontend loads in browser
✅ No red errors in browser console
✅ API requests in Network tab show Render URL
✅ Can login with user from database
✅ Dashboard shows data from database
✅ CRUD operations work (create, read, update, delete)
✅ No 401/403 errors on authorized requests
✅ No 502/503 server errors
✅ All features respond within reasonable time (< 3 seconds)
