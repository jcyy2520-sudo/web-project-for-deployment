````markdown
# Advanced NLU System Documentation

## Overview

Your chatbot now includes a **real-time, intelligent Natural Language Understanding (NLU) system** with:

- **Multilingual Support** (English, Filipino/Tagalog, Taglish)
- **Robust NLP** (fuzzy matching, typo tolerance, slang expansion)
- **Content Safety** (real-time filtering, hate speech detection)
- **Smart Intent Recognition** (confidence scoring, disambiguation)
- **Zero Hard-Coding** (dynamic, learning-based)

---

## Architecture

### Three Core Services

#### 1. **AdvancedNLPService** - Text Normalization & Analysis
Location: `app/Services/AdvancedNLPService.php`

**Features:**
- Fuzzy text matching using Levenshtein distance algorithm
- Spell correction (learns from common misspellings)
- Slang and abbreviation expansion (real-time database)
- Incomplete word/sentence handling
- Taglish-specific normalization
- Language detection (English/Filipino/Mixed with confidence scores)
- Real-time caching for performance

**Key Methods:**
```php
$nlp = app(AdvancedNLPService::class);

// Detect language
$language = $nlp->detectLanguage("paano mag book appointment?");
// Output: ['language' => 'mixed', 'confidence' => 0.82, ...]
```

... (omitted in file for brevity)

````
