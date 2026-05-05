# Competition Runbook

## Goal

Keep the system stable during the competition.

Do not improvise architecture changes during the event.
If something starts failing, recover fast and reduce load.

## Recovery Snapshot

Before the final freeze, generate a recovery snapshot.

- Run `create-competition-snapshot.bat`
- Output goes to `competition-snapshots/<timestamp>/`
- The snapshot must contain:
	- a verified SQL backup
	- copied environment files
	- copied startup and recovery scripts
	- current git commit and dirty status
	- current process, port, and health-check outputs

If you cannot create this folder successfully, Tier 0 is not complete.

## Startup Order

Use this order if you need to start services manually.

1. ML service
2. Backend API
3. Queue worker
4. Reverb websocket server
5. Scheduler worker
6. Frontend

Primary launcher:

- `start-backend-frontend.bat`

Quick verification script:

- `competition-health-check.bat`

Recovery snapshot command:

- `create-competition-snapshot.bat`

ML-only launcher:

- `start-ml-service.bat`

Production entrypoint:

- `web-backend/scripts/start-production.sh`

## Quick Health Checks

Check these before the demo starts.

### Backend

- Open `http://127.0.0.1:8000/api/health`
- Expected result: success response

### ML Service

- Open `http://127.0.0.1:8100/health`
- Expected result: `{"status":"healthy","service":"ml-service"}`

### Frontend

- Open the frontend URL used by Vite or your deployed frontend
- Confirm login page or landing page loads cleanly

### Queue Worker

- Confirm a queue worker terminal is open if running locally
- Confirm it is using `php artisan queue:work --tries=1 --sleep=3`

### Realtime

- Confirm the Reverb terminal is open if running locally
- If realtime is unstable, the app should still function with slower polling

## Smoke Test Checklist

Run this exact order before the competition starts.

1. Login with a normal user
2. Open client appointments page
3. Load available slots
4. Create one appointment
5. Open admin dashboard
6. Open cashier dashboard
7. Verify chatbot gives one basic response
8. Verify ML health endpoint still responds
9. Verify no terminal is visibly crashing or restarting in a loop

## Kill Switch Order

If the system becomes slow, disable or avoid these in this order.

1. Heavy admin dashboard refreshing
2. Cashier action log live refreshing
3. Chatbot streaming behavior
4. Realtime-dependent behavior
5. Online payment polling if not needed for the demo
6. Non-essential email sending during demo runs

## Fast Recovery Rules

If one part fails, do not restart everything immediately.

### If chatbot is slow

- Stop using it in the demo flow
- Continue core appointment and dashboard flows
- Confirm backend health still passes

### If ML service is down

- Restart only the ML service first
- Recheck `http://127.0.0.1:8100/health`
- Continue demo without ML-dependent explanation features if needed

### If queue-backed actions stop working

- Check queue worker terminal first
- Restart only the queue worker if needed
- Do not change queue configuration during the competition

### If realtime is unstable

- Leave Reverb alone unless clearly dead
- Continue using the app with fallback polling
- Avoid websocket-heavy tabs if they are not required for judging

## Freeze Rules

During the final 24 hours before the competition:

1. No new dependencies
2. No schema migrations unless fixing a critical blocker
3. No refactors of large files
4. No architecture experiments
5. No feature additions

## Minimum Rollback Rule

If a change cannot be rolled back in a few minutes, do not ship it before the competition.

The correct target is predictability, not ambition.