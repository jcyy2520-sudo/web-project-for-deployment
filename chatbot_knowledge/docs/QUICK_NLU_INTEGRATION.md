````markdown
# Quick Integration Guide - Advanced NLU System

## Overview
This guide shows you how to integrate the new Advanced NLU System into your existing chatbot endpoints.

---

## Step 1: Inject Services into Your Controller

Update your `ChatbotController.php`:

```php
use App\Services\AdvancedNLPService;
use App\Services\AdvancedContentModerationService;
use App\Services\SmartIntentRecognitionService;
use App\Services\AdvancedNLUPipelineService;

class ChatbotController extends Controller
{
    private AdvancedNLUPipelineService $nluPipeline;
    
    public function __construct(
        // ... existing services
        AdvancedNLUPipelineService $nluPipeline
    ) {
        // ... existing assignments
        $this->nluPipeline = $nluPipeline;
    }
}
```

---

... (omitted in file for brevity)

````
