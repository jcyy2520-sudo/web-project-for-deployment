# Cashier System — Implementation Plan (Items #1–#16)

> **Goal:** Close all identified loopholes in the cashier payment flow, improve UX,  
> and enhance audit transparency — in a safe, phased rollout order.  
> Each phase is independent after Phase 1. No phase breaks a previous one.

---

## Dependency Map

```
Phase 1 (Security Foundation)
  ├── #1  Backend amount validation
  ├── #6  No-show vs payment_status guard
  └── #16 Restrict cashier refund initiation
          │
Phase 2 (Discount Overhaul) ← depends on Phase 1 validation logic
  ├── #2  Require ID-proof field for discounts
  └── #10 Fetch discount rates from DB (DiscountRate model already exists)
          │
Phase 3 (Partial Payment Fix) ← depends on Phase 1 validation
  ├── #4  True partial payment with multiple installments
  └── #9  Remaining balance tracker
          │
Phase 4 (Payment UX) ← depends on Phase 2 (rates) + Phase 3 (balance)
  ├── #8  Auto-fill service price
  ├── #12 Replace 5-sec countdown with checkbox confirmation
  └── #13 Confirmation modal flags price mismatch / shortfall
          │
Phase 5 (In-Kind + Audit Trail) ← independent, can run parallel to Phase 3/4
  ├── #5  In-kind guardrails (required description + estimated value)
  └── #15 Enhanced ActionLog entries for every sensitive action
          │
Phase 6 (Receipt System) ← depends on Phase 3 (balance shown on receipt)
  ├── #3  Server-generated receipt with integrity hash
  └── #14 HTML email receipts matching print layout
          │
Phase 7 (Search & Reports) ← fully independent
  ├── #7  Search/filter in Approved appointments list
  └── #11 Enhanced shift reports
```

---

## Phase 1 — Critical Security (Backend-First)

**Items:** #1, #6, #16  
**Risk if skipped:** Financial loss, data inconsistency, refund fraud loop  
**Estimated files touched:** 3 backend, 0 frontend

### #1 — Backend amount validation against service price

**Problem:** Cashier can enter any amount. Backend accepts it without checking service price.

**Changes:**

| File | Change |
|------|--------|
| `CashierController.php` → `processPayment()` | After `lockForUpdate()`, load `$appointment->service->price`. Validate: `payment_amount + discount_amount >= service_price` for full payments. For partial: `payment_amount > 0` (already done) but also log the shortfall explicitly. |
| `PayMongoController.php` → `checkout()` | Same validation — amount sent to PayMongo must equal `service_price - discount_amount`. |

**Validation rules to add (backend):**
```
- Full payment:  payment_amount >= (service_price - discount_amount)
- Partial:       payment_amount > 0 AND payment_amount < service_price (explicit partial flag)
- In-kind:       separate handling (Phase 5)
- Reject:        discount_amount > service_price
- Reject:        payment_amount + discount_amount results in negative total
```

**ActionLog:** Log every payment with metadata: `{ service_price, entered_amount, discount, shortfall, payment_type }`.

---

### #6 — No-show + payment_status conflict guard

**Problem:** If `payment_status = 'paid'` but `status = 'approved'` (timing edge case), appointment can be marked no-show.

**Changes:**

| File | Change |
|------|--------|
| `AppointmentController.php` → `markNoShow()` | Add check: `if ($appointment->payment_status === 'paid') return 422 "Cannot mark paid appointment as no-show"` |

**One-line fix.** Low risk.

---

### #16 — Restrict cashier-initiated refund requests

**Problem:** Cashier can: process payment → request refund → admin approves → cashier completes. Full fraud loop.

**Changes:**

| File | Change |
|------|--------|
| `RefundController.php` → `requestRefund()` | Add rule: if `auth()->user()->role === 'cashier'`, the `recorded_by` (who processed the original payment) must NOT be the same cashier requesting the refund. i.e., a cashier cannot request a refund on a payment they themselves processed. |
| `routes/api.php` | Move `completeRefund` from cashier group to admin-only group (currently cashiers can complete refunds). |

**ActionLog:** Log refund requests with metadata: `{ requested_by, original_cashier_id, appointment_id, refund_amount }`.

---

## Phase 2 — Discount System Overhaul

**Items:** #2, #10  
**Risk if skipped:** Over-applied discounts (hardcoded 20% vs DB 15%), zero accountability for discount claims  
**Estimated files touched:** 1 backend controller, 1 new route, 1 frontend file

### #10 — Fetch discount rates from database

**Problem:** Frontend hardcodes 20%/20%/10%. Database has 15%/10%/10%. They disagree.

**Changes:**

| File | Change |
|------|--------|
| `routes/api.php` | Add `GET /api/discount-rates` → new controller method (or add to existing settings controller). Returns active rates from `DiscountRate` model (`DiscountRate::activeDiscounts()`). |
| `CashierController.php` → `processPayment()` | Server-side: load `DiscountRate::getByType($type)` and recalculate discount. **Ignore** the discount_amount sent by frontend — recalculate from DB rate × service_price. This makes the backend the source of truth. |
| `CashierDashboard.jsx` → `calculateDiscount()` | Replace hardcoded rates with rates fetched from `/api/discount-rates` on mount. Store in state. Use those percentages in the calculation. |

### #2 — Require ID-proof field for discounts

**Problem:** No verification. Cashier checks a box, gets free discount.

**Changes:**

| File | Change |
|------|--------|
| `CashierDashboard.jsx` — payment modal | When a discount is selected, show a required text field: "ID/Proof Reference" (e.g., "PWD Card #12345" or "Senior Citizen ID #6789"). Cannot submit payment with discount if this field is empty. |
| `CashierController.php` → `processPayment()` | Add `discount_proof` field (nullable string, max 255). Store in Payment model. |
| `Payment.php` model | Add `discount_proof` to fillable. |
| Migration | Add `discount_proof` varchar(255) nullable to `payments` table. |

**ActionLog:** Log every discounted payment with metadata: `{ discount_type, discount_rate, discount_proof, calculated_discount }`.

---

## Phase 3 — Partial Payment System

**Items:** #4, #9  
**Risk if skipped:** "Partial" is cosmetic only — no balance tracking, no follow-up payments  
**Estimated files touched:** 1 migration, 2 backend files, 1 frontend file  
**Note:** Payment model already has `shortfall`, `calculateShortfall()`, `determinePaymentStatus()` — partially built but never wired up.

### #4 + #9 — True partial payments with balance tracking

**Current state:** Appointment goes to `status=completed` + `payment_status=paid` after ANY payment, even partial. Payment model has `shortfall` field but it's never populated.

**Changes:**

| File | Change |
|------|--------|
| Migration | Add `balance_remaining` decimal(10,2) to `appointments` table (computed field, updated on each payment). |
| `CashierController.php` → `processPayment()` | **Full payment path:** Set `payment_status = 'paid'`, `status = 'completed'`, `balance_remaining = 0`. **Partial payment path:** Set `payment_status = 'partially_paid'`, keep `status = 'approved'`, compute `balance_remaining = service_price - total_paid_so_far`. Create a new Payment record (allow multiple). Do NOT change appointment status to completed. |
| `CashierController.php` | Add new method `getPaymentHistory($appointmentId)` — returns all Payment records + current balance. |
| `Appointment.php` model | Add `balance_remaining` to fillable + casts. Add accessor `getTotalPaidAttribute()` that sums related payments. |
| `CashierDashboard.jsx` — appointment card | Show balance badge: "₱1,500 remaining" in orange if `balance_remaining > 0`. |
| `CashierDashboard.jsx` — payment modal | If appointment has prior payments, show payment history table and "Remaining: ₱X". Pre-fill amount field with remaining balance. Allow "Pay Remaining" or enter custom partial amount. |
| `CashierDashboard.jsx` — Approved tab | Include `partially_paid` appointments (they stay in Approved since they still need payment). |

**Appointment status flow after this:**
```
approved → (partial payment) → approved + payment_status=partially_paid
         → (full/final payment) → completed + payment_status=paid
```

---

## Phase 4 — Payment UX Improvements

**Items:** #8, #12, #13  
**Risk if skipped:** Human error, cashier friction  
**Estimated files touched:** 1 frontend file

### #8 — Auto-fill service price

**Changes:**

| File | Change |
|------|--------|
| `CashierDashboard.jsx` — payment modal open handler | When appointment modal opens for payment, auto-set `paymentAmount` to `appointment.service.price - discount` (or `balance_remaining` if partial payment exists). Cashier can still override for partial, but starts at correct value. |

### #12 — Replace 5-second countdown with checkbox

**Changes:**

| File | Change |
|------|--------|
| `CashierDashboard.jsx` — `CompletionConfirmationModal` area | Remove the 5-second countdown timer. Replace with a checkbox: `☐ I confirm this payment is correct and verified`. Confirm button enables only when checkbox is checked. Instant, no wait — but deliberate. |

### #13 — Confirmation modal flags price mismatch

**Changes:**

| File | Change |
|------|--------|
| `CashierDashboard.jsx` — confirmation modal | Add comparison row: `Service Price: ₱X | You Entered: ₱Y | Shortfall: ₱Z`. If shortfall > 0 and payment_type is NOT 'partial', show amber warning: "⚠ Amount is less than service price. Switch to partial payment?" If amount > service_price, show info: "Overpayment of ₱Z will be recorded." |

---

## Phase 5 — In-Kind Guardrails + Enhanced Audit Trail

**Items:** #5, #15  
**Risk if skipped:** Untraceable in-kind payments, cashier can fabricate entries  
**Estimated files touched:** 1 migration, 2 backend files, 1 frontend file

### #5 — In-kind payment guardrails

**Confirmed:** In-kind is a legitimate payment type for this office.

**Changes:**

| File | Change |
|------|--------|
| Migration | Add `in_kind_estimated_value` decimal(10,2) nullable to `payments` table. |
| `Payment.php` model | Add `in_kind_estimated_value` to fillable. |
| `CashierController.php` → `processPayment()` | If `payment_type === 'in-kind'`: require `goods_description` (reject if empty), require `in_kind_estimated_value` (must be > 0), store both in Payment. |
| `CashierDashboard.jsx` — payment modal | When "In-kind" is selected: make description field **required** (red border if empty), add "Estimated Value (₱)" number field (required). Show clear label: "Describe the items received and their estimated peso value." |

### #15 — Enhanced ActionLog for transparency

**Goal:** Every sensitive cashier action gets a detailed, structured log entry so abuses are discoverable by admin.

**Add logging to these actions (with metadata):**

| Action | Metadata to log |
|--------|-----------------|
| `process_payment` (already logged) | **Enhance:** Add `{ service_price, amount_entered, discount_type, discount_rate, discount_proof, discount_amount, payment_type, shortfall, in_kind_description, in_kind_estimated_value }` |
| `apply_discount` (new) | `{ discount_type, discount_rate_from_db, calculated_amount, proof_reference }` |
| `process_inkind_payment` (new) | `{ goods_description, estimated_value, appointment_id, client_name }` |
| `mark_no_show` (enhance existing) | `{ appointment_id, client_name, was_payment_attempted, previous_status }` |
| `request_refund` (enhance) | `{ refund_amount, reason, original_payment_amount, original_cashier_id, requesting_user_id }` |
| `complete_refund` (enhance) | `{ refund_id, refund_amount, approved_by, completed_by }` |
| `reprint_receipt` (new) | `{ appointment_id, receipt_id, reprint_count }` |
| `partial_payment` (new) | `{ appointment_id, this_payment, total_paid_so_far, balance_remaining }` |

**Frontend:** No changes needed — ActionLog::log() is called server-side.

---

## Phase 6 — Receipt System

**Items:** #3, #14  
**Risk if skipped:** Receipts can be tampered via browser DevTools  
**Estimated files touched:** 1 new backend service, 1 backend controller, 1 frontend file

### #3 — Server-generated receipt with integrity hash

**Changes:**

| File | Change |
|------|--------|
| New: `ReceiptService.php` | Service class that generates receipt data server-side. Includes all fields + a SHA-256 HMAC integrity hash computed from: `receipt_id + appointment_id + total_paid + date + app_key`. Returns JSON with `integrity_hash` field. |
| `CashierController.php` → `processPayment()` | After payment success, call `ReceiptService::generate($appointment, $payment)`. Return receipt data with hash in response. |
| New: `GET /api/cashier/receipts/{appointmentId}` | Endpoint to re-fetch receipt (for reprints). Recomputes hash to verify integrity. |
| `CashierDashboard.jsx` — ReceiptModal | Use the receipt data from API response (already mostly does this). Add integrity hash display at bottom: small code like `Verification: ABC123...`. Remove any client-side receipt data construction. |

### #14 — HTML email receipts

**Changes:**

| File | Change |
|------|--------|
| New: `resources/views/emails/receipt.blade.php` | HTML email template matching the print receipt layout. Professional styling with inline CSS. |
| `CashierController.php` → `emailReceipt()` | Replace plain-text email with Mailable using the Blade template. Include all receipt fields, discount breakdown, payment type. |

---

## Phase 7 — Search & Reports

**Items:** #7, #11  
**Risk if skipped:** UX friction, limited operational insight  
**Estimated files touched:** 1 frontend file, 1 backend controller

### #7 — Search/filter in Approved appointments list

**Changes:**

| File | Change |
|------|--------|
| `CashierDashboard.jsx` — Approved appointments tab | Add a search bar above the appointment list. Filter client-side by: client name, appointment ID, service name, date. Debounced input (300ms). |

Simple client-side filter — no backend changes needed since appointments are already loaded.

### #11 — Enhanced shift reports

**Changes:**

| File | Change |
|------|--------|
| `CashierController.php` → `getShiftReport()` | Expand response to include: revenue breakdown by service type, discount usage summary (count + total per type), in-kind payment summary (count + total estimated value), hourly distribution of payments. |
| `CashierDashboard.jsx` — Reports section | Add sections: "Revenue by Service" (horizontal bar chart), "Discount Usage" (table: type / count / total), "In-Kind Summary" (count + total estimated), "Peak Hours" (simple bar). |

---

## Implementation Order (Safe Rollout)

```
Week 1: Phase 1 — Security foundation
  Day 1-2: #1 backend amount validation + tests
  Day 2:   #6 no-show guard (one-line fix)
  Day 3:   #16 refund restriction + route change
  Day 3:   Deploy, verify in staging

Week 1-2: Phase 5 — Audit trail (parallel-safe)
  Day 4:   #15 enhanced ActionLog metadata
  Day 4-5: #5 in-kind guardrails (migration + backend + frontend)
  Day 5:   Deploy with Phase 1

Week 2: Phase 2 — Discounts
  Day 1:   #10 discount rates API endpoint + backend recalculation
  Day 2:   #10 frontend fetch rates + #2 ID proof field
  Day 2:   Migration for discount_proof
  Day 3:   Deploy

Week 2-3: Phase 3 — Partial payments
  Day 3-4: Migration + backend partial payment logic
  Day 4-5: Frontend balance display + follow-up payment UI
  Day 5:   Deploy

Week 3: Phase 4 — Payment UX
  Day 1: #8 auto-fill + #13 price comparison in modal
  Day 1: #12 replace countdown with checkbox
  Day 2: Deploy

Week 3-4: Phase 6 — Receipts
  Day 2-3: #3 ReceiptService + integrity hash
  Day 3-4: #14 HTML email template
  Day 4:   Deploy

Week 4: Phase 7 — Search & Reports
  Day 1: #7 appointment search
  Day 2: #11 enhanced reports (backend + frontend)
  Day 3: Deploy + full regression test
```

---

## Migration Checklist

| Phase | Migration needed | Fields |
|-------|------------------|--------|
| Phase 2 | `add_discount_proof_to_payments` | `discount_proof` varchar(255) nullable |
| Phase 3 | `add_balance_remaining_to_appointments` | `balance_remaining` decimal(10,2) default 0 |
| Phase 5 | `add_in_kind_estimated_value_to_payments` | `in_kind_estimated_value` decimal(10,2) nullable |

All migrations are **additive** (new nullable columns). Zero risk of breaking existing data.

---

## Testing Strategy

Each phase should be verified with:

1. **Happy path:** Normal full cash payment → receipt → correct log
2. **Partial payment:** Enter less than service price → balance tracked → follow-up payment → completed
3. **Discount abuse attempt:** Apply discount → verify backend recalculates from DB rate, not frontend value
4. **Amount manipulation:** Send payment_amount < service_price for full payment → backend rejects
5. **Refund loop:** Cashier processes payment → same cashier tries refund request → rejected
6. **No-show on paid:** Pay appointment → try mark no-show → rejected
7. **In-kind without description:** Try submitting in-kind with empty description → rejected
8. **Receipt integrity:** Modify receipt data in browser → hash mismatch detectable
9. **Partial payment reprint:** Reprint receipt mid-partial → shows balance remaining
