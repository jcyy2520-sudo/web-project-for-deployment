# Landing Page CMS Implementation Guide

Complete step-by-step guide to implement the Landing Page CMS.

---

## Step 0: Run the Setup Script

```powershell
# Run in PowerShell as Administrator in your project root
.\SETUP_LANDING_CMS.ps1
```

This creates the migration, model, and controller scaffolds automatically.

---

## Step 1: Update Migration File

**File:** `web-backend/database/migrations/[DATE]_create_landing_page_content_table.php`

Replace the entire `up()` function with:

```php
public function up(): void
{
    Schema::create('landing_page_content', function (Blueprint $table) {
        $table->id();
        $table->string('section')->unique(); // hero, features, process_steps
        $table->longText('content')->nullable(); // JSON content
        $table->timestamps();
        $table->index('section');
    });
}
```

---

## Step 2: Update Model

**File:** `web-backend/app/Models/LandingPageContent.php`

Replace entire content with:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingPageContent extends Model
{
    protected $table = 'landing_page_content';
    
    protected $fillable = ['section', 'content'];
    
    protected $casts = [
        'content' => 'array',
    ];
    
    // Shortcut to get or create a section
    public static function getSection($section, $default = [])
    {
        $record = self::where('section', $section)->first();
        
        if (!$record) {
            $record = self::create([
                'section' => $section,
                'content' => $default
            ]);
        }
        
        return $record->content ?? $default;
    }
    
    // Shortcut to update a section
    public static function updateSection($section, $content)
    {
        return self::updateOrCreate(
            ['section' => $section],
            ['content' => $content]
        );
    }
}
```

---

## Step 3: Update Controller

**File:** `web-backend/app/Http/Controllers/LandingPageContentController.php`

Replace entire content with:

```php
<?php

namespace App\Http\Controllers;

use App\Models\LandingPageContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LandingPageContentController extends Controller
{
    // Get all landing page content
    public function index()
    {
        try {
            $sections = ['hero', 'features', 'process_steps'];
            $content = [];
            
            foreach ($sections as $section) {
                $content[$section] = LandingPageContent::getSection($section, $this->getDefault($section));
            }
            
            return response()->json([
                'status' => 'success',
                'data' => $content
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch landing page content', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch content'
            ], 500);
        }
    }
    
    // Get specific section
    public function show($section)
    {
        try {
            $content = LandingPageContent::getSection($section, $this->getDefault($section));
            
            return response()->json([
                'status' => 'success',
                'section' => $section,
                'data' => $content
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch landing page section', ['section' => $section, 'error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Section not found'
            ], 404);
        }
    }
    
    // Update a section
    public function update(Request $request, $section)
    {
        try {
            $this->authorize('isAdmin');
            
            $validated = $request->validate([
                'content' => 'required|array'
            ]);
            
            LandingPageContent::updateSection($section, $validated['content']);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Section updated successfully',
                'data' => $validated['content']
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update landing page section', ['section' => $section, 'error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update section'
            ], 500);
        }
    }
    
    // Get default content for new sections
    private function getDefault($section)
    {
        $defaults = [
            'hero' => [
                'headline' => 'Notarization Made Easy',
                'subtitle' => 'Professional notary services at your fingertips. Book, meet, and get notarized instantly.',
                'cta_text' => 'Get Started Today',
                'background_image' => null
            ],
            'features' => [
                [
                    'id' => 1,
                    'title' => 'Instant Booking',
                    'description' => 'Schedule appointments in seconds with our intuitive booking system',
                    'icon' => '⏱️'
                ],
                [
                    'id' => 2,
                    'title' => 'Document Security',
                    'description' => 'Military-grade encryption for all your sensitive legal documents',
                    'icon' => '🛡️'
                ],
                [
                    'id' => 3,
                    'title' => 'Real-time Tracking',
                    'description' => 'Track your appointment status and receive live updates',
                    'icon' => '📱'
                ],
                [
                    'id' => 4,
                    'title' => 'Available Always',
                    'description' => 'Book and manage appointments anytime, anywhere',
                    'icon' => '🌙'
                ]
            ],
            'process_steps' => [
                [
                    'id' => 1,
                    'step' => '01',
                    'title' => 'Register Account',
                    'description' => 'Create your secure account in under 2 minutes'
                ],
                [
                    'id' => 2,
                    'step' => '02',
                    'title' => 'Verify Identity',
                    'description' => 'Complete quick identity verification process'
                ],
                [
                    'id' => 3,
                    'step' => '03',
                    'title' => 'Book Appointment',
                    'description' => 'Choose your preferred date and time slot'
                ],
                [
                    'id' => 4,
                    'step' => '04',
                    'title' => 'Get Notarized',
                    'description' => 'Complete your notarization seamlessly'
                ]
            ]
        ];
        
        return $defaults[$section] ?? [];
    }
}
```

---

## Step 4: Update Routes

**File:** `web-backend/routes/api.php`

Add this route group (find the section around line 650 where announcements are, add after that):

```php
// Landing Page Content Management
Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('landing')->group(function () {
        Route::get('/', [LandingPageContentController::class, 'index']);
        Route::get('/{section}', [LandingPageContentController::class, 'show']);
        Route::put('/{section}', [LandingPageContentController::class, 'update'])->middleware('admin');
    });
});

// Public endpoint to fetch landing page content (no auth required)
Route::get('/landing-public', [LandingPageContentController::class, 'index']);
```

Also add the import at the top:

```php
use App\Http\Controllers\LandingPageContentController;
```

---

## Step 5: Create React Admin Component

**File:** `web-frontend/src/components/admin/AdminLandingPageSettings.jsx`

Create new file with content:

```jsx
import { useState, useEffect, useCallback } from 'react';
import axios from 'axios';
import {
  PencilIcon,
  PlusIcon,
  TrashIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  XMarkIcon,
  SparklesIcon
} from '@heroicons/react/24/outline';

const AdminLandingPageSettings = ({ isDarkMode }) => {
  const [activeTab, setActiveTab] = useState('hero');
  const [content, setContent] = useState({});
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [successMessage, setSuccessMessage] = useState('');
  const [errorMessage, setErrorMessage] = useState('');
  const [editingItem, setEditingItem] = useState(null);

  // Fetch content
  const fetchContent = useCallback(async () => {
    setLoading(true);
    try {
      const response = await axios.get('/api/landing');
      setContent(response.data.data || {});
    } catch (error) {
      console.error('Failed to fetch landing content', error);
      setErrorMessage('Failed to load content');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchContent();
  }, [fetchContent]);

  // Save section
  const saveSection = async (section, data) => {
    setSaving(true);
    try {
      await axios.put(`/api/landing/${section}`, { content: data });
      setSuccessMessage(`${section} updated successfully!`);
      setTimeout(() => setSuccessMessage(''), 3000);
      setEditingItem(null);
      fetchContent();
    } catch (error) {
      console.error('Failed to save content', error);
      setErrorMessage('Failed to save changes');
      setTimeout(() => setErrorMessage(''), 3000);
    } finally {
      setSaving(false);
    }
  };

  const handleHeroChange = (field, value) => {
    setEditingItem({
      ...editingItem,
      [field]: value
    });
  };

  const handleFeatureChange = (index, field, value) => {
    const updated = [...(editingItem || content.features || [])];
    updated[index] = { ...updated[index], [field]: value };
    setEditingItem(updated);
  };

  const handleStepChange = (index, field, value) => {
    const updated = [...(editingItem || content.process_steps || [])];
    updated[index] = { ...updated[index], [field]: value };
    setEditingItem(updated);
  };

  const addFeature = () => {
    const newFeature = {
      id: Math.max(...(content.features || []).map(f => f.id || 0), 0) + 1,
      title: 'New Feature',
      description: 'Feature description',
      icon: '✨'
    };
    const updated = [...(editingItem || content.features || []), newFeature];
    setEditingItem(updated);
  };

  const deleteFeature = (index) => {
    const updated = (editingItem || content.features || []).filter((_, i) => i !== index);
    setEditingItem(updated);
  };

  const renderHeroEditor = () => {
    const heroData = editingItem || content.hero || {};

    return (
      <div className={`space-y-6 p-6 rounded-lg border ${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-gray-200'}`}>
        <h3 className={`text-lg font-semibold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>
          <SparklesIcon className="inline h-5 w-5 mr-2" />
          Hero Section
        </h3>

        <div>
          <label className={`block text-sm font-medium mb-2 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
            Headline
          </label>
          <input
            type="text"
            value={heroData.headline || ''}
            onChange={(e) => handleHeroChange('headline', e.target.value)}
            className={`w-full px-4 py-2 rounded-lg border ${isDarkMode ? 'bg-gray-800 border-gray-700 text-white' : 'bg-white border-gray-300 text-gray-900'}`}
            placeholder="Enter headline"
          />
        </div>

        <div>
          <label className={`block text-sm font-medium mb-2 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
            Subtitle
          </label>
          <textarea
            value={heroData.subtitle || ''}
            onChange={(e) => handleHeroChange('subtitle', e.target.value)}
            className={`w-full px-4 py-2 rounded-lg border h-24 ${isDarkMode ? 'bg-gray-800 border-gray-700 text-white' : 'bg-white border-gray-300 text-gray-900'}`}
            placeholder="Enter subtitle"
          />
        </div>

        <div>
          <label className={`block text-sm font-medium mb-2 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
            CTA Button Text
          </label>
          <input
            type="text"
            value={heroData.cta_text || ''}
            onChange={(e) => handleHeroChange('cta_text', e.target.value)}
            className={`w-full px-4 py-2 rounded-lg border ${isDarkMode ? 'bg-gray-800 border-gray-700 text-white' : 'bg-white border-gray-300 text-gray-900'}`}
            placeholder="e.g., Get Started Today"
          />
        </div>

        <div className="flex gap-3">
          <button
            onClick={() => saveSection('hero', editingItem || heroData)}
            disabled={saving}
            className="px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 disabled:opacity-50"
          >
            {saving ? 'Saving...' : 'Save Hero Section'}
          </button>
          <button
            onClick={() => setEditingItem(null)}
            className={`px-4 py-2 rounded-lg border ${isDarkMode ? 'border-gray-600 text-gray-300 hover:bg-gray-800' : 'border-gray-300 text-gray-600 hover:bg-gray-100'}`}
          >
            Cancel
          </button>
        </div>
      </div>
    );
  };

  const renderFeaturesEditor = () => {
    const featuresData = editingItem || content.features || [];

    return (
      <div className={`space-y-6 p-6 rounded-lg border ${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-gray-200'}`}>
        <div className="flex justify-between items-center">
          <h3 className={`text-lg font-semibold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>
            Features Section
          </h3>
          <button
            onClick={addFeature}
            className="px-3 py-1 bg-amber-600 text-white rounded-lg hover:bg-amber-700 flex items-center gap-1 text-sm"
          >
            <PlusIcon className="h-4 w-4" />
            Add Feature
          </button>
        </div>

        <div className="space-y-4">
          {featuresData.map((feature, idx) => (
            <div
              key={feature.id || idx}
              className={`p-4 rounded-lg border ${isDarkMode ? 'bg-gray-800/50 border-gray-700' : 'bg-gray-50 border-gray-200'}`}
            >
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className={`block text-sm font-medium mb-1 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
                    Icon
                  </label>
                  <input
                    type="text"
                    value={feature.icon || ''}
                    onChange={(e) => handleFeatureChange(idx, 'icon', e.target.value)}
                    className={`w-full px-3 py-1 rounded-lg border text-center text-2xl ${isDarkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900'}`}
                    maxLength="2"
                    placeholder="🎯"
                  />
                </div>
                <div>
                  <label className={`block text-sm font-medium mb-1 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
                    Title
                  </label>
                  <input
                    type="text"
                    value={feature.title || ''}
                    onChange={(e) => handleFeatureChange(idx, 'title', e.target.value)}
                    className={`w-full px-3 py-1 rounded-lg border ${isDarkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900'}`}
                  />
                </div>
              </div>
              <div className="mt-3">
                <label className={`block text-sm font-medium mb-1 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
                  Description
                </label>
                <textarea
                  value={feature.description || ''}
                  onChange={(e) => handleFeatureChange(idx, 'description', e.target.value)}
                  className={`w-full px-3 py-1 rounded-lg border h-16 ${isDarkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900'}`}
                />
              </div>
              <button
                onClick={() => deleteFeature(idx)}
                className="mt-3 px-3 py-1 bg-red-600 text-white rounded-lg hover:bg-red-700 flex items-center gap-1 text-sm"
              >
                <TrashIcon className="h-4 w-4" />
                Delete
              </button>
            </div>
          ))}
        </div>

        <div className="flex gap-3">
          <button
            onClick={() => saveSection('features', editingItem || featuresData)}
            disabled={saving}
            className="px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 disabled:opacity-50"
          >
            {saving ? 'Saving...' : 'Save Features'}
          </button>
          <button
            onClick={() => setEditingItem(null)}
            className={`px-4 py-2 rounded-lg border ${isDarkMode ? 'border-gray-600 text-gray-300 hover:bg-gray-800' : 'border-gray-300 text-gray-600 hover:bg-gray-100'}`}
          >
            Cancel
          </button>
        </div>
      </div>
    );
  };

  const renderStepsEditor = () => {
    const stepsData = editingItem || content.process_steps || [];

    return (
      <div className={`space-y-6 p-6 rounded-lg border ${isDarkMode ? 'bg-gray-900 border-amber-500/20' : 'bg-white border-gray-200'}`}>
        <h3 className={`text-lg font-semibold ${isDarkMode ? 'text-amber-50' : 'text-gray-900'}`}>
          Process Steps
        </h3>

        <div className="space-y-4">
          {stepsData.map((step, idx) => (
            <div
              key={step.id || idx}
              className={`p-4 rounded-lg border ${isDarkMode ? 'bg-gray-800/50 border-gray-700' : 'bg-gray-50 border-gray-200'}`}
            >
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className={`block text-sm font-medium mb-1 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
                    Step Number
                  </label>
                  <input
                    type="text"
                    value={step.step || ''}
                    onChange={(e) => handleStepChange(idx, 'step', e.target.value)}
                    className={`w-full px-3 py-1 rounded-lg border text-center ${isDarkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900'}`}
                    placeholder="01"
                  />
                </div>
                <div>
                  <label className={`block text-sm font-medium mb-1 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
                    Title
                  </label>
                  <input
                    type="text"
                    value={step.title || ''}
                    onChange={(e) => handleStepChange(idx, 'title', e.target.value)}
                    className={`w-full px-3 py-1 rounded-lg border ${isDarkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900'}`}
                  />
                </div>
              </div>
              <div className="mt-3">
                <label className={`block text-sm font-medium mb-1 ${isDarkMode ? 'text-gray-300' : 'text-gray-700'}`}>
                  Description
                </label>
                <textarea
                  value={step.description || ''}
                  onChange={(e) => handleStepChange(idx, 'description', e.target.value)}
                  className={`w-full px-3 py-1 rounded-lg border h-16 ${isDarkMode ? 'bg-gray-700 border-gray-600 text-white' : 'bg-white border-gray-300 text-gray-900'}`}
                />
              </div>
            </div>
          ))}
        </div>

        <div className="flex gap-3">
          <button
            onClick={() => saveSection('process_steps', editingItem || stepsData)}
            disabled={saving}
            className="px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 disabled:opacity-50"
          >
            {saving ? 'Saving...' : 'Save Steps'}
          </button>
          <button
            onClick={() => setEditingItem(null)}
            className={`px-4 py-2 rounded-lg border ${isDarkMode ? 'border-gray-600 text-gray-300 hover:bg-gray-800' : 'border-gray-300 text-gray-600 hover:bg-gray-100'}`}
          >
            Cancel
          </button>
        </div>
      </div>
    );
  };

  if (loading) {
    return <div className={`p-8 text-center ${isDarkMode ? 'text-gray-400' : 'text-gray-500'}`}>Loading...</div>;
  }

  return (
    <div className="space-y-6">
      {successMessage && (
        <div className="p-4 bg-green-500/20 text-green-400 rounded-lg flex items-center gap-2 border border-green-500/30">
          <CheckCircleIcon className="h-5 w-5" />
          {successMessage}
        </div>
      )}

      {errorMessage && (
        <div className="p-4 bg-red-500/20 text-red-400 rounded-lg flex items-center gap-2 border border-red-500/30">
          <ExclamationTriangleIcon className="h-5 w-5" />
          {errorMessage}
        </div>
      )}

      <div className="flex gap-4 border-b" style={{ borderColor: isDarkMode ? 'rgba(217, 119, 6, 0.1)' : 'rgba(59, 130, 246, 0.2)' }}>
        {['hero', 'features', 'process_steps'].map((tab) => (
          <button
            key={tab}
            onClick={() => { setActiveTab(tab); setEditingItem(null); }}
            className={`px-4 py-3 font-medium transition-colors ${
              activeTab === tab
                ? isDarkMode
                  ? 'text-amber-400 border-b-2 border-amber-400'
                  : 'text-blue-600 border-b-2 border-blue-600'
                : isDarkMode
                ? 'text-gray-400 hover:text-amber-300'
                : 'text-gray-600 hover:text-blue-600'
            }`}
          >
            {tab === 'hero' && 'Hero Section'}
            {tab === 'features' && 'Features'}
            {tab === 'process_steps' && 'Process Steps'}
          </button>
        ))}
      </div>

      {activeTab === 'hero' && renderHeroEditor()}
      {activeTab === 'features' && renderFeaturesEditor()}
      {activeTab === 'process_steps' && renderStepsEditor()}
    </div>
  );
};

export default AdminLandingPageSettings;
```

---

## Step 6: Run Migrations

```powershell
cd web-backend
php artisan migrate
```

---

## Step 7: Update Admin Dashboard

**File:** `web-frontend/src/pages/AdminDashboard.jsx`

Add import at the top:

```jsx
import AdminLandingPageSettings from '../components/admin/AdminLandingPageSettings';
```

Find the section where tabs/navigation is defined (around line 200-300) and add:

```jsx
case 'landing-settings':
  return <AdminLandingPageSettings isDarkMode={isDarkMode} />;
```

Add navigation button near the settings icon (around line 550-600):

```jsx
<button
  onClick={() => setActiveTab('landing-settings')}
  className="flex items-center gap-2 px-4 py-2 rounded-lg hover:bg-gray-800 transition-all"
>
  <SparklesIcon className="h-5 w-5" />
  Landing Page Settings
</button>
```

Also import SparklesIcon at the top:

```jsx
import { SparklesIcon } from '@heroicons/react/24/outline';
```

---

## Step 8: Update LandingPage Frontend

**File:** `web-frontend/src/pages/LandingPage.jsx`

Modify the `useEffect` that fetches services (around line 130-150):

```jsx
useEffect(() => {
  const fetchServicesAndTestimonials = async () => {
    try {
      // Fetch all landing content
      const contentResponse = await axios.get('/api/landing-public', { timeout: 3000 });
      const landingContent = contentResponse.data?.data || {};
      
      // Use API content or fallback to defaults
      if (landingContent.features && Array.isArray(landingContent.features)) {
        setServices(landingContent.features);
      }
      
      // ... rest of testimonials code
    } catch (err) {
      logger.debug('Landing content API unavailable, using defaults');
    }
  };

  fetchServicesAndTestimonials();
}, []);
```

Replace hardcoded process steps with API data (around line 330-345):

```jsx
const processSteps = (content.process_steps || [
  {
    step: "01",
    title: "Register Account",
    description: "Create your secure account in under 2 minutes"
  },
  // ... other defaults
]);
```

---

## Done!

You now have a fully functional Landing Page CMS. 

### To Use:
1. Go to Admin Dashboard
2. Click "Landing Page Settings"
3. Choose section (Hero, Features, Process Steps)
4. Edit content
5. Click Save
6. Changes appear instantly on landing page

### API Endpoints Available:
- `GET /api/landing` - Get all sections (requires auth)
- `GET /api/landing-public` - Get all sections (public, no auth)
- `GET /api/landing/{section}` - Get one section
- `PUT /api/landing/{section}` - Update one section (admin only)

