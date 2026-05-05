# Competition Stability Implementation Plan

## Bottom Line

If the competition is close, do not chase full scalability right now. The safe objective is system stability, fast recovery, and graceful degradation.

Recommended scope before the competition:

- Do Tier 0 and Tier 1.
- Do Tier 2 only if Tier 1 is already stable and fully tested.
- Freeze Tier 3 and above until after the competition.

If you ignore that and start platform-level changes now, the most likely outcome is not better scalability. It is an unstable demo.

## What Is Most Likely To Fail First

1. Chatbot and AI requests hanging or slowing down.
2. Database load from dashboard polling and oversized fetches.
3. Queue and mail behavior if background workers are not actually running.
4. ML service not being up when the app expects it.
5. Realtime and polling creating extra traffic during the demo.

## Risk Ladder

| Tier | Risk Level | Description | Do Before Competition? |
| --- | --- | --- | --- |
| Tier 0 | Very Safe | Backups, freeze rules, smoke tests, restart prep, health checks | Yes |
| Tier 1 | Safe | Small isolated hardening changes with low blast radius | Yes |
| Tier 2 | Medium | Useful stability improvements, but only with staged validation | Maybe |
| Tier 3 | Risky | Structural changes that can create regressions or deployment problems | No |
| Tier 4 | Dangerous | Major architectural or platform shifts with high failure risk | No |

## Tier 0: Very Safe, Do Immediately

These are no-regret safeguards. They reduce crash risk without changing core architecture.

### 0.1 Take A Full Recovery Snapshot

- Back up the production or demo database.
- Copy the current `.env` files and startup scripts.
- Record the current known-good commit hash.
- Export a list of current running services and ports.

Why this is safe:

- It does not change runtime behavior.
- It gives you a hard rollback point.

Validation:

- Confirm backup files exist.
- Confirm you can restore to a local or staging copy.

Rollback:

- Not needed. This is a safety action.

### 0.2 Declare A Competition Freeze Rule

- No new features.
- No UI rewrites.
- No database migrations unless they fix an actual crash-level issue.
- No architecture changes that introduce a new dependency unless already proven.

Why this is safe:

- Most competition failures come from last-minute change volume, not lack of ideas.

### 0.3 Prepare A Restart And Recovery Sheet

Document the exact order for restarting:

- Backend
- Frontend
- ML service
- Queue worker
- Realtime service

Relevant files and entry points:

- `start-backend-frontend.bat`
- `start-ml-service.bat`
- `web-backend/scripts/start-production.sh`

Why this is safe:

- If something fails during the demo, recovery speed matters more than elegance.

### 0.4 Run A Critical Smoke Test And Keep It Manual

Verify these flows on the exact environment you will demo:

1. Login
2. Appointment booking
3. Admin dashboard load
4. Cashier dashboard load
5. Payment processing path
6. Chatbot basic reply
7. ML service health

Why this is safe:

- It exposes real breakage before the competition.

### 0.5 Add A Demo Kill-Switch Decision List

Before the competition, decide what you will disable if instability appears:

- Chatbot streaming
- Realtime updates
- Online payment polling
- Non-essential email sending
- Heavy analytics widgets

Why this is safe:

- Graceful degradation is safer than letting the whole app hang.

## Tier 1: Safe Changes To Ship Before The Competition

These are the best pre-competition changes because they are targeted and low blast radius.

### 1.1 Lock Down The Public User-Limit Endpoint

Problem:

- The booking-limit endpoint currently exposes user-specific booking information by user ID.

Touch points:

- `web-backend/routes/api.php`
- `web-backend/app/Http/Controllers/AppointmentSettingsController.php`

Change:

- Allow users to request only their own booking limit.
- Keep full user-ID access for admins only.

Why this is safe:

- Small isolated access-control change.
- Reduces security risk immediately.

Validation:

- Logged-in user can fetch their own data.
- Logged-in user cannot fetch another user's data.
- Admin can still inspect limits if needed.

Rollback:

- Restore previous route/controller condition if something unexpectedly breaks.

### 1.2 Reduce Dashboard Query Size And Polling Pressure

Problem:

- Admin and cashier dashboards fetch large datasets and poll aggressively.

Touch points:

- `web-frontend/src/pages/AdminDashboard.jsx`
- `web-frontend/src/pages/CashierDashboard.jsx`

Change:

- Reduce large list requests such as `limit=1000`.
- Use smaller page sizes.
- Increase polling intervals.
- Prefer manual refresh on non-critical panels.

Why this is safe:

- This directly lowers backend and database load.
- It does not change core business rules.

Validation:

- Dashboard still loads.
- Pagination still works.
- Counts and lists still match expected values.

Rollback:

- Revert the limit and interval values.

### 1.3 Verify That The ML Service Is Always Up

Problem:

- If the ML service is down, AI-related features become unstable or slow.

Touch points:

- `start-ml-service.bat`
- `start-backend-frontend.bat`
- `ml-service/main.py`
- `web-backend/app/Services/MLServiceClient.php`

Change:

- Confirm the ML service starts automatically in the demo environment.
- Confirm `/health` returns success before the app starts using it.
- If reliability is uncertain, make the UI tolerate ML unavailability cleanly.

Why this is safe:

- Operational verification is safer than redesigning the ML stack now.

Validation:

- `http://127.0.0.1:8100/health` or the configured host returns 200.
- Appointment flows still work if the ML service is temporarily unavailable.

Rollback:

- Fall back to non-ML behavior for the competition.

### 1.4 Make Sure Background Work Actually Runs

Problem:

- The app uses queued work, but the production startup path shown in the repo does not clearly run a queue worker.

Touch points:

- `web-backend/config/queue.php`
- `web-backend/scripts/start-production.sh`
- deployment process or service manager outside the repo

Change:

- Ensure a queue worker is running in the actual competition environment.
- If that is not possible, disable non-essential queued features or move only critical actions to a known-working mode.

Why this is safe:

- This is runtime verification, not a large refactor.

Validation:

- Dispatch a test queued job or queued mail and confirm it completes.

Rollback:

- Use the previous worker setup if one already exists.

### 1.5 Tighten Fallback Behavior Instead Of Expanding AI Features

Problem:

- Chatbot and LLM calls are the first likely throughput bottleneck.

Touch points:

- `web-backend/app/Http/Controllers/UnifiedChatbotController.php`
- `web-backend/app/Services/LLMService.php`
- `web-frontend/src/hooks/useChatbot.js`

Change:

- Prefer fast failure and fallback over long waits.
- If there is a feature flag for streaming or advanced RAG behavior, keep it conservative.
- If needed, reduce chatbot prominence during the competition rather than expanding it.

Why this is safe:

- It reduces hang risk.
- It favors a stable response over a perfect response.

Validation:

- Chat still returns a response when providers fail.
- UI does not hang indefinitely.

Rollback:

- Restore previous timeout or feature flag values.

## Tier 2: Medium Risk, Only If Tier 1 Is Stable

These are good changes, but they are not automatically safe right before a competition.

### 2.1 Move Sync Mail Out Of Hot Request Paths

Touch points:

- `web-backend/app/Http/Controllers/AppointmentController.php`
- mailables and queue worker setup

Value:

- Improves request latency and reduces blocking.

Why this is only medium-safe:

- It depends on queue reliability.
- If the worker setup is wrong, notifications silently fail.

Competition rule:

- Do this only if queue workers are already proven stable.

### 2.2 Split Web, Queue, And Realtime Processes

Touch points:

- `web-backend/scripts/start-production.sh`
- deployment configuration

Value:

- Removes a single runtime bottleneck.

Why this is only medium-safe:

- It changes deployment shape.
- Process supervision mistakes can create a new outage.

Competition rule:

- Do this only if you have time to verify every process independently.

### 2.3 Introduce Redis For Cache, Locks, And Queue

Touch points:

- `web-backend/config/cache.php`
- `web-backend/config/queue.php`
- `web-backend/config/database.php`

Value:

- Removes major database pressure.
- Improves lock and queue behavior.

Why this is only medium-safe:

- It adds a new infrastructure dependency.
- Misconfiguration can break sessions, locks, queues, or cache behavior.

Competition rule:

- Only do this before the competition if Redis is already available, familiar to the team, and can be rolled back quickly.

## Tier 3: Risky, Defer Until After The Competition

These are valid engineering improvements, but they are not safe under competition pressure.

### 3.1 Replace In-PHP RAG Search With A Real Vector Store

- Move embedding search out of PHP loops.
- Candidate targets: Qdrant or pgvector.

Why risky now:

- New data model, new indexing flow, new failure modes.

### 3.2 Add Read Replicas For Reporting And Dashboards

- Split reporting reads from primary writes.

Why risky now:

- Replication lag and query-routing mistakes can cause confusing behavior.

### 3.3 Move ML Artifacts And Feedback Off Local Disk

- Stop relying on local `.joblib` and JSONL state as the long-term source of truth.

Why risky now:

- Changes ML persistence and training assumptions.

### 3.4 Refactor The Monolith Into Modules

- Break large controllers and services into domain modules.

Why risky now:

- It is the right long-term move, but it touches too much code at once.

## Tier 4: Dangerous Before The Competition

Do not attempt these now.

### 4.1 Full Microservices Split

- High coordination cost.
- New networking, deployment, and observability problems.

### 4.2 Kubernetes Migration

- Powerful, but the operational overhead is real.
- It does not save a shaky app a week before a competition.

### 4.3 Kafka Or Major Event Platform Introduction

- Useful only when you already have stable service boundaries and real event scale.

### 4.4 Database Migration Or Sharding

- Extremely high blast radius.
- Too dangerous under demo pressure.

## Exact Competition Recommendation

If the competition is within the next few days, this is the safest plan:

### Do Now

1. Backup everything.
2. Freeze new features.
3. Smoke test the full demo flow.
4. Lock down the public user-limit endpoint.
5. Reduce dashboard fetch size and polling frequency.
6. Verify ML service startup and health.
7. Verify queue worker behavior.
8. Decide kill switches for chatbot, realtime, and non-essential email.

### Do Only If Time Remains And Validation Is Strong

1. Queue email out of request paths.
2. Separate worker and realtime processes from the web process.
3. Move cache and locks to Redis only if Redis is already operational and familiar.

### Do Not Touch Before The Competition

1. Microservices.
2. Kubernetes.
3. Vector database migration.
4. Read replicas.
5. Big controller or service rewrites.
6. Database migration or sharding.

## Suggested Execution Timeline

### Today

1. Backup and freeze.
2. Verify startup and restart flow.
3. Verify ML service and queue worker.
4. Fix the exposed booking-limit endpoint.

### Next Working Session

1. Reduce dashboard fetch sizes.
2. Slow or remove non-essential polling.
3. Test the full demo flow again.

### Final 24 Hours Before Competition

1. No more architecture changes.
2. No dependency additions unless they fix an active blocker.
3. Re-run smoke tests.
4. Keep a rollback build ready.
5. Keep kill switches documented and accessible.

## Final Decision Rule

If a change requires one of the following, it is too risky for pre-competition unless there is a hard blocker:

- a new infrastructure dependency
- a deployment topology change
- a data migration
- a broad refactor across multiple core flows
- a change that cannot be rolled back in minutes

The correct short-term strategy is not maximum scalability. It is maximum predictability.