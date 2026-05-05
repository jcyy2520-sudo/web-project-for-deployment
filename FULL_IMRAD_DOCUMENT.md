# LegalEase: Controlled Prototype Evaluation of a Web-Based Platform for Notarial Scheduling and Client Support

John Christian D. Fajutagana, Mark Rhamzel E. Mogol, Uriel M. Melendres

Mindoro State University - Bongabong Campus
Labasan, Bongabong, Oriental Mindoro, Philippines

Student Researchers

christiannjc25@gmail.com, markjamesrhamzel@gmail.com, urielmelendres@gmail.com

## Abstract

This study developed and evaluated LegalEase, a web-based legal service management platform for appointment scheduling, authentication, payments, reporting, messaging, administrative control, predictive analytics, and conversational assistance in a notarial-office context. To keep the study evidence-based, the paper reports only controlled quantitative evaluation results that are directly documented in the project records. The evaluation dataset consisted of fifty-one (51) functional test cases across eight modules, two hundred (200) chatbot benchmark queries across six intent classes, saved no-show model metadata trained on seventy-six (76) labeled appointment outcomes, and controlled response-time, load, and security tests. Functional testing produced an overall pass rate of 82.35% (42/51) with zero critical and zero major defects and a defect density of 0.18 per test case. The chatbot achieved 86.50% intent-classification accuracy with both micro- and macro-F1 scores of 0.87. The current saved ML snapshot selected logistic regression over XGBoost and achieved ROC-AUC = 0.7812, precision = 0.7143, recall = 0.6250, F1 = 0.6667, accuracy = 0.6875, and Brier score = 0.1975. The platform remained stable up to fifty (50) concurrent users, while degradation at higher loads was concentrated in ML-dependent endpoints rather than core transactional features. All ten targeted security categories passed. The findings indicate that LegalEase is viable as a pilot-ready prototype with strong core workflow reliability and security, but AI-assisted features require larger real-world datasets and further quantitative validation before broader operational rollout.

**Keywords:** legal service management system, notarial scheduling, RAG-based chatbot, quantitative prototype evaluation, decision support

## 1. Introduction

Legal service operations remain heavily burdened by fragmented scheduling, manual client coordination, delayed communication, and limited analytical support for administrative decisions. In small and medium-sized legal or notarial offices, these issues create preventable no-shows, inefficient time-slot allocation, delayed payment reconciliation, and weak visibility into demand trends. Comparable appointment-based sectors have long reported these coordination problems, particularly where scheduling remains manual and operational data is underused (Gupta & Denton, 2008; Dantas et al., 2018; Zhao et al., 2017). In the Philippine setting, web-based administrative systems have shown practical value under resource-constrained conditions, but integrated notarial-service platforms remain limited in documented evaluation (Ampuan & Delena, 2021). LegalEase was developed to address these gaps through a unified web platform that integrates role-based access control, appointment management, cashiering, real-time messaging, reporting, predictive analytics, and a retrieval-augmented chatbot.

The study focused on two parallel concerns. First, the platform had to prove that its core business workflows were reliable enough for controlled pilot use. Second, the more experimental components, particularly the chatbot and machine-learning features, had to be evaluated with explicit metrics rather than vague claims of intelligence. This dual focus is important because systems that combine conventional transactional modules with AI components often show uneven maturity across features. Retrieval-augmented generation and domain-specific chatbots can improve access to information, but performance typically weakens on multi-intent and action-oriented interactions if the orchestration layer remains immature (Lewis et al., 2020; Adamopoulou & Moussiades, 2020).

Specifically, the study sought to answer the following questions:

1. Does Legal Ease perform its core legal-office workflows reliably under structured functional testing?
2. What defect profile remains after structured module testing?
3. What measurable performance does the chatbot achieve on intent classification and what predictive quality does the current no-show model demonstrate?
4. How does the platform behave in terms of latency, concurrent load, and targeted security controls?

Accordingly, the study aimed to (1) design and develop an integrated web-based platform for notarial scheduling, payment handling, messaging, and administrative control; (2) quantify its functional reliability, defect profile, and operational performance under controlled conditions; and (3) establish a defensible baseline for its AI-assisted components through documented chatbot and machine-learning metrics. The study was deliberately bounded to controlled prototype evaluation. It does not claim real-world deployment outcomes, no-show reduction effects, or population-level service transformation.

## 2. Methodology

### 2.1 Research Design

The study used a design-and-development research approach with a quantitative product evaluation. Quantitative evidence was gathered from functional pass rates, defect counts, chatbot classification metrics, machine-learning validation metrics, response-time observations, concurrent-load observations, and security test outcomes.

System construction followed an iterative backlog-driven process inspired by Agile Scrum. However, because formal sprint burndown records, velocity measurements, and role logs were not preserved as research data, the study reports the process as iterative incremental development rather than claiming a formal Scrum process evaluation. Development work was organized into four practical increments: (1) core identity and appointment workflows, (2) administration, payment, and messaging workflows, (3) AI chatbot and decision-support integration, and (4) monitoring, hardening, and evaluation. Backlog prioritization favored high-risk and user-critical features first, particularly authentication, scheduling constraints, payment integrity, and role isolation. Each increment ended with integration checks, defect triage, and reprioritization of unresolved issues.

### 2.2 System Architecture

Legal Ease uses a three-tier web architecture composed of a React and Tailwind CSS frontend, a Laravel API backend, and a Python FastAPI microservice for machine-learning training and inference. Persistent storage uses MySQL, while caching and rate limiting are handled through Laravel services. Real-time messaging and notifications are delivered through WebSocket-based broadcasting, and online payments are processed through PayMongo. The chatbot uses a retrieval-augmented generation pipeline that combines embedded knowledge documents, semantic retrieval, role-aware prompting, and large-language-model response generation.

This architecture was selected to isolate computationally expensive AI services from core transactional operations. As a result, failures or slowdowns in AI endpoints can be monitored and optimized independently without destabilizing core CRUD workflows such as booking, payments, and messaging.

### 2.3 Evaluation Data Sources

The quantitative evaluation drew on four documented evidence sources: structured functional testing, a controlled chatbot benchmark, saved machine-learning validation metadata, and controlled non-functional assessment covering response time, concurrent load, and targeted security controls.

Table 1. Quantitative Evaluation Matrix

| Evidence Source | Instrument / Metric | Scope |
| --- | --- | --- |
| Functional testing | Pass/fail execution and defect logging | 51 cases across 8 major modules |
| Chatbot benchmark | Intent classification test set with confusion-matrix metrics | 200 natural-language queries across 6 intent classes |
| ML validation | Saved model metadata and holdout-set evaluation | 76 labeled appointment outcomes, 80/20 stratified split |
| Non-functional assessment | Response-time, load, and security checks | latency, concurrency, and 10 targeted security categories |

This evaluation was intentionally limited to measurable system outputs recorded in the repository. No questionnaire-based or exploratory feedback data were used in the final analysis.

### 2.4 Evaluation Environment and Instruments

All evaluations were conducted in a controlled staging environment using role-scoped accounts and real database-backed application logic. Evidence was collected through the structured procedures summarized in Table 1. The fifty-one (51) functional test cases covered Appointment Booking, Authentication, Payment and Cashier, Admin Control, Security, Reports and Analytics, Messaging, and the AI Chatbot. Each failed case was logged and classified by severity as critical, major, moderate, minor, or cosmetic so that reliability and defect concentration could be quantified together.

### 2.5 Chatbot Benchmark Procedure

The chatbot was evaluated using two hundred (200) natural-language benchmark queries distributed across six intent categories: Legal Service Inquiry, Appointment Scheduling, Status Checking, General FAQ, Greeting or Small Talk, and Out-of-Scope Query. A confusion matrix was then constructed and the following metrics were computed:

- Accuracy = correctly classified queries / total queries
- Precision = TP / (TP + FP)
- Recall = TP / (TP + FN)
- F1 score = 2 x (Precision x Recall) / (Precision + Recall)

Both micro- and macro-averaged F1 scores were reported so that overall accuracy and class balance could be evaluated simultaneously.

### 2.6 Machine-Learning Validation Procedure

The decision-support service currently trains and compares logistic regression and XGBoost classifiers for appointment completion versus cancellation/no-show prediction. The saved model metadata used in this study shows that the current saved snapshot was trained on seventy-six (76) labeled historical appointment records with an 80/20 stratified train-test split, resulting in sixty (60) training samples and sixteen (16) test samples. The target variable was binary: completed appointments were encoded as positive outcomes, while cancelled and no-show appointments were encoded as negative outcomes.

The model used twenty (20) engineered features derived from temporal variables and historical user behavior, including day of week, hour of day, month, lead time, same-day appointment count, service type encoding, payment presence, and user cancellation, no-show, and completion rates. Validation metrics included ROC-AUC, precision, recall, F1 score, accuracy, and Brier score. The best model was selected according to validation performance and probability quality. Importantly, the present manuscript reports a saved validated snapshot rather than a newly rerun training cycle. The current training configuration in the repository requires a substantially larger record count for fresh retraining, so the seventy-six-record result is treated as baseline evidence from the documented snapshot, not as proof of mature retrainability.

### 2.7 Performance and Security Assessment

Response-time observations were collected for key operations such as login, appointment creation, dashboard statistics, payment session creation, chatbot response generation, slot recommendation, demand forecasting, and real-time messaging. Concurrent-load observations were recorded at 10, 25, 50, 75, and 100 simultaneous users.

Security assessment focused on ten targeted categories: SQL injection, XSS, CSRF, authentication bypass, authorization bypass, session fixation, sensitive data exposure, clickjacking, rate-limit bypass, and insecure direct object reference.

### 2.8 Data Analysis

The following descriptive analyses were used:

- Functional pass rate = passed test cases / total test cases x 100
- Defect density = defects found / total test cases
- Chatbot metrics = accuracy, precision, recall, F1, micro-F1, macro-F1
- ML metrics = ROC-AUC, precision, recall, F1, accuracy, and Brier score
- Response-time and load outcomes = descriptive summaries of average latency and observed stability thresholds
- Security outcomes = categorical pass/fail summary across targeted test classes

### 2.9 Ethical and Privacy Considerations

The study was conducted in a controlled testing environment and aligned with the Data Privacy Act of 2012 (RA 10173) as an operational privacy framework. No personally identifiable client information was reproduced in the manuscript. Role-scoped accounts were used to limit data exposure during testing, and security-sensitive features such as authentication, payments, and audit logging were exercised using controlled scenarios rather than live public deployment.

## 3. Results and Discussion

### 3.1 Functional Reliability of Core Modules

Table 2 presents the functional testing summary across the eight major modules.

Table 2. Functional Test Case Summary

| Module | Total Cases | Passed | Failed | Pass Rate | Key Interpretation |
| --- | ---: | ---: | ---: | ---: | --- |
| Appointment Booking | 8 | 7 | 1 | 87.50% | Strong enforcement of scheduling constraints; ML slot ranking still inconsistent |
| Authentication | 7 | 6 | 1 | 85.71% | Secure registration, OAuth, and 2FA worked; lockout message lacked clarity |
| Payment and Cashier | 7 | 6 | 1 | 85.71% | Transaction integrity was reliable across cash and online flows |
| Admin Control | 6 | 5 | 1 | 83.33% | Administrative coverage was broad; monitoring UX needed refinement |
| Security | 6 | 5 | 1 | 83.33% | Security controls worked, but brute-force protection remains IP-centric |
| Reports and Analytics | 5 | 4 | 1 | 80.00% | Transactional reports were accurate; forecasting remained data-limited |
| Messaging | 5 | 4 | 1 | 80.00% | Real-time delivery was effective; preference labeling needed improvement |
| AI Chatbot | 7 | 5 | 2 | 71.43% | Retrieval worked well; multi-intent and missing-parameter handling remain weak |
| Overall | 51 | 42 | 9 | 82.35% | Core functionality verified under controlled testing |

The overall pass rate of 82.35% establishes that the platform's core workflows operate reliably in a staging environment. The strongest module was Appointment Booking, which passed seven of eight cases and demonstrated dependable enforcement of weekend restrictions, lunch-break blocking, blackout dates, and status-transition rules. Authentication and Payment also performed strongly, which is important because these modules handle sensitive account and financial operations.

The weakest module was the AI Chatbot, with a pass rate of 71.43%. This result does not mean the chatbot is unusable; rather, it indicates that conversational orchestration remains less mature than deterministic transactional features. Specifically, failures were concentrated in multi-intent utterances and action requests that lacked required parameters. This distinction matters because the chatbot's informational retrieval is already functional, while its action execution still depends on better dialogue management.

Table 3. Defect Severity Summary

| Severity | Count | Percentage |
| --- | ---: | ---: |
| Critical | 0 | 0.00% |
| Major | 0 | 0.00% |
| Moderate | 2 | 22.22% |
| Minor | 6 | 66.67% |
| Cosmetic | 1 | 11.11% |
| Total | 9 | 100.00% |

The absence of critical and major defects is one of the most important findings in the study. The defect profile indicates that most failures were not catastrophic breakdowns but issues of explanation, formatting, monitoring usability, or AI refinement. The overall defect density was 0.18 defects per test case, which is acceptable for a prototype but still high enough to justify pilot deployment rather than production certification.

### 3.2 Chatbot Benchmark Results

The chatbot was evaluated more rigorously than a simple anecdotal description by measuring intent-classification performance across six categories. Out of two hundred (200) queries, one hundred seventy-three (173) were classified correctly, resulting in 86.50% raw accuracy.

Table 4. Chatbot Intent Classification Metrics

| Intent Category | Precision | Recall | F1 Score |
| --- | ---: | ---: | ---: |
| Legal Service Inquiry | 0.86 | 0.88 | 0.87 |
| Appointment Scheduling | 0.82 | 0.80 | 0.81 |
| Status Checking | 0.85 | 0.81 | 0.83 |
| General FAQ | 0.89 | 0.95 | 0.92 |
| Greeting / Small Talk | 0.95 | 0.95 | 0.95 |
| Out-of-Scope Query | 0.83 | 0.78 | 0.81 |
| Micro Average | 0.87 | 0.87 | 0.87 |
| Macro Average | 0.87 | 0.86 | 0.87 |

These results show that the chatbot is strongest when queries are informational and linguistically distinct. Greeting or Small Talk achieved F1 = 0.95, while General FAQ achieved F1 = 0.92. In contrast, Appointment Scheduling and Out-of-Scope detection both achieved F1 = 0.81, making them the weakest intent classes.

The near-identical micro- and macro-F1 scores are analytically important. If the chatbot had performed well only on high-volume classes, the micro-F1 would be much higher than the macro-F1. Because both scores are 0.87, the model appears reasonably balanced across classes. However, balanced intent classification does not eliminate workflow limitations. The functional tests showed that the chatbot still struggles with multi-intent messages and missing-parameter prompts, meaning that good classification is necessary but not sufficient for reliable action execution.

In short, the chatbot already performs well enough to support routine service inquiries, greetings, and common FAQ interactions. Its current limitation lies in compound conversational tasks that require turn management, clarification, or chained actions.

### 3.3 Validation of the No-Show Risk Model

One of the study's central technical concerns was the need for explicit machine-learning validation. Table 5 addresses that gap using the saved model metadata from the active ML service snapshot.

Table 5. No-Show Risk Model Validation

| Model | Training Samples | Test Samples | ROC-AUC | Precision | Recall | F1 Score | Accuracy | Brier Score |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| Logistic Regression | 60 | 16 | 0.7812 | 0.7143 | 0.6250 | 0.6667 | 0.6875 | 0.1975 |
| XGBoost | 60 | 16 | 0.7500 | 0.6250 | 0.6250 | 0.6250 | 0.6250 | 0.2300 |

The current saved model selected logistic regression as the better-performing classifier. On the holdout set, logistic regression outperformed XGBoost on ROC-AUC, precision, F1 score, accuracy, and Brier score. This result is technically sensible because small tabular datasets often favor simpler calibrated models over more complex learners. The model used twenty engineered features derived from temporal behavior and user history, including cancellation rate, no-show rate, lead time, same-day load, and service-type encoding.

At the same time, the model evidence must be interpreted cautiously. The dataset contains only seventy-six labeled appointments, which is enough for preliminary validation but not enough to claim strong predictive stability in routine use. The ROC-AUC of 0.7812 indicates meaningful signal rather than randomness, but the recall of 0.6250 shows that a substantial share of risky cases would still be missed. In addition, the repository's current retraining threshold has not yet been met by the available data volume, so the existing metrics should be read as a documented baseline from a saved model snapshot rather than a claim of ongoing robust retraining. Therefore, the present ML result should be framed as an initial predictive baseline, not as a finalized field-ready model.

This limitation also explains why the forecasting and recommendation modules underperformed relative to the more deterministic modules. The ML subsystem is technically valid and evaluated, but it is not yet data-rich.

### 3.4 Response-Time and Load Results

Table 6 summarizes the measured response times of representative system operations.

Table 6. Key Response-Time Benchmarks

| Operation | Average Response Time | Observed Range | Assessment |
| --- | --- | --- | --- |
| User login | 320 ms | 210-480 ms | Acceptable |
| Appointment creation | 450 ms | 310-650 ms | Acceptable |
| Admin dashboard statistics (cached) | 190 ms | 120-340 ms | Acceptable |
| Admin dashboard statistics (uncached) | 680 ms | 520-890 ms | Acceptable |
| PayMongo checkout session creation | 1.8 s | 1.2-2.6 s | Acceptable |
| Chatbot response, standard query | 2.3 s | 1.6-3.4 s | Acceptable |
| Chatbot response, action execution | 3.1 s | 2.2-4.5 s | Needs monitoring |
| ML slot recommendation | 1.9 s | 1.4-2.8 s | Acceptable |
| Demand forecasting report | 2.4 s | 1.8-3.2 s | Acceptable |
| Message sending with WebSocket broadcast | 180 ms | 110-290 ms | Good |

The performance profile shows a practical split between conventional web operations and AI-dependent operations. Core CRUD features remain comfortably sub-second, while AI-assisted features naturally consume more time because they depend on embedding retrieval, model scoring, or language generation. This is acceptable in context: a chat response at 2.3 seconds still feels conversational, while message delivery at 180 ms feels instant.

One especially useful result is the caching effect on dashboard statistics. Caching reduced the admin statistics response time from 680 ms to 190 ms, a 72% improvement. This demonstrates that the platform is not only functional but also being optimized with explicit performance controls.

Table 7. Concurrent-Load Behavior

| Concurrent Users | Average API Response Time | System Behavior | Assessment |
| --- | --- | --- | --- |
| 10 | 250 ms | All requests completed without delay | Stable |
| 25 | 310 ms | Minimal increase in latency | Stable |
| 50 | 480 ms | Noticeable but acceptable slowdown | Stable |
| 75 | 720 ms | Slower responses, but requests still completed | Acceptable |
| 100 | 1.1 s | Degradation with occasional ML-endpoint timeouts | Degraded |

The system remained stable up to fifty concurrent users, which is a useful empirical boundary for pilot deployment. Performance degradation at seventy-five to one hundred users was concentrated in ML-dependent endpoints, particularly the chatbot, slot recommendations, and demand forecasting. Core transactional features such as appointments, payments, and messaging remained functional even when AI endpoints slowed down. This is a favorable architectural property because it localizes scalability risk to the microservice layer rather than the full platform.

### 3.5 Security Assessment

Table 8 presents the security assessment summary.

Table 8. Targeted Security Test Results

| Security Category | Result | Remarks |
| --- | --- | --- |
| SQL injection | Passed | Parameterized ORM queries prevented payload execution |
| Cross-site scripting | Passed | Output escaping and sanitization blocked script execution |
| Cross-site request forgery | Passed | Laravel CSRF and token controls protected state-changing endpoints |
| Authentication bypass | Passed | Protected endpoints rejected invalid or missing tokens |
| Authorization bypass | Passed | RBAC correctly denied client access to admin or cashier features |
| Session fixation | Passed | Invalid or expired sessions were rejected |
| Sensitive data exposure | Passed | Responses did not leak passwords, tokens, or internal details |
| Clickjacking | Passed | X-Frame-Options: DENY blocked iframe embedding |
| Rate-limit bypass | Passed | Configured rate limits were enforced during rapid requests |
| Insecure direct object reference | Passed | Ownership checks restricted access to user-scoped records |

Security was one of the strongest parts of the platform because all ten targeted security categories passed. The only notable caution is that one functional case identified registration brute-force protection as primarily IP-based, which is acceptable for a prototype but should be strengthened with additional account-based lockout logic before large-scale deployment.

### 3.6 Integrated Discussion

Taken together, the results provide a more rigorous interpretation than a simple pass-fail chapter. The core platform is strong where legal-office systems most need reliability: authentication, appointment logic, payments, messaging, reporting accuracy, and role isolation. This conclusion is supported not by one metric, but by converging quantitative evidence from module pass rates, zero critical and major defects, low defect severity concentration, and stable performance in core transactional operations.

The AI-related findings are more nuanced. The chatbot is not weak in a general sense; it is strong for routine information retrieval and socially lightweight interactions, as shown by 86.50% accuracy and F1 scores above 0.90 for FAQ and small talk. What remains weak is multi-step action orchestration, especially when a single message combines multiple intents or omits a required parameter. Likewise, the no-show model already shows predictive signal with ROC-AUC = 0.7812, but the small dataset and modest recall make it unsuitable for high-stakes autonomous decision-making. Therefore, the correct academic interpretation is not that the AI failed, but that it achieved measurable baseline performance while remaining data-constrained.

The engineering evidence is also internally consistent across quantitative measures. The chatbot's weakest intent classes align with the functional failures observed in multi-intent and missing-parameter handling, while the ML model's modest recall aligns with weaker forecasting-related observations in structured testing. This agreement across benchmark, functional, and performance data strengthens the interpretation without relying on subjective or fabricated user-response measures.

The non-functional evidence further clarifies readiness boundaries. The platform is performant enough for controlled use, especially because core transactional endpoints remain responsive and stable up to fifty concurrent users. Security evidence is similarly strong. However, the system should still be described as pilot-ready rather than field-validated at scale because ML endpoints degrade earlier than conventional endpoints and because the predictive components do not yet have the longitudinal data needed for mature calibration.

### 3.7 Study Limitations

The findings must be interpreted within four clear limitations.

1. The study was conducted in a controlled staging environment rather than routine field operation, so there are no longitudinal real-world usage logs or business-outcome measurements yet.
2. The no-show model was validated on only seventy-six labeled appointments, which is adequate for baseline experimentation but not for strong predictive generalization.
3. Load behavior was observed under simulated concurrent access, which is informative for engineering readiness but not identical to production traffic diversity.
4. The chatbot benchmark was based on a controlled query set rather than longitudinal live traffic, so real-world language drift and behavior changes are not yet measured.

These limitations do not invalidate the study. They define its correct claim: the paper demonstrates prototype viability and measurable baseline performance, not final production effectiveness.

## 4. Conclusion

The study showed that LegalEase is a technically credible legal-service management prototype with strong core workflow reliability, measurable AI baselines, and a sound security posture. Functional testing produced an 82.35% pass rate with no critical or major defects and a defect density of 0.18, the chatbot achieved 86.50% accuracy with micro- and macro-F1 of 0.87, the current no-show model achieved ROC-AUC of 0.7812, and the platform remained stable up to fifty concurrent users while passing all ten targeted security categories.

The evidence also makes the system's maturity boundaries explicit. Conventional modules are substantially more mature than AI-driven features. The chatbot still requires stronger multi-intent handling and clarification logic, while the no-show and forecasting models require more historical appointment data before they can support stronger decision claims. Accordingly, the most defensible conclusion is that LegalEase is recommended for controlled pilot use and further quantitative validation, with continued iteration focused on AI orchestration, richer historical datasets, and ML-service scalability.

## 5. Recommendations

Based on the documented findings, the following next steps are recommended.

1. Expand the longitudinal appointment dataset before making stronger predictive claims for no-show risk or demand forecasting, and treat current ML performance as a baseline rather than a deployment-grade endpoint.
2. Improve the chatbot's dialogue orchestration, particularly for missing-parameter prompts, compound instructions, and multi-intent queries that currently weaken action execution despite solid intent-classification results.
3. Strengthen operational hardening prior to broader field use by adding account-level brute-force protections alongside existing IP-based rate limiting and by simplifying complex administrative monitoring views.
4. Scale and monitor the AI microservice separately from core transactional services so that chatbot and forecasting latency under higher concurrency does not affect booking, payment, or messaging reliability.
5. Conduct longer-duration quantitative field monitoring using logs, error rates, latency traces, and outcome data so the next evaluation cycle can extend beyond controlled staging tests.

## References

Adamopoulou, E., & Moussiades, L. (2020). Chatbots: History, technology, and applications. *Machine Learning with Applications, 2*, 100006. https://doi.org/10.1016/j.mlwa.2020.100006

AlSerkal, A., Al Faisal, W., Al Olama, H., Khan, S., Al Maqbali, H., Zulfiqar, N., Al Redha, E., Alsheikh-Ali, A., Elbarazi, I., Blair, I., Saddik, B., & Oulhaj, A. (2025). Real-time analytics and AI for managing no-show appointments in primary health care in the United Arab Emirates: Before-and-after study. *JMIR Medical Informatics, 13*, e63078. https://doi.org/10.2196/63078

Ampuan, M. A., & Delena, R. (2021). An implementation and evaluation of web-based appointment system for the Mindanao State University - Main Campus. *International Journal of Scientific Research and Engineering Development, 4*(3), 1103-1110.

Dantas, L. F., Fleck, J. L., Cyrino Oliveira, F. L., & Hamacher, S. (2018). No-shows in appointment scheduling - a systematic literature review. *Health Policy, 122*(4), 412-421. https://doi.org/10.1016/j.healthpol.2018.02.002

Gupta, D., & Denton, B. (2008). Appointment scheduling in health care: Challenges and opportunities. *IIE Transactions, 40*(9), 800-819. https://doi.org/10.1080/07408170802165880

Lewis, P., Perez, E., Piktus, A., Petroni, F., Karpukhin, V., Goyal, N., Kuttler, H., Lewis, M., Yih, W., Rocktaschel, T., Riedel, S., & Kiela, D. (2020). Retrieval-augmented generation for knowledge-intensive NLP tasks. *Advances in Neural Information Processing Systems, 33*, 9459-9474.

Sourdin, T. (2018). Judge v robot?: Artificial intelligence and judicial decision-making. *University of New South Wales Law Journal, 41*(4), 1114-1133.

Susskind, R. (2017). *Tomorrow's lawyers: An introduction to your future* (2nd ed.). Oxford University Press.

Zhao, P., Yoo, I., Lavoie, J., Lavoie, B. J., & Simoes, E. (2017). Web-based medical appointment systems: A systematic review. *Journal of Medical Internet Research, 19*(4), e134. https://doi.org/10.2196/jmir.6747
