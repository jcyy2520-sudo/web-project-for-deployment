# 🚀 Quick Start - Run These Commands in Order

## Copy-Paste Commands (Windows PowerShell)

### Run the automated setup script:
```powershell
.\SETUP_LANDING_CMS.bat
```

This creates all the scaffolds automatically.

---

## Then: Copy-Paste Code from IMPLEMENTATION_GUIDE.md

After running the script, follow these steps:

### 1. Update Migration File
- Open: `web-backend/database/migrations/[DATE]_create_landing_page_content_table.php`
- Replace content with code from IMPLEMENTATION_GUIDE.md (Step 1)

### 2. Update Model
- Open: `web-backend/app/Models/LandingPageContent.php`
- Replace entire file with code from IMPLEMENTATION_GUIDE.md (Step 2)

### 3. Update Controller
- Open: `web-backend/app/Http/Controllers/LandingPageContentController.php`
- Replace entire file with code from IMPLEMENTATION_GUIDE.md (Step 3)

### 4. Update Routes
- Open: `web-backend/routes/api.php`
- Add the route code from IMPLEMENTATION_GUIDE.md (Step 4)
- Add the import statement at the top

### 5. Create React Component
- Create new file: `web-frontend/src/components/admin/AdminLandingPageSettings.jsx`
- Copy entire content from IMPLEMENTATION_GUIDE.md (Step 5)

### 6. Run Migrations
```powershell
cd web-backend
php artisan migrate
```

### 7. Update Admin Dashboard
- Open: `web-frontend/src/pages/AdminDashboard.jsx`
- Add import and components as shown in IMPLEMENTATION_GUIDE.md (Step 7)

---

## 🎉 Done! 

Your Landing Page CMS is ready to use:

1. Go to Admin Dashboard
2. Find "Landing Page Settings" in the menu
3. Edit Hero, Features, or Process Steps
4. Click Save
5. Changes appear instantly on landing page

---

## Troubleshooting

**Problem:** Migration doesn't work  
**Solution:** Make sure `php artisan migrate` runs without errors. Check your database connection in `.env`

**Problem:** React component doesn't show  
**Solution:** Make sure you added the import and the menu item to AdminDashboard.jsx

**Problem:** Can't save changes  
**Solution:** Check browser console for errors. Make sure you're logged in as admin.

**Questions?** See IMPLEMENTATION_GUIDE.md for full details.
