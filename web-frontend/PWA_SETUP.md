# PWA Setup Guide - Jezee Law Notary System

## Overview
Your project is now configured as a Progressive Web App (PWA). This allows users to:
- **Install the app** on their device (Windows, Mac, iOS, Android)
- **Access offline** with cached content and data
- **Receive notifications** (when implemented)
- **Use as a native app** with standalone window

## What's Been Added

### 1. **Service Worker** (`public/sw.js`)
- Handles offline functionality
- Implements intelligent caching strategies:
  - **API Requests**: Network-first (try network, fallback to cache)
  - **Images**: Cache-first (use cached, fallback to network)
  - **Static Assets**: Cache-first with network fallback

### 2. **Manifest File** (`public/manifest.json`)
- Defines app metadata (name, icons, colors)
- Specifies shortcuts for quick actions
- Controls display mode (standalone, fullscreen, etc.)

### 3. **Index.html Updates**
- Added meta tags for PWA support
- Apple iOS support tags
- Service worker registration script

### 4. **Install Prompt Component** (`src/components/InstallPrompt.jsx`)
- Beautiful UI for install prompts
- Appears when app is installable
- Automatically dismisses after installation

### 5. **Main App Integration**
- Service worker automatically registered on page load
- Install prompt triggers at optimal time
- Works seamlessly in background

## Testing Locally

### Build for Production
```bash
cd web-frontend
npm run build
```

### Preview PWA Build
```bash
npm run preview
```

### In Browser DevTools
1. Open Chrome DevTools (F12)
2. Go to **Application** tab
3. Select **Service Workers** - you'll see the registered worker
4. Check **Manifest** to view app metadata
5. Check **Storage** to see cached assets

### Testing Install Prompt
1. Build and preview the app
2. Look for install prompt in bottom-right corner
3. Click "Install" to install as app
4. The app will open in standalone mode (no address bar)

## Desktop Installation (Windows/Mac/Linux)

### Chrome/Edge Browser
1. Open your app in Chrome or Edge
2. Click the **Install** button (or app icon in address bar)
3. Follow the installation wizard
4. Find the app in your Start Menu or Applications

### Safari (iOS/macOS)
1. Open app in Safari
2. Tap **Share** → **Add to Home Screen**
3. App appears on home screen/dock

## Mobile Installation

### Android (Chrome)
1. Open app in Chrome
2. Tap menu (⋮) → **Install app**
3. Confirm installation

### iOS (Safari)
1. Open app in Safari
2. Tap **Share** → **Add to Home Screen**
3. Name the app and add it

## Offline Functionality

### What Works Offline
- **UI and Navigation**: All previously loaded pages
- **Cached Images**: Already-viewed images display
- **Static Content**: HTML, CSS, JavaScript assets

### What Needs Network
- **API Calls**: Real-time data from backend (shows cached data or error message)
- **New Resources**: Images not previously viewed
- **Authentication**: Login requires network (cached during session)

### Cache Storage
- **API Cache**: 200 requests, 5-minute expiration
- **Image Cache**: 500 images, 30-day expiration
- **Static Cache**: All assets, updated with app

## Customization

### Change App Name/Icons
Edit `public/manifest.json`:
```json
{
  "name": "Your App Name",
  "short_name": "App",
  "icons": [
    {
      "src": "/your-icon.png",
      "sizes": "192x192",
      "type": "image/png"
    }
  ]
}
```

### Change Theme Colors
```json
{
  "theme_color": "#000000",
  "background_color": "#ffffff"
}
```

### Modify Cache Strategy
Edit `public/sw.js`:
```javascript
// Change cache names
const CACHE_VERSION = 'v2';

// Modify cache duration
maxAgeSeconds: 3600 // 1 hour instead of default
```

### Change Install Prompt Timing
Edit `src/components/InstallPrompt.jsx` to show at different times or add to specific pages

## Production Deployment

### Requirements for PWA
1. ✅ HTTPS (required for service workers)
2. ✅ Manifest.json (already added)
3. ✅ Service worker (already added)
4. ✅ App icons (logos in public folder)
5. ✅ Responsive design (already implemented)

### Deploy Steps
1. Ensure app is deployed with HTTPS
2. Add icons in `public/` folder:
   - `logo192.png` (192x192)
   - `logo512.png` (512x512)
   - `favicon.ico` (at least 64x64)
3. Build: `npm run build`
4. Deploy `dist/` folder to production
5. Verify service worker registration in production

### Vercel Deployment
The app is already configured for Vercel:
- HTTPS is automatic ✅
- Build configuration in `vite.config.js` ✅
- All PWA files included ✅

## Troubleshooting

### Install Button Not Showing
- **Chrome**: App must meet PWA requirements (icons, manifest, HTTPS)
- **Check DevTools**: 
  - Application → Manifest should show no errors
  - Application → Service Workers should show registered
- **Must be over HTTP/HTTPS**: `http://localhost:3000` works for testing

### Service Worker Not Registering
1. Check browser console for errors
2. Verify `public/sw.js` exists
3. Clear cache: DevTools → Application → Clear storage
4. Restart dev server

### Offline Pages Not Loading
1. Open DevTools → Application → Cache Storage
2. Check if `jezee-cache-v1` exists with assets
3. Verify `sw.js` has correct cache strategy

### Icons Not Showing in Manifest
1. Verify icons exist in `public/` folder
2. Check manifest paths match file names exactly
3. Clear browser cache and rebuild

## Performance Tips

### Reduce Cache Size
Limit cached items in `sw.js`:
```javascript
maxEntries: 100, // Instead of 500
```

### Speed Up Load Times
- Pre-cache critical assets
- Use compression (automatic on Vercel)
- Optimize images (use .webp format)

### Monitor Cache Usage
DevTools → Application → Cache Storage → Size shown

## Next Steps

1. **Add Notifications**: Implement push notifications
2. **Sync Data**: Background sync for offline changes
3. **Analytics**: Track installs and usage
4. **Update Strategy**: Auto-update installed apps
5. **Shortcuts**: Add quick actions to app icon menu (already configured)

## Resources

- [Google PWA Documentation](https://developers.google.com/web/progressive-web-apps)
- [MDN PWA Guide](https://developer.mozilla.org/en-US/docs/Web/Progressive_web_apps)
- [Web.dev PWA Checklist](https://web.dev/pwa-checklist/)
- [vite-plugin-pwa Docs](https://vite-pwa-org.netlify.app/)

## Files Modified/Created

### Created
- `public/manifest.json` - App manifest and metadata
- `public/sw.js` - Service worker logic
- `src/components/InstallPrompt.jsx` - Install UI component

### Modified
- `index.html` - Added PWA meta tags and service worker registration
- `vite.config.js` - Updated PWA plugin configuration
- `src/App.jsx` - Added InstallPrompt component
- `src/main.jsx` - Removed service worker unregistration

## Support
For issues or questions, check the troubleshooting section or refer to official PWA documentation.
