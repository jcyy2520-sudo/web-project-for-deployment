# Results and Discussion

## Current Automated Backend Validation State

The current backend evidence is based on a two-step validation chain completed on May 4-5, 2026. First, a full `php artisan test` rerun executed the complete backend inventory of one hundred forty-seven (147) discovered automated test cases and produced one hundred forty-six (146) passes with one (1) failure. Second, the single failing case was isolated to secure share-link token generation, corrected in the backend tokenization flow, and the affected authentication and secure-token slice was rerun through `php artisan test tests/Feature/TokenizationTest.php tests/Feature/AuthSecurityHardeningTest.php`, where all sixteen (16) tests passed.

Taken together, this yields a current validated backend state of one hundred forty-seven out of one hundred forty-seven (147/147) passing test cases, equivalent to 100.00%, while keeping the evidence chain explicit: the overall backend inventory came from the full-suite rerun, and the previously failing slice was directly revalidated after the fix. This is stronger than simply replacing the previous percentage without showing where the missing case was resolved.

For IMRAD reporting, the 147-test table below remains preferable to the older 51-case matrix. The 51-case table was a curated functional evaluation instrument, whereas the 147-case count is the actual backend automated test inventory discovered by PHPUnit through `php artisan test --list-tests` in the current repository state.

### Table 1. Backend Automated Regression Test Results Summary

| Module / Suite Family | Total Cases | Passed | Pass Rate | Observed Performance Status |
| --- | ---: | ---: | ---: | --- |
| Appointment Booking and Scheduling | 45 | 45 | 100.00% | Logic Stability: Booking limits, slot-capacity enforcement, day-boundary resets, authorization checks, and multi-service appointment flows all passed after the current booking-limit corrections. |
| Authentication and Secure Tokens | 16 | 16 | 100.00% | Protection Reliability: Registration hardening, hashed verification codes, password-reset safeguards, secure token lifecycle checks, and corrected share-link generation all passed after the tokenization fix. |
| Payment and Cashier Operations | 13 | 13 | 100.00% | Transactional Accuracy: Cashier dashboards, receipt dispatch, refund handling, and PayMongo payment/webhook flows completed without regression. |
| Admin Control and Operational Governance | 10 | 10 | 100.00% | Administrative Integrity: Service CRUD, appeals flow, audit logging, and production-readiness safeguards remained fully operational in the rerun. |
| AI Chatbot and Decision Support | 57 | 57 | 100.00% | AI Stability Recovery: The chatbot and decision-support slice reached full pass status after eliminating redundant authenticated-role lookups in load admission and aligning stale prompt-behavior expectations with the current prompt contract. |
| Feedback and Smoke Validation | 6 | 6 | 100.00% | Baseline Health: Feedback moderation, notification flow, and smoke-level application checks executed successfully. |
| **OVERALL TOTAL** | **147** | **147** | **100.00%** | **Current validated backend state indicates complete pass coverage across the discovered backend automated test inventory after the final authentication/tokenization defect was corrected.** |

### Interpretation

The validated backend state indicates that the system is currently strongest in deterministic business logic: appointment scheduling, cashier operations, administrative control, and system-readiness safeguards all achieved full pass rates within their grouped suite families. This matters because these areas represent the platform's transactional core and the features most directly tied to reliable pilot use.

The updated evidence also shows that the previously isolated authentication/tokenization defect has now been removed. After correcting the route-to-service parameter mismatch in share-link generation, the Authentication and Secure Tokens family improved from fifteen out of sixteen (15/16) passing to sixteen out of sixteen (16/16) passing. Combined with the earlier chatbot recovery from thirty-eight out of fifty-seven (38/57) to fifty-seven out of fifty-seven (57/57), the backend no longer shows any known failing case within the current discovered automated inventory.

From a reporting standpoint, the 147-case backend inventory is easier to defend than the earlier 51-case functional matrix because it is not a handpicked number. It is the count of executable backend automated cases automatically discovered by PHPUnit in the current codebase. Each listed test method counts as one case, and dataset-driven executions are also counted separately when the framework enumerates them. The module totals shown in Table 1 are regroupings of that discovered inventory, and their arithmetic sum is $45 + 16 + 13 + 10 + 57 + 6 = 147$.

### Why the Number is 147

The number one hundred forty-seven (147) was not selected manually. It came from the Laravel/PHPUnit test-discovery output of `php artisan test --list-tests`, which enumerated one hundred forty-seven distinct backend automated cases in the current repository state.

- Each executable test method was counted once.
- Dataset-driven executions were also counted individually because PHPUnit reports each dataset case as a separate runnable test.
- The six module families in Table 1 were created only after discovery, as reporting groups for the already enumerated backend test inventory.
- This means the number is framework-derived and reproducible, not researcher-invented.

For example, `ChatbotPublicInfoEndpointsTest` alone contributes twenty (20) executable cases because its public-info send and stream checks run across multiple role and endpoint datasets. This is why the AI/chatbot category is large without the number being inflated manually.

### Resolved Failure Note

The formerly failing automated case was the secure share-link token generation endpoint, which returned HTTP 500 during testing because the share-link route passed metadata into a token-service parameter slot that was being interpreted as a base URL. After correcting that tokenization contract and rerunning the affected authentication/token slice, the failure was eliminated.

No runnable frontend automated test suite was detected during this rerun, so the results above represent backend automated regression evidence only.



Pwede mong sabihin like this:

Hindi ko lang hinulaan yung 147. Yung system mismo ang nagpakita na may 147 na existing backend checks na puwedeng patakbuhin. So ang ginawa ko, kinuha ko yung actual total ng tests na nasa project, hindi ako nag invent ng number.

Mas simple pa:
Bawat test is parang isang checking item ng system. May ibang tests na may iba’t ibang scenarios, so hiwalay din silang binibilang kasi hiwalay din silang tine-test ng system. Kaya umabot sa 147. Tapos after lumabas yung total, saka ko lang sila pinagsama-sama into 6 categories para mas presentable sa paper. So summary lang yung categories, pero yung 147 galing talaga sa actual system count.

If judge asks, this is a cleaner Taglish answer:

The 147 came from the actual tests already built into the backend, not from manual estimation. In other words, the system already had 147 runnable checks for different features and scenarios. After getting that total, I only grouped them into six sections for reporting, but the original number itself was system-generated.

If gusto mo mas casual and confident:

Sir/Ma’am, the 147 was not manually chosen. It was the actual total number of backend test checks already present in the system. Some tests also cover multiple scenarios, so each scenario is counted separately because each one is a separate validation. After that, I just organized them into categories for the results table.

If they ask “why not 51?”, answer this:

Yung 51 was a smaller, curated evaluation table. Yung 147 is the fuller and more updated backend test inventory of the system, so mas complete and mas defensible siya as current evidence.