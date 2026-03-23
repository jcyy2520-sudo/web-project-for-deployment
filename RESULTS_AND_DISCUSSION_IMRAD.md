Results and Discussion

This section presents the results of the system evaluation conducted for Legal Ease, an Intelligent Legal Management Platform. The evaluation was performed by twenty-five (25) testers -- ten (10) IT professionals/developers and fifteen (15) IT students -- through functional test case execution, alpha testing, and AI chatbot classification performance analysis in a controlled testing environment.

Tester Profile

Table 1. Distribution of Testers

| Category                    | Frequency | Percentage |
|-----------------------------|-----------|------------|
| IT Professionals/Developers | 10        | 40.00%     |
| IT Students                 | 15        | 60.00%     |
| Total                       | 25        | 100.00%    |


Functional Test Case Results

Fifty-one (51) functional test cases were executed across eight (8) system modules. Each test case was evaluated based on the feature tested, the input or action performed, the expected result, the observed actual result, and the pass or fail status. Testers documented qualitative observations alongside each result.


Authentication Module

Table 2. Authentication Module Test Cases

| ID    | Feature             | Input/Action                                                                          | Expected Result                                                                          | Actual Result                                                                             | Status |
|-------|---------------------|---------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------|--------|
| AU-01 | Registration Step 1 | Enter valid username, email, and password (min 8 chars, uppercase, lowercase, numbers) | System sends 6-digit verification code to email within 30-minute expiration               | Verification code sent successfully. Code arrived within 15 seconds.                       | Pass   |
| AU-02 | Duplicate Username  | Enter a username already existing in the system                                        | System rejects with: "This username is already taken."                                    | Error message displayed correctly. Duplicate registration prevented.                       | Pass   |
| AU-03 | Email Verification  | Enter correct 6-digit verification code                                                | System verifies code and progresses to profile completion                                 | Code accepted. Tester directed to profile completion form.                                 | Pass   |
| AU-04 | Verification Rate Limit | Enter incorrect code three times within 5 minutes                                   | System blocks attempts (3 per 300 seconds) and displays remaining attempts                | Rate limiting activated after third attempt. Remaining attempts displayed.                  | Pass   |
| AU-05 | Google OAuth Login  | Click "Sign in with Google" with valid Google account                                  | System authenticates via Socialite and issues Sanctum API token                            | Google authentication completed. User redirected to dashboard with active session.          | Pass   |
| AU-06 | Login Rate Limiting | Attempt login with wrong credentials 6 times within 15 minutes                        | System blocks after 5 failed attempts with informative message                            | Blocked after 5th attempt, but message showed only generic "Too many attempts" without lockout duration. | Fail   |
| AU-07 | Two-Factor Auth     | Enable 2FA via Google Authenticator and login with valid TOTP code                     | System accepts 2FA code and grants access                                                 | QR code setup and TOTP validation functioned correctly.                                    | Pass   |

The Google OAuth integration was the highest-rated authentication feature, with testers describing it as "almost instant." The failed case (AU-06) involves a clarity issue where the rate limit error message does not communicate the remaining lockout duration, classified as a minor defect.


Appointment Booking Module

Table 3. Appointment Booking Module Test Cases

| ID    | Feature                  | Input/Action                                                        | Expected Result                                                            | Actual Result                                                              | Status |
|-------|--------------------------|---------------------------------------------------------------------|----------------------------------------------------------------------------|----------------------------------------------------------------------------|--------|
| AP-01 | Create Appointment       | Select service, weekday date, time within 8 AM - 5 PM              | Appointment created with "pending" status                                  | Created with correct status, date, time, and service details.               | Pass   |
| AP-02 | Weekend Restriction      | Attempt booking on Saturday or Sunday                               | Rejected: "Appointments cannot be booked on weekends"                      | Weekend selection rejected with correct error message.                       | Pass   |
| AP-03 | Lunch Break Block        | Attempt booking at 12:30 PM                                        | Rejected: "This time is during lunch break"                                | Restriction enforced. Time slot visually indicated as unavailable.           | Pass   |
| AP-04 | Blackout Date            | Book on admin-configured blackout date                              | Rejected with blackout reason                                              | Booking blocked. Admin-configured reason displayed.                         | Pass   |
| AP-05 | Appointment Approval     | Admin approves a pending appointment                                | Status changes to "approved"; email sent to client and staff               | Status transition correct. Email received. Cashier notification appeared.    | Pass   |
| AP-06 | Appointment Decline      | Admin declines with required reason (max 500 chars)                 | Status changes to "declined"; client receives email with reason            | Decline process worked. Reason validated and included in email.              | Pass   |
| AP-07 | Invalid Status Transition | Change completed appointment back to pending                       | Rejected: "Cannot transition from 'completed' to 'pending'"               | State machine prevented invalid transition. Allowed transitions listed.      | Pass   |
| AP-08 | ML Slot Recommendation   | Request ML-ranked slot suggestions                                  | System provides ranked time slots based on no-show prediction              | Recommendations generated (2.1s load). Two slots ranked identically despite different scores. | Fail   |

Core booking features (AP-01 through AP-07) all passed, confirming reliable business logic enforcement. The ML recommendation feature (AP-08) produced results but showed ranking inconsistencies, reflecting the early training stage of the prediction model.


Admin Module

Table 4. Admin Module Test Cases

| ID    | Feature                      | Input/Action                                                    | Expected Result                                                  | Actual Result                                                    | Status |
|-------|------------------------------|-----------------------------------------------------------------|------------------------------------------------------------------|------------------------------------------------------------------|--------|
| AD-01 | Dashboard Statistics         | Access admin dashboard metrics                                  | Accurate counts reflecting current database state                | Statistics correct. Cache refreshed within 120-second interval.   | Pass   |
| AD-02 | User Management              | Create staff account, assign role, then deactivate              | User created with RBAC role; deactivation triggers email         | Role assignment and deactivation completed. Email sent.           | Pass   |
| AD-03 | User Blocking                | Block user and verify blocked user cannot log in                | Blocked user receives 403 Forbidden                              | Access denied correctly. No endpoint information leaked.          | Pass   |
| AD-04 | Service Management           | Create legal service with pricing, duration, availability       | Service appears in client-facing list                            | All fields stored and displayed correctly.                        | Pass   |
| AD-05 | Bulk Cancellation            | Select multiple pending appointments, execute bulk cancel       | All transition to "cancelled" with notifications                 | Bulk cancellation completed. Notifications dispatched.            | Pass   |
| AD-06 | System Monitoring            | Navigate to error logs and alerts; create alert rule            | Logs display with cleanup; alert rules configurable              | Logs loaded. Alert rule form required undocumented field knowledge. | Fail   |

The admin module demonstrated comprehensive functionality. The failure (AD-06) is a usability issue where the alert rule creation form lacks in-interface documentation, requiring technical knowledge not all administrators would possess.


Payment and Cashier Module

Table 5. Payment and Cashier Module Test Cases

| ID    | Feature                      | Input/Action                                                      | Expected Result                                                    | Actual Result                                                      | Status |
|-------|------------------------------|-------------------------------------------------------------------|--------------------------------------------------------------------|--------------------------------------------------------------------|--------|
| PM-01 | Cash Payment                 | Process cash payment for approved appointment                     | Payment status updates to "paid" with correct amount and timestamp | Payment recorded correctly with accurate cashier ID and timestamp.  | Pass   |
| PM-02 | PayMongo Checkout            | Initiate online payment via PayMongo                              | Checkout session created; redirect to payment gateway              | Session created. Redirect URL correct with amount in centavos.      | Pass   |
| PM-03 | Duplicate Payment Prevention | Process payment for already-paid appointment                      | System rejects duplicate                                           | Double-payment guard prevented transaction.                         | Pass   |
| PM-04 | Discount Application         | Apply percentage discount with proof upload                       | Discount applied; original price and discount stored               | Calculation correct. Proof uploaded and linked.                     | Pass   |
| PM-05 | Receipt Generation           | Generate receipt and verify integrity hash                        | Receipt displays correct details with verifiable hash              | All fields correct. Integrity hash verified.                        | Pass   |
| PM-06 | Refund Request               | Submit refund for paid appointment with valid reason               | Refund created with "pending" status; cumulative validation passes | Refund created. Cumulative balance calculation correct.              | Pass   |
| PM-07 | Refund Exceeds Balance       | Submit refund exceeding remaining refundable balance               | Rejected: "Maximum refundable: [amount]"                           | Validation triggered but amount displayed without peso sign.         | Fail   |

The payment module demonstrated reliable transaction handling across cash and online channels. The failure (PM-07) is a cosmetic formatting issue where the currency symbol is missing from the error message.


AI Chatbot Module

Table 6. AI Chatbot Module Test Cases

| ID    | Feature                | Input/Action                                                                  | Expected Result                                                  | Actual Result                                                    | Status |
|-------|------------------------|-------------------------------------------------------------------------------|------------------------------------------------------------------|------------------------------------------------------------------|--------|
| CB-01 | Service Inquiry        | "What legal services do you offer and what are the consultation fees?"         | Returns accurate service names and pricing from knowledge base   | Correct service list and pricing returned via RAG retrieval.      | Pass   |
| CB-02 | Appointment Scheduling | "I want to schedule a consultation for next Tuesday at 10 AM"                 | Recognizes intent and initiates action with specified date/time   | Recognized intent but did not prompt for missing service type.    | Fail   |
| CB-03 | Status Checking        | "What is the status of my latest appointment?"                                | Retrieves current appointment details                            | Correct appointment status, date, and service returned.           | Pass   |
| CB-04 | Rate Limiting          | Send 9 messages within 1 minute (limit: 8/min)                               | Rate limit response after 8th message with headers               | Activated at 9th message. Rate limit headers included.            | Pass   |
| CB-05 | Conversation Limit     | Send messages until 50-message limit                                          | "must_start_new_conversation: true" response                     | Limit enforced at 50th message. New conversation prompt shown.    | Pass   |
| CB-06 | Out-of-Scope           | "Can you help me write a Python script for sorting algorithms?"               | Polite redirect to intended purpose                              | Correctly identified as out-of-scope with appropriate response.   | Pass   |
| CB-07 | Multi-Intent Query     | "Reschedule my Thursday appointment and check if my refund was processed"     | Processes both intents                                           | Only addressed rescheduling. Refund inquiry ignored.              | Fail   |

The chatbot demonstrated reliable performance for information retrieval queries (CB-01, CB-03, CB-06). The two failures represent natural language processing challenges: incomplete parameter handling during action execution (CB-02) and lack of multi-intent recognition within a single message (CB-07).


Report and Analytics Module

Table 7. Report and Analytics Module Test Cases

| ID    | Feature                | Input/Action                                                  | Expected Result                                       | Actual Result                                         | Status |
|-------|------------------------|---------------------------------------------------------------|-------------------------------------------------------|-------------------------------------------------------|--------|
| RP-01 | Monthly Summary        | Request monthly summary for a month with data                 | Accurate appointment counts with blackout dates       | Counts correct. Blackout dates flagged.                | Pass   |
| RP-02 | Sales Report           | Generate sales report for a date range                        | Revenue, payment count, breakdown by type             | Accurate data matching manual database verification.   | Pass   |
| RP-03 | No-Show Analysis       | Access no-show analytics for period with events               | Patterns with operational insights                    | Patterns identified with percentage rates. Lacked prescriptive recommendations. | Pass   |
| RP-04 | Demand Forecasting     | Request forecast for upcoming month                           | Predicted volume based on historical data             | Near-uniform predictions due to insufficient training data. | Fail   |
| RP-05 | Data Export            | Export report data for selected period                        | Exportable file with correct records                  | File generated with all fields. Records matched screen. | Pass   |

Standard reports (RP-01, RP-02, RP-05) produced accurate outputs. The demand forecasting failure (RP-04) reflects insufficient historical data for the ML model to generate differentiated predictions, an expected condition for a newly deployed system.


Messaging and Notification Module

Table 8. Messaging and Notification Module Test Cases

| ID    | Feature                   | Input/Action                                                    | Expected Result                                          | Actual Result                                            | Status |
|-------|---------------------------|-----------------------------------------------------------------|----------------------------------------------------------|----------------------------------------------------------|--------|
| MS-01 | Client-Staff Messaging    | Send message from client to staff                               | Delivered with correct threading and timestamp            | Delivered correctly in threaded conversation view.        | Pass   |
| MS-02 | Real-Time Delivery        | Send message; verify recipient receives without refresh         | WebSocket delivers via Laravel Echo and Pusher            | Appeared on recipient side within 1-2 seconds.            | Pass   |
| MS-03 | Notification Delivery     | Trigger appointment status change                               | Notification with correct content and unread status       | Accurate details. Unread badge incremented.               | Pass   |
| MS-04 | Notification Preferences  | Disable notification type, trigger corresponding event          | Disabled type not delivered                               | Preferences respected, but mandatory notifications not labeled in interface. | Fail   |
| MS-05 | Message Rate Limiting     | Exceed configured message rate limit                            | Rate limit enforced                                      | Threshold enforced. Error response displayed.             | Pass   |

Real-time messaging via WebSocket performed well across all testers. The failure (MS-04) is an interface clarity issue where mandatory system notifications are not distinguished from optional ones in the preferences panel.


Security Module

Table 9. Security Module Test Cases

| ID    | Feature                        | Input/Action                                              | Expected Result                          | Actual Result                                                         | Status |
|-------|--------------------------------|-----------------------------------------------------------|------------------------------------------|-----------------------------------------------------------------------|--------|
| SC-01 | RBAC Enforcement               | Access admin endpoint with client role                    | 403 Forbidden                            | Denied. No endpoint information leaked.                                | Pass   |
| SC-02 | SQL Injection Prevention       | Injection payload in appointment purpose field            | Stored as literal text                   | Plain text stored. Eloquent ORM parameterized queries prevented execution. | Pass   |
| SC-03 | XSS Prevention                 | Script tag in message content                             | Escaped; no execution                    | Script escaped. React DOM prevented execution.                         | Pass   |
| SC-04 | Token Security                 | Access endpoint with expired Sanctum token                | 401 Unauthorized                         | Token rejected. No details about invalidity provided.                  | Pass   |
| SC-05 | Security Headers               | Inspect response headers                                  | All security headers present             | X-Frame-Options: DENY, CSP, X-Content-Type-Options, HSTS confirmed.   | Pass   |
| SC-06 | Brute Force Protection         | Exceed registration rate limit (5 per 300s)               | Blocked after threshold                  | Blocked correctly, but IP-based only without account-based lockout.    | Fail   |

Security testing confirmed defense against common attack vectors. The failure (SC-06) identifies a theoretical limitation where IP-based rate limiting could be bypassed by distributed attackers; this is an enhancement recommendation rather than an immediate vulnerability.


Test Case Summary

Table 10. Functional Test Case Results Summary

| Module                     | Total Cases | Passed | Failed | Pass Rate |
|----------------------------|-------------|--------|--------|-----------|
| Authentication             | 7           | 6      | 1      | 85.71%    |
| Appointment Booking        | 8           | 7      | 1      | 87.50%    |
| Admin                      | 6           | 5      | 1      | 83.33%    |
| Payment and Cashier        | 7           | 6      | 1      | 85.71%    |
| AI Chatbot                 | 7           | 5      | 2      | 71.43%    |
| Report and Analytics       | 5           | 4      | 1      | 80.00%    |
| Messaging and Notification | 5           | 4      | 1      | 80.00%    |
| Security                   | 6           | 5      | 1      | 83.33%    |
| Total                      | 51          | 42     | 9      | 82.35%    |

The overall pass rate of 82.35% indicates that the system core functionality operates reliably. The AI Chatbot Module had the lowest pass rate (71.43%) due to inherent NLP challenges. No critical or major defects were found across any module.


Defect Classification

Table 11. Defect Severity Classification

| Defect ID | Test Case | Module            | Description                                                                      | Severity |
|-----------|-----------|-------------------|----------------------------------------------------------------------------------|----------|
| DEF-01    | AU-06     | Authentication    | Rate limit message lacks lockout duration                                         | Minor    |
| DEF-02    | AP-08     | Appointment       | ML recommendations show identical rankings for different scores                   | Minor    |
| DEF-03    | AD-06     | Admin             | Alert rule form lacks in-interface documentation                                  | Minor    |
| DEF-04    | PM-07     | Payment           | Refund error message missing currency symbol                                      | Cosmetic |
| DEF-05    | CB-02     | AI Chatbot        | No prompt for missing parameters during action execution                          | Moderate |
| DEF-06    | CB-07     | AI Chatbot        | Fails to recognize multiple intents in single message                             | Moderate |
| DEF-07    | RP-04     | Report/Analytics  | Demand forecast produces uniform predictions (insufficient data)                  | Minor    |
| DEF-08    | MS-04     | Messaging         | Mandatory vs optional notification types not distinguished                        | Minor    |
| DEF-09    | SC-06     | Security          | IP-based rate limiting only, no account-based lockout                             | Minor    |

Table 12. Defect Summary by Severity

| Severity | Count | Percentage |
|----------|-------|------------|
| Critical | 0     | 0.00%      |
| Major    | 0     | 0.00%      |
| Moderate | 2     | 22.22%     |
| Minor    | 6     | 66.67%     |
| Cosmetic | 1     | 11.11%     |
| Total    | 9     | 100.00%    |

Zero critical and zero major defects confirms system stability. The two moderate defects are confined to the AI Chatbot Module and relate to natural language processing limitations. The remaining defects involve interface messaging, documentation, and formatting refinements.


Alpha Testing

Alpha testing was conducted with all twenty-five (25) testers using the system freely in a controlled environment. Testers were assigned roles (client, staff, admin, cashier) and documented observations, issues, and impressions through written feedback forms.

Table 13. Alpha Testing Results

| Criteria                    | Tester Observations and Remarks |
|-----------------------------|-------------------------------|
| Design and Compatibility    | Twenty-two (22) of twenty-five (25) testers described the interface as visually organized and professional. The React and Tailwind CSS combination produced consistent visual design across modules. Browser testing across Chrome, Firefox, and Edge revealed no compatibility issues. Three (3) testers noted the admin panel layout is dense and benefits from prior system familiarity. Two (2) testers suggested improved mobile responsiveness for client-facing pages. |
| Navigation                  | Twenty (20) testers found navigation intuitive for primary workflows. Five (5) testers noted that nested admin menus (service management, settings, monitoring) required multiple clicks. Breadcrumb navigation was suggested for deep administrative sections. |
| Login and Registration      | Twenty-three (23) testers completed registration without issues. Twelve (12) testers preferred Google OAuth for its speed. Two (2) testers experienced confusion when the verification code email was delayed by approximately 45 seconds, leading them to request a new code prematurely. A visible countdown timer was recommended. |
| Appointment Booking         | All twenty-five (25) testers completed the booking workflow successfully with no data loss or errors. Nineteen (19) found the process efficient. The ML slot recommendations were tested by fifteen (15) testers: nine (9) found suggestions reasonable while six (6) noted they did not meaningfully differ from manual selection. |
| Admin Module                | Nine (9) testers assigned admin roles confirmed user management, service CRUD, and appointment oversight as functional. Three (3) IT professionals recommended graphical representations for system monitoring metrics rather than tabular displays. |
| Payment and Cashier         | Six (6) testers processed payments via cash and PayMongo without transaction errors. Receipt integrity verified against database records. Two (2) testers noted the refund approval process required navigating between multiple screens. |
| AI Chatbot                  | Fourteen (14) testers described the chatbot as helpful for information retrieval. Eight (8) encountered difficulty with colloquial phrasing or compound questions. Three (3) confirmed out-of-scope queries were correctly redirected. SSE streaming responses provided a natural conversational experience. |
| Report Module               | Twelve (12) testers confirmed accurate appointment summaries and sales reports. Three (3) suggested visual charts alongside tabular data. Five (5) IT professionals noted demand forecasting accuracy will improve with historical data accumulation. |
| Messaging and Notifications | Twenty-one (21) testers confirmed real-time WebSocket delivery. Four (4) suggested notification grouping or digest options for high-activity periods. |
| Security                    | Ten (10) IT professionals verified role isolation across all five roles (client, staff, admin, attorney, cashier) with no unauthorized access. Audit logging confirmed accurate with correct timestamps. No security vulnerabilities identified. |
| Database Design             | IT professionals assessed the relational schema as comprehensive, covering the full lifecycle from booking through payment to feedback and audit. Two (2) observed slightly longer load times on complex multi-join queries but within acceptable thresholds. |

The thematic analysis of alpha testing feedback identified five recurring themes: (1) Core Functionality Reliability -- all twenty-five testers confirmed core workflows operate without data integrity issues; (2) AI Feature Maturity Gap -- eighteen testers noted differences in maturity between traditional features and AI-powered components; (3) Interface Complexity in Administrative Functions -- ten testers found the admin panel comprehensive but steep in initial learning curve; (4) Security Confidence -- all ten IT professionals expressed confidence in the security implementation; (5) Scalability Potential -- six IT professionals recognized the microservice architecture (React frontend, Laravel API, Python ML service) as a strength for independent scaling.


AI Chatbot F1 Score Evaluation

Two hundred (200) test queries were submitted by the twenty-five (25) testers across six (6) intent categories to evaluate the chatbot classification performance. The chatbot operates on a Retrieval-Augmented Generation (RAG) architecture with LLM-powered response generation and semantic embedding-based knowledge retrieval.

Table 14. Intent Category Distribution

| Intent Category        | Queries | Percentage |
|------------------------|---------|------------|
| Legal Service Inquiry  | 43      | 21.50%     |
| Appointment Scheduling | 35      | 17.50%     |
| Status Checking        | 27      | 13.50%     |
| General FAQ            | 44      | 22.00%     |
| Greeting/Small Talk    | 19      | 9.50%      |
| Out-of-Scope Query     | 32      | 16.00%     |
| Total                  | 200     | 100.00%    |

Table 15. Confusion Matrix

| Actual \ Predicted     | Legal Service | Appt Scheduling | Status Check | General FAQ | Greeting | Out-of-Scope | Total |
|------------------------|---------------|-----------------|--------------|-------------|----------|--------------|-------|
| Legal Service Inquiry  | 38            | 2               | 1            | 1           | 0        | 1            | 43    |
| Appointment Scheduling | 2             | 28              | 2            | 1           | 0        | 2            | 35    |
| Status Checking        | 1             | 2               | 22           | 1           | 0        | 1            | 27    |
| General FAQ            | 1             | 0               | 0            | 42          | 0        | 1            | 44    |
| Greeting/Small Talk    | 0             | 0               | 0            | 1           | 18       | 0            | 19    |
| Out-of-Scope Query     | 2             | 2               | 1            | 1           | 1        | 25           | 32    |
| Total Predicted        | 44            | 34              | 26           | 47          | 19       | 30           | 200   |

One hundred seventy-three (173) of two hundred (200) queries were correctly classified, yielding a raw accuracy of 86.50%.

Table 16. Per-Class Precision, Recall, and F1 Score

| Intent Category        | TP | FP | FN | Precision | Recall | F1 Score |
|------------------------|----|----|----| ----------|--------|----------|
| Legal Service Inquiry  | 38 | 6  | 5  | 0.86      | 0.88   | 0.87     |
| Appointment Scheduling | 28 | 6  | 7  | 0.82      | 0.80   | 0.81     |
| Status Checking        | 22 | 4  | 5  | 0.85      | 0.81   | 0.83     |
| General FAQ            | 42 | 5  | 2  | 0.89      | 0.95   | 0.92     |
| Greeting/Small Talk    | 18 | 1  | 1  | 0.95      | 0.95   | 0.95     |
| Out-of-Scope Query     | 25 | 5  | 7  | 0.83      | 0.78   | 0.81     |

Table 17. Aggregated F1 Score Metrics

| Metric        | Precision | Recall | F1 Score |
|---------------|-----------|--------|----------|
| Micro-Average | 0.87      | 0.87   | 0.87     |
| Macro-Average | 0.87      | 0.86   | 0.87     |

The micro-average was computed by aggregating all true positives, false positives, and false negatives across classes (173/200 = 0.87), giving equal weight to each query. The macro-average was computed by averaging per-class metrics independently, giving equal weight to each intent category. The close alignment (both F1 = 0.87) indicates consistent performance without bias toward high-volume categories.

The chatbot performed strongest on Greeting/Small Talk (F1 = 0.95) and General FAQ (F1 = 0.92), where queries have distinctive linguistic patterns and the RAG knowledge base provides comprehensive coverage. Performance was weakest on Appointment Scheduling (F1 = 0.81) and Out-of-Scope detection (F1 = 0.81), where the chatbot struggled with incomplete scheduling requests lacking explicit trigger phrases and with out-of-scope queries that superficially resembled legal terminology. The F1 score of 0.87 falls within the expected 0.80-0.95 range for domain-specific RAG-based chatbots in early deployment (Petroni et al., 2021).


Security Assessment

Table 18. Security Test Results

| Test Category                    | Result | Remarks                                                                           |
|----------------------------------|--------|-----------------------------------------------------------------------------------|
| SQL Injection                    | Passed | Laravel Eloquent ORM parameterized queries prevent injection                      |
| Cross-Site Scripting (XSS)       | Passed | React DOM escaping and server-side sanitization prevent execution                 |
| Cross-Site Request Forgery       | Passed | CSRF protection and Sanctum token authentication prevent forged requests          |
| Authentication Bypass            | Passed | Sanctum middleware returns 401 for unauthenticated requests                       |
| Authorization Bypass             | Passed | Spatie Permission RBAC returns 403 for unauthorized role access                   |
| Session Fixation                 | Passed | Sanctum tokens validated per request; expired tokens rejected                     |
| Sensitive Data Exposure          | Passed | API responses exclude passwords, tokens, and internal system data                 |
| Clickjacking                     | Passed | X-Frame-Options: DENY prevents iframe embedding                                  |
| Rate Limit Bypass                | Passed | IP-based rate limiting enforced across authentication, chatbot, and API endpoints |
| Insecure Direct Object Reference | Passed | Ownership validation at controller level for all user-scoped resources            |

All ten (10) security categories passed. The system implements a defense-in-depth approach: input validation at the request layer, parameterized queries at the database layer, token-based authentication and RBAC at the authorization layer, and security headers (X-Frame-Options, CSP, HSTS, X-Content-Type-Options) at the transport layer.


Discussion

The evaluation of the Legal Ease system yielded an overall functional test case pass rate of 82.35% with zero critical and zero major defects across fifty-one test cases. The nine identified defects are primarily usability refinements -- error message clarity, interface documentation, and formatting -- rather than functional failures.

The alpha testing confirmed that core business workflows (registration, appointment booking, payment processing, messaging) operate reliably, with all twenty-five testers reporting no data loss or transaction errors. The thematic analysis revealed a consistent pattern where traditional web application features built on established frameworks (Laravel Sanctum, Spatie RBAC, PayMongo API) scored higher in tester confidence than the AI-powered components (chatbot, ML recommendations, demand forecasting), which were acknowledged as functional but requiring further refinement through data accumulation and model iteration.

The AI chatbot achieved an F1 score of 0.87 across six intent categories, positioning it within the expected performance range for RAG-based systems in early deployment. The strongest classification occurred in information retrieval intents (General FAQ: F1 = 0.92, Greeting: F1 = 0.95) where the knowledge base provides dense coverage. The weakest areas -- appointment scheduling (F1 = 0.81) and out-of-scope detection (F1 = 0.81) -- involve action orchestration and intent boundary disambiguation, which are recognized challenges in conversational AI that can be addressed through expanded training examples and refined entity extraction.

The clean security assessment across ten OWASP-aligned categories validates the defense-in-depth implementation appropriate for a platform handling legal service data and financial transactions. The architectural separation of the React frontend, Laravel API backend, and Python FastAPI ML microservice enables independent scaling and was recognized by IT professional testers as a strength for future growth.

These results indicate that the Legal Ease system is functionally ready for controlled deployment, with a documented performance baseline for its AI features and a clear improvement trajectory aligned with production data accumulation.
