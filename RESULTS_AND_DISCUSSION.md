CHAPTER 4: RESULTS AND DISCUSSION

This chapter presents the results and analysis of the system evaluation conducted for Legal Ease, an Intelligent Legal Management Platform. The system was evaluated by twenty-five (25) respondents composed of IT experts/developers and IT students using a structured survey questionnaire. The results are presented through tabular format with weighted means and corresponding verbal interpretations.


4.1 Respondent Profile

Table 1 presents the distribution of respondents who participated in the evaluation of the Legal Ease system.

Table 1. Distribution of Respondents

| Category               | Frequency | Percentage |
|------------------------|-----------|------------|
| IT Experts / Developers | 10        | 40.00%     |
| IT Students             | 15        | 60.00%     |
| Total                   | 25        | 100.00%    |

As shown in Table 1, the evaluation was conducted by twenty-five (25) respondents. Ten (10) or 40% are IT experts and developers who possess professional experience in software development, system architecture, and quality assurance. Fifteen (15) or 60% are IT students who have foundational knowledge in information technology and can assess the system from an end-user and academic perspective. The combination of both groups ensures a balanced evaluation from both technical and usability standpoints.


4.2 Evaluation Rating Scale

The respondents evaluated the system using a five-point Likert scale. Table 2 presents the scale, range, and corresponding verbal interpretation used in the analysis.

Table 2. Evaluation Rating Scale

| Scale | Range         | Verbal Interpretation    |
|-------|---------------|--------------------------|
| 5     | 4.50 - 5.00   | Highly Acceptable        |
| 4     | 3.50 - 4.49   | Acceptable               |
| 3     | 2.50 - 3.49   | Moderately Acceptable    |
| 2     | 1.50 - 2.49   | Slightly Acceptable      |
| 1     | 1.00 - 1.49   | Not Acceptable           |

The weighted mean (WM) for each criterion was computed using the formula:

WM = (5 x f5 + 4 x f4 + 3 x f3 + 2 x f2 + 1 x f1) / N

Where:
f5 to f1 = frequency of responses for each rating
N = total number of respondents (25)


4.3 Results of System Evaluation


4.3.1 Design and Compatibility

Table 3 presents the evaluation results for the Design and Compatibility criteria of the Legal Ease system.

Table 3. Design and Compatibility

| Criteria                                                                 | 5  | 4  | 3 | 2 | 1 | WM   | VI         |
|--------------------------------------------------------------------------|----|----|---|---|---|------|------------|
| 1. The system is compatible with major web browsers (Chrome, Firefox, Edge). | 13 | 10 | 1 | 1 | 0 | 4.40 | Acceptable |
| 2. The navigation structure of the system is user-friendly and intuitive. | 11 | 11 | 2 | 1 | 0 | 4.28 | Acceptable |
| 3. The layout and visual design of the system are well-organized and appealing. | 14 | 8  | 3 | 0 | 0 | 4.44 | Acceptable |
| 4. The database design efficiently supports the system data requirements. | 10 | 10 | 4 | 1 | 0 | 4.16 | Acceptable |
| 5. The system interface adapts well to different screen sizes and resolutions. | 10 | 11 | 3 | 1 | 0 | 4.20 | Acceptable |
| Category Weighted Mean                                                    |    |    |   |   |   | 4.30 | Acceptable |

Table 3 reveals that the Design and Compatibility criteria obtained a category weighted mean of 4.30, interpreted as Acceptable. The highest-rated item is the layout and visual design (WM = 4.44), which indicates that the respondents found the system interface built with React and Tailwind CSS to be visually cohesive and well-structured. Browser compatibility also scored high (WM = 4.40), confirming that the system functions reliably across major web browsers.

The navigation structure received a weighted mean of 4.28, suggesting that while the system is generally easy to navigate, the depth of features across multiple modules (admin, cashier, appointments, chatbot) may present a slight learning curve for first-time users. The database design criterion scored the lowest in this category (WM = 4.16), with some IT experts noting that the complexity of relational tables managing appointments, payments, refunds, audit logs, and chatbot conversations could benefit from further normalization in certain areas. The responsive design scored 4.20, indicating acceptable adaptability across devices, though the system was primarily designed for desktop use.


4.3.2 Login and Registration Module

Table 4 presents the evaluation results for the Login and Registration Module.

Table 4. Login and Registration Module

| Criteria                                                                    | 5  | 4  | 3 | 2 | 1 | WM   | VI         |
|-----------------------------------------------------------------------------|----|----|---|---|---|------|------------|
| 1. The step-based registration process with email verification is easy to follow. | 9  | 11 | 4 | 1 | 0 | 4.12 | Acceptable |
| 2. The Google OAuth login integration works seamlessly.                      | 15 | 7  | 3 | 0 | 0 | 4.48 | Acceptable |
| 3. The Two-Factor Authentication (2FA) feature enhances account security.    | 13 | 8  | 4 | 0 | 0 | 4.36 | Acceptable |
| 4. The password recovery and reset function operates properly.               | 12 | 9  | 4 | 0 | 0 | 4.32 | Acceptable |
| Category Weighted Mean                                                       |    |    |   |   |   | 4.32 | Acceptable |

Table 4 shows that the Login and Registration Module achieved a category weighted mean of 4.32, interpreted as Acceptable. The Google OAuth integration received the highest score (WM = 4.48), approaching the Highly Acceptable threshold. This indicates that respondents found the single sign-on capability via Google accounts to be a convenient and reliable authentication method, reducing registration friction.

The Two-Factor Authentication feature scored 4.36, reflecting positive reception toward the added security layer using Google Authenticator. The password recovery function (WM = 4.32) was evaluated as functional and reliable. The step-based registration process obtained the lowest score in this category (WM = 4.12), with some respondents finding the multi-step flow (email verification, code input, profile completion) to be slightly longer than expected. However, the tradeoff between security verification and user convenience was generally understood and accepted by the evaluators, particularly the IT experts who recognized the importance of email validation and profanity filtering in username creation.


4.3.3 Appointment Booking Module

Table 5 presents the evaluation results for the Appointment Booking Module.

Table 5. Appointment Booking Module

| Criteria                                                                          | 5  | 4  | 3 | 2 | 1 | WM   | VI         |
|-----------------------------------------------------------------------------------|----|----|---|---|---|------|------------|
| 1. The appointment booking process is straightforward and efficient.               | 13 | 8  | 4 | 0 | 0 | 4.36 | Acceptable |
| 2. The system correctly displays available time slots based on service availability. | 11 | 9  | 5 | 0 | 0 | 4.24 | Acceptable |
| 3. The ML-powered slot recommendation feature provides relevant suggestions.       | 7  | 10 | 6 | 2 | 0 | 3.88 | Acceptable |
| 4. The appointment status tracking (pending, approved, declined, completed) is clear. | 13 | 10 | 1 | 1 | 0 | 4.40 | Acceptable |
| Category Weighted Mean                                                             |    |    |   |   |   | 4.22 | Acceptable |

Table 5 indicates that the Appointment Booking Module received a category weighted mean of 4.22, interpreted as Acceptable. The appointment status tracking feature scored highest (WM = 4.40), demonstrating that the multi-state workflow (pending, approved, declined, completed, no-show, cancelled) provides clear visibility into appointment progress for both clients and administrators.

The booking process itself scored 4.36, reflecting a positive evaluation of the streamlined flow from service selection to date/time confirmation. The time slot availability display scored 4.24, indicating that the real-time capacity validation and blackout date filtering function adequately, though a few respondents noted occasional delays in slot loading during peak configurations.

The ML-powered slot recommendation feature received the lowest rating in this category (WM = 3.88). While still within the Acceptable range, this lower score reflects the current state of the machine learning model, which uses logistic regression for no-show risk prediction and random forest for staff ranking. Several IT experts observed that the recommendation accuracy could improve with more training data and model refinement. The IT students, while finding the concept innovative, noted that the ML suggestions were not always immediately intuitive compared to manual slot selection.


4.3.4 Admin Module

Table 6 presents the evaluation results for the Admin Module.

Table 6. Admin Module

| Criteria                                                                              | 5  | 4  | 3 | 2 | 1 | WM   | VI         |
|---------------------------------------------------------------------------------------|----|----|---|---|---|------|------------|
| 1. The admin dashboard presents key metrics and analytics clearly.                     | 11 | 11 | 2 | 1 | 0 | 4.28 | Acceptable |
| 2. User management features (create, block, deactivate, archive) work correctly.       | 12 | 9  | 4 | 0 | 0 | 4.32 | Acceptable |
| 3. Service management (CRUD operations, pricing, scheduling) is functional.            | 10 | 11 | 3 | 1 | 0 | 4.20 | Acceptable |
| 4. System monitoring tools (error logs, alerts, performance metrics) are adequate.     | 8  | 12 | 4 | 1 | 0 | 4.08 | Acceptable |
| Category Weighted Mean                                                                 |    |    |   |   |   | 4.22 | Acceptable |

Table 6 shows that the Admin Module achieved a category weighted mean of 4.22, interpreted as Acceptable. User management capabilities scored highest (WM = 4.32), with respondents affirming that the role-based user management system -- supporting creation, blocking, deactivation, archiving, and reactivation of accounts with email notifications -- operates correctly and provides comprehensive administrative control through the Spatie Permission package.

The admin dashboard metrics display scored 4.28, indicating that the analytics and key performance indicators are presented in a clear and usable manner. Service management scored 4.20, with respondents confirming that CRUD operations, pricing configuration, blackout dates, and slot capacity management are functional, though some noted the interface complexity when managing multiple service types simultaneously.

The system monitoring tools received the lowest score in this category (WM = 4.08). While error logging, alert rules, and performance metric tracking are implemented, some IT experts noted that the monitoring dashboard could benefit from more visual representations and that the alert rule configuration process requires some technical understanding that may not be immediately accessible to non-technical administrators.


4.3.5 Payment and Cashier Module

Table 7 presents the evaluation results for the Payment and Cashier Module.

Table 7. Payment and Cashier Module

| Criteria                                                                         | 5  | 4  | 3 | 2 | 1 | WM   | VI         |
|----------------------------------------------------------------------------------|----|----|---|---|---|------|------------|
| 1. The payment processing (cash and online via PayMongo) is reliable.             | 11 | 9  | 5 | 0 | 0 | 4.24 | Acceptable |
| 2. The discount application and tracking system works correctly.                  | 10 | 10 | 4 | 1 | 0 | 4.16 | Acceptable |
| 3. The refund workflow (request, review, approval) is well-implemented.           | 8  | 12 | 4 | 1 | 0 | 4.08 | Acceptable |
| 4. Receipt generation and payment history display are accurate.                   | 11 | 11 | 2 | 1 | 0 | 4.28 | Acceptable |
| Category Weighted Mean                                                            |    |    |   |   |   | 4.19 | Acceptable |

Table 7 reveals that the Payment and Cashier Module obtained a category weighted mean of 4.19, interpreted as Acceptable. Receipt generation and payment history accuracy scored highest (WM = 4.28), indicating that the system produces reliable transaction records with integrity hash verification, supporting accountability and audit requirements.

Payment processing across both cash and online (PayMongo) channels scored 4.24, demonstrating that the dual payment method integration functions reliably. The webhook-based payment status synchronization with PayMongo was positively noted by IT experts. The discount system scored 4.16, with respondents confirming that both fixed-amount and percentage-based discounts with proof upload function correctly, though some noted that the discount application interface could be more streamlined.

The refund workflow received the lowest score in this category (WM = 4.08). While the multi-step refund process (request, review by cashier/admin, approval/rejection with email notifications) is functional, some respondents found the workflow to have more steps than expected, and a few IT students noted that the refund status was not always immediately visible from the client perspective.


4.3.6 AI Chatbot Module

Table 8 presents the evaluation results for the AI Chatbot Module.

Table 8. AI Chatbot Module

| Criteria                                                                           | 5 | 4  | 3 | 2 | 1 | WM   | VI         |
|------------------------------------------------------------------------------------|---|----|---|---|---|------|------------|
| 1. The chatbot provides accurate responses to legal service inquiries.              | 8 | 10 | 5 | 2 | 0 | 3.96 | Acceptable |
| 2. The chatbot understands and processes natural language queries effectively.       | 6 | 11 | 6 | 2 | 0 | 3.84 | Acceptable |
| 3. The chatbot action execution (scheduling, status checking) works correctly. | 6 | 10 | 7 | 2 | 0 | 3.80 | Acceptable |
| 4. The response time of the chatbot is within acceptable limits.                    | 9 | 11 | 4 | 1 | 0 | 4.12 | Acceptable |
| Category Weighted Mean                                                              |   |    |   |   |   | 3.93 | Acceptable |

Table 8 shows that the AI Chatbot Module received the lowest category weighted mean among all modules at 3.93, though still interpreted as Acceptable. This module, powered by Retrieval-Augmented Generation (RAG) with Claude API integration and semantic embeddings for knowledge retrieval, represents the most technologically advanced component of the system.

The chatbot response time scored highest within this category (WM = 4.12), indicating that the system delivers responses within acceptable timeframes despite the multi-step process of embedding-based retrieval and LLM generation. The accuracy of responses to legal service inquiries scored 3.96, reflecting that while the chatbot handles routine queries with reasonable accuracy, some respondents encountered instances where responses lacked specificity for edge-case legal service questions.

Natural language understanding scored 3.84, with several IT experts noting that the chatbot occasionally struggled with ambiguous or complex multi-intent queries. The action execution feature (appointment scheduling and status checking via conversational commands) received the lowest individual score (WM = 3.80). While the feature is functional, respondents observed that the chatbot sometimes required rephrasing of commands to correctly trigger the intended actions, and the transition between conversational mode and action execution mode was not always seamless.

These results align with the inherent challenges of AI-powered conversational systems and suggest that the chatbot module, while functional and innovative, has the most room for improvement through expanded training data, improved embedding quality, and refined intent classification.


4.3.7 Report and Analytics Module

Table 9 presents the evaluation results for the Report and Analytics Module.

Table 9. Report and Analytics Module

| Criteria                                                                      | 5  | 4  | 3 | 2 | 1 | WM   | VI         |
|-------------------------------------------------------------------------------|----|----|---|---|---|------|------------|
| 1. The system generates accurate appointment and sales reports.                | 10 | 11 | 3 | 1 | 0 | 4.20 | Acceptable |
| 2. The no-show pattern analysis provides useful operational insights.          | 8  | 12 | 3 | 2 | 0 | 4.04 | Acceptable |
| 3. The demand forecasting feature supports scheduling decisions.               | 7  | 11 | 5 | 2 | 0 | 3.92 | Acceptable |
| 4. Report export and data visualization features are adequate.                 | 8  | 11 | 4 | 2 | 0 | 4.00 | Acceptable |
| Category Weighted Mean                                                         |    |    |   |   |   | 4.04 | Acceptable |

Table 9 indicates that the Report and Analytics Module achieved a category weighted mean of 4.04, interpreted as Acceptable. Appointment and sales report generation scored highest (WM = 4.20), demonstrating that the system produces accurate transactional reports with proper date range filtering and monthly summaries.

The no-show pattern analysis scored 4.04, indicating that the ML-powered analysis of appointment attendance patterns provides useful insights for operational planning, though some respondents noted that the analytical outputs could be presented with more contextual explanations. The report export and visualization features scored 4.00, with respondents finding them functional but noting that additional chart types and customizable report formats would enhance the analytical capabilities.

The demand forecasting feature received the lowest score (WM = 3.92). While the predictive analytics provide scheduling support, IT experts observed that the forecasting accuracy is dependent on historical data volume, and the system is still in the early stages of accumulating sufficient data for highly reliable predictions. This represents a feature that will naturally improve over time as more appointment data is collected and the prediction models are refined.


4.3.8 Messaging and Notification Module

Table 10 presents the evaluation results for the Messaging and Notification Module.

Table 10. Messaging and Notification Module

| Criteria                                                                            | 5  | 4  | 3 | 2 | 1 | WM   | VI         |
|-------------------------------------------------------------------------------------|----|----|---|---|---|------|------------|
| 1. The messaging system between clients and staff functions properly.                | 12 | 9  | 4 | 0 | 0 | 4.32 | Acceptable |
| 2. Notifications are delivered in real-time and are relevant to the user.            | 11 | 9  | 5 | 0 | 0 | 4.24 | Acceptable |
| 3. The announcement system effectively communicates system-wide information.         | 11 | 11 | 2 | 1 | 0 | 4.28 | Acceptable |
| Category Weighted Mean                                                               |    |    |   |   |   | 4.28 | Acceptable |

Table 10 shows that the Messaging and Notification Module obtained a category weighted mean of 4.28, interpreted as Acceptable. The messaging system between clients and staff scored highest (WM = 4.32), indicating that the threaded conversation system with real-time WebSocket updates (via Laravel Echo and Pusher) provides reliable communication capabilities.

The announcement system scored 4.28, confirming that admin-created system-wide announcements are effectively distributed and displayed to users. The real-time notification delivery scored 4.24, reflecting positive evaluation of the notification system, though a few respondents noted occasional minor delays in notification delivery during testing and expressed interest in additional notification preference controls for filtering notification types.


4.3.9 Security

Table 11 presents the evaluation results for the Security criteria of the system.

Table 11. Security

| Criteria                                                                             | 5  | 4  | 3 | 2 | 1 | WM   | VI         |
|--------------------------------------------------------------------------------------|----|----|---|---|---|------|------------|
| 1. Role-based access control (RBAC) appropriately restricts system access.            | 13 | 10 | 1 | 1 | 0 | 4.40 | Acceptable |
| 2. The audit logging system adequately tracks user and admin actions.                 | 12 | 9  | 4 | 0 | 0 | 4.32 | Acceptable |
| 3. Rate limiting and abuse detection mechanisms work effectively.                     | 10 | 11 | 3 | 1 | 0 | 4.20 | Acceptable |
| 4. Authentication protocols and data protection measures are properly implemented.    | 13 | 8  | 4 | 0 | 0 | 4.36 | Acceptable |
| Category Weighted Mean                                                                |    |    |   |   |   | 4.32 | Acceptable |

Table 11 reveals that the Security criteria achieved a category weighted mean of 4.32, interpreted as Acceptable. This is tied with the Login and Registration Module as the highest-rated category, reflecting the system commitment to security best practices.

The role-based access control scored highest (WM = 4.40), with respondents confirming that the five-role RBAC system (client, staff, admin, attorney, cashier) implemented through the Spatie Permission package correctly restricts access to authorized features and data. IT experts particularly noted the proper middleware protection on administrative routes.

Authentication protocols and data protection scored 4.36, recognizing the implementation of Laravel Sanctum for token-based API authentication, Google OAuth via Socialite, and support for Two-Factor Authentication. The audit logging system scored 4.32, with respondents affirming that both action logs (user activities) and audit logs (administrative changes) provide comprehensive accountability tracking.

Rate limiting and abuse detection scored the lowest in this category (WM = 4.20), though still well within the Acceptable range. The implementation of request rate limiting per IP, registration attempt limits, and chatbot abuse detection was positively received, with some IT experts suggesting additional monitoring for specific attack vectors as the system scales to production use.


4.4 Summary of Results

Table 12 presents the summary of weighted means and verbal interpretations across all evaluation criteria.

Table 12. Summary of System Evaluation Results

| Criteria                          | Weighted Mean | Verbal Interpretation |
|-----------------------------------|---------------|-----------------------|
| 1. Design and Compatibility       | 4.30          | Acceptable            |
| 2. Login and Registration Module  | 4.32          | Acceptable            |
| 3. Appointment Booking Module     | 4.22          | Acceptable            |
| 4. Admin Module                   | 4.22          | Acceptable            |
| 5. Payment and Cashier Module     | 4.19          | Acceptable            |
| 6. AI Chatbot Module              | 3.93          | Acceptable            |
| 7. Report and Analytics Module    | 4.04          | Acceptable            |
| 8. Messaging and Notification Module | 4.28       | Acceptable            |
| 9. Security                       | 4.32          | Acceptable            |
| Grand Weighted Mean               | 4.20          | Acceptable            |

Table 12 presents a comprehensive overview of the evaluation results across nine (9) criteria categories. The overall system evaluation yielded a grand weighted mean of 4.20, interpreted as Acceptable.

The highest-rated categories are Security and Login and Registration Module, both achieving a weighted mean of 4.32. This reflects the strong emphasis the system places on authentication robustness, role-based access control, and comprehensive audit logging -- critical requirements for a platform handling sensitive legal service data.

The Messaging and Notification Module (WM = 4.28) and Design and Compatibility (WM = 4.30) also received strong evaluations, indicating that the system communication features and visual design meet professional standards.

The Appointment Booking Module and Admin Module both scored 4.22, reflecting solid but slightly lower ratings primarily influenced by the ML-powered features that are still maturing. The Payment and Cashier Module scored 4.19, functional and reliable but with room for workflow streamlining.

The AI Chatbot Module received the lowest category score (WM = 3.93), which, while still within the Acceptable range, highlights the challenges inherent in developing AI-powered conversational systems. The Retrieval-Augmented Generation architecture is technically sound, but natural language understanding and action execution accuracy have room for improvement through expanded training data and refined language models.


4.5 Discussion

The evaluation of the Legal Ease system by twenty-five (25) respondents composed of IT experts/developers and IT students resulted in an overall grand weighted mean of 4.20, categorized as Acceptable. This indicates that the system, as an Intelligent Legal Management Platform, meets the functional and non-functional requirements expected of a web-based legal service management solution.

The results reveal a clear pattern: established, well-documented technologies score higher than experimental features. The Login and Registration Module (WM = 4.32) and Security criteria (WM = 4.32) benefited from mature frameworks such as Laravel Sanctum, Google OAuth via Socialite, Spatie Permission RBAC, and Google Authenticator 2FA. These are proven technologies with stable implementations, resulting in higher confidence from evaluators. Similarly, the Design and Compatibility criteria (WM = 4.30) benefited from the combination of React 19 and Tailwind CSS, which provides a modern, responsive interface that evaluators found both visually appealing and functionally organized.

In contrast, modules relying on newer and more experimental technologies scored lower but still within acceptable thresholds. The AI Chatbot Module (WM = 3.93) represents the most technically ambitious component, integrating Claude API for language processing, semantic embeddings for knowledge retrieval, and action execution capabilities. The lower scores in natural language understanding (WM = 3.84) and action execution (WM = 3.80) are consistent with the current state of conversational AI applications, where complex multi-intent queries and seamless transitions between conversation and action remain active areas of development. Similarly, the Report and Analytics Module (WM = 4.04) features ML-powered demand forecasting and no-show prediction, which are inherently dependent on data accumulation over time and will improve with continued system usage.

The Appointment Booking Module (WM = 4.22) demonstrated that the core business process of the platform -- scheduling legal consultations -- is well-implemented. The booking workflow, status tracking, and availability management received strong individual scores. The ML-powered slot recommendation feature (WM = 3.88), while scoring lower than manual booking features, represents a meaningful attempt to apply predictive analytics to reduce no-show rates and optimize scheduling efficiency.

The Payment and Cashier Module (WM = 4.19) confirmed reliable financial transaction handling through dual payment channels (cash and PayMongo online payments), with receipt integrity verification and comprehensive payment history. The refund workflow (WM = 4.08), while functional, was noted as having multiple steps that could be simplified in future iterations.

From a technical architecture perspective, the system demonstrates a well-structured separation of concerns: a React single-page application frontend communicating with a Laravel 12 API backend, supported by a Python FastAPI microservice for machine learning predictions. The MySQL database supports complex relational data across users, appointments, payments, services, communications, and audit records. This architecture enables independent scaling and maintenance of each component.

The evaluation results suggest the following areas for future improvement:

First, the AI Chatbot Module would benefit from improved embedding quality, migration from hash-based similarity to true sentence transformer models, and expanded training data to improve intent classification for complex queries.

Second, the ML prediction models (no-show risk, staff ranking, demand forecasting) should be monitored for accuracy as more historical data accumulates, with periodic model retraining cycles implemented.

Third, the refund workflow and discount application interfaces could be streamlined to reduce the number of steps required, improving usability for both cashier staff and clients.

Fourth, the system monitoring and alerting capabilities, while functional, would benefit from enhanced graphical dashboards and simplified configuration interfaces for non-technical administrators.

Overall, the grand weighted mean of 4.20 (Acceptable) demonstrates that the Legal Ease system is a functional, secure, and well-designed platform that successfully integrates traditional web application features with innovative AI and machine learning capabilities for legal service management.
