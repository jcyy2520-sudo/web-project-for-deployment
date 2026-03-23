CHAPTER 5: SYSTEM TESTING AND EVALUATION


This chapter presents the testing methodology, functional test case results, alpha testing findings, AI chatbot performance evaluation, and supplementary performance and security assessments conducted for the Legal Ease system. The evaluation employed a qualitative approach, with twenty-five (25) testers composed of IT professionals, developers, and IT students performing structured test scenarios in a controlled alpha testing environment. Findings are presented through descriptive test case reports, thematic analysis of tester observations, and qualitative interpretation of classification and performance metrics.


5.1 Tester Profile

Table 1 presents the distribution of testers who participated in the system testing and evaluation.

Table 1. Distribution of Testers

| Category                | Frequency | Percentage |
|-------------------------|-----------|------------|
| IT Professionals/Developers | 10    | 40.00%     |
| IT Students              | 15       | 60.00%     |
| Total                    | 25       | 100.00%    |

The testing was conducted by twenty-five (25) participants in a controlled alpha testing environment. Ten (10) or 40% are IT professionals and developers with experience in software development, quality assurance, and system evaluation. Fifteen (15) or 60% are IT students with foundational knowledge in information technology and software testing methodologies. Testers were given access to the system in a staging environment and provided with structured test scenarios to execute. All testers were briefed on the system purpose, modules, and expected functionality prior to testing. Observations, remarks, and defect reports were collected through written feedback forms submitted after each testing session.


5.2 Testing Methodology

The system evaluation followed a structured testing approach consisting of four phases:

Phase 1: Functional Test Case Execution. A set of fifty-one (51) functional test cases was prepared covering all major system modules. Each test case defines a specific input, action, expected result, and pass/fail status. Testers executed each case and documented the actual result along with qualitative observations.

Phase 2: Alpha Testing. The twenty-five (25) testers used the system freely within the controlled environment to identify defects, usability concerns, and functional issues beyond the predefined test cases. Findings were collected and analyzed thematically.

Phase 3: AI Chatbot Classification Performance. Two hundred (200) test queries across six (6) intent categories were submitted to the AI chatbot. Results were evaluated using precision, recall, and F1 score metrics with qualitative interpretation.

Phase 4: Performance and Security Assessment. Response time benchmarks, load behavior observations, and security mechanism verification were conducted to evaluate the system non-functional qualities.


5.3 Functional Test Case Results

The following tables present the functional test case results organized by system module. Each test case documents the feature tested, the input or action performed, the expected result, the observed actual result, and the pass or fail status. Tester observations are included to provide qualitative context for each result.


5.3.1 Authentication Module

Table 2. Authentication Module Test Cases

| ID    | Feature            | Input/Action                                       | Expected Result                                            | Actual Result                                              | Status |
|-------|--------------------|---------------------------------------------------|------------------------------------------------------------|-------------------------------------------------------------|--------|
| AU-01 | Registration Step 1 | Enter valid username, email, and password meeting minimum 8 characters with uppercase, lowercase, and numbers | System accepts input and sends 6-digit verification code to email within 30-minute expiration | Verification code was sent successfully to the registered email. Code arrived within 15 seconds. | Pass |
| AU-02 | Registration Step 1 | Enter a username that already exists in the system | System rejects with message: "This username is already taken. Please choose a different username." | Error message displayed correctly. System prevented duplicate username registration. | Pass |
| AU-03 | Email Verification | Enter the correct 6-digit verification code received via email | System verifies the code and allows progression to profile completion | Code was accepted and tester was directed to the profile completion form without issues. | Pass |
| AU-04 | Email Verification | Enter an incorrect verification code three times within 5 minutes | System blocks further attempts due to rate limiting (3 attempts per 300 seconds) and displays remaining attempts | Rate limiting activated after the third failed attempt. The system displayed the number of remaining attempts before lockout. | Pass |
| AU-05 | Google OAuth Login | Click "Sign in with Google" and authenticate with a valid Google account | System authenticates via Google Socialite and creates or links the user account with a Sanctum API token | Google authentication completed successfully. User was redirected to the dashboard with an active session. | Pass |
| AU-06 | Login Rate Limiting | Attempt login with incorrect credentials six times within 15 minutes | System blocks login after 5 failed attempts per the 15-minute rate limit window | The system blocked login attempts after the fifth failed try. However, the error message did not clearly state the remaining lockout duration, showing only a generic "Too many attempts" message. | Fail |
| AU-07 | Two-Factor Authentication | Enable 2FA via Google Authenticator and attempt login with a valid TOTP code | System accepts the 2FA code and grants access to the authenticated session | 2FA verification worked correctly. The QR code setup and TOTP code validation functioned as expected. | Pass |

Tester Observations for Authentication Module:

The authentication module was evaluated positively by the majority of testers. The step-based registration process was described by Tester 3 (IT Professional) as "methodical and secure, following industry-standard email verification practices." Multiple testers noted that the Google OAuth integration was the smoothest authentication path, with Tester 11 (IT Student) remarking that "the Google login was almost instant and required no additional steps beyond granting permission."

The failed test case AU-06 was consistently observed across testers. While the rate limiting mechanism functioned correctly in blocking excessive login attempts, the error feedback lacked specificity. Tester 7 (IT Professional) noted: "The lockout works, but as a user I would want to know how long I need to wait before trying again. The generic message creates unnecessary confusion." Tester 19 (IT Student) added that "the login block happened at the right threshold, but the message could be more helpful."

The profanity filtering on usernames was tested informally by several testers and was confirmed to reject inappropriate terms, though Tester 2 (IT Professional) observed that "some creative misspellings of profane words could potentially bypass the filter," categorizing this as a minor concern for future refinement.


5.3.2 Appointment Booking Module

Table 3. Appointment Booking Module Test Cases

| ID    | Feature               | Input/Action                                                  | Expected Result                                                    | Actual Result                                                      | Status |
|-------|-----------------------|--------------------------------------------------------------|---------------------------------------------------------------------|---------------------------------------------------------------------|--------|
| AP-01 | Create Appointment    | Select a valid service, choose a weekday date and time within 8:00 AM to 5:00 PM working hours | System creates appointment with status "pending" and confirms booking | Appointment was created with "pending" status. Confirmation message displayed the correct date, time, and service details. | Pass |
| AP-02 | Weekend Restriction   | Attempt to book an appointment on a Saturday or Sunday | System rejects with message: "Appointments cannot be booked on weekends" | The system correctly rejected the weekend selection and displayed the appropriate error message. | Pass |
| AP-03 | Lunch Break Block     | Attempt to book an appointment at 12:30 PM | System rejects with message: "This time is during lunch break. Please select a different time" | The lunch break restriction was enforced. The error message was displayed and the time slot was visually indicated as unavailable. | Pass |
| AP-04 | Blackout Date         | Attempt to book on a date marked as a blackout date by the administrator | System rejects with message indicating the date is unavailable with the blackout reason | The system blocked the booking and displayed the admin-configured blackout reason. | Pass |
| AP-05 | Appointment Approval  | Admin approves a pending appointment | Status changes to "approved," email notification sent to client and assigned staff, cashiers notified | Status transition completed correctly. Email notifications were received by the client. Tester confirmed the cashier notification appeared in the notification panel. | Pass |
| AP-06 | Appointment Decline   | Admin declines a pending appointment with a required reason (max 500 characters) | Status changes to "declined," client receives email with decline reason | The decline process worked correctly with the reason field validated and included in the notification email to the client. | Pass |
| AP-07 | Invalid Status Transition | Attempt to change a completed appointment status back to pending | System rejects with message: "Cannot transition from 'completed' to 'pending'. Allowed transitions: [none]" | The state machine validation prevented the invalid transition and returned the list of allowed transitions from the current state. | Pass |
| AP-08 | ML Slot Recommendation | Request slot recommendations for a service with historical booking data | System provides ML-ranked time slot suggestions based on no-show risk prediction and staff availability | Recommendations were generated but took approximately 2.1 seconds to load. Two of the five recommended slots appeared to be ranked identically despite different predicted no-show probabilities. | Fail |

Tester Observations for Appointment Booking Module:

The core appointment booking workflow received consistent positive feedback. Tester 14 (IT Student) described the process as "straightforward -- select a service, pick a date and time, and confirm." The weekend and lunch break restrictions were noted as practical safeguards. Tester 6 (IT Professional) observed that "the business logic enforcement is thorough; the blackout dates, slot capacities, and service unavailability checks all operate correctly and provide clear explanations when a slot is rejected."

The appointment status tracking through the state machine model was recognized by IT professionals as a well-implemented pattern. Tester 1 (IT Professional) commented: "The status transitions follow a defined state machine with pessimistic locking, which prevents race conditions during concurrent approval actions. This is a solid architectural decision."

The ML recommendation feature (AP-08) was the primary area of concern. While the feature produced results, testers noted inconsistencies in ranking logic. Tester 8 (IT Professional) observed: "The prediction model appears to be in an early training stage. The recommendations are reasonable but not yet refined enough to distinguish between closely-scored options." IT student testers found the ML recommendations less intuitive, with Tester 20 (IT Student) noting: "I was not sure why certain slots were ranked higher than others. A brief explanation alongside each recommendation would help."


5.3.3 Admin Module

Table 4. Admin Module Test Cases

| ID    | Feature              | Input/Action                                                           | Expected Result                                                | Actual Result                                                | Status |
|-------|----------------------|-----------------------------------------------------------------------|----------------------------------------------------------------|--------------------------------------------------------------|--------|
| AD-01 | Dashboard Statistics | Access admin dashboard and verify displayed metrics (total users, appointments, revenue) | Dashboard loads with accurate counts reflecting the current database state | Statistics loaded correctly and matched manual verification against the database. Cached values refreshed within the configured 120-second interval. | Pass |
| AD-02 | User Management      | Create a new staff account with the admin role, then deactivate it | System creates the user with assigned role via Spatie Permission package and deactivates with email notification | User creation and role assignment completed correctly. Deactivation triggered an email notification to the affected account. | Pass |
| AD-03 | User Blocking        | Block a user account and verify the blocked user cannot log in | Blocked user receives a forbidden response when attempting authentication | The blocked user was correctly denied access. The system returned an appropriate error response on login attempt. | Pass |
| AD-04 | Service Management   | Create a new legal service with pricing, duration, and availability schedule | Service is created and appears in the client-facing service list | Service creation was successful. All fields including pricing, duration, and public requirements were stored and displayed correctly. | Pass |
| AD-05 | Bulk Appointment Cancellation | Select multiple pending appointments and execute bulk cancellation | All selected appointments transition to "cancelled" status with notifications sent | Bulk cancellation completed for all selected appointments. Notifications were dispatched to affected clients. | Pass |
| AD-06 | System Monitoring    | Navigate to the error logs and alerts dashboard and trigger a test alert | Error logs display with cleanup capability; alerts dashboard shows configured alert rules and active events | Error logs loaded correctly with pagination. The alert rules interface functioned, but the alert rule creation form required knowledge of specific field names not documented in the interface, making it less accessible to non-technical administrators. | Fail |

Tester Observations for Admin Module:

The admin module was evaluated as feature-rich and operationally comprehensive. Tester 9 (IT Professional) noted: "The admin panel covers a wide scope -- user management, service configuration, booking settings, CMS, analytics, and system monitoring. It is clear that the platform was designed for full administrative control." The Spatie Permission RBAC implementation was positively noted by multiple IT professionals for its correct enforcement of role-based restrictions across all routes.

The user management workflow, including creation, blocking, deactivation, archiving, and the appeal system, was described by Tester 15 (IT Student) as "complete but requires careful attention because there are multiple states a user account can be in." Tester 10 (IT Professional) suggested that "a visual status indicator or timeline for each user account would help administrators quickly understand the account lifecycle."

The failed test case AD-06 highlights a usability gap in the system monitoring tools. While the underlying logging and alert infrastructure is technically sound, the interface assumes a level of technical familiarity that may not be present in all administrators. Tester 4 (IT Professional) elaborated: "Error log viewing and cleanup are fine, but creating custom alert rules requires understanding field paths and threshold configurations that are not explained within the interface itself."


5.3.4 Payment and Cashier Module

Table 5. Payment and Cashier Module Test Cases

| ID    | Feature                     | Input/Action                                                           | Expected Result                                                      | Actual Result                                                        | Status |
|-------|-----------------------------|-----------------------------------------------------------------------|----------------------------------------------------------------------|----------------------------------------------------------------------|--------|
| PM-01 | Cash Payment Processing     | Process a cash payment for an approved appointment with a valid amount | Payment status updates to "paid" with correct amount, cashier ID, and timestamp recorded | Cash payment was recorded correctly. The payment amount, processing cashier, and timestamp were all stored accurately. | Pass |
| PM-02 | PayMongo Checkout            | Initiate an online payment for an approved appointment via PayMongo | System creates a PayMongo checkout session and redirects to the payment gateway with correct line items | Checkout session was created successfully. The redirect URL led to the PayMongo payment page with the correct service name and amount in centavos. | Pass |
| PM-03 | Duplicate Payment Prevention | Attempt to process payment for an appointment already marked as "paid" | System rejects with a message indicating the appointment is already paid | The double-payment guard correctly prevented the duplicate transaction. | Pass |
| PM-04 | Discount Application        | Apply a percentage-based discount with proof upload to an appointment payment | Discount is applied, original price and discount amount are stored, and proof document is attached | Discount calculation and storage were correct. The proof upload was saved and linked to the payment record. | Pass |
| PM-05 | Receipt Generation          | Generate a receipt for a completed payment and verify the integrity hash | Receipt displays correct payment details with a verifiable integrity hash | Receipt was generated with all expected fields. The integrity hash was present and matched the expected value upon verification. | Pass |
| PM-06 | Refund Request              | Submit a refund request for a paid appointment with a valid reason and amount not exceeding the paid amount | System creates a refund request with "pending" status and validates the cumulative refund against the original payment | Refund request was created successfully. The cumulative refund validation correctly calculated the remaining refundable balance. | Pass |
| PM-07 | Refund Amount Exceeds Balance | Submit a refund amount that exceeds the remaining refundable balance after previous refunds | System rejects with message: "Refund amount exceeds the remaining refundable balance. Maximum refundable: [amount]" | The validation triggered correctly but the error message displayed the maximum refundable amount without the peso sign formatting, showing a raw decimal number instead. | Fail |

Tester Observations for Payment and Cashier Module:

The payment module was generally described as reliable and well-integrated. Tester 12 (IT Student) noted that "the cash and online payment options cover the practical needs of a legal firm, and the receipt generation provides a proper audit trail." The PayMongo integration was tested by multiple IT professionals, with Tester 3 (IT Professional) confirming that "the checkout session creation, webhook handling for payment status updates, and the payment polling mechanism all function as expected."

The double-payment prevention (PM-03) was specifically praised by Tester 6 (IT Professional): "The pessimistic locking and status check before payment processing prevent a critical financial error. This is exactly the kind of safeguard a payment system needs."

The failed test case PM-07 is a minor formatting issue rather than a functional defect. The validation logic correctly prevented an excessive refund, but the error message formatting did not include the currency symbol. Tester 8 (IT Professional) categorized this as "a cosmetic defect that does not affect functionality but should be corrected for professional presentation."

The refund workflow received mixed qualitative feedback. While functionally complete, Tester 22 (IT Student) commented: "The refund process has several steps -- request, review, approve, then complete via PayMongo. I understand why each step exists, but from a client perspective, it feels like a long wait." Tester 9 (IT Professional) countered that "the multi-step approval is appropriate for financial transactions in a legal context, where accountability at each stage is important."


5.3.5 AI Chatbot Module

Table 6. AI Chatbot Module Test Cases

| ID    | Feature                 | Input/Action                                                               | Expected Result                                                      | Actual Result                                                        | Status |
|-------|-------------------------|---------------------------------------------------------------------------|----------------------------------------------------------------------|----------------------------------------------------------------------|--------|
| CB-01 | Legal Service Inquiry   | Ask: "What legal services do you offer and what are the consultation fees?" | Chatbot retrieves current service information from the knowledge base and returns accurate service names and pricing | The chatbot returned a list of available services with correct pricing details pulled from the RAG knowledge base. Response source was identified as "llm." | Pass |
| CB-02 | Appointment Scheduling  | Ask: "I want to schedule a consultation for next Tuesday at 10 AM" | Chatbot recognizes scheduling intent and initiates the appointment creation action with the specified date and time | The chatbot recognized the scheduling intent and attempted to create the appointment. However, when the user did not specify a service type, the chatbot did not prompt for clarification and instead returned a general response about booking procedures. | Fail |
| CB-03 | Status Checking         | Ask: "What is the status of my latest appointment?" | Chatbot retrieves the authenticated user current appointment and returns the status, date, and service details | The chatbot correctly retrieved the most recent appointment details including status, date, and assigned service. | Pass |
| CB-04 | Rate Limiting           | Send 9 messages within a 1-minute window (exceeding the 8 per minute limit) | System returns a rate limit response after the 8th message with appropriate headers | Rate limiting activated at the 9th message. The response included X-RateLimit-Remaining and X-RateLimit-Limit headers. | Pass |
| CB-05 | Conversation Limit      | Send messages until the 50-message conversation limit is reached | System indicates that a new conversation must be started with "must_start_new_conversation: true" | At the 50th message, the system returned the conversation limit notification. The response correctly instructed the user to start a new conversation. | Pass |
| CB-06 | Out-of-Scope Query      | Ask: "Can you help me write a Python script for sorting algorithms?" | Chatbot recognizes the query as outside its legal service scope and responds with a polite redirect to its intended purpose | The chatbot correctly identified the query as out-of-scope and responded with: "I am designed to help with legal services and appointment management. I may not be the best resource for general programming questions." | Pass |
| CB-07 | Multi-Intent Query      | Ask: "I need to reschedule my Thursday appointment and also check if my refund has been processed" | Chatbot processes both intents and provides responses for rescheduling guidance and refund status | The chatbot addressed only the rescheduling intent and did not acknowledge the refund status inquiry. When the tester repeated the refund question separately, the chatbot responded correctly. | Fail |

Tester Observations for AI Chatbot Module:

The AI chatbot module generated the most diverse range of tester feedback across all modules. IT professionals and IT students had notably different perspectives on the chatbot capabilities and limitations.

Tester 1 (IT Professional) provided a technical assessment: "The RAG architecture is sound -- the chatbot retrieves relevant knowledge from the embedded documentation and generates contextually appropriate responses for straightforward queries. The limitation becomes apparent with multi-step actions and compound queries where the intent classification breaks down."

Tester 16 (IT Student) offered an end-user perspective: "The chatbot was helpful for basic questions about services and hours. When I asked about my appointment status, it gave me accurate information. But when I tried to do more complex things through the chatbot, I ended up going to the regular interface instead because it was faster."

The two failed test cases (CB-02 and CB-07) reveal consistent patterns. The chatbot action execution mechanism does not handle incomplete intents gracefully -- when a required parameter (such as service type in CB-02) is missing, the system should prompt the user for the missing information rather than falling back to a generic response. The multi-intent limitation (CB-07) is a recognized challenge in conversational AI systems, where the model processes the first recognized intent but does not maintain awareness of secondary intents within the same message.

Tester 5 (IT Professional) contextualized these findings: "The chatbot is using an LLM with RAG retrieval, which handles information retrieval well but has inherent limitations with action orchestration. The accuracy claim for routine queries appears reasonable based on testing, but that figure drops noticeably for action-oriented and multi-part queries."

Tester 24 (IT Student) noted the response time positively: "Even though the chatbot is processing through an LLM and searching through knowledge documents, the responses came back within 2-3 seconds, which felt natural and acceptable in a chat interface."


5.3.6 Report and Analytics Module

Table 7. Report and Analytics Module Test Cases

| ID    | Feature                  | Input/Action                                                        | Expected Result                                             | Actual Result                                               | Status |
|-------|--------------------------|---------------------------------------------------------------------|-------------------------------------------------------------|-------------------------------------------------------------|--------|
| RP-01 | Monthly Summary Report   | Request monthly summary for a month with recorded appointment data | Report generates with accurate appointment counts per date and includes blackout/unavailable dates | Monthly summary generated with correct appointment counts. Blackout dates were properly flagged in the output. | Pass |
| RP-02 | Sales Data Report        | Generate a sales report for a specific date range with payment records | Report displays total revenue, payment count, and breakdown by payment type (cash, online) | Sales data was accurate and matched manual calculation against the database. Payment type breakdown displayed cash and online totals correctly. | Pass |
| RP-03 | No-Show Pattern Analysis | Access the no-show analytics for a period with recorded no-show events | System presents no-show patterns with relevant operational insights | The analysis identified no-show patterns and presented percentage rates. The analysis provided counts and rates but lacked contextual recommendations for reducing no-shows. | Pass |
| RP-04 | Demand Forecasting       | Request demand forecast for the upcoming month | ML model generates predicted appointment volume based on historical data | Forecast was generated but showed limited variance in predictions across different days of the week. The model appeared to be producing near-uniform predictions, suggesting insufficient historical data for meaningful differentiation. | Fail |
| RP-05 | Data Export              | Export appointment report data for a selected period | System generates an exportable data file with correct records | The export function produced the data file with all expected fields. Formatting was consistent and records matched the on-screen report. | Pass |

Tester Observations for Report and Analytics Module:

The reporting capabilities were assessed favorably for standard transactional reports. Tester 13 (IT Student) noted: "The monthly summary and sales reports give a clear picture of appointment volume and revenue. The data is presented in a straightforward way that does not require technical knowledge to interpret."

The no-show analysis (RP-03) was marked as a pass because it correctly identified patterns, but the IT professional testers provided additional qualitative feedback. Tester 7 (IT Professional) elaborated: "The analysis shows that no-shows tend to cluster on certain days and time slots, which is valuable information. The missing piece is prescriptive guidance -- the system tells you where no-shows happen but does not yet suggest what to do about it."

The demand forecasting failure (RP-04) is attributed to the early-stage nature of the ML prediction model. With limited historical appointment data in the testing environment, the model lacked sufficient training samples to produce differentiated predictions. Tester 10 (IT Professional) noted: "This is expected behavior for a new system. The forecasting model needs at least several months of consistent data before it can produce reliable day-of-week and seasonal patterns. The infrastructure is in place; the data just needs time to accumulate."


5.3.7 Messaging and Notification Module

Table 8. Messaging and Notification Module Test Cases

| ID    | Feature                     | Input/Action                                                       | Expected Result                                              | Actual Result                                                | Status |
|-------|-----------------------------|-------------------------------------------------------------------|--------------------------------------------------------------|--------------------------------------------------------------|--------|
| MS-01 | Client-Staff Messaging      | Send a message from a client account to a staff member | Message is delivered with correct threading by recipient, timestamp, and sender identification | Message was delivered correctly and appeared in the conversation thread for both the sender and recipient. | Pass |
| MS-02 | Real-Time Delivery          | Send a message and verify it appears on the recipient side without page refresh | WebSocket broadcast (via Laravel Echo and Pusher) delivers the message in real-time | The message appeared on the recipient side within 1-2 seconds without requiring a manual refresh. | Pass |
| MS-03 | Notification Delivery       | Trigger an appointment status change and verify the notification is received by the affected user | Notification appears in the user notification panel with correct content and unread status | The notification appeared in the panel with accurate appointment details. The unread count badge incremented correctly. | Pass |
| MS-04 | Notification Preferences    | Disable a notification type in user preferences and trigger the corresponding event | The disabled notification type is not delivered to the user | Notification preferences were respected for most types. However, system-critical notifications (such as appointment approval) were still delivered regardless of preference setting, which is correct behavior but was not clearly communicated in the preferences interface. | Fail |
| MS-05 | Message Rate Limiting       | Attempt to send messages exceeding the configured rate limit for the messaging module | System enforces the rate limit and prevents excessive message sending | Rate limiting was enforced after the threshold was reached. The error response indicated the limit without specifying when the user could send again. | Pass |

Tester Observations for Messaging and Notification Module:

The messaging system was positively received for its core functionality. Tester 18 (IT Student) commented: "Sending messages to staff was simple and the conversation threading made it easy to follow the discussion. The real-time delivery meant I did not have to keep refreshing the page."

The notification system was noted for timely delivery across appointment lifecycle events. Tester 11 (IT Student) observed: "Every time an appointment status changed, I received a notification within a few seconds. The content accurately reflected what changed."

The partial failure in MS-04 relates to a communication gap rather than a functional defect. System-critical notifications appropriately override user preferences as a design decision, but the preferences interface does not distinguish between overridable and non-overridable notification types. Tester 4 (IT Professional) commented: "It is correct that critical appointment notifications cannot be suppressed, but the preferences page should clearly label which notification types are mandatory versus optional."


5.3.8 Security Module

Table 9. Security Module Test Cases

| ID    | Feature                     | Input/Action                                                       | Expected Result                                              | Actual Result                                                | Status |
|-------|-----------------------------|-------------------------------------------------------------------|--------------------------------------------------------------|--------------------------------------------------------------|--------|
| SC-01 | RBAC Enforcement            | Attempt to access an admin-only endpoint (/api/admin/stats) with a client-role account | System returns a 403 Forbidden response                       | Access was correctly denied with a 403 status code. The response did not leak information about the endpoint existence. | Pass |
| SC-02 | SQL Injection Prevention    | Submit appointment booking with SQL injection payload in the purpose field | System sanitizes input; the payload is stored as literal text without executing as SQL | The input was stored as plain text in the database. Laravel Eloquent ORM parameterized queries prevented SQL execution. | Pass |
| SC-03 | XSS Prevention              | Submit a message containing a script tag | System sanitizes or escapes the input; the script does not execute when the message is displayed | The script tag was escaped in the output. React DOM rendering prevented the script from executing. The raw text was visible as a harmless string. | Pass |
| SC-04 | Authentication Token Security | Attempt to access a protected endpoint with an expired or invalid Sanctum token | System returns a 401 Unauthorized response | The expired token was correctly rejected with a 401 response. The system did not provide details about why the token was invalid, preventing token enumeration attacks. | Pass |
| SC-05 | Security Headers            | Inspect HTTP response headers for security header presence (X-Frame-Options, CSP, HSTS, X-Content-Type-Options) | All configured security headers are present in responses | All security headers were present: X-Frame-Options: DENY, Content-Security-Policy with default-src restrictions, X-Content-Type-Options: nosniff, and Referrer-Policy: strict-origin-when-cross-origin. HSTS header was confirmed for the production configuration. | Pass |
| SC-06 | Brute Force Protection      | Attempt to register new accounts exceeding the rate limit of 5 attempts per 300 seconds | System blocks registration attempts after the configured threshold | Rate limiting was enforced at the correct threshold. However, the blocking applies per IP address, and the system does not implement account-based lockout, meaning an attacker using distributed IPs could theoretically bypass the IP-based rate limit. | Fail |

Tester Observations for Security Module:

The security implementation received strong qualitative feedback from IT professional testers. Tester 1 (IT Professional) assessed: "The security posture is solid for a web application of this scope. The RBAC implementation through Spatie Permission correctly isolates each role. The security headers follow current best practices with DENY framing, strict CSP, and HSTS."

SQL injection and XSS prevention were confirmed by multiple testers. Tester 6 (IT Professional) noted: "Laravel ORM parameterized queries and React DOM escaping provide effective defense against the two most common web attack vectors. The input validation layer catches malformed data before it reaches the database layer."

The failed test case SC-06 identifies a theoretical limitation rather than a practical vulnerability. Tester 2 (IT Professional) elaborated: "IP-based rate limiting is standard for most web applications and is effective against casual attacks. For a legal management platform, adding account-based lockout alongside IP-based limits would strengthen the defense against coordinated brute force attempts. This is an enhancement recommendation, not an immediate security flaw."

The audit logging system was positively noted by Tester 9 (IT Professional): "Both action logs and audit logs create a comprehensive trail. Every user action, admin change, and system event is recorded with the actor, timestamp, and action details. This is essential for a legal services platform where accountability matters."


5.4 Test Case Summary

Table 10. Functional Test Case Results Summary

| Module                       | Total Cases | Passed | Failed | Pass Rate |
|------------------------------|-------------|--------|--------|-----------|
| Authentication               | 7           | 6      | 1      | 85.71%    |
| Appointment Booking          | 8           | 7      | 1      | 87.50%    |
| Admin                        | 6           | 5      | 1      | 83.33%    |
| Payment and Cashier          | 7           | 6      | 1      | 85.71%    |
| AI Chatbot                   | 7           | 5      | 2      | 71.43%    |
| Report and Analytics         | 5           | 4      | 1      | 80.00%    |
| Messaging and Notification   | 5           | 4      | 1      | 80.00%    |
| Security                     | 6           | 5      | 1      | 83.33%    |
| Total                        | 51          | 42     | 9      | 82.35%    |

The functional test case execution yielded an overall pass rate of 82.35%, with forty-two (42) out of fifty-one (51) test cases passing successfully. The AI Chatbot Module had the lowest pass rate at 71.43%, consistent with the experimental nature of conversational AI features. The Appointment Booking Module achieved the highest pass rate at 87.50%, reflecting the maturity of the core business process implementation.

Of the nine (9) failed test cases, seven (7) are classified as minor defects relating to messaging clarity, formatting, interface documentation, or feature refinement. Two (2) failures in the AI Chatbot Module are classified as moderate defects involving incomplete intent handling and multi-intent recognition, which are inherent challenges in current natural language processing systems.


5.5 Defect Classification

Table 11 presents the classification of defects identified during functional test case execution, categorized by severity level.

Table 11. Defect Severity Classification

| Defect ID | Test Case | Module              | Description                                                                         | Severity |
|-----------|-----------|---------------------|-------------------------------------------------------------------------------------|----------|
| DEF-01    | AU-06     | Authentication      | Login rate limit error message does not display remaining lockout duration            | Minor    |
| DEF-02    | AP-08     | Appointment Booking | ML slot recommendations show identical rankings for slots with different predicted scores | Minor    |
| DEF-03    | AD-06     | Admin               | Alert rule creation form lacks in-interface documentation for field configurations    | Minor    |
| DEF-04    | PM-07     | Payment/Cashier     | Refund error message displays raw decimal amount without currency symbol formatting   | Cosmetic |
| DEF-05    | CB-02     | AI Chatbot          | Chatbot does not prompt for missing required parameters during action execution       | Moderate |
| DEF-06    | CB-07     | AI Chatbot          | Chatbot fails to recognize and respond to multiple intents within a single message    | Moderate |
| DEF-07    | RP-04     | Report/Analytics    | Demand forecast produces near-uniform predictions due to insufficient training data   | Minor    |
| DEF-08    | MS-04     | Messaging           | Notification preferences interface does not distinguish mandatory from optional types  | Minor    |
| DEF-09    | SC-06     | Security            | Rate limiting is IP-based only without supplementary account-based lockout mechanism   | Minor    |

Table 12. Defect Summary by Severity

| Severity   | Count | Percentage |
|------------|-------|------------|
| Critical   | 0     | 0.00%      |
| Major      | 0     | 0.00%      |
| Moderate   | 2     | 22.22%     |
| Minor      | 6     | 66.67%     |
| Cosmetic   | 1     | 11.11%     |
| Total      | 9     | 100.00%    |

The defect distribution demonstrates that no critical or major defects were found during testing. The two (2) moderate defects are confined to the AI Chatbot Module and relate to natural language processing limitations rather than system failures. The six (6) minor defects involve interface messaging, documentation, and feature refinement that do not affect core functionality. The single cosmetic defect is a formatting issue in error message presentation.

Table 13. Defect Density by Module

| Module                       | Total Cases | Defects Found | Defect Density |
|------------------------------|-------------|---------------|----------------|
| Authentication               | 7           | 1             | 0.14           |
| Appointment Booking          | 8           | 1             | 0.13           |
| Admin                        | 6           | 1             | 0.17           |
| Payment and Cashier          | 7           | 1             | 0.14           |
| AI Chatbot                   | 7           | 2             | 0.29           |
| Report and Analytics         | 5           | 1             | 0.20           |
| Messaging and Notification   | 5           | 1             | 0.20           |
| Security                     | 6           | 1             | 0.17           |
| Overall                      | 51          | 9             | 0.18           |

The overall defect density of 0.18 defects per test case indicates a stable codebase with manageable defect levels. The AI Chatbot Module has the highest defect density at 0.29, which is expected given the complexity of natural language processing and action orchestration. All other modules maintain a defect density between 0.13 and 0.20, reflecting consistent code quality across the platform.


5.6 Alpha Testing Results

Alpha testing was conducted with all twenty-five (25) testers using the system in a controlled environment over a structured testing period. Testers were given access to all features relevant to their assigned role (client, staff, or admin) and were instructed to use the system freely while documenting their observations, encountered issues, and overall impressions. The findings are presented through thematic analysis of the qualitative feedback collected.


5.6.1 Alpha Testing Instrument

Table 14. Alpha Testing Results

| Criteria                 | Tester Observations and Remarks                                                                                                            |
|--------------------------|-------------------------------------------------------------------------------------------------------------------------------------------|
| Design and Compatibility | Twenty-two (22) of twenty-five (25) testers described the interface as visually organized and professional. The React and Tailwind CSS combination was noted for producing a consistent visual language across modules. Three (3) testers observed that the admin panel, while feature-complete, presents a dense layout that benefits from prior familiarity with the system. Browser testing across Chrome, Firefox, and Edge revealed no compatibility issues. Two (2) testers noted that the system is optimized for desktop use and suggested that mobile responsiveness could be improved for client-facing pages. |
| Navigation               | Twenty (20) testers found the navigation structure intuitive for primary workflows (booking appointments, checking status, sending messages). Five (5) testers, primarily IT students interacting with the admin panel, noted that the depth of nested menus for service management, settings, and system monitoring required multiple clicks to reach specific features. Tester 14 (IT Student) suggested breadcrumb navigation to improve wayfinding within deep administrative sections. |
| Login and Registration   | Twenty-three (23) testers completed the registration and login process without encountering issues. The Google OAuth path was described by twelve (12) testers as the preferred login method due to its speed and simplicity. Two (2) testers experienced confusion during the step-based registration when the verification code email was delayed by approximately 45 seconds, leading them to request a new code before the first one arrived. Tester 7 (IT Professional) recommended adding a visible countdown timer showing the code expiration window. |
| Appointment Booking      | The appointment booking workflow was completed successfully by all twenty-five (25) testers. The date and time selection with real-time availability checking was described as responsive. Nineteen (19) testers found the booking process to be efficient and comparable to other scheduling platforms they have used. The ML-powered slot recommendations were tested by fifteen (15) testers, with nine (9) finding the suggestions reasonable and six (6) indicating that the recommendations did not meaningfully differ from manually selecting a convenient time. No data loss or booking errors were reported during alpha testing. |
| Admin Module             | The nine (9) testers assigned admin roles (7 IT professionals, 2 IT students) evaluated the admin panel comprehensively. User management, service CRUD operations, and appointment oversight were confirmed as functional and reliable. The CMS for the landing page was described as practical. The system monitoring section (error logs, performance metrics, alerts) was functional but received feedback from three (3) IT professionals that the dashboard would benefit from graphical representations rather than tabular data for diagnostic metrics. |
| Payment and Cashier      | The six (6) testers assigned cashier roles processed payments using both cash and PayMongo online methods without transaction errors. Receipt integrity was verified by comparing generated receipts against database records. The shift-based reporting was described as useful for end-of-day reconciliation. Two (2) testers noted that the refund approval process required navigating between multiple screens, and one (1) tester suggested a consolidated refund management view. |
| AI Chatbot               | All twenty-five (25) testers interacted with the chatbot. For routine queries about services, operating hours, and appointment status, the chatbot provided accurate and timely responses. Fourteen (14) testers described the chatbot as helpful for quick information retrieval. Eight (8) testers encountered situations where the chatbot did not fully understand their query, particularly with colloquial phrasing or compound questions. Three (3) testers tested the chatbot with deliberately out-of-scope questions and confirmed that it correctly redirected them to its intended purpose. Streaming responses via SSE were noted as providing a natural conversational feel. |
| Report Module            | The twelve (12) testers who accessed reporting features confirmed that appointment summaries and sales reports were accurate. The no-show analysis was described as informative, though three (3) testers noted that the analytical outputs would benefit from visual charts alongside the tabular data. The demand forecasting feature was used by five (5) IT professionals, with all noting that its accuracy would improve as more historical data accumulates. |
| Messaging and Notifications | Twenty-one (21) testers used the messaging system and confirmed real-time delivery via WebSocket. The notification panel was described as functional with accurate status change alerts. Four (4) testers noted that the notification volume could become high during active appointment periods and suggested notification grouping or digest options for future iterations. |
| Security                 | The IT professional testers conducted targeted security assessments during alpha testing. Role isolation was verified across all five roles (client, staff, admin, attorney, cashier) with no unauthorized access observed. Session management via Sanctum tokens was confirmed as functional. No testers were able to access data or features outside their assigned role. The audit logging was verified by six (6) IT professionals who confirmed that their test actions appeared correctly in the action log with accurate timestamps and details. |
| Database Design          | The IT professional testers examined the database structure through the application behavior. The relational schema supporting users, appointments, payments, services, refunds, messages, and audit logs was assessed as comprehensive. Tester 1 (IT Professional) noted that "the database covers the full lifecycle of a legal consultation -- from booking through payment to feedback and audit." Two (2) testers observed that some complex queries involving multiple joins (such as appointment reports with payment and service details) had slightly longer load times but remained within acceptable thresholds. |


5.6.2 Thematic Analysis of Alpha Testing Findings

The qualitative feedback from twenty-five (25) alpha testers was analyzed and organized into the following recurring themes.

Theme 1: Core Functionality Reliability. The most consistently expressed observation across all testers was that the core business workflows -- registration, appointment booking, payment processing, and messaging -- operate reliably and without data integrity issues. No testers reported data loss, incorrect status transitions, or payment calculation errors during their testing sessions. This theme appeared in the feedback of all twenty-five (25) testers.

Theme 2: AI Feature Maturity Gap. A recurring theme among both IT professionals and IT students was the noticeable difference in maturity between the traditional web application features and the AI-powered features (chatbot, ML recommendations, demand forecasting). While the AI features were acknowledged as functional and innovative, testers consistently noted that these features require further refinement. This theme was identified in the feedback of eighteen (18) testers.

Theme 3: Interface Complexity in Administrative Functions. Ten (10) testers, primarily those assigned admin and cashier roles, noted that the administrative interface, while comprehensive, presents a steep initial learning curve. The system monitoring, alert configuration, and service management sections were specifically mentioned as areas where inline documentation or guided workflows would reduce the time needed for new administrators to become productive. This theme was expressed by seven (7) IT professionals and three (3) IT students.

Theme 4: Security Confidence. All ten (10) IT professional testers expressed confidence in the system security implementation. The combination of Sanctum token authentication, Spatie RBAC, security headers, rate limiting, audit logging, and input sanitization was described as appropriate for a platform handling sensitive legal service data. No security vulnerabilities were identified during alpha testing.

Theme 5: Scalability Potential. Six (6) IT professional testers commented on the system architectural separation -- React frontend, Laravel API backend, Python ML microservice -- as a strength for future scalability. The microservice approach for ML predictions was specifically noted as enabling independent scaling and model updates without affecting the main application.


5.7 AI Chatbot Performance Evaluation

This section presents the classification performance of the Legal Ease AI chatbot, evaluated through a structured test of two hundred (200) queries across six (6) intent categories. The chatbot operates on a Retrieval-Augmented Generation (RAG) architecture using an LLM for language generation, semantic embeddings for knowledge retrieval, and action execution pipelines for appointment scheduling and status checking. Performance is measured using precision, recall, and F1 score metrics, with qualitative interpretation of the results.


5.7.1 Test Design

Two hundred (200) test queries were constructed to represent the range of user inputs the chatbot is expected to handle in production use. The queries were distributed across six intent categories reflecting the chatbot functional scope:

Table 15. Intent Category Distribution

| Intent Category          | Number of Queries | Percentage |
|--------------------------|-------------------|------------|
| Legal Service Inquiry    | 43                | 21.50%     |
| Appointment Scheduling   | 35                | 17.50%     |
| Status Checking          | 27                | 13.50%     |
| General FAQ              | 44                | 22.00%     |
| Greeting/Small Talk      | 19                | 9.50%      |
| Out-of-Scope Query       | 32                | 16.00%     |
| Total                    | 200               | 100.00%    |

The queries were submitted by the twenty-five (25) testers (8 queries each on average) to capture diverse phrasing styles, vocabulary choices, and levels of specificity. Testers were instructed to phrase queries naturally rather than using scripted inputs.


5.7.2 Confusion Matrix

Table 16 presents the confusion matrix showing how the chatbot classified the two hundred (200) test queries across the six intent categories.

Table 16. Chatbot Intent Classification Confusion Matrix

| Actual \ Predicted       | Legal Service | Appt Scheduling | Status Check | General FAQ | Greeting | Out-of-Scope | Total |
|--------------------------|---------------|-----------------|--------------|-------------|----------|--------------|-------|
| Legal Service Inquiry    | 38            | 2               | 1            | 1           | 0        | 1            | 43    |
| Appointment Scheduling   | 2             | 28              | 2            | 1           | 0        | 2            | 35    |
| Status Checking          | 1             | 2               | 22           | 1           | 0        | 1            | 27    |
| General FAQ              | 1             | 0               | 0            | 42          | 0        | 1            | 44    |
| Greeting/Small Talk      | 0             | 0               | 0            | 1           | 18       | 0            | 19    |
| Out-of-Scope Query       | 2             | 2               | 1            | 1           | 1        | 25           | 32    |
| Total Predicted          | 44            | 34              | 26           | 47          | 19       | 30           | 200   |

The diagonal values represent correct classifications (true positives). The off-diagonal values represent misclassifications. Overall, one hundred seventy-three (173) of two hundred (200) queries were classified correctly, yielding a raw accuracy of 86.50%.


5.7.3 Per-Class Performance Metrics

Table 17 presents the precision, recall, and F1 score for each intent category.

Table 17. Per-Class Precision, Recall, and F1 Score

| Intent Category          | TP | FP | FN | Precision | Recall | F1 Score |
|--------------------------|----|----|----| ----------|--------|----------|
| Legal Service Inquiry    | 38 | 6  | 5  | 0.86      | 0.88   | 0.87     |
| Appointment Scheduling   | 28 | 6  | 7  | 0.82      | 0.80   | 0.81     |
| Status Checking          | 22 | 4  | 5  | 0.85      | 0.81   | 0.83     |
| General FAQ              | 42 | 5  | 2  | 0.89      | 0.95   | 0.92     |
| Greeting/Small Talk      | 18 | 1  | 1  | 0.95      | 0.95   | 0.95     |
| Out-of-Scope Query       | 25 | 5  | 7  | 0.83      | 0.78   | 0.81     |

Where:
- TP (True Positive) = correctly classified as this intent
- FP (False Positive) = incorrectly classified as this intent when it belongs to another
- FN (False Negative) = belongs to this intent but incorrectly classified as another
- Precision = TP / (TP + FP)
- Recall = TP / (TP + FN)
- F1 Score = 2 x (Precision x Recall) / (Precision + Recall)


5.7.4 Aggregated Performance Metrics

Table 18 presents the micro-averaged and macro-averaged performance metrics across all intent categories.

Table 18. Aggregated F1 Score Metrics

| Metric          | Precision | Recall | F1 Score |
|-----------------|-----------|--------|----------|
| Micro-Average   | 0.87      | 0.87   | 0.87     |
| Macro-Average   | 0.87      | 0.86   | 0.87     |

Micro-Average Computation:
Micro-Precision = Sum of all TP / Sum of all (TP + FP) = 173 / 200 = 0.87
Micro-Recall = Sum of all TP / Sum of all (TP + FN) = 173 / 200 = 0.87
Micro-F1 = 2 x (0.87 x 0.87) / (0.87 + 0.87) = 0.87

Micro-averaging aggregates all true positives, false positives, and false negatives across all classes before computing metrics. This gives equal weight to each individual query, making it sensitive to performance on high-volume categories.

Macro-Average Computation:
Macro-Precision = (0.86 + 0.82 + 0.85 + 0.89 + 0.95 + 0.83) / 6 = 0.87
Macro-Recall = (0.88 + 0.80 + 0.81 + 0.95 + 0.95 + 0.78) / 6 = 0.86
Macro-F1 = (0.87 + 0.81 + 0.83 + 0.92 + 0.95 + 0.81) / 6 = 0.87

Macro-averaging computes precision, recall, and F1 for each class independently, then averages. This gives equal weight to each intent category regardless of sample size, ensuring that minority classes are not overshadowed by dominant ones.

The close alignment between micro and macro averages (both F1 = 0.87) indicates that the chatbot performs consistently across intent categories without severe bias toward high-volume classes. This consistency suggests that the RAG knowledge base provides relatively uniform coverage across the defined intent taxonomy.


5.7.5 Qualitative Interpretation of F1 Results

The overall F1 score of 0.87 (both micro and macro) indicates that the Legal Ease chatbot achieves reliable intent classification for the majority of user queries. The following qualitative observations contextualize these metrics:

Strongest Performance -- Greeting/Small Talk (F1 = 0.95). The chatbot demonstrates near-perfect classification for conversational greetings and small talk. These queries have distinctive linguistic patterns that are easily distinguished from task-oriented intents. Tester 17 (IT Student) noted: "The chatbot always responded naturally to greetings and closings. It felt conversational and polite."

Strong Performance -- General FAQ (F1 = 0.92). The chatbot performs well on general frequently asked questions about services, operating hours, and procedures. This is attributed to the RAG knowledge base containing comprehensive documentation about Legal Ease services, which provides strong retrieval candidates for these queries. Tester 3 (IT Professional) observed: "For factual questions about what the platform offers, the chatbot pulls accurate information from its knowledge base consistently."

Moderate Performance -- Legal Service Inquiry (F1 = 0.87) and Status Checking (F1 = 0.83). These categories involve more specific queries that sometimes overlap linguistically. A query like "Can I check if my consultation is confirmed?" could be classified as either a status check or a legal service inquiry depending on the phrasing emphasis. The six (6) false positives for legal service inquiry and four (4) for status checking reflect this semantic overlap. Tester 5 (IT Professional) noted: "The chatbot occasionally misroutes queries that sit at the boundary between information retrieval and action execution."

Weakest Performance -- Appointment Scheduling (F1 = 0.81) and Out-of-Scope (F1 = 0.81). The appointment scheduling intent had the highest false negative count (7), meaning seven scheduling-related queries were misclassified as other intents. This aligns with the functional test case findings where the chatbot struggled with incomplete scheduling requests that did not contain explicit trigger phrases. The out-of-scope category also showed weaker performance (recall = 0.78), with seven queries that should have been flagged as out-of-scope instead being routed to other intent handlers. Tester 2 (IT Professional) observed: "Some out-of-scope queries that were phrased in a way that superficially resembled legal terminology were incorrectly treated as legitimate legal service inquiries."

The F1 score of 0.87 positions the Legal Ease chatbot within the expected performance range for RAG-based conversational systems in early deployment. Industry benchmarks for domain-specific chatbots typically range from 0.80 to 0.95, depending on the complexity of the intent taxonomy and the volume of training data. The primary areas for improvement are the action-oriented intents (scheduling, status checking) and out-of-scope boundary detection, which can be enhanced through expanded training examples, improved entity extraction, and refined intent boundary definitions.


5.8 Performance Assessment

This section presents the response time benchmarks and load behavior observations collected during the testing period.


5.8.1 Response Time Benchmarks

Table 19 presents the observed response times for key system operations, measured across multiple test executions.

Table 19. Response Time Benchmarks

| Operation                                     | Average Response Time | Observed Range     | Assessment       |
|-----------------------------------------------|----------------------|--------------------|------------------|
| Landing page initial load (public/init endpoint) | 1.4 seconds        | 0.9 - 2.1 seconds | Acceptable       |
| User login (Sanctum token generation)          | 320 ms              | 210 - 480 ms      | Acceptable       |
| Appointment list retrieval (paginated)         | 280 ms              | 180 - 420 ms      | Acceptable       |
| Appointment creation                           | 450 ms              | 310 - 650 ms      | Acceptable       |
| Admin dashboard statistics (cached)            | 190 ms              | 120 - 340 ms      | Acceptable       |
| Admin dashboard statistics (uncached)          | 680 ms              | 520 - 890 ms      | Acceptable       |
| PayMongo checkout session creation             | 1.8 seconds         | 1.2 - 2.6 seconds | Acceptable       |
| Chatbot response (standard query)              | 2.3 seconds         | 1.6 - 3.4 seconds | Acceptable       |
| Chatbot response (action execution)            | 3.1 seconds         | 2.2 - 4.5 seconds | Needs Monitoring |
| ML slot recommendation                         | 1.9 seconds         | 1.4 - 2.8 seconds | Acceptable       |
| Demand forecasting report                      | 2.4 seconds         | 1.8 - 3.2 seconds | Acceptable       |
| Message sending (with WebSocket broadcast)     | 180 ms              | 110 - 290 ms      | Good             |

The response times were assessed qualitatively by testers during their usage sessions. Tester 13 (IT Student) observed: "The appointment booking and messaging felt fast. The chatbot takes a moment to respond but it feels natural for a conversation." Tester 6 (IT Professional) provided technical context: "The chatbot response times of 2-3 seconds are within acceptable range for a system that processes queries through semantic embedding retrieval and LLM generation. The action execution path taking up to 4.5 seconds is worth monitoring as it approaches the threshold where users may perceive a lag."

The PayMongo checkout session creation time (average 1.8 seconds) is influenced by external API latency to the PayMongo servers and is outside the direct control of the application. Tester 9 (IT Professional) noted that "the loading state during payment processing is clearly communicated to the user, which mitigates the perceived wait time."

The caching strategy for admin dashboard statistics (120-second TTL per admin user) was noted as effective, reducing the response time from an average of 680 ms (uncached) to 190 ms (cached), a 72% improvement.


5.8.2 Load Behavior Observations

Table 20 presents the observed system behavior under simulated concurrent user loads.

Table 20. Load Behavior Observations

| Concurrent Users | API Response Time (Average) | System Behavior                              | Assessment       |
|------------------|-----------------------------|----------------------------------------------|------------------|
| 10               | 250 ms                      | All requests processed without delays         | Stable           |
| 25               | 310 ms                      | Minimal increase in response time             | Stable           |
| 50               | 480 ms                      | Noticeable but acceptable increase            | Stable           |
| 75               | 720 ms                      | Response time increases; all requests completed | Acceptable       |
| 100              | 1.1 seconds                 | Slower responses; occasional timeout on ML endpoints | Degraded |

The system maintained stable behavior up to fifty (50) concurrent users with response times remaining under 500 ms. At seventy-five (75) concurrent users, response times increased to an average of 720 ms, which remains functional but represents a noticeable slowdown. At one hundred (100) concurrent users, the system showed degraded performance with occasional timeouts on ML-dependent endpoints (chatbot responses, slot recommendations, demand forecasting) while core CRUD operations (appointments, payments, messaging) remained functional.

Tester 1 (IT Professional) provided architectural context: "The core Laravel API handles concurrent requests well. The bottleneck at higher loads is the Python FastAPI ML microservice, which processes requests sequentially for prediction operations. Adding request queuing or horizontal scaling for the ML service would improve high-load behavior."


5.9 Security Assessment Summary

Table 21 presents the results of targeted security tests conducted by the IT professional testers during the evaluation period.

Table 21. Security Test Results

| Test Category                  | Test Description                                                    | Result | Remarks                                                                                |
|--------------------------------|--------------------------------------------------------------------|--------|----------------------------------------------------------------------------------------|
| SQL Injection                  | Injection payloads in form fields, query parameters, and API bodies | Passed | Laravel Eloquent ORM uses parameterized queries preventing SQL injection               |
| Cross-Site Scripting (XSS)     | Script injection in messages, appointment notes, and feedback text  | Passed | React DOM escaping and server-side input sanitization prevent script execution          |
| Cross-Site Request Forgery     | Forged requests to state-changing endpoints                        | Passed | Laravel CSRF protection and Sanctum token authentication prevent unauthorized requests |
| Authentication Bypass          | Direct access to protected endpoints without valid tokens          | Passed | Sanctum middleware returns 401 for all unauthenticated requests                        |
| Authorization Bypass           | Client role accessing admin and cashier endpoints                  | Passed | Spatie Permission RBAC middleware returns 403 for unauthorized role access              |
| Session Fixation               | Attempt to reuse expired or stolen session tokens                  | Passed | Sanctum tokens are validated per request and expired tokens are rejected                |
| Sensitive Data Exposure        | Inspection of API responses for credential or token leakage        | Passed | API responses do not include passwords, raw tokens, or internal system information      |
| Clickjacking                   | Embedding the application in an iframe                             | Passed | X-Frame-Options: DENY header prevents iframe embedding                                 |
| Rate Limit Bypass              | Rapid requests exceeding configured thresholds                     | Passed | IP-based rate limiting enforced across authentication, chatbot, and API endpoints       |
| Insecure Direct Object Reference | Accessing other users appointments, messages, and payment records | Passed | Ownership validation enforced at the controller level for all user-scoped resources     |

All ten (10) security test categories passed without identified vulnerabilities. Tester 6 (IT Professional) summarized: "The security implementation follows a defense-in-depth approach: input validation at the request layer, parameterized queries at the database layer, token-based authentication and RBAC at the authorization layer, and security headers at the transport layer. For a legal management platform, this level of security is appropriate and well-implemented."


5.10 Summary of Findings

The comprehensive testing and evaluation of the Legal Ease system produced the following key findings:

Functional Testing. Fifty-one (51) test cases were executed across eight (8) system modules with an overall pass rate of 82.35%. Nine (9) defects were identified: zero (0) critical, zero (0) major, two (2) moderate, six (6) minor, and one (1) cosmetic. The two moderate defects are confined to the AI chatbot module and relate to NLP limitations rather than system failures.

Alpha Testing. Twenty-five (25) testers confirmed that core business workflows (registration, appointment booking, payment processing, messaging) operate reliably in a controlled environment. Five recurring themes emerged: core functionality reliability, AI feature maturity gap, admin interface complexity, security confidence, and scalability potential.

AI Chatbot Performance. The chatbot achieved an F1 score of 0.87 (both micro and macro averaged) across six intent categories with two hundred (200) test queries. The strongest performance was in greeting/small talk (F1 = 0.95) and general FAQ (F1 = 0.92). The weakest performance was in appointment scheduling (F1 = 0.81) and out-of-scope detection (F1 = 0.81), which represent areas for improvement through expanded training data and refined intent classification.

Performance. The system maintains acceptable response times under normal usage conditions (up to 50 concurrent users). Core CRUD operations respond within 200-500 ms. Chatbot responses average 2.3 seconds for standard queries. System degradation begins at approximately 75-100 concurrent users, primarily affecting ML-dependent endpoints.

Security. All ten (10) security test categories passed. The implementation employs parameterized queries, input sanitization, token-based authentication, RBAC, security headers, and audit logging. No vulnerabilities were identified during the testing period.


5.11 Discussion

The testing and evaluation of the Legal Ease system through functional test cases, alpha testing, AI classification metrics, and performance and security assessments reveals a platform that is fundamentally reliable in its core operations while presenting identifiable areas for growth in its more experimental features.

The 82.35% functional test case pass rate reflects a system with no critical or major defects. The nine identified defects are predominantly usability refinements (error message clarity, interface documentation, formatting) rather than functional failures. This defect profile is characteristic of software that has undergone iterative development with attention to core functionality while edge cases and polish items remain to be addressed. The zero critical and zero major defect count is notable for a platform integrating traditional web application features with machine learning microservices and LLM-powered conversational AI.

The alpha testing thematic analysis reveals a consistent narrative: testers trust the system for what it was primarily designed to do. The universal confirmation of core workflow reliability across all twenty-five testers establishes a strong functional foundation. The AI maturity gap theme, identified by eighteen of twenty-five testers, is not a criticism of the system design but rather an observation that newer technology components naturally require more iteration than established web development patterns. The chatbot, ML recommendations, and demand forecasting are functional and architecturally sound, but they require the accumulation of production usage data and continued model refinement to reach their full potential.

The AI chatbot F1 score of 0.87 provides a quantifiable benchmark for the current system capabilities. The near-identical micro and macro F1 scores indicate balanced performance across intent categories without systematic bias. The classification strengths (greetings, FAQ) align with areas where the RAG knowledge base has the most comprehensive documentation. The classification weaknesses (scheduling actions, out-of-scope detection) align with areas requiring more nuanced intent boundary definitions and multi-turn conversation management. These findings provide a clear roadmap for improvement: expand training examples for action-oriented intents, implement multi-intent recognition within single messages, and refine the out-of-scope detection boundary to reduce false routing of ambiguous queries.

The performance benchmarks confirm that the system operates within acceptable thresholds for its intended deployment scale. The architectural decision to separate the ML service as a Python FastAPI microservice provides flexibility for independent optimization and scaling. The identified bottleneck at higher concurrent loads is localized to the ML microservice rather than the core Laravel API, which means that addressing scalability can be targeted without restructuring the main application.

The clean security assessment across all ten test categories reflects deliberate security implementation throughout the development process. The defense-in-depth approach -- spanning input validation, parameterized queries, authentication, authorization, security headers, and audit logging -- creates multiple protective layers appropriate for a platform handling legal service data and financial transactions.

In summation, the Legal Ease system demonstrates readiness for controlled deployment with its core functionality verified as reliable, its security posture validated, and its AI features functional with a documented performance baseline and clear trajectory for continued improvement.
