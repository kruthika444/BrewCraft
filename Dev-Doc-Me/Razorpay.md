# BrewCraft — Razorpay Custom Payment Integration Development Log

**Project:** BrewCraft Magento 2
**Module:** `BrewCraft_RazorpayPayment`
**Purpose:** Learning / proof-of-concept custom payment gateway integration
**Gateway:** Razorpay Test Mode
**Final production approach:** Use Razorpay's supported Magento extension; retain this custom module as a learning implementation.

---

### 1. Objective

The purpose of this development was to understand how a payment gateway integrates with Magento 2 internally rather than immediately installing a ready-made extension.

The main learning objectives were:

```text
Magento Checkout
       ↓
Payment Method
       ↓
Backend Gateway API
       ↓
Gateway Order
       ↓
Hosted Payment UI
       ↓
Payment
       ↓
Signature Verification
       ↓
Magento Order
       ↓
Payment Transaction
       ↓
Invoice
       ↓
Order Processing
```

The module was developed manually so that the complete payment lifecycle could be understood.

---

## 2. Why Razorpay was selected

Initially other gateways such as Cybersource and Amazon Payment Services/PayFort were investigated.

Those providers required business/company onboarding or credentials that were not convenient for a learning environment.

Razorpay was selected because Test Mode credentials could be obtained and used to perform a realistic payment integration without processing real money.

The integration was therefore developed using:

```text
Razorpay Test Mode
```

with test:

```text
Key ID
Key Secret
Test payments
```

No real customer payment data or real money was involved.

---

## 3. Custom Magento module created

Module:

```text
BrewCraft_RazorpayPayment
```

Location:

```text
app/code/BrewCraft/RazorpayPayment
```

Important structure developed during the integration:

```text
BrewCraft/
└── RazorpayPayment/
    ├── registration.php
    ├── etc/
    │   ├── module.xml
    │   ├── config.xml
    │   ├── di.xml
    │   ├── db_schema.xml
    │   ├── csp_whitelist.xml
    │   ├── frontend/
    │   │   ├── di.xml
    │   │   └── routes.xml
    │   └── adminhtml/
    │       └── system.xml
    │
    ├── Model/
    │   ├── Config.php
    │   ├── CheckoutConfigProvider.php
    │   ├── Payment/
    │   │   └── Method.php
    │   └── Service/
    │       ├── CreateCheckoutOrderService.php
    │       ├── PaymentSignatureVerifier.php
    │       ├── VerifyAndPlaceOrderService.php
    │       ├── FinalizePaymentService.php
    │       ├── WebhookSignatureVerifier.php
    │       └── ProcessWebhookService.php
    │
    ├── Gateway/
    │   └── Http/
    │       └── Client/
    │           └── RazorpayClient.php
    │
    ├── Controller/
    │   ├── Payment/
    │   │   ├── CreateOrder.php
    │   │   └── Verify.php
    │   └── Webhook/
    │       └── Payment.php
    │
    ├── Console/
    │   └── Command/
    │       └── TestOrderCommand.php
    │
    └── view/
        └── frontend/
            ├── layout/
            │   └── checkout_index_index.xml
            └── web/
                ├── js/view/payment/
                │   ├── razorpay.js
                │   └── method-renderer/
                │       └── razorpay-method.js
                └── template/payment/
                    └── razorpay.html
```

---

## 4. Phase 1 — Magento payment configuration

The first requirement was to create Magento Admin configuration for Razorpay.

The following fields were created under:

```text
Stores
→ Configuration
→ Sales
→ Payment Methods
→ BrewCraft Razorpay
```

Configuration included:

```text
Enabled
Title
Test Mode
Key ID
Key Secret
New Order Status
Debug Logging
Webhook Secret (later)
```

The API Key Secret was configured using Magento's encrypted configuration backend:

```text
Magento\Config\Model\Config\Backend\Encrypted
```

This prevents the plain secret from being stored directly in configuration storage.

---

## 5. Configuration service

A dedicated:

```text
Model/Config.php
```

was created.

Its responsibility was to provide methods such as:

```text
isActive()
getTitle()
isTestMode()
getKeyId()
getKeySecret()
```

rather than accessing `ScopeConfigInterface` throughout the module.

Example responsibility:

```text
Magento Configuration
       ↓
Config.php
       ↓
Other payment classes
```

This keeps gateway configuration access centralized.

---

## 6. Important credential decryption issue

During the first API test Razorpay returned:

```text
401 Authentication failed
```

The problem was not the Razorpay credentials themselves.

Because Magento stores fields using:

```text
Config\Backend\Encrypted
```

the Key Secret was encrypted in Magento configuration storage.

Initially our configuration class returned that encrypted value directly.

Therefore Razorpay effectively received:

```text
Key ID       = correct
Key Secret   = encrypted Magento value
```

and authentication failed.

The configuration implementation was corrected by injecting:

```text
Magento\Framework\Encryption\EncryptorInterface
```

and explicitly decrypting the stored secret.

Conceptually:

```text
Magento encrypted secret
        ↓
EncryptorInterface::decrypt()
        ↓
Actual Razorpay Key Secret
        ↓
Razorpay API
```

After this change API authentication succeeded.

#### Learning

Magento admin fields using an encrypted backend model may require proper decryption before being used by external API clients.

---

## 7. Phase 2 — Razorpay HTTP API client

Created:

```text
Gateway/Http/Client/RazorpayClient.php
```

The client used Magento's:

```text
Magento\Framework\HTTP\Client\Curl
```

instead of raw PHP cURL calls.

The Razorpay API base URL was:

```text
https://api.razorpay.com/v1
```

Authentication used HTTP Basic Authentication:

```text
Key ID
+
Key Secret
```

The first implemented operation was:

```text
POST /orders
```

through:

```text
createOrder()
```

---

## 8. Razorpay Order concept learned

An important distinction was established:

```text
Razorpay Order
≠
Razorpay Payment
≠
Magento Order
```

A Razorpay Order is created before payment.

Example:

```text
order_TNEhKIZfWtElZy
```

At that stage:

```text
status      = created
amount_paid = 0
amount_due  = full amount
```

No payment has happened yet.

---

## 9. Amount conversion

Razorpay expects amounts in the smallest currency subunit.

For INR:

```text
₹100
=
10000 paise
```

Therefore Magento's amount was converted using:

```text
grand_total × 100
```

Example:

```text
Magento total = 205.00 INR

Razorpay API amount:
20500
```

This conversion was intentionally done on the **server**, not in browser JavaScript.

---

## 10. Development CLI command

Before touching checkout, a console command was created:

```text
brewcraft:razorpay:test-order
```

This allowed the backend integration to be tested independently.

Successful output included:

```text
Creating Razorpay test order...

Razorpay order created successfully.

Razorpay Order ID: order_...
Status: created
Amount: ...
Currency: INR
```

This proved:

```text
Magento
→ configuration
→ decrypted credentials
→ HTTP authentication
→ Razorpay Orders API
```

before checkout complexity was introduced.

#### Learning

When integrating external services, test the server-to-server connection independently before connecting it to frontend checkout.

---

## 11. Amount limit issue encountered

During testing Razorpay returned:

```text
Amount exceeds maximum amount allowed.
```

Debug logging was added around:

```text
grand_total
currency
amount_in_subunits
quote_id
```

Example:

```text
quote_id: 65
grand_total: 205
currency: INR
amount_in_subunits: 20500
```

This helped verify that the Magento → Razorpay amount conversion was correct.

A smaller cart total was subsequently used for Test Mode transactions.

---

## 12. Phase 3 — Magento payment method model

Created:

```text
Model/Payment/Method.php
```

with code:

```text
brewcraft_razorpay
```

The custom payment method was registered with Magento's payment system.

Initially capabilities such as:

```text
authorize
capture
refund
void
```

were kept disabled because those operations had not been implemented yet.

This prevented the module from claiming functionality it did not support.

---

## 13. Checkout renderer

Magento checkout integration required:

```text
checkout_index_index.xml
razorpay.js
razorpay-method.js
razorpay.html
```

The renderer was registered under Magento's payment render list.

Checkout then displayed:

```text
○ Razorpay

Pay securely using Razorpay.

[Pay with Razorpay]
```

---

## 14. Initial Place Order problem

The first button used Magento's default:

```text
placeOrder()
```

That resulted in Magento placing an order immediately without actually opening Razorpay.

This demonstrated an important concept:

```text
Magento placeOrder()
```

only knows how to place the Magento order unless the payment integration adds the external gateway flow.

Therefore the default button behavior was replaced with:

```text
startRazorpayPayment()
```

---

## 15. Phase 4 — Checkout Razorpay Order creation

Created backend route:

```text
POST /razorpay/payment/createOrder
```

Flow:

```text
Customer clicks Pay with Razorpay
        ↓
JavaScript calls Magento
        ↓
Magento loads active quote
        ↓
Magento calculates grand total
        ↓
Magento creates Razorpay Order
        ↓
order_xxx returned to browser
```

Importantly, the browser did **not** supply the amount.

Magento calculated:

```text
quote grand_total
```

on the server.

#### Security reasoning

Never trust:

```text
amount supplied by JavaScript/browser
```

because a user could modify it.

Correct:

```text
Browser → "create payment for my cart"

Magento → calculates actual amount itself
```

---

## 16. Checkout configuration provider

A Magento:

```text
CheckoutConfigProvider
```

was created to expose safe frontend configuration:

```text
Key ID
Title
Test Mode
Store Name
```

The following was deliberately **never exposed to JavaScript**:

```text
Key Secret
```

#### Important rule

```text
Key ID     → frontend allowed
Key Secret → backend only
```

---

## 17. Razorpay Checkout SDK

Razorpay Standard Checkout was loaded from:

```text
checkout.razorpay.com
```

Initially it was configured as a hard RequireJS dependency.

That caused the Razorpay payment method to disappear when the external SDK failed to initialize during Magento checkout rendering.

The design was changed so that:

```text
Magento checkout loads normally
        ↓
Razorpay option appears
        ↓
Customer clicks Pay
        ↓
Only then checkout.js is loaded
```

This prevented an external CDN failure from breaking Magento's payment renderer itself.

---

## 18. Magento CSP handling

Magento's Content Security Policy needed to permit Razorpay resources.

A:

```text
csp_whitelist.xml
```

was introduced for required Razorpay domains.

This taught another important payment integration concept:

External payment SDKs may require Magento CSP configuration in addition to JavaScript integration.

---

## 19. Razorpay popup successfully opened

Once the renderer and SDK were wired correctly:

```text
Magento cart
        ↓
Create Razorpay Order
        ↓
Razorpay Checkout popup
```

worked successfully.

A Razorpay Test Mode card payment was then completed.

The browser received:

```text
razorpay_payment_id
razorpay_order_id
razorpay_signature
```

However, no Magento order was created yet.

This was deliberate.

---

## 20. Why frontend payment success was not trusted

At this stage Razorpay's JavaScript reported success.

But we learned:

```text
Frontend says "payment success"
≠
Trusted payment
```

A browser response can be manipulated.

Therefore Magento did **not** place an order simply because JavaScript said payment had succeeded.

Instead the response had to be sent back to Magento for cryptographic verification.

---

## 21. Phase 5 — Persist Razorpay Order ID

Before verification, the gateway order ID was stored against the Magento quote.

Fields added included:

```text
brewcraft_razorpay_order_id
brewcraft_razorpay_payment_id
brewcraft_razorpay_signature
```

Why store the order ID?

Because signature verification must use a **trusted server-side order ID**, rather than blindly trusting:

```text
razorpay_order_id
```

coming from JavaScript.

Relationship:

```text
Magento Quote
    ↕
Razorpay Order
```

was therefore persisted.

---

## 22. Payment signature verification

Created:

```text
PaymentSignatureVerifier.php
```

Verification used HMAC-SHA256.

Conceptually:

```text
Razorpay Order ID
        +
"|"
        +
Razorpay Payment ID
        ↓
HMAC SHA256
using Key Secret
        ↓
Generated Signature
        ↓
compare with Razorpay Signature
```

Comparison used:

```text
hash_equals()
```

rather than normal string comparison.

---

## 23. Verification controller

Created:

```text
POST /razorpay/payment/verify
```

The frontend submitted:

```text
razorpay_payment_id
razorpay_order_id
razorpay_signature
```

Magento then:

```text
loaded active quote
↓
loaded stored Razorpay Order ID
↓
checked browser order ID consistency
↓
generated HMAC
↓
verified signature
```

Only after successful verification was the payment accepted by our application.

---

## 24. Phase 6 — Magento order creation

After successful signature verification:

```text
CartManagementInterface::placeOrder()
```

was used.

This converted:

```text
Magento Quote
```

into:

```text
Magento Sales Order
```

The quote became inactive.

The customer was then redirected to:

```text
checkout/onepage/success
```

Testing confirmed:

```text
Razorpay payment ✅
Signature verification ✅
Magento order created ✅
```

---

## 25. Magento payment information

Before converting the quote to an order, Razorpay information was attached to Magento payment additional information.

Stored references included:

```text
razorpay_order_id
razorpay_payment_id
razorpay_signature
```

The Magento payment method remained:

```text
brewcraft_razorpay
```

This allowed the generated sales order payment record to preserve gateway information.

---

## 26. Phase 7 — Fetch actual Razorpay payment

Signature verification alone was not considered sufficient for final payment bookkeeping.

`RazorpayClient` was extended with:

```text
fetchPayment()
```

calling:

```text
GET /v1/payments/{payment_id}
```

The gateway payment was checked for:

```text
payment ID
order ID
amount
currency
status
captured flag
```

This allowed Magento to verify the gateway's actual current payment state.

---

## 27. Amount and currency validation

The fetched Razorpay payment was compared with the Magento order.

Validation included:

```text
Magento grand total × 100
=
Razorpay amount
```

and:

```text
Magento order currency
=
Razorpay payment currency
```

This protects against scenarios where a valid gateway payment might correspond to a different amount or currency.

---

## 28. Captured payment handling

A dedicated:

```text
FinalizePaymentService.php
```

was created.

For a captured Razorpay payment it:

```text
set gateway transaction ID
stored payment information
created Magento capture transaction
created invoice
updated order state/status
```

---

## 29. Razorpay Payment ID as transaction reference

Gateway IDs have different purposes:

```text
order_...
= Razorpay Order

pay_...
= actual Razorpay Payment
```

Therefore:

```text
pay_...
```

was used as Magento's payment transaction reference.

Magento payment records showed:

```text
last_trans_id = pay_...
```

---

## 30. Magento payment transaction

A Magento payment transaction was recorded as:

```text
TYPE_CAPTURE
```

because the Razorpay payment was already captured.

This creates the relationship:

```text
Razorpay pay_xxx
        ↓
Magento capture transaction
```

which is important for:

```text
payment history
reconciliation
refunds
admin investigation
```

---

## 31. Invoice creation

Once Razorpay confirmed that the payment was captured, Magento created an invoice.

The invoice used an offline capture case because the gateway money had already been captured externally.

The intention was:

```text
Do NOT charge Razorpay again.
Just record the captured payment in Magento.
```

This established an important Magento concept:

```text
Order
≠
Invoice
```

Order means:

> Customer has ordered products.

Invoice means:

> Payment has been invoiced/recorded.

---

## 32. Order moved to Processing

After successful payment finalization and invoice creation:

```text
Order State  = processing
Order Status = processing
```

Testing confirmed the order appeared as:

```text
Processing
```

This is the appropriate state for a paid physical-product order that still requires fulfilment.

The order was deliberately **not** marked:

```text
Complete
```

because shipment/fulfilment had not yet occurred.

---

## 33. Final synchronous payment flow achieved

At this point the custom integration successfully completed:

```text
Customer Checkout
       ↓
Select BrewCraft Razorpay
       ↓
Magento creates Razorpay Order
       ↓
Razorpay popup
       ↓
Test card payment
       ↓
Razorpay returns:
 payment ID
 order ID
 signature
       ↓
Magento verifies HMAC
       ↓
Magento creates sales order
       ↓
Magento fetches Razorpay payment
       ↓
Validate:
 captured
 amount
 currency
 references
       ↓
Create capture transaction
       ↓
Create invoice
       ↓
Order → Processing
       ↓
Success Page
```

This was the primary objective of the custom learning implementation.

---

## 34. Webhook investigation

Webhook support was started as an additional production-reliability exercise.

Webhook configuration included a separate:

```text
Webhook Secret
```

This introduced an important distinction.

#### Checkout signature

Uses:

```text
order_id + "|" + payment_id
+
API Key Secret
```

#### Webhook signature

Uses:

```text
raw HTTP request body
+
Webhook Secret
```

These are different mechanisms and different secrets.

---

## 35. Webhook endpoint

Created:

```text
POST /razorpay/webhook/payment
```

The endpoint:

```text
reads raw body
reads X-Razorpay-Signature
verifies HMAC
reads X-Razorpay-Event-Id
parses event payload
```

CSRF validation was explicitly bypassed for this endpoint because an external Razorpay server cannot supply Magento's frontend form key.

Security was instead provided by webhook signature verification.

---

## 36. Webhook idempotency design

A table was introduced:

```text
brewcraft_razorpay_webhook_event
```

to record:

```text
event_id
event_type
payment_id
razorpay_order_id
processing status
error
timestamps
```

A unique constraint on:

```text
event_id
```

was intended to protect against duplicate webhook deliveries.

This introduced the concept:

```text
same event delivered multiple times
        ↓
process only once
```

or **idempotency**.

---

## 37. Local webhook networking problem

The webhook itself became difficult to test because Magento runs locally under Reward/Docker:

```text
https://project1.test
```

which Razorpay's servers cannot directly access.

`zrok` was investigated as a public tunnel.

Initial setup:

```text
zrok share public http://localhost:80
```

reached Traefik rather than Magento.

Traefik was configured with the router rule:

```text
Host(project1.test)
```

or subdomains of it.

The zrok hostname:

```text
*.shares.zrok.io
```

did not satisfy that routing rule.

Responses such as:

```text
418
404
302
```

were encountered before the request reached Magento.

---

## 38. Local forwarder experiment

A temporary forwarding path was tested to bypass Traefik:

```text
localhost:8088
       ↓
Varnish
       ↓
nginx
       ↓
Magento
```

Initially HTTP resulted in:

```text
302
→ https://project1.test/...
```

because Magento correctly enforced HTTPS.

Adding:

```text
X-Forwarded-Proto: https
```

allowed the request to reach the webhook controller.

An unsigned test request then returned:

```text
400 Bad Request
```

with:

```text
Invalid Razorpay webhook signature
```

which proved that the Magento webhook controller itself worked.

---

## 39. Decision regarding webhooks

At this point the webhook implementation had become primarily a **local Docker/Traefik/tunnelling problem**, rather than a Magento payment-development problem.

Because the purpose of BrewCraft is learning Magento backend development rather than spending excessive time solving local tunnel networking, the decision was made to stop further webhook infrastructure debugging.

Webhook functionality is therefore considered:

```text
Designed / partially implemented
but not required for final learning-project completion.
```

For a production system it would still be recommended for payment reconciliation and resilience.

---

## 40. Important webhook architecture limitation discovered

Our custom flow creates the Magento sales order **after** Razorpay payment and browser verification.

Therefore:

```text
Customer pays
↓
browser disappears BEFORE /verify
```

could result in:

```text
Razorpay payment exists
Magento order does not yet exist
```

A webhook cannot easily locate an order that was never created.

A stronger production architecture could instead use:

```text
Pending Magento Order
       ↓
Gateway payment
```

or a dedicated:

```text
payment_attempt
```

record before opening the payment gateway.

This was deliberately not pursued further in the custom learning module.

---

## 41. Functionality successfully completed

The custom module successfully implemented and tested:

```text
✅ Magento payment module creation

✅ Admin payment configuration

✅ Encrypted API credentials

✅ Correct secret decryption

✅ Razorpay Basic Authentication

✅ Razorpay Orders API

✅ CLI API test command

✅ Server-side amount calculation

✅ INR → paise conversion

✅ Checkout payment renderer

✅ Razorpay Checkout SDK

✅ Razorpay popup

✅ Test card payment

✅ Server-side Razorpay Order persistence

✅ Payment callback handling

✅ HMAC-SHA256 signature verification

✅ Timing-safe signature comparison

✅ Magento quote → order conversion

✅ Razorpay payment API lookup

✅ Payment ID validation

✅ Gateway Order ID validation

✅ Amount validation

✅ Currency validation

✅ Captured payment validation

✅ Magento payment information

✅ Magento capture transaction

✅ Invoice creation

✅ Processing order state

✅ Checkout success redirect
```

---

## 42. Functionality intentionally not completed

The following were deliberately not pursued in the custom learning module:

```text
Webhook production deployment
Webhook async RabbitMQ processing
Refund API integration
Void support
Manual capture support
Partial capture
Partial refund
Payment retry workflow
Admin refund integration
Production/live credentials
Production monitoring/reconciliation
```

These are not required to prove the core learning objective.

---

## 43. Major issues encountered and fixes

#### Issue 1 — API authentication failed

```text
401 Authentication failed
```

**Cause:** Magento encrypted Key Secret was sent without decryption.

**Fix:** Used `EncryptorInterface` to retrieve the real secret.

---

#### Issue 2 — Amount rejected by Razorpay

```text
Amount exceeds maximum amount allowed
```

**Cause:** Test cart amount exceeded the permitted transaction amount.

**Fix:** Added server-side amount debugging and tested with a smaller cart.

---

#### Issue 3 — Razorpay option disappeared from checkout

**Cause:** Razorpay external SDK was loaded as a hard RequireJS dependency.

If SDK loading failed, the payment renderer itself failed.

**Fix:** Changed to lazy SDK loading after clicking the payment button and added appropriate CSP configuration.

---

#### Issue 4 — Magento order created without Razorpay

**Cause:** Checkout button initially used Magento's default:

```text
placeOrder()
```

**Fix:** Replaced it with:

```text
startRazorpayPayment()
```

to begin the gateway flow first.

---

#### Issue 5 — Payment succeeded but Magento order didn't exist

This was actually expected at that development stage.

The gateway popup worked, but server verification/order placement had not yet been written.

**Fix:** Added signature verification endpoint and `CartManagementInterface::placeOrder()`.

---

#### Issue 6 — Payment needed proper Magento bookkeeping

Creating an order alone was insufficient.

**Fix:** Added:

```text
Gateway payment lookup
Transaction ID
Capture transaction
Invoice
Processing status
```

---

#### Issue 7 — Webhook signature initially invalid

```text
Invalid Razorpay webhook signature
```

**Cause:** Webhook secret mismatch/change during testing.

**Fix:** Synced Magento Webhook Secret with Razorpay Test Mode configuration.

---

#### Issue 8 — zrok requests never reached Magento

Responses:

```text
418
404
302
```

were received.

**Cause:** Reward's Traefik router requires:

```text
Host: project1.test
```

while zrok requests used:

```text
*.shares.zrok.io
```

Further local forwarding proved the webhook controller itself was functional.

**Decision:** Stop tunnel debugging because it was outside the primary learning objective.

---

## 44. Security concepts implemented

Several important payment-security rules were applied.

#### Secret credentials remain backend-only

Never expose:

```text
Key Secret
Webhook Secret
```

to checkout JavaScript.

---

#### Do not trust browser payment success

Browser response:

```text
payment_id
order_id
signature
```

must be verified server-side.

---

#### Do not trust browser amount

Magento calculates payment amount from the active quote.

---

#### Persist gateway Order ID server-side

Verification uses Magento's stored Razorpay Order ID instead of blindly trusting the returned browser value.

---

#### Use HMAC verification

Payment authenticity is verified cryptographically.

---

#### Use `hash_equals()`

Used for timing-safe signature comparison.

---

#### Validate gateway payment state

After signature verification Magento additionally verifies:

```text
payment ID
gateway order
amount
currency
captured state
```

through the Razorpay API.

---

## 46. Payment concepts learned

The integration also established the following gateway concepts:

```text
Payment gateway
Gateway credentials
Gateway order
Gateway payment
Hosted checkout
Authorization
Capture
Void
Refund
Callback
Webhook
Transaction ID
Signature
HMAC
Idempotency
Reconciliation
Payment status
Magento order state
Invoice
Asynchronous payment notification
```

Most of these concepts are provider-independent and apply to integrations with gateways such as:

```text
Razorpay
Cybersource
Stripe
Adyen
Amazon Payment Services
PayPal
etc.
```

although their APIs and integration requirements differ.

---

## 47. Final architectural flow of custom module

```text
CUSTOMER
   │
   │ Checkout
   ▼
MAGENTO QUOTE
   │
   │ Create Razorpay Order
   ▼
RAZORPAY API
   │
   │ order_xxx
   ▼
MAGENTO CHECKOUT
   │
   │ Open Razorpay Checkout
   ▼
RAZORPAY PAYMENT UI
   │
   │ Customer pays
   ▼
RAZORPAY
   │
   │ payment_id
   │ order_id
   │ signature
   ▼
MAGENTO VERIFY ENDPOINT
   │
   ├─ load trusted gateway order
   ├─ verify HMAC
   └─ accept/reject
   │
   ▼
MAGENTO SALES ORDER
   │
   │ Fetch payment from Razorpay
   ▼
RAZORPAY PAYMENT API
   │
   └─ captured
      amount
      currency
      order ID
   │
   ▼
MAGENTO PAYMENT FINALIZATION
   │
   ├─ transaction = pay_xxx
   ├─ capture transaction
   ├─ invoice
   └─ order processing
   │
   ▼
CHECKOUT SUCCESS
```

---

## 48. Final project decision

The custom:

```text
BrewCraft_RazorpayPayment
```

module has achieved its intended purpose:

> **Learning how a Magento payment gateway works internally.**

It will therefore be retained as a learning/proof-of-concept module but will **not be used as the final BrewCraft production-style payment implementation**.

For the final project integration, the plan is:

```text
Preserve custom module in Git
        ↓
Disable custom payment method/module
        ↓
Install supported Razorpay Magento extension
        ↓
Configure Test Mode credentials
        ↓
Test official checkout integration
        ↓
Use official module as BrewCraft payment solution
```

This avoids maintaining custom security-sensitive payment code while still preserving all of the learning obtained by building the integration manually.

---

## 49. Interview-ready project explanation

A concise way to explain this work in an interview would be:

> “In my Magento learning project, I implemented a Razorpay payment gateway manually to understand the complete payment lifecycle. I created the Magento payment method, admin configuration with encrypted credentials, Razorpay API client, gateway order creation, checkout renderer, Razorpay hosted checkout integration, server-side HMAC signature verification, quote-to-order conversion, payment-status verification using Razorpay's API, Magento capture transaction, invoice creation and order state transition to Processing.
>
> I also explored webhook signature verification and idempotency, although local webhook testing became primarily a reverse-proxy/tunnelling concern. For the final storefront implementation, I chose to use Razorpay's supported Magento extension rather than maintain custom payment-critical code. The custom module remains as a proof of concept and learning implementation.”

---

### Development status

```text
BrewCraft_RazorpayPayment
────────────────────────────────────────────

Module architecture                    ✅
Admin configuration                   ✅
Encrypted credentials                 ✅
Razorpay API authentication           ✅
Gateway order creation                ✅
Checkout payment method               ✅
Hosted Razorpay Checkout              ✅
Test payment                          ✅
Payment signature verification        ✅
Magento order creation                ✅
Gateway payment verification          ✅
Magento payment transaction           ✅
Invoice creation                      ✅
Processing order status               ✅
Success flow                          ✅

Webhook code/design                   ◐ Partial
Production webhook connectivity       ⏸ Not required
Refund implementation                 ⏸ Not required
Live mode                             ⏸ Not required

CUSTOM LEARNING INTEGRATION           ✅ COMPLETE

Next:
Official Razorpay Magento extension
for final BrewCraft integration.
```


