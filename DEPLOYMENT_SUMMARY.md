# System Testing & Deployment Summary

## What Was Done

I've prepared your system for comprehensive testing with these updates:

### 1. ✅ Frontend API Configuration Updated
- **File**: `src/context/AuthContext.jsx`
- **Change**: Added environment-based API URL configuration
- **How it works**:
  - **Development** (Local): Uses Vite proxy to `http://localhost:8000`
  - **Production** (Vercel): Uses `VITE_API_URL` environment variable pointing to Render backend

### 2. ✅ Environment Configuration Added
- **File**: `.env.production`
- **Purpose**: Stores Render backend URL for Vercel deployment
- **Next Step**: Update this file with your actual Render URL

### 3. ✅ Testing Scripts Created
- **Windows**: `test-system.ps1` - PowerShell testing script
- **Mac/Linux**: `test-system.sh` - Bash testing script
- **Usage**: Run to automatically test all endpoints

### 4. ✅ Documentation Created
- `TESTING_GUIDE.md` - Complete step-by-step testing guide
- `DEPLOYMENT_CHECKLIST.md` - Quick reference checklist

---

## Quick Start Testing

### Step 1: Get Your Backend URL
1. Go to https://dashboard.render.com
2. Find your backend service
3. Copy the URL (e.g., `https://your-service-xxxxx.onrender.com`)

### Step 2: Update Frontend Configuration

**Option A: For Local Testing**
```bash
# Make sure your backend is running locally:
cd web-backend
php artisan serve
# This runs on http://localhost:8000

# In another terminal, run frontend:
cd web-frontend
npm run dev
# This runs on http://localhost:3000
```

**Option B: For Production (Vercel)**

1. **Update Vercel Environment Variables**:
   - Go to https://vercel.com/dashboard
   - Select your project
   - Settings → Environment Variables
   - Add: `VITE_API_URL` = `https://your-service-xxxxx.onrender.com`
   - Apply to Production
   - **Redeploy** your frontend

2. **Update local .env.production**:
   ```
   VITE_API_URL=https://your-service-xxxxx.onrender.com
   ```

### Step 3: Test Backend Connectivity

**Test 1: Browser**
```
https://your-backend-url/api/health
```

**Test 2: PowerShell**
```powershell
.\test-system.ps1 "https://your-backend-url" "https://your-frontend-url"
```

**Test 3: Manual API Test**
```bash
# Test CSRF token
curl -X GET https://your-backend-url/sanctum/csrf-cookie

# Test health
curl -X GET https://your-backend-url/api/health

# Try login (replace with real credentials)
curl -X POST https://your-backend-url/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'
```

### Step 4: Test Frontend in Browser

1. Visit your frontend URL
2. Open DevTools (F12)
3. Go to Console tab
4. Run: `window.debugApiConfig()` - This will test connectivity
5. Try to login
6. Check Network tab to see requests going to Render URL

### Step 5: Verify Database

Your backend should have a MySQL database with tables:
- `users` - User accounts
- `appointments` - Appointment data
- `messages` - Messages
- `audit_logs` - Activity logs
- etc.

**To verify**, you can:
1. Check data appears in frontend after login
2. Connect to DB directly if you have access
3. Run: `php artisan tinker` and check data

---

## What to Check

### ✅ Backend Health
- [ ] Backend URL responds without errors
- [ ] Database is accessible
- [ ] Migrations have been run
- [ ] All tables exist in database

### ✅ Frontend Connection
- [ ] Frontend loads without errors
- [ ] API requests go to Render URL (check Network tab)
- [ ] No CORS errors in console

### ✅ Authentication
- [ ] Can register new users
- [ ] Can login successfully
- [ ] User data appears on dashboard
- [ ] Token is saved in localStorage

### ✅ Data Retrieval
- [ ] Appointments load from database
- [ ] Users list shows data
- [ ] Filters and searches work
- [ ] Real-time data updates work

---

## Key Files Modified

```
web-frontend/
├── src/context/AuthContext.jsx       ← Updated API config
├── .env.production                   ← Added (new)
└── vite.config.js                    ← Already has proxy setup

web/
├── test-system.ps1                   ← Added (new)
├── test-system.sh                    ← Added (new)
├── TESTING_GUIDE.md                  ← Added (new)
└── DEPLOYMENT_CHECKLIST.md           ← Added (new)
```

---

## Common Issues & Fixes

### Issue: Frontend shows "Cannot connect to server"
**Fix**: 
1. Check `VITE_API_URL` is set correctly in Vercel
2. Redeploy frontend: Go to Vercel → Deployments → Redeploy
3. Clear browser cache: Ctrl+Shift+Delete
4. Verify backend URL is correct

### Issue: Database operations fail
**Fix**:
1. Run migrations: `php artisan migrate`
2. Check database credentials in Render env vars
3. Verify tables exist: `php artisan tinker` → `DB::table('users')->count()`

### Issue: Login fails with 401
**Fix**:
1. Verify user exists in database
2. Check credentials are correct
3. Look at Laravel logs in Render dashboard
4. Verify CSRF token is being fetched

### Issue: CORS errors
**Fix**:
1. Add Vercel domain to `config/cors.php`
2. Add to `SANCTUM_STATEFUL_DOMAINS` in .env
3. Redeploy backend

---

## Next Steps

1. **Update .env.production** with your actual Render URL
2. **Redeploy frontend** on Vercel after updating env vars
3. **Run test scripts** to verify connectivity
4. **Test login flow** to confirm everything works
5. **Check database** to see if data is being stored

---

## Support Documents

- `TESTING_GUIDE.md` - Detailed testing steps with curl commands
- `DEPLOYMENT_CHECKLIST.md` - Quick reference checklist

---

## Your URLs

**You will need:**
- Render Backend URL: `https://your-service-xxxxx.onrender.com`
- Vercel Frontend URL: `https://your-project-xxxxx.vercel.app`

Get these and update them in the configuration files.

---

Good luck with your deployment! 🚀
