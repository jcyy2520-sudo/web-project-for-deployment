<?php

/*
  Copy of backend API routes for chatbot knowledge bundle.
  Use this file for reference only (contains route definitions and role notes).
*/

// NOTE: This is a documentation copy of routes/web-backend/routes/api.php

// Major route groups included:
// - Public: health, services, frontend error logging (throttled + abuse detection)
// - Tokenized/secure routes: password reset, verify email, share links
// - Protected (auth:sanctum): user, cashier, admin groups
// - Admin routes: prefixed with /admin, require role:admin middleware
// - Chatbot routes: /chatbot (public + protected subroutes) and /chatbot/advanced

// Important auth/role notes:
// - Admin routes often use middleware ['role:admin']
// - Cashier routes use middleware ['role:cashier,staff,admin']
// - Chatbot public endpoints allow guests, while certain actions require 'auth:sanctum'

// See original: web-backend/routes/api.php for full code.
