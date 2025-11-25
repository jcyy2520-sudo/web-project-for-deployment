## Debugging Network Error: ERR_INTERNET_DISCONNECTED

### Problem
When clicking "Book Appointment" on mobile screen sizes, you see:
- `GET http://localhost:3000/api/services net::ERR_INTERNET_DISCONNECTED`
- `GET http://localhost:3000/api/appointments/types/all net::ERR_INTERNET_DISCONNECTED`
- `GET http://localhost:3000/api/appointments/my/appointments net::ERR_INTERNET_DISCONNECTED`

### Root Cause Analysis
The error shows requests being made to `http://localhost:3000/api/...` (frontend port) instead of being proxied to `http://localhost:8000` (backend port).

This happens when:
1. **Vite dev server is NOT running** - The proxy won't work
2. **Service worker caching** - Old code with incorrect baseURL
3. **Proxy misconfiguration** - vite.config.js proxy settings not applied

### Diagnostic Steps

#### Step 1: Verify Backend (Laravel) is Running
```powershell
# In web-backend directory
php artisan serve
# Should show: Laravel development server started on http://127.0.0.1:8000
```

#### Step 2: Verify Frontend (React/Vite) is Running
```powershell
# In web-frontend directory
npm run dev
# Should show: Local: http://localhost:3000/
```

#### Step 3: Check Configuration
1. Open browser DevTools (F12)
2. Go to **Console** tab
3. Type and run: `window.debugApiConfig()`
4. Check the output:
   - ✅ `baseURL: NOT SET (using Vite proxy)` - CORRECT
   - ❌ `baseURL: http://localhost:8000` - WRONG (causes CORS issues)

#### Step 4: Clear Service Worker Cache
The PWA service worker might be caching old code:

1. Open DevTools → **Application** tab
2. Click **Service Workers** in left sidebar
3. Click **Unregister** for any listed service workers
4. Go to **Cache Storage**
5. Delete all cached items
6. Refresh page (Ctrl+F5)

#### Step 5: Clear Browser Cache
1. Press **Ctrl+Shift+Delete** (Windows) or **Cmd+Shift+Delete** (Mac)
2. Select **All time**
3. Check all boxes
4. Click **Clear data**
5. Close and reopen browser

#### Step 6: Test API Connection
1. DevTools → **Console**
2. Run this test:
```javascript
fetch('/api/health')
  .then(r => {
    console.log('✅ Proxy works! Status:', r.status);
    return r.json();
  })
  .catch(e => console.error('❌ Proxy failed:', e.message));
```

Expected result: `✅ Proxy works! Status: 200`

### Solutions

#### Solution 1: Ensure Both Servers Are Running
```powershell
# Terminal 1 - Backend
cd C:\laragon\www\web\web-backend
php artisan serve

# Terminal 2 - Frontend
cd C:\laragon\www\web\web-frontend
npm run dev
```

#### Solution 2: Force Fresh Browser State
1. Clear cache (Step 5 above)
2. Unregister service workers (Step 4 above)
3. Hard refresh: **Ctrl+Shift+F5** (or Cmd+Shift+R on Mac)

#### Solution 3: Check Vite Proxy Configuration
File: `vite.config.js`
```javascript
server: {
  port: 3000,
  proxy: {
    '/api': {
      target: 'http://localhost:8000',  // ✅ Should point to backend
      changeOrigin: true,
      secure: false,
    }
  }
}
```

#### Solution 4: Verify No Explicit BaseURL
File: `src/context/AuthContext.jsx`
```javascript
// ✅ CORRECT - No baseURL (uses Vite proxy)
// axios.defaults.baseURL = 'http://localhost:8000';

axios.defaults.withCredentials = true;
axios.defaults.timeout = 15000;
```

If you see `axios.defaults.baseURL = 'http://localhost:8000'` UNCOMMENTED, comment it out.

### Network Flow (How It Should Work)

```
Browser Request to /api/services
    ↓
Vite Dev Server (localhost:3000)
    ↓ [Proxy Rule Matches]
Forwards to Backend (localhost:8000)
    ↓
Laravel API
    ↓ [Response]
Back to Vite Dev Server
    ↓
Back to Browser
```

### Troubleshooting Checklist

- [ ] Backend (Laravel) running on port 8000
- [ ] Frontend (React/Vite) running on port 3000
- [ ] No `axios.defaults.baseURL` set to localhost:8000
- [ ] vite.config.js has proxy rule for `/api`
- [ ] Service workers unregistered
- [ ] Browser cache cleared
- [ ] Hard refresh (Ctrl+Shift+F5)
- [ ] Console test shows: `✅ Proxy works!`

### If Still Not Working

1. **Check browser console** (F12 → Console):
   - Are there any other error messages?
   - Run: `window.debugApiConfig()` and share output

2. **Check server logs**:
   - Backend: Look for errors in Laravel terminal
   - Frontend: Look for errors in `npm run dev` terminal

3. **Test backend directly** (outside of frontend):
   - Open `http://localhost:8000/api/services` in browser
   - Should see JSON response (not an error)

4. **Check firewall**:
   - Ensure ports 3000 and 8000 are not blocked

### Mobile-Specific Note
The error seems to occur on mobile screen sizes. This might be:
- **More noticeable** on mobile due to stricter CORS/connection handling
- **Service worker caching** old code more aggressively
- **Network timeout** from slower mobile simulation

**Solution**: Hard refresh and clear cache works best for this.
