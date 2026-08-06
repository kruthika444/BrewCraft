# 1.BrewCraft Request Quote Module — Development Log
**DATE:** 4th Aug
Module:

```text
BrewCraft_RequestQuote
```

Platform:

```text
Magento Open Source 2.4.7-p9
PHP 8.3
```

Current completion point:

```text
Customer quote submission
My Account quote listing
Customer quote details
Eligibility and ownership protection
```

---

## 1. Why we created this module

Magento Open Source does not include the Adobe Commerce B2B Negotiable Quotes feature.

BrewCraft already had:

```text
Business Account registration
Admin approval and rejection
Business Customer group assignment
Business-customer catalog pricing
```

After approval, business customers could receive a fixed discount, but they could not ask BrewCraft for a special price based on:

```text
Large quantity
Recurring purchases
Delivery requirements
Bulk orders
Special commercial terms
```

Therefore, we created a custom Request for Quote module.

The purpose is:

```text
Approved Business Customer
        ↓
Adds products to cart
        ↓
Requests a custom quotation
        ↓
Admin reviews the request
        ↓
Admin proposes custom prices
        ↓
Customer accepts or rejects
        ↓
Accepted quotation becomes an order
```

Only the customer-side request and history portions are complete currently. Admin negotiation and quote conversion are the next phases.

---

## 2. Why it is a separate Magento module

We already have:

```text
BrewCraft_BusinessAccount
```

That module answers:

> Is this customer an approved BrewCraft business customer?

The new module answers:

> What products and prices does this approved customer want to negotiate?

Therefore, the modules have separate responsibilities:

```text
BrewCraft_BusinessAccount
→ Business registration and approval

BrewCraft_RequestQuote
→ Quote requests and future negotiation
```

The Request Quote module depends on the Business Account module:

```xml
<sequence>
    <module name="Magento_Customer"/>
    <module name="Magento_Catalog"/>
    <module name="Magento_Quote"/>
    <module name="Magento_Checkout"/>
    <module name="Magento_Sales"/>
    <module name="BrewCraft_BusinessAccount"/>
</sequence>
```

Magento concept:

```text
Module sequence
```

This tells Magento that the required modules must load before `BrewCraft_RequestQuote`.

It does not mean normal PHP inheritance. It defines module loading and setup dependency order.

---

## 3. Main business rules

The quote feature is available only when both conditions are true:

```text
Business application status = approved
        +
Customer group = Business Customer
```

Customer behavior:

| Customer type              | Quote button | My Quote Requests menu |  Direct access |
| -------------------------- | -----------: | ---------------------: | -------------: |
| Guest                      |       Hidden |        No account page | Login required |
| Normal customer            |       Hidden |                 Hidden |        Blocked |
| Pending applicant          |       Hidden |                 Hidden |        Blocked |
| Rejected applicant         |       Hidden |                 Hidden |        Blocked |
| Approved business customer |      Visible |                Visible |        Allowed |

Rejected customers cannot currently apply again online. Reapplication is a future Business Account enhancement.

---

## 4. Database design

Two tables were created.

```text
brewcraft_quote_request
brewcraft_quote_request_item
```

### Quote header table

The header represents one complete quote request.

Important columns:

```text
entity_id
customer_id
business_account_id
quote_number
quote_name
customer_message
admin_comment
status
original_subtotal
proposed_subtotal
currency_code
expires_at
created_at
updated_at
```

Example:

```text
BCQ-20260804-12345678
Monthly Office Coffee Order
Status: pending
Customer: 25
Original subtotal: 18,000
```

### Quote item table

The item table stores every visible cart item included in the request.

Important columns:

```text
quote_request_id
product_id
sku
product_name
requested_qty
original_price
proposed_price
original_row_total
proposed_row_total
product_options
```

Relationship:

```text
One quote request
        ↓
Many quote request items
```

Magento/database concept:

```text
One-to-many relationship
```

Example:

```text
Quote request BCQ-001
├── Coffee Machine × 2
├── Grinder × 3
└── Coffee Beans × 20
```

---

## 5. Why product data is copied into the quote tables

We store both:

```text
product_id
```

and a product snapshot:

```text
SKU
Product name
Quantity
Current price
Product options
```

This is necessary because catalog data can change after submission.

For example:

```text
Customer submits request today
        ↓
Product price changes next week
        ↓
Admin reviews the request later
```

The quote must still show the values the customer originally requested.

Therefore:

```text
Catalog product
→ Current product data

Quote request item
→ Historical snapshot at submission time
```

`product_id` is nullable so that the request history can remain even if the product is later deleted.

---

## 6. Declarative schema

The tables were created through:

```text
etc/db_schema.xml
```

Magento concept:

```text
Declarative schema
```

Instead of manually writing:

```sql
CREATE TABLE ...
```

we declare the required database structure in XML.

Magento compares the XML declaration with the actual database when running:

```bash
bin/magento setup:upgrade
```

The schema contains:

```text
Columns
Primary keys
Foreign keys
Unique constraints
Indexes
Delete behavior
```

Important constraints:

```text
quote_request.customer_id
→ customer_entity.entity_id

quote_request.business_account_id
→ brewcraft_business_account.entity_id

quote_request_item.quote_request_id
→ brewcraft_quote_request.entity_id
```

If a quote request header is deleted:

```text
Its item rows are deleted automatically
```

because the foreign key uses:

```text
onDelete="CASCADE"
```

---

## 7. Model, Resource Model and Collection

For both header and item entities, we created:

```text
Model
Resource Model
Collection
```

### Model

Example:

```php
class QuoteRequest extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(
            \BrewCraft\RequestQuote\Model\ResourceModel\QuoteRequest::class
        );
    }
}
```

Magento concept:

```text
Model
```

The model represents business data as a PHP object.

Example:

```php
$quoteRequest->getData('quote_name');
$quoteRequest->getStatus();
$quoteRequest->getCustomerId();
```

It does not normally contain direct SQL.

### Resource Model

Important code:

```php
class QuoteRequest extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init(
            'brewcraft_quote_request',
            'entity_id'
        );
    }
}
```

Magento concept:

```text
Resource Model
```

It connects:

```text
PHP model
        ↓
Database table
        ↓
Primary key
```

The Resource Model performs persistence operations such as:

```text
Load
Save
Delete
```

### Collection

Important code:

```php
$this->_init(
    QuoteRequestModel::class,
    QuoteRequestResource::class
);
```

Magento concept:

```text
Collection
```

A collection represents multiple database records.

Example:

```php
$collection->addFieldToFilter(
    'customer_id',
    $customerId
);

$collection->setOrder(
    'created_at',
    'DESC'
);
```

This is used for the My Quote Requests list.

---

## 8. Repository pattern

We created repository interfaces:

```text
QuoteRequestRepositoryInterface
QuoteRequestItemRepositoryInterface
```

and implementations:

```text
QuoteRequestRepository
QuoteRequestItemRepository
```

Important repository methods:

```php
save()
getById()
getByQuoteNumber()
getByCustomerId()
delete()
deleteById()
```

Magento concept:

```text
Service contract / Repository pattern
```

The rest of the module does not need to know how the database is accessed.

For example, the controller or block uses:

```php
$this->quoteRequestRepository
    ->getByQuoteNumber($quoteNumber);
```

instead of directly writing SQL.

This provides:

```text
Separation of concerns
Reusable data access
Centralized exception handling
Easier API exposure later
Easier testing
```

---

## 9. Dependency Injection preference

The repository interfaces were connected to their implementations in:

```text
etc/di.xml
```

Important code:

```xml
<preference
    for="BrewCraft\RequestQuote\Api\QuoteRequestRepositoryInterface"
    type="BrewCraft\RequestQuote\Model\QuoteRequestRepository"/>
```

Magento concept:

```text
Dependency Injection preference
```

When a constructor asks for:

```php
QuoteRequestRepositoryInterface $quoteRequestRepository
```

Magento injects:

```text
QuoteRequestRepository
```

This lets code depend on an interface instead of a concrete implementation.

---

## 10. Quote status model

The quote status constants are stored in the `QuoteRequest` model:

```php
public const STATUS_PENDING = 'pending';
public const STATUS_UNDER_REVIEW = 'under_review';
public const STATUS_QUOTED = 'quoted';
public const STATUS_ACCEPTED = 'accepted';
public const STATUS_REJECTED = 'rejected';
public const STATUS_CONVERTED = 'converted';
public const STATUS_CANCELLED = 'cancelled';
public const STATUS_EXPIRED = 'expired';
```

Current submissions start with:

```text
pending
```

A separate source model converts machine values into readable labels:

```php
[
    'value' => QuoteRequest::STATUS_PENDING,
    'label' => __('Pending')
]
```

Magento concept:

```text
Option source
```

This can later be reused by:

```text
Admin grid filters
Admin forms
Customer status labels
Dropdown fields
```

---

## 11. Business-customer eligibility service

Class:

```text
BusinessCustomerEligibilityService
```

This service centralizes the access rule.

Important method:

```php
public function validate(int $customerId): BusinessAccount
```

Important checks:

```php
$businessAccount = $this
    ->businessAccountRepository
    ->getByCustomerId($customerId);
```

Then:

```php
if (!$businessAccount->isApproved()) {
    throw new LocalizedException(...);
}
```

Then it checks the customer group:

```php
$customer = $this->customerRepository->getById($customerId);

$group = $this->groupRepository->getById(
    (int)$customer->getGroupId()
);
```

Finally:

```php
if ($group->getCode() !== 'Business Customer') {
    throw new LocalizedException(...);
}
```

Magento concept:

```text
Service layer
```

Instead of repeating the same approval logic in every controller and block, all components call one service.

Used by:

```text
Cart button block
Quote create controller
Quote submission service
My Quote Requests navigation
My Quote Requests controller
Quote details controller
```

This prevents business rules from becoming inconsistent.

---

## 12. Why both application status and customer group are checked

A customer is eligible only when:

```text
Application approved
        +
Correct group assigned
```

This handles inconsistent states.

Example 1:

```text
Application is approved
but group assignment failed
```

Example 2:

```text
Admin manually placed a retail customer in Business Customer group
but there is no approved application
```

Either situation should block quote access.

Therefore:

```text
Approval record
→ proves business verification

Customer group
→ proves active storefront entitlement
```

---

## 13. Quote submission service

Class:

```text
QuoteSubmissionService
```

This is the core class that actually creates the quote request.

Main method:

```php
public function submit(
    int $customerId,
    string $quoteName,
    ?string $customerMessage = null
): QuoteRequest
```

The service flow is:

```text
Validate form input
        ↓
Validate business customer
        ↓
Load active Magento cart
        ↓
Validate cart items
        ↓
Start transaction
        ↓
Create quote header
        ↓
Create quote items
        ↓
Commit transaction
        ↓
Return created quote
```

---

## 14. Magento shopping cart versus Request Quote

Magento already uses the word `quote` for the shopping cart.

```text
Magento Quote
→ Active shopping cart

BrewCraft Quote Request
→ Saved business negotiation request
```

The submission service loads the active cart using:

```php
$this->cartRepository->getActiveForCustomer(
    $customerId
);
```

Magento concept:

```text
Quote repository
```

The active cart contains:

```text
Customer
Items
Quantities
Calculated prices
Currency
Totals
Product options
```

We copy the required information into BrewCraft’s custom tables.

---

## 15. Why `getAllVisibleItems()` is used

Important code:

```php
$visibleItems = $cart->getAllVisibleItems();
```

We did not use:

```php
$cart->getAllItems();
```

For configurable, bundle or grouped products, Magento may maintain parent and child quote-item records internally.

`getAllVisibleItems()` returns the rows that the customer actually sees in the cart.

This prevents a configurable product from being copied twice as:

```text
Configurable parent
Simple child
```

---

## 16. Price snapshot logic

Important code:

```php
$unitPrice = (float)$cartItem->getCalculationPrice();
```

This captures the current calculated cart price.

For an approved BrewCraft Business Customer:

```text
Retail price: ₹1,000
Business discount price: ₹900
Stored original quote price: ₹900
```

This was intentional.

The quote negotiation begins from the price the customer is currently entitled to receive, not necessarily the retail base price.

The item row total is calculated as:

```php
$rowTotal = $unitPrice * $quantity;
```

The quote header subtotal is created from all visible cart items.

---

## 17. Database transaction

Important code:

```php
$connection->beginTransaction();
```

Then:

```php
Save quote header
Save item 1
Save item 2
Save item 3
```

On success:

```php
$connection->commit();
```

On failure:

```php
$connection->rollBack();
```

Magento/database concept:

```text
Transaction
```

This ensures atomicity.

Without a transaction, we could get:

```text
Quote header saved
Item 1 saved
Item 2 failed
Item 3 never saved
```

That would create an incomplete quote request.

With the transaction:

```text
Everything saves
or
Nothing saves
```

---

## 18. Quote number generation

The service generates a readable number similar to:

```text
BCQ-20260804-12345678
```

Important logic:

```php
sprintf(
    'BCQ-%s-%s',
    gmdate('Ymd'),
    $this->random->getRandomString(
        8,
        Random::CHARS_DIGITS
    )
);
```

Meaning:

```text
BCQ
→ BrewCraft Quote

20260804
→ Creation date

12345678
→ Random numeric portion
```

The database also has a unique constraint on `quote_number`.

This prevents two quote records from sharing the same external reference.

---

## 19. Product options snapshot

The submission service checks cart item options such as:

```text
info_buyRequest
options
additional_options
```

Then serializes them into JSON.

This supports products with choices such as:

```text
Size
Color
Machine configuration
Custom options
Bundle selections
```

Magento concept:

```text
Quote item options
```

The option snapshot allows the quote request to preserve exactly what the customer selected.

---

## 20. Frontend route

The route was created in:

```text
etc/frontend/routes.xml
```

Important configuration:

```xml
<route id="requestquote"
       frontName="requestquote">
    <module name="BrewCraft_RequestQuote"/>
</route>
```

Magento concept:

```text
Frontend routing
```

Example URL:

```text
/requestquote/request/create
```

Magento resolves it as:

```text
frontName: requestquote
controller folder: Request
action class: Create
```

Another example:

```text
/requestquote/account/view
```

resolves to:

```text
Controller/Account/View.php
```

---

## 21. Request a Quote cart button

A custom block was added to the shopping cart.

Class:

```text
Block/Cart/RequestQuote.php
```

Important method:

```php
public function canRequestQuote(): bool
```

It checks:

```text
Logged in
Approved business customer
Business Customer group
Cart has visible items
```

The template displays:

```text
Request a Quote
```

only when:

```php
$block->canRequestQuote()
```

returns `true`.

Magento concept:

```text
Block + template
```

The block prepares data and business decisions.

The `.phtml` file renders HTML.

However, hiding the button is not considered security. Controllers also repeat server-side validation.

---

## 22. Quote create controller

URL:

```text
/requestquote/request/create
```

Purpose:

```text
Open the quote submission form
```

The controller checks:

```text
Customer logged in
Customer eligible
Cart exists
Cart has items
```

Then it creates the page:

```php
$resultPage = $this->pageFactory->create();
```

Magento concept:

```text
Page result
```

The layout handle is:

```text
requestquote_request_create.xml
```

Magento creates the handle from:

```text
frontName_controller_action
```

The layout adds the block and template to the page.

---

## 23. Quote form

The form displays:

```text
Products
SKUs
Quantities
Current prices
Current subtotal
Quote name
Customer message
```

Important security field:

```php
<?= $block->getBlockHtml('formkey') ?>
```

Magento concept:

```text
Form key / CSRF protection
```

Magento uses the form key to verify that the POST request came from the legitimate storefront session.

---

## 24. Save controller

URL:

```text
/requestquote/request/save
```

The controller implements:

```php
HttpPostActionInterface
```

Purpose:

```text
Receive form POST
Validate form key
Call QuoteSubmissionService
Redirect to success page
```

Important code:

```php
if (!$this->formKeyValidator->validate($this->request)) {
    ...
}
```

Then:

```php
$quoteRequest = $this
    ->quoteSubmissionService
    ->submit(
        $customerId,
        $quoteName,
        $customerMessage
    );
```

Notice that the controller does not create database records itself.

Its responsibility is:

```text
Read HTTP request
Call service
Add success/error message
Redirect
```

The service performs the business operation.

This is an important Magento architecture principle:

```text
Thin controller
        ↓
Service layer
        ↓
Repository and persistence
```

---

## 25. Controller request injection issue we fixed

Initially, the Save controller called:

```php
$this->getRequest()
```

But it only implemented:

```php
HttpPostActionInterface
```

and did not extend Magento’s old base `Action` class.

Therefore, `getRequest()` did not exist.

The error was:

```text
Call to undefined method Save\Interceptor::getRequest()
```

We corrected it by injecting:

```php
RequestInterface $request
```

Then using:

```php
$this->request->getParam('quote_name');
```

Magento concept:

```text
Constructor dependency injection
```

Rule:

```text
Controller extends Action
→ getRequest() inherited

Controller only implements HTTP action interface
→ inject RequestInterface
```

The same correction was applied to the Success controller.

---

## 26. Success page

After successful submission, the user is redirected to:

```text
/requestquote/request/success
```

The URL includes:

```text
quote_number
```

The controller loads the quote and checks:

```php
$quoteRequest->getCustomerId()
===
$this->customerSession->getCustomerId()
```

This prevents one customer from viewing another customer’s success page.

The success page shows:

```text
Quote number
Quote name
Pending status
Confirmation message
```

---

## 27. My Account navigation

A new My Account menu item was added:

```text
My Quote Requests
```

Initially, the normal Magento `SortLinkInterface` was used.

That caused the link to appear for every logged-in customer, including retail customers.

We corrected this by creating:

```text
QuoteNavigationLink
```

The block extends Magento’s account navigation link block and overrides:

```php
protected function _toHtml(): string
```

Important logic:

```php
if (!$this->eligibilityService->isEligible($customerId)) {
    return '';
}

return parent::_toHtml();
```

Magento concept:

```text
Conditional block rendering
```

Ineligible customer:

```text
Block returns empty HTML
→ Menu item is hidden
```

Eligible customer:

```text
Parent block renders normal navigation link
```

---

## 28. Hiding a menu is not security

The navigation is hidden for:

```text
Normal customers
Pending applicants
Rejected applicants
```

But a user can manually enter:

```text
/requestquote/account/index
```

Therefore, the Account controller also calls:

```php
$this->eligibilityService->validate($customerId);
```

If validation fails:

```text
Redirect to Business Account page
```

This gives two levels:

```text
UI protection
→ Hide irrelevant feature

Server-side protection
→ Block unauthorized access
```

The second one is the real security control.

---

## 29. My Quote Requests listing

URL:

```text
/requestquote/account/index
```

The listing block obtains the current customer ID:

```php
$customerId = (int)$this
    ->customerSession
    ->getCustomerId();
```

Then calls:

```php
$this->quoteRequestRepository
    ->getByCustomerId($customerId);
```

The repository filters:

```php
$collection->addFieldToFilter(
    'customer_id',
    $customerId
);
```

This ensures the customer’s list contains only their own requests.

The listing displays:

```text
Quote number
Quote name
Status
Original subtotal
Proposed subtotal
Submitted date
View action
```

For a quote that has not yet been priced by Admin:

```text
Proposed total
→ Not yet available
```

---

## 30. Pagination

The listing uses Magento’s pager block:

```php
Pager::class
```

Allowed limits:

```text
5
10
20
```

Magento concept:

```text
Collection pagination
```

The pager updates:

```text
Current page
Page size
Collection limit
```

This prevents all customer quote records from being loaded at once.

---

## 31. Quote details page

URL pattern:

```text
/requestquote/account/view/quote_number/BCQ-...
```

The controller loads the request using:

```php
$this->quoteRequestRepository
    ->getByQuoteNumber($quoteNumber);
```

Then performs an ownership check:

```php
if ($quoteRequest->getCustomerId() !== $customerId) {
    ...
}
```

Magento/security concept:

```text
Object-level authorization
```

It is not enough that the customer is logged in or is a Business Customer.

They must also own the specific quote being requested.

This prevents:

```text
Customer A changing quote number in URL
and viewing Customer B’s quote
```

---

## 32. Registry usage

After loading and validating the quote, the controller places it in Magento’s registry:

```php
$this->registry->register(
    'current_brewcraft_quote_request',
    $quoteRequest
);
```

The View block retrieves it:

```php
$this->registry->registry(
    'current_brewcraft_quote_request'
);
```

Magento concept:

```text
Registry
```

This passes the currently loaded entity from the controller to page blocks during the same request.

For this learning module, it follows a common Magento pattern.

A newer design could also use a dedicated current-quote provider service, but the registry is valid for this implementation.

---

## 33. Quote items on the details page

The details block loads items using:

```php
$this->itemRepository
    ->getByQuoteRequestId(
        (int)$quoteRequest->getId()
    );
```

The item repository filters:

```php
quote_request_id = current request ID
```

The details page displays:

```text
Product
SKU
Requested quantity
Original unit price
Proposed unit price
Original row total
Proposed row total
```

Currently:

```text
Proposed price
→ Pending

Proposed row total
→ Pending
```

because the Admin negotiation feature has not yet been built.

---

## 34. Layout XML flow

Examples:

```text
requestquote_account_index.xml
requestquote_account_view.xml
```

Both use:

```xml
<update handle="customer_account"/>
```

Magento concept:

```text
Layout handle update
```

This imports the standard customer account layout, including:

```text
Account sidebar
Account navigation
Main account content area
```

Then the custom quote block is inserted into:

```xml
<referenceContainer name="content">
```

The result is a standard My Account page with BrewCraft content.

---

## 35. Cache decisions

The quote-related blocks use:

```xml
cacheable="false"
```

because their content depends on:

```text
Current customer session
Current cart
Current customer quote records
```

Magento concept:

```text
Full Page Cache safety
```

Customer-specific data must not be served from a shared page cache.

Otherwise, one user could potentially receive another user’s personalized block output.

---

## 36. Logging

The submission service logs success:

```php
$this->logger->info(
    'BrewCraft quote request submitted.',
    [
        'quote_request_id' => ...,
        'quote_number' => ...,
        'customer_id' => ...,
        'item_count' => ...
    ]
);
```

It also logs failures:

```php
$this->logger->error(
    'BrewCraft quote request submission failed.',
    [...]
);
```

Magento concept:

```text
PSR logger
```

Logs help diagnose:

```text
Submission failures
Cart loading issues
Eligibility issues
Unexpected exceptions
```

---

## 37. Exception handling

Repositories convert low-level persistence failures into Magento exceptions such as:

```text
CouldNotSaveException
CouldNotDeleteException
NoSuchEntityException
```

The service uses:

```text
LocalizedException
```

for safe customer-facing messages.

Example:

```php
throw new LocalizedException(
    __('Add at least one product to your cart before requesting a quote.')
);
```

Magento concept:

```text
Exception translation
```

Low-level technical errors are logged.

Customers receive understandable messages without database or stack-trace details.

---

## 38. Complete request flow

### Customer opens cart

```text
Cart page layout
        ↓
RequestQuote block created
        ↓
Eligibility service checks customer
        ↓
Button shown only for approved Business Customer
```

### Customer opens request form

```text
Create controller
        ↓
Session validation
        ↓
Business eligibility validation
        ↓
Active cart validation
        ↓
PageFactory creates page
        ↓
Layout loads Create block and template
```

### Customer submits form

```text
Save controller
        ↓
POST method
        ↓
Form key validation
        ↓
Read quote name and message
        ↓
QuoteSubmissionService::submit()
```

### Submission service

```text
Validate input
        ↓
Validate Business Customer
        ↓
Load active Magento cart
        ↓
Read visible cart items
        ↓
Begin database transaction
        ↓
Create quote-request header
        ↓
Create quote-request items
        ↓
Commit
        ↓
Return quote request
```

### Success page

```text
Save controller redirects with quote number
        ↓
Success controller loads quote
        ↓
Ownership validation
        ↓
Confirmation page displayed
```

### My Quote Requests

```text
Account navigation checks eligibility
        ↓
Approved customer sees menu
        ↓
Index controller validates eligibility
        ↓
Block loads collection filtered by customer ID
        ↓
Customer sees their own quotes
```

### Quote details

```text
Customer clicks View
        ↓
View controller loads quote number
        ↓
Eligibility validation
        ↓
Ownership validation
        ↓
Registry stores current quote
        ↓
View block loads quote items
        ↓
Details template renders
```

---

## 39. Tests completed

### Access tests

```text
Guest
→ No account menu
→ Direct URL redirects to login

Normal customer
→ Quote button hidden
→ My Quote Requests hidden
→ Direct access blocked

Pending applicant
→ Quote button hidden
→ My Quote Requests hidden
→ Direct access blocked

Rejected applicant
→ Quote button hidden
→ My Quote Requests hidden
→ Direct access blocked

Approved Business Customer
→ Quote button visible
→ My Quote Requests visible
→ Access allowed
```

### Submission test

```text
Approved customer added products
Opened Request a Quote form
Entered quote name and message
Submitted successfully
Success page displayed quote number
```

### Database test

Verified header in:

```text
brewcraft_quote_request
```

Verified items in:

```text
brewcraft_quote_request_item
```

Verified:

```text
Status = pending
Customer ID correct
Business application ID correct
Subtotal correct
One item row per visible cart item
Business price stored as original price
Proposed prices are NULL
```

### Ownership test

```text
Customer A cannot open Customer B’s quote
```

### Account listing test

```text
Approved customer with quote
→ Quote appears in list

Approved customer without quote
→ Correct empty state

Ineligible customer
→ Menu hidden and direct access blocked
```

---

## 40. Current status of the module


Not built yet:

```text
Admin Quote Requests grid
Admin quote details page
Set request to Under Review
Admin item-level proposed prices
Proposed subtotal calculation
Admin comments
Quote expiration
Customer email notifications
Customer accept action
Customer reject action
Accepted quote to cart conversion
Negotiated price application
Order reference
Quote status history
Admin and customer comment history
Cron-based quote expiration
```

---

## 41. Interview-ready explanation

> I created a custom Request for Quote module for Magento Open Source because the native Negotiable Quotes feature belongs to Adobe Commerce B2B. The module integrates with our custom Business Account module, Magento customer groups and the active shopping cart. Only customers with an approved business application and the Business Customer group can access the feature.

> When an eligible customer requests a quote, the service loads their active Magento cart, copies the visible cart items into custom quote header and item tables, preserves product and price snapshots, and saves everything inside a database transaction. The original price is the customer’s current calculated cart price, including any applicable business catalog discount.

> I used Models, Resource Models, Collections and Repository interfaces for persistence, a service layer for eligibility and submission logic, dependency injection preferences, frontend routes, controllers, blocks, templates and layout XML. The storefront includes a conditional Request a Quote button, CSRF-protected submission form, success page, My Account listing and details page.

> The feature is secured at multiple levels. Navigation links and buttons are hidden for ineligible customers, but controllers also perform server-side eligibility checks. Quote details perform ownership validation so one customer cannot access another customer’s quote by changing the URL.

> The next phase is the Admin negotiation workflow, where the seller reviews requests, proposes item-level prices, sends the quotation to the customer, and allows the customer to accept, reject or convert the accepted quote into a cart and order.



# 2.BrewCraft Request Quote Module — Development Log
**DATE:** 5th AUG
### Phase Covered: Admin Setup → Admin Proposal → Customer Accept/Reject

Module:

```text
BrewCraft_RequestQuote
```

This log covers the work completed after customer quote submission and My Account history were already working.

The completed scope is:

```text
Admin route
Admin menu
ACL
Admin Quote Requests grid
Admin quote details page
Mark Under Review
Admin price proposal
Proposed totals
Admin comment
Expiry date
Customer Accept
Customer Reject
Security and validation
```

---

## 1. Starting point of this phase

Before starting the Admin workflow, the module already supported:

```text
Approved Business Customer
        ↓
Adds products to cart
        ↓
Submits Request for Quote
        ↓
Quote header saved
        ↓
Quote items saved
        ↓
Status = pending
        ↓
Customer sees quote in My Account
```

At that point, the customer could submit and view a request, but Admin had no interface to manage it.

The next requirement was:

```text
Admin receives quote request
        ↓
Reviews customer and products
        ↓
Changes status
        ↓
Proposes custom prices
        ↓
Customer accepts or rejects
```

---

## 2. Why an Admin workflow was required

A Request for Quote feature is not complete when the customer only submits a request.

The seller must be able to:

```text
See all quote requests
Filter and search requests
Review customer information
Review company information
Review requested items
Start reviewing the request
Enter negotiated prices
Add commercial terms
Set an expiry date
Publish the proposal
Track the customer’s response
```

Therefore, we built a custom Magento Admin workflow.

---

## 3. Admin route

File:

```text
etc/adminhtml/routes.xml
```

The Admin route registered the Request Quote module with Magento’s Admin router.

Important structure:

```xml
<router id="admin">
    <route id="requestquote"
           frontName="brewcraft_requestquote">

        <module name="BrewCraft_RequestQuote"
                before="Magento_Backend"/>
    </route>
</router>
```

### Magento concept

This is:

```text
Admin routing
```

The route connects an Admin URL to a controller class.

For example:

```text
requestquote/quote/index
```

resolves to:

```text
Controller/Adminhtml/Quote/Index.php
```

The real browser URL also contains Magento’s custom Admin path and security key.

We never hard-code the complete Admin URL. Magento generates it using the URL builder.

---

## 4. Admin menu

File:

```text
etc/adminhtml/menu.xml
```

The Request Quote module added a new menu item under the existing BrewCraft root menu:

```text
BrewCraft
├── Business Applications
└── Quote Requests
```

Important configuration:

```xml
<add id="BrewCraft_RequestQuote::quote_requests"
     title="Quote Requests"
     module="BrewCraft_RequestQuote"
     parent="BrewCraft_BusinessAccount::brewcraft"
     action="requestquote/quote/index"
     resource="BrewCraft_RequestQuote::quote_requests"/>
```

### Magento concept

This is:

```text
Admin menu configuration
```

Important fields:

```text
id
→ Unique menu-node ID

title
→ Label displayed in Admin

parent
→ Existing BrewCraft root menu

action
→ Controller route to open

resource
→ ACL permission required
```

We reused the root menu created by `BrewCraft_BusinessAccount`.

This avoided creating duplicate BrewCraft menu sections.

---

## 5. ACL permission

File:

```text
etc/acl.xml
```

ACL means:

```text
Access Control List
```

We created:

```text
BrewCraft_RequestQuote::quote_requests
```

Important configuration:

```xml
<resource id="Magento_Backend::admin">
    <resource id="BrewCraft_RequestQuote::quote_requests"
              title="Quote Requests"
              sortOrder="30"/>
</resource>
```

This ACL resource is used by:

```text
Admin menu
Admin grid controller
Admin details controller
Mark Under Review controller
Save Proposal controller
Admin roles
```

### Why ACL is important

Without ACL, every Admin user could potentially access quote requests.

With ACL, an Admin role can be configured to allow or deny this feature.

The controller declares:

```php
public const ADMIN_RESOURCE =
    'BrewCraft_RequestQuote::quote_requests';
```

Magento automatically checks that permission before executing the controller.

---

## 6. Admin grid controller

File:

```text
Controller/Adminhtml/Quote/Index.php
```

The controller creates the Quote Requests Admin page.

Important work:

```php
$resultPage = $this->pageFactory->create();

$resultPage->setActiveMenu(
    'BrewCraft_RequestQuote::quote_requests'
);

$resultPage->getConfig()->getTitle()->prepend(
    __('Quote Requests')
);
```

### Responsibility of the controller

The controller does not load all database rows manually.

Its responsibility is:

```text
Check Admin access
Create page result
Set active menu
Set breadcrumbs
Set page title
```

The UI Component grid handles the data loading and rendering.

This keeps the controller thin.

---

## 7. Admin layout handle

File:

```text
view/adminhtml/layout/requestquote_quote_index.xml
```

Important code:

```xml
<referenceContainer name="content">
    <uiComponent name="brewcraft_quote_request_listing"/>
</referenceContainer>
```

### Magento concept

This is:

```text
Layout XML
```

Magento creates the handle name from:

```text
route ID + controller + action
```

For:

```text
requestquote/quote/index
```

the layout handle is:

```text
requestquote_quote_index.xml
```

The layout adds the UI Component to the main Admin content container.

---

## 8. Admin UI Component grid

File:

```text
view/adminhtml/ui_component/
brewcraft_quote_request_listing.xml
```

The UI Component defines:

```text
Data source
Data provider
Columns
Filters
Sorting
Pagination
Bookmarks
Column controls
Status dropdown
Actions column
```

### Main data-source name

```text
brewcraft_quote_request_listing_data_source
```

This name is important because it must match the collection mapping in `di.xml`.

The connection is:

```text
UI Component data-source name
        ↓
CollectionFactory DI mapping
        ↓
Custom grid collection
        ↓
Database query
```

---

## 9. UI Component ACL issue fixed

Initially, the grid XML contained:

```xml
<aclResource>
    BrewCraft_RequestQuote::quote_requests
</aclResource>
```

Magento rejected it with:

```text
Element 'aclResource': This element is not expected.
```

It was rejected both:

```text
Directly under dataSource
```

and:

```text
Inside dataSource settings
```

The correction was to remove the UI Component `aclResource` node completely.

Security still remains because:

```text
menu.xml uses the ACL resource
        +
Admin controller uses ADMIN_RESOURCE
```

So unauthorized Admin users still cannot open the grid page.

This taught an important Magento lesson:

> UI Component XML must match the XSD supported by the exact installed Magento version. A configuration example that works in another version may fail in the local schema.

---

## 10. Grid collection

File:

```text
Model/ResourceModel/QuoteRequest/Grid/Collection.php
```

The collection extends:

```php
Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult
```

### Magento concept

This is:

```text
Admin UI Component SearchResult collection
```

The main table is:

```text
brewcraft_quote_request
```

But the Admin grid also needed:

```text
Customer email
Company name
```

Those values are stored in:

```text
customer_entity
brewcraft_business_account
```

Therefore, the grid collection joined those tables.

Important code:

```php
$this->getSelect()->joinLeft(
    ['customer' => $this->getTable('customer_entity')],
    'main_table.customer_id = customer.entity_id',
    ['customer_email' => 'customer.email']
);
```

And:

```php
$this->getSelect()->joinLeft(
    [
        'business_account' =>
            $this->getTable('brewcraft_business_account')
    ],
    'main_table.business_account_id = business_account.entity_id',
    [
        'company_name' =>
            'business_account.company_name'
    ]
);
```

Conceptual SQL:

```sql
SELECT
    main_table.*,
    customer.email AS customer_email,
    business_account.company_name
FROM brewcraft_quote_request AS main_table
LEFT JOIN customer_entity AS customer
    ON customer.entity_id = main_table.customer_id
LEFT JOIN brewcraft_business_account AS business_account
    ON business_account.entity_id =
       main_table.business_account_id;
```

---

## 11. Filter mapping

Joined fields require filter mappings.

Important examples:

```php
$this->addFilterToMap(
    'customer_email',
    'customer.email'
);
```

```php
$this->addFilterToMap(
    'company_name',
    'business_account.company_name'
);
```

```php
$this->addFilterToMap(
    'status',
    'main_table.status'
);
```

### Why this is needed

When Admin filters:

```text
Customer Email = customer@example.com
```

Magento needs to know the real SQL field:

```text
customer.email
```

Without filter mapping, Magento might try:

```text
WHERE customer_email = ...
```

even though `customer_email` is only a selected alias.

This can produce:

```text
Unknown column
Ambiguous field
Incorrect filtering
```

---

## 12. DI collection mapping

File:

```text
etc/di.xml
```

Important mapping:

```xml
<type name="Magento\Framework\View\Element\UiComponent\DataProvider\CollectionFactory">
    <arguments>
        <argument name="collections"
                  xsi:type="array">

            <item name="brewcraft_quote_request_listing_data_source"
                  xsi:type="string">
                BrewCraft\RequestQuote\Model\ResourceModel\QuoteRequest\Grid\Collection
            </item>

        </argument>
    </arguments>
</type>
```

### Magento concept

This is:

```text
Dependency Injection argument merging
```

Magento receives the data-source name from the UI Component and asks `CollectionFactory` for the matching collection class.

The data-source name must match exactly.

```text
brewcraft_quote_request_listing_data_source
```

Any mismatch can cause:

```text
Grid loading forever
Collection not registered
Invalid data source
Missing handle errors
```

We placed the mapping in global:

```text
etc/di.xml
```

This was based on the earlier Business Account grid issue where an incorrect Admin-only DI mapping affected core grid collection mappings.

---

## 13. Admin grid columns

The grid displays:

```text
ID
Quote Number
Quote Name
Company
Customer Email
Status
Original Subtotal
Proposed Subtotal
Currency
Submitted At
Updated At
Actions
```

### Status column

The status column uses:

```text
Model/Source/Status.php
```

Important UI configuration:

```xml
<column name="status"
        component="Magento_Ui/js/grid/columns/select">

    <settings>
        <options class="BrewCraft\RequestQuote\Model\Source\Status"/>
        <filter>select</filter>
        <dataType>select</dataType>
        <label translate="true">Status</label>
    </settings>
</column>
```

This lets Admin filter by:

```text
Pending
Under Review
Quoted
Accepted
Rejected
Converted
Cancelled
Expired
```

---

## 14. Admin grid verified

The submitted customer quote was successfully displayed in Admin.

Verified information included:

```text
Quote number
Quote name
Company
Customer email
Pending status
Original subtotal
Currency
Submitted date
```

The grid filtering and pagination were also available through Magento’s normal UI Component toolbar.

---

## 15. Actions column

Class:

```text
Ui/Component/Listing/Column/QuoteActions.php
```

The grid needed a View action.

Important code:

```php
$item[$columnName]['view'] = [
    'href' => $this->urlBuilder->getUrl(
        'requestquote/quote/view',
        [
            'id' => $entityId
        ]
    ),
    'label' => __('View'),
    'hidden' => false
];
```

### Magento concept

This is:

```text
UI Component custom column data preparation
```

`prepareDataSource()` receives the rows loaded by the data provider.

For every row, it adds:

```text
Actions
└── View
```

The URL is generated through Magento’s URL builder, including the Admin route and secret key.

---

## 16. Admin View controller

File:

```text
Controller/Adminhtml/Quote/View.php
```

Flow:

```text
Admin clicks View
        ↓
Controller receives entity ID
        ↓
Repository loads quote request
        ↓
Record exists?
    ├── No → message and grid redirect
    └── Yes → register current quote
        ↓
Create details page
```

Important loading code:

```php
$quoteRequest = $this
    ->quoteRequestRepository
    ->getById($entityId);
```

The controller stores the loaded quote in Magento’s Registry:

```php
$this->registry->register(
    self::REGISTRY_KEY,
    $quoteRequest
);
```

### Magento concept

This uses:

```text
Repository
Registry
PageFactory
Admin ACL
```

---

## 17. Admin details block

File:

```text
Block/Adminhtml/Quote/View.php
```

The block prepares data for the template.

It loads:

```text
Current quote request
Quote items
Customer
Business Account
Status label
Formatted prices
Formatted dates
Action URLs
```

### Current quote from Registry

```php
$this->registry->registry(
    ViewController::REGISTRY_KEY
);
```

### Quote items

```php
$this->itemRepository
    ->getByQuoteRequestId(
        (int)$quoteRequest->getId()
    );
```

### Customer

```php
$this->customerRepository->getById(
    $quoteRequest->getCustomerId()
);
```

### Business Account

```php
$this->businessAccountRepository
    ->getByCustomerId(
        $quoteRequest->getCustomerId()
    );
```

The block keeps the template focused mainly on rendering.

---

## 18. Admin quote details page

Files:

```text
view/adminhtml/layout/requestquote_quote_view.xml
view/adminhtml/templates/quote/view.phtml
```

The details page displays:

```text
Quote Information
Customer and Company
Customer Request
Admin Comment
Requested Products
```

### Quote information

```text
Internal ID
Quote number
Quote name
Status
Currency
Original subtotal
Proposed subtotal
Submitted date
Updated date
Expiry date
```

### Customer and company

```text
Customer ID
Customer name
Customer email
Business application ID
Company name
Registration number
```

### Requested products

```text
Item ID
Product name
SKU
Quantity
Original unit price
Proposed unit price
Original row total
Proposed row total
```

At the initial read-only phase, proposed prices showed:

```text
Not set
```

---

## 19. Mark Under Review feature

The first Admin state transition was:

```text
pending → under_review
```

Files:

```text
Model/Service/QuoteStatusService.php
Controller/Adminhtml/Quote/MarkUnderReview.php
```

---

## 20. QuoteStatusService

Class:

```text
QuoteStatusService
```

Important method:

```php
public function markUnderReview(
    QuoteRequest $quoteRequest
): QuoteRequest
```

Validation:

```php
if (
    $currentStatus
    !== QuoteRequest::STATUS_PENDING
) {
    throw new LocalizedException(...);
}
```

Status update:

```php
$quoteRequest->setData(
    'status',
    QuoteRequest::STATUS_UNDER_REVIEW
);
```

Persistence:

```php
$this->quoteRequestRepository->save(
    $quoteRequest
);
```

### Magento concept

This is:

```text
Service layer
State-transition validation
Repository persistence
```

We did not place the transition rule directly in the controller.

The service owns the business rule:

```text
Only pending requests can move to under review
```

---

## 21. Mark Under Review controller

File:

```text
Controller/Adminhtml/Quote/MarkUnderReview.php
```

The controller implements:

```php
HttpPostActionInterface
```

### Why POST?

The action changes database state.

Correct:

```text
POST → change status
```

Incorrect:

```text
GET → change status
```

The controller:

```text
Reads ID
Loads quote
Calls QuoteStatusService
Adds success/error message
Redirects to details page
```

Important call:

```php
$this->quoteStatusService->markUnderReview(
    $quoteRequest
);
```

---

## 22. Conditional Admin button

The Admin details block contains:

```php
public function canMarkUnderReview(): bool
{
    return $quoteRequest !== null
        && $quoteRequest->getStatus()
            === QuoteRequest::STATUS_PENDING;
}
```

The template displays:

```text
Mark Under Review
```

only while the request status is:

```text
pending
```

After the transition:

```text
Status = under_review
Button disappears
```

This is good user-interface behavior, but the real validation remains in the service.

---

## 23. Under Review verified

After clicking the button:

```text
Status changed from pending to under_review
```

Verified in:

```text
Admin details page
Admin grid
Customer My Quote Requests list
Customer quote details
Database
```

Because all pages read the status from the same quote-request record, no duplicate status logic was needed.

---

## 24. Admin proposal workflow

The next transition was:

```text
under_review → quoted
```

Admin can enter:

```text
Proposed unit price for each item
Admin comment
Optional expiry date
```

Files:

```text
Model/Service/QuoteProposalService.php
Controller/Adminhtml/Quote/SaveProposal.php
```

---

## 25. QuoteProposalService

This is the main class that performs the proposal operation.

Main method:

```php
public function saveProposal(
    QuoteRequest $quoteRequest,
    array $proposedPrices,
    ?string $adminComment = null,
    ?string $expiresAt = null
): QuoteRequest
```

Flow:

```text
Validate quote status
        ↓
Validate Admin comment
        ↓
Load quote items
        ↓
Validate every proposed price
        ↓
Validate expiry date
        ↓
Start transaction
        ↓
Save proposed item prices
        ↓
Calculate row totals
        ↓
Calculate subtotal
        ↓
Save comment and expiry
        ↓
Change status to quoted
        ↓
Commit transaction
```

---

## 26. Proposal-status validation

Important rule:

```php
if (
    $quoteRequest->getStatus()
    !== QuoteRequest::STATUS_UNDER_REVIEW
) {
    throw new LocalizedException(...);
}
```

Therefore:

```text
under_review → proposal allowed
pending → proposal blocked
quoted → proposal overwrite blocked
accepted → proposal blocked
rejected → proposal blocked
```

This prevents an already customer-visible proposal from being silently replaced.

---

## 27. Proposed-price validation

The Admin must provide a price for every item.

Important checks:

```php
if (!array_key_exists($itemId, $proposedPrices)) {
    throw new LocalizedException(...);
}
```

```php
if ($rawPrice === '' || !is_numeric($rawPrice)) {
    throw new LocalizedException(...);
}
```

```php
if ($price <= 0) {
    throw new LocalizedException(...);
}
```

Server-side validation is required because browser-side validation can be bypassed.

---

## 28. Item ownership validation

The service also checks:

```php
if (
    $quoteItem->getQuoteRequestId()
    !== (int)$quoteRequest->getId()
) {
    throw new LocalizedException(...);
}
```

This protects against submitting an item ID belonging to another quote.

Even though the service loads items by quote ID, this additional check makes the relationship explicit.

---

## 29. Proposed row-total calculation

For each item:

```php
$proposedRowTotal = round(
    $proposedPrice * $quantity,
    4
);
```

Example:

```text
Quantity = 5
Proposed unit price = ₹400

Proposed row total
= 5 × ₹400
= ₹2,000
```

The item stores:

```text
proposed_price
proposed_row_total
```

---

## 30. Proposed subtotal calculation

The service accumulates every proposed row total:

```php
$proposedSubtotal += $proposedRowTotal;
```

Then:

```php
$proposedSubtotal = round(
    $proposedSubtotal,
    4
);
```

The quote header stores:

```text
proposed_subtotal
```

This follows the header/item pattern:

```text
Item rows
→ Individual proposed totals

Header
→ Overall proposed subtotal
```

---

## 31. Admin comment

The Admin can provide commercial terms such as:

```text
Bulk-order discount applied
Delivery included
Minimum purchase requirement
Payment conditions
Offer limitations
```

The comment is stored in:

```text
brewcraft_quote_request.admin_comment
```

Maximum length:

```text
5,000 characters
```

The customer sees it under:

```text
Message from BrewCraft
```

---

## 32. Expiry date

Admin may select an optional expiry date.

The service validates:

```text
Correct YYYY-MM-DD date
Not in the past
```

The selected date is stored as:

```text
23:59:59 on the chosen day
```

For example:

```text
Input: 2026-08-20

Stored:
2026-08-20 23:59:59
```

This means the quotation remains valid throughout the selected date.

---

## 33. Proposal transaction

The proposal operation modifies:

```text
Multiple quote-request item rows
        +
One quote-request header row
```

Therefore, we used a database transaction.

Important structure:

```php
$connection->beginTransaction();
```

Then:

```text
Save item 1
Save item 2
Save item 3
Save quote header
```

On success:

```php
$connection->commit();
```

On failure:

```php
$connection->rollBack();
```

### Why this matters

Without a transaction:

```text
Item 1 saved
Item 2 saved
Item 3 failed
Header not updated
```

This would create an incomplete proposal.

With a transaction:

```text
Everything saves
or
Nothing saves
```

---

## 34. Proposal status transition

After prices and totals are saved:

```php
$quoteRequest->setData(
    'status',
    QuoteRequest::STATUS_QUOTED
);
```

The transition is:

```text
under_review → quoted
```

`quoted` means:

```text
The seller has prepared a proposal
The customer can now review it
The customer can accept or reject it
```

---

## 35. Save Proposal controller

File:

```text
Controller/Adminhtml/Quote/SaveProposal.php
```

The controller receives:

```text
Quote request ID
Proposed prices array
Admin comment
Expiry date
```

Example request structure:

```php
proposed_price[
    quote_item_id
] = proposed_unit_price
```

The controller delegates the work:

```php
$savedQuote = $this
    ->quoteProposalService
    ->saveProposal(
        $quoteRequest,
        $proposedPrices,
        $adminComment,
        $expiresAt
    );
```

The controller does not calculate totals.

That remains inside the service.

Architecture:

```text
Admin form
        ↓
SaveProposal controller
        ↓
QuoteProposalService
        ↓
Repositories
        ↓
Resource Models
        ↓
Database
```

---

## 36. Email intentionally postponed

The original proposal success message mentioned:

```text
saved and sent for customer review
```

Because email is not configured yet, we corrected it to:

```text
The price proposal for quote request %1 has been saved.
```

No email is sent currently.

The customer still sees the proposal immediately because My Account reads directly from the database.

Email notifications will be implemented later as one complete phase.

---

## 37. Admin proposal form

The Admin details page displays the proposal form only when:

```text
status = under_review
```

Important block method:

```php
public function canCreateProposal(): bool
{
    return $quoteRequest !== null
        && $quoteRequest->getStatus()
            === QuoteRequest::STATUS_UNDER_REVIEW;
}
```

The form contains:

```text
Product
SKU
Quantity
Current unit price
Proposed unit price
Admin comment
Expiry date
Save Proposal button
```

The default proposed price is the original item price.

Example:

```text
Original business price: ₹900
Proposal input default: ₹900
Admin changes it to: ₹820
```

---

## 38. Proposal verified

After saving:

```text
Status = quoted
Proposed unit prices saved
Proposed row totals calculated
Proposed subtotal calculated
Admin comment saved
Expiry date saved
```

Verified in:

```text
Admin details page
Admin grid
Customer quote listing
Customer quote details
Database header table
Database item table
```

---

## 39. Customer Accept/Reject requirement

Once status became:

```text
quoted
```

the customer needed to decide:

```text
Accept Proposal
or
Reject Proposal
```

Business transitions:

```text
quoted → accepted
quoted → rejected
```

These are final for the current implementation.

---

## 40. QuoteResponseService

File:

```text
Model/Service/QuoteResponseService.php
```

Main methods:

```php
accept()
reject()
canRespond()
```

Accept:

```php
return $this->updateStatus(
    $quoteRequest,
    QuoteRequest::STATUS_ACCEPTED
);
```

Reject:

```php
return $this->updateStatus(
    $quoteRequest,
    QuoteRequest::STATUS_REJECTED
);
```

Before changing status, the service validates the request.

---

## 41. Response validation

The service verifies:

```text
Quote exists
Customer is logged in
Quote belongs to customer
Current status is quoted
Proposal subtotal exists
Quote has not expired
```

### Quote existence

```php
if (!$quoteRequest->getId()) {
    throw new LocalizedException(...);
}
```

### Customer authentication

```php
if ($customerId <= 0) {
    throw new LocalizedException(...);
}
```

### Ownership

```php
if ($quoteRequest->getCustomerId() !== $customerId) {
    throw new LocalizedException(...);
}
```

### Current status

```php
if (
    $quoteRequest->getStatus()
    !== QuoteRequest::STATUS_QUOTED
) {
    throw new LocalizedException(...);
}
```

### Proposal existence

```php
if (
    $quoteRequest->getData(
        'proposed_subtotal'
    ) === null
) {
    throw new LocalizedException(...);
}
```

---

## 42. Object-level authorization

The ownership check is especially important:

```php
$quoteRequest->getCustomerId() !== $customerId
```

Magento concept:

```text
Object-level authorization
```

Being logged in is not enough.

Being an approved Business Customer is not enough.

The customer must own the specific quote they are trying to accept or reject.

This prevents:

```text
Customer A submitting Customer B’s quote ID
```

---

## 43. Expiry validation

If `expires_at` is empty:

```text
No expiry restriction
```

If it has a value:

```php
if ($expiryTimestamp < $currentTimestamp) {
    throw new LocalizedException(...);
}
```

An expired proposal cannot be:

```text
Accepted
Rejected
```

The status currently remains:

```text
quoted
```

until we implement expiry cron.

For now:

```text
Expired quote
→ Buttons hidden
→ Direct POST blocked
```

Later:

```text
Cron
→ quoted changes to expired
```

---

## 44. Accept controller

File:

```text
Controller/Account/Accept.php
```

The controller implements:

```php
HttpPostActionInterface
```

Flow:

```text
Check login
        ↓
Validate form key
        ↓
Read quote ID
        ↓
Validate Business Customer eligibility
        ↓
Load quote
        ↓
Call QuoteResponseService::accept()
        ↓
Add success message
        ↓
Redirect to quote details
```

Important call:

```php
$savedQuote = $this
    ->quoteResponseService
    ->accept(
        $quoteRequest,
        $customerId
    );
```

---

## 45. Reject controller

File:

```text
Controller/Account/Reject.php
```

The flow is almost the same, but it calls:

```php
$savedQuote = $this
    ->quoteResponseService
    ->reject(
        $quoteRequest,
        $customerId
    );
```

We kept Accept and Reject as separate controllers because they represent different business commands.

```text
Accept proposal
Reject proposal
```

Both share the same service-level validation.

---

## 46. Form-key validation

Both controllers validate:

```php
$this->formKeyValidator->validate(
    $this->request
);
```

Magento concept:

```text
CSRF protection
```

Accepting or rejecting changes the database, so each action uses:

```text
POST
+
Magento form key
```

A GET URL is not used for these actions.

---

## 47. Business eligibility validation

Before responding, both controllers also call:

```php
$this->eligibilityService->validate(
    $customerId
);
```

Therefore, the customer must still satisfy:

```text
Approved business application
        +
Business Customer group
```

This protects against a situation where:

```text
Customer had an approved quote
but later lost business eligibility
```

---

## 48. Customer View block changes

The customer details block now includes:

```php
canRespondToProposal()
getAcceptUrl()
getRejectUrl()
isAccepted()
isRejected()
isProposalExpired()
```

### Button visibility

```php
public function canRespondToProposal(): bool
{
    return $this->quoteResponseService->canRespond(
        $quoteRequest,
        $customerId
    );
}
```

The service is reused, so button visibility follows the same rules as the actual POST action.

This reduces the risk of UI and backend validation becoming inconsistent.

---

## 49. Customer response buttons

The quote details template shows:

```text
Accept Proposal
Reject Proposal
```

only when:

```text
Status = quoted
Quote belongs to customer
Proposal exists
Proposal is not expired
```

Both actions are HTML forms.

Example:

```php
<form method="post"
      action="<?= $block->getAcceptUrl() ?>">

    <?= $block->getBlockHtml('formkey') ?>

    <button type="submit">
        Accept Proposal
    </button>
</form>
```

The Reject action follows the same pattern.

---

## 50. Confirmation messages

Before submitting, the customer sees a browser confirmation.

Accept:

```text
Accept this quote proposal?
You cannot reject it afterward.
```

Reject:

```text
Reject this quote proposal?
This action cannot be reversed.
```

This is usability protection.

The real transition protection remains in `QuoteResponseService`.

---

## 51. Accepted state

When the customer accepts:

```text
quoted → accepted
```

The customer page displays:

```text
You accepted this quote proposal.
The next step is to convert it into your shopping cart.
```

Buttons disappear because the quote is no longer `quoted`.

The Admin grid and details page also show:

```text
Accepted
```

without extra code because they read the same status field.

---

## 52. Rejected state

When the customer rejects:

```text
quoted → rejected
```

The customer page displays:

```text
You rejected this quote proposal.
```

The response buttons disappear.

The Admin sees:

```text
Rejected
```

in the grid and details page.

---

## 53. Invalid transition protection

Once accepted:

```text
accepted → reject
```

is blocked.

Once rejected:

```text
rejected → accept
```

is blocked.

Repeated acceptance:

```text
accepted → accepted
```

is blocked.

The service requires:

```php
status === QuoteRequest::STATUS_QUOTED
```

for every response.

This creates a controlled quote-status workflow.

---

## 54. Current state machine

The implemented quote lifecycle is:

```text
pending
    ↓
under_review
    ↓
quoted
    ├── accepted
    └── rejected
```

Allowed transitions:

```text
pending → under_review
under_review → quoted
quoted → accepted
quoted → rejected
```

Blocked examples:

```text
pending → accepted
pending → rejected
under_review → accepted
accepted → rejected
rejected → accepted
quoted → quoted overwrite
```

---

## 55. Logging

Important actions are logged.

Examples:

```text
Quote marked under review
Proposal saved
Customer accepted proposal
Customer rejected proposal
Unexpected failures
```

Typical context:

```text
quote_request_id
quote_number
customer_id
previous_status
new_status
proposed_subtotal
exception
```

Magento concept:

```text
PSR logging
```

This helps debug quote lifecycle changes.

---

## 56. Exception handling

The services use:

```text
LocalizedException
```

for safe user-facing messages.

Examples:

```text
Only a pending quote request can be marked as under review.

A proposal can only be created when the quote request is under review.

Only a quoted proposal can be accepted or rejected.

You are not allowed to respond to this quote request.

This quote proposal has expired.
```

Unexpected technical exceptions are:

```text
Logged internally
Converted to a generic customer/Admin error
```

This avoids exposing stack traces or database details.

---

## 57. Full Admin-to-customer flow

```text
Customer submits Request for Quote
        ↓
Status = pending
        ↓
Admin opens BrewCraft → Quote Requests
        ↓
Admin grid loads quote
        ↓
Admin clicks View
        ↓
Admin reviews:
    Customer
    Company
    Message
    Products
    Quantities
    Original prices
        ↓
Admin clicks Mark Under Review
        ↓
Status = under_review
        ↓
Admin enters proposed prices
        ↓
QuoteProposalService validates input
        ↓
Proposed row totals calculated
        ↓
Proposed subtotal calculated
        ↓
Admin comment and expiry saved
        ↓
Status = quoted
        ↓
Customer opens My Quote Requests
        ↓
Customer reviews proposal
        ↓
Customer chooses:
    ├── Accept
    │       ↓
    │   Status = accepted
    │
    └── Reject
            ↓
        Status = rejected
```

---

## 63. Interview-ready explanation

> After implementing customer quote submission, I built a complete Admin negotiation workflow using Magento Admin routing, menu XML, ACL, UI Components and a custom SearchResult collection. The Admin grid joins customer and business-account data so the seller can filter requests by customer email, company, status and date.

> The Admin can open a quote details page and review the request, customer, business information and requested products. I implemented status transitions through service classes rather than directly in controllers. A pending request can be marked under review, and only an under-review request can receive a price proposal.

> The proposal service validates every item-level price, calculates proposed row totals and the header subtotal, stores the Admin comment and optional expiry date, and saves the item and header changes inside one database transaction. The status then changes to quoted.

> On the customer side, Accept and Reject are implemented as POST actions with form-key validation. The response service verifies that the customer owns the quote, remains an approved Business Customer, the quote is in quoted status, a proposal exists, and it has not expired. It then performs the controlled transition to accepted or rejected.

> The workflow is secured through Admin ACL, customer authentication, business eligibility checks, quote ownership validation, CSRF protection and strict status-transition rules. The next phase is converting an accepted quote into a Magento cart while applying the negotiated prices.



# 3.BrewCraft Request Quote — Development Log

### Enhancement: Customer Expected Price and Mini-Cart Request Quote

This development phase improved the original Request for Quote experience in two areas:

```text
1. Customers can specify requested quantities and optional expected prices.
2. Approved Business Customers can access Request a Quote from the mini-cart.
```

These improvements were made before starting accepted-quote cart conversion because they affect how the quote request is originally created.

---

## 1. Original Request Quote behavior

Initially, the Request Quote workflow was:

```text
Customer adds products to cart
        ↓
Opens the shopping cart
        ↓
Clicks Request a Quote
        ↓
Enters quote name and message
        ↓
Magento copies cart products and quantities
        ↓
Admin provides proposed prices
```

The quote request stored:

```text
Product
SKU
Cart quantity
Current business price
Current row total
Customer message
```

The customer could describe their expectations only through the general message field.

Example:

```text
We need 100 units and expect approximately ₹750 per unit.
```

This technically worked, but it had limitations.

---

## 2. Problems identified in the original workflow

### 2.1 Customer could not enter a structured expected price

There was no dedicated field for:

```text
Customer expected unit price
```

The customer could mention a target price only in the message.

This caused several problems:

```text
The Admin had to read and interpret free-text messages.
Expected prices could not be compared item by item.
Expected totals could not be calculated automatically.
The customer’s pricing expectation was not stored structurally.
Reporting and future negotiation history would be difficult.
```

For a quote containing multiple products, a message such as:

```text
We expect a total price of around ₹50,000.
```

does not clearly explain the expected price for each item.

### 2.2 Customer was forced to use the cart quantity

The original service used:

```php
$quantity = (float)$cartItem->getQty();
```

Therefore:

```text
Cart quantity = Quote requested quantity
```

But those values may represent different intentions.

Example:

```text
Customer adds 1 sample machine to the cart
but wants pricing for 25 machines.
```

The old implementation required the customer to modify the shopping-cart quantity before requesting a quote.

That was not ideal because the cart was being used both as:

```text
Product-selection tool
and
Quote quantity configuration
```

### 2.3 Request Quote was not easy to discover

The Request Quote button was available only on:

```text
/checkout/cart
```

When a customer used:

```text
Mini-cart → Proceed to Checkout
```

the full cart page was skipped.

Therefore, even an approved Business Customer might never see the Request Quote feature.

---

## 3. Final design decision

We changed the quote request form to support:

```text
Requested quantity
→ Required and editable

Expected unit price
→ Optional

Customer message
→ Optional
```

The expected price was deliberately kept optional.

This supports two different customer intentions.

### Customer knows their target price

```text
Requested quantity: 100
Expected unit price: ₹750
```

### Customer wants BrewCraft’s best offer

```text
Requested quantity: 100
Expected unit price: blank
```

The blank value means:

```text
Best offer requested
```

This is more flexible than forcing every customer to enter a target price.

---

## 4. Enhanced RFQ workflow

The new customer workflow is:

```text
Customer selects products
        ↓
Opens Request a Quote
        ↓
Reviews current product prices
        ↓
Enters requested quantity for every item
        ↓
Optionally enters expected unit price
        ↓
Adds quote name and message
        ↓
Submits quote
```

The Admin can now compare:

```text
Original unit price
Customer expected unit price
Admin proposed unit price
```

Example:

| Product        | Requested Qty | Original Price | Customer Expected | Admin Proposed |
| -------------- | ------------: | -------------: | ----------------: | -------------: |
| Coffee Beans   |           100 |           ₹900 |              ₹750 |           ₹800 |
| Coffee Machine |            10 |         ₹9,000 |      Not provided |         ₹8,500 |

This produces a clearer negotiation workflow.

---

## 5. Database changes

The existing declarative schema was updated in:

```text
app/code/BrewCraft/RequestQuote/etc/db_schema.xml
```

### Quote header table

Table:

```text
brewcraft_quote_request
```

New column:

```text
customer_expected_subtotal
```

Purpose:

```text
Stores the sum of customer expected row totals.
```

The field is nullable because the customer may leave every expected-price field blank.

Conceptually:

```text
customer_expected_subtotal
= SUM(expected_price × requested_qty)
```

Only items with an entered expected price contribute to this total.

### Quote item table

Table:

```text
brewcraft_quote_request_item
```

New columns:

```text
expected_price
expected_row_total
```

#### `expected_price`

Stores the optional customer-requested unit price.

Example:

```text
₹750 per unit
```

#### `expected_row_total`

Stores:

```text
expected_price × requested_qty
```

Example:

```text
Expected price = ₹750
Requested quantity = 100

Expected row total = ₹75,000
```

Both columns are nullable.

When the customer requests BrewCraft’s best offer:

```text
expected_price = NULL
expected_row_total = NULL
```

---

## 6. Request Quote form changes

Template:

```text
view/frontend/templates/request/create.phtml
```

The form originally displayed products and current cart information.

It was changed to show:

```text
Product
SKU
Current Unit Price
Cart Quantity
Requested Quantity
Expected Unit Price
```

### Requested quantity input

Each product now submits:

```text
items[cart_item_id][requested_qty]
```

Example:

```php
items[41][requested_qty] = 100
```

The field is:

```text
Required
Numeric
Greater than zero
Editable independently of cart quantity
```

### Expected unit price input

Each product also submits:

```text
items[cart_item_id][expected_price]
```

Example:

```php
items[41][expected_price] = 750
```

The field is:

```text
Optional
Numeric when provided
Greater than zero when provided
```

### Important HTML form correction

During implementation, the product table was initially outside the `<form>` element.

That meant inputs such as:

```text
items[41][requested_qty]
items[41][expected_price]
```

would not be included in the POST request.

The corrected structure became:

```text
<form>
    Form key
    Product table
    Requested quantities
    Expected prices
    Quote name
    Customer message
    Submit button
</form>
```

This ensured all item values were submitted to the Save controller.

---

## 7. Save controller changes

Controller:

```text
Controller/Request/Save.php
```

The controller already received:

```text
quote_name
customer_message
```

It was updated to also receive:

```php
$itemRequests = $this->request->getParam(
    'items',
    []
);
```

A type check was added:

```php
if (!is_array($itemRequests)) {
    $itemRequests = [];
}
```

The item information is then passed to the submission service:

```php
$quoteRequest = $this->quoteSubmissionService->submit(
    $customerId,
    $quoteName,
    $customerMessage,
    $itemRequests
);
```

The controller remains thin.

Its responsibility is:

```text
Validate login
Validate form key
Read POST parameters
Call submission service
Display success or error message
Redirect customer
```

The controller does not calculate expected totals.

---

## 8. QuoteSubmissionService changes

Service:

```text
Model/Service/QuoteSubmissionService.php
```

This service received the main business-logic changes.

The method signature changed from:

```php
submit(
    int $customerId,
    string $quoteName,
    string $customerMessage
)
```

to:

```php
submit(
    int $customerId,
    string $quoteName,
    string $customerMessage,
    array $itemRequests = []
)
```

---

## 9. Active-cart item validation

The service does not blindly trust submitted cart-item IDs.

The secure flow is:

```text
Load the logged-in customer’s active cart
        ↓
Get real visible cart items
        ↓
Loop through those real items
        ↓
Find submitted values for each real cart item ID
        ↓
Validate and save
```

The service does not loop through arbitrary submitted item IDs and create quote items from them.

This protects against a modified request such as:

```text
items[another_customer_cart_item_id][requested_qty]
```

Only items actually present in the customer’s active cart are processed.

---

## 10. Requested-quantity validation

For every real cart item, the service validates:

```text
Value exists
Value is numeric
Value is greater than zero
```

Invalid examples:

```text
Blank
0
-5
abc
```

These values produce a `LocalizedException`, and the quote is not created.

The requested quantity is rounded to four decimal places:

```php
$requestedQty = round(
    (float)$rawValue,
    4
);
```

This supports both:

```text
Whole quantities
Decimal quantities
```

depending on product configuration.

---

## 11. Expected-price validation

Expected price is optional.

When blank:

```php
$expectedPrice = null;
$expectedRowTotal = null;
```

When provided, the service checks:

```text
Numeric
Greater than zero
```

Invalid examples:

```text
0
-100
text
```

The normalized price is rounded to four decimal places.

---

## 12. Total calculation changes

Previously, the original row total was based on cart quantity:

```text
Original price × cart quantity
```

Now it is based on the quantity requested for quotation:

```text
Original price × requested quantity
```

Example:

```text
Cart quantity = 2
Requested quote quantity = 100
Original price = ₹900
```

The quote now stores:

```text
Original row total = ₹900 × 100 = ₹90,000
```

It does not use:

```text
₹900 × 2
```

because the quote is for 100 units.

### Expected row total

When the customer enters an expected price:

```text
Expected row total
= expected unit price × requested quantity
```

Example:

```text
₹750 × 100 = ₹75,000
```

### Header original subtotal

The header total is recalculated from the prepared quote items:

```text
original_subtotal
= sum of original row totals
```

### Customer expected subtotal

The new header field is calculated as:

```text
customer_expected_subtotal
= sum of non-null expected row totals
```

When no expected price is supplied for any item:

```text
customer_expected_subtotal = NULL
```

---

## 13. Transaction behavior

Quote creation continues to use a database transaction.

The operation writes:

```text
One quote-request header
Multiple quote-request items
```

Flow:

```text
Validate all request data
        ↓
Begin transaction
        ↓
Create quote header
        ↓
Create quote items
        ↓
Commit
```

On failure:

```text
Rollback
```

This prevents partial data such as:

```text
Header saved
Item 1 saved
Item 2 failed
```

The result is:

```text
Everything saves
or
Nothing saves
```

---

## 14. Admin quote-details changes

Template:

```text
view/adminhtml/templates/quote/view.phtml
```

The Admin page was enhanced to show customer pricing expectations.

### Quote summary

The summary now displays:

```text
Original Subtotal
Customer Expected Subtotal
Proposed Subtotal
```

When the customer did not provide expected prices, Admin sees:

```text
Best offer requested
```

### Requested-products table

The table now compares:

```text
Original Unit Price
Customer Expected Unit Price
Admin Proposed Unit Price
Original Row Total
Customer Expected Row Total
Admin Proposed Row Total
```

### Admin proposal form

The proposal form now shows the customer expected price beside the Admin input.

Example:

```text
Current price: ₹900
Customer expected price: ₹750
Admin proposed price: [₹800]
```

This makes the negotiation decision easier for Admin.

### Button wording

Because email is not configured yet, wording such as:

```text
Save and Send Proposal
```

was changed to:

```text
Save Proposal
```

The proposal is stored and becomes visible in My Account, but no email is sent at this stage.

---

## 15. Customer quote-details changes

Template:

```text
view/frontend/templates/account/view.phtml
```

The customer page was enhanced to show:

```text
Original Total
Your Expected Total
BrewCraft Proposed Total
```

At item level, the customer sees:

```text
Requested Quantity
Original Unit Price
Your Expected Unit Price
BrewCraft Proposed Unit Price
Original Row Total
Your Expected Row Total
Proposed Row Total
```

When expected price was left blank:

```text
Best offer requested
```

The existing customer actions were preserved:

```text
Accept Proposal
Reject Proposal
Accepted message
Rejected message
Expired message
```

---

## 16. Why the mini-cart enhancement was needed

The original Request Quote button was placed on the full cart page:

```text
checkout_cart_index
```

This meant the button appeared only when the customer opened:

```text
/checkout/cart
```

However, Magento allows customers to proceed directly from the mini-cart:

```text
Open mini-cart
        ↓
Click Proceed to Checkout
        ↓
Full cart page skipped
```

As a result, approved Business Customers could miss the RFQ option entirely.

The feature was working, but its discovery depended on the customer visiting the full cart page.

---

## 17. Mini-cart design decision

We decided to show the Request Quote option in both:

```text
Full shopping cart
Mini-cart
```

We deliberately did not add the action inside checkout.

The two paths represent different intentions:

```text
Proceed to Checkout
→ Customer agrees to current prices and wants to buy.

Request a Quote
→ Customer wants negotiation before purchasing.
```

Therefore, the correct choice point is:

```text
Cart or mini-cart
├── Proceed to Checkout
└── Request a Quote
```

---

## 18. Why we did not override the complete mini-cart template

A possible implementation would have been to override Magento’s mini-cart template.

That approach was avoided because a complete override could:

```text
Duplicate core template code
Break after Magento updates
Conflict with custom themes
Conflict with checkout extensions
Make future maintenance harder
```

Instead, we used Magento’s existing mini-cart UI Component region:

```text
extraInfo
```

This allowed us to add a small child component without replacing Magento’s mini-cart implementation.

---

## 19. Mini-cart files added

The implementation added:

```text
CustomerData/QuoteEligibility.php

etc/frontend/di.xml

view/frontend/layout/default.xml

view/frontend/web/js/view/minicart-quote.js

view/frontend/web/template/minicart/quote-action.html
```

Optional styling may also exist in:

```text
view/frontend/web/css/source/_module.less
```

---

## 20. Customer-data section

Class:

```text
CustomerData/QuoteEligibility.php
```

The class implements:

```php
Magento\Customer\CustomerData\SectionSourceInterface
```

Its responsibility is to provide customer-specific private data:

```php
[
    'can_request_quote' => true,
    'request_quote_url' => '...'
]
```

It checks:

```text
Customer is logged in
Customer ID is valid
BusinessCustomerEligibilityService passes
```

When the customer is not eligible:

```php
[
    'can_request_quote' => false,
    'request_quote_url' => ''
]
```

### Why customer-data was used

Eligibility is customer-specific information.

It must not be placed directly into cacheable public page HTML because Magento Full Page Cache may reuse that HTML between visitors.

Magento customer-data provides private browser-side sections that can vary per logged-in customer.

This makes it suitable for:

```text
Approved customer → show button
Other customer → hide button
```

---

## 21. Frontend DI registration

The custom section was registered in:

```text
etc/frontend/di.xml
```

Section name:

```text
brewcraft_quote_eligibility
```

It maps to:

```text
BrewCraft\RequestQuote\CustomerData\QuoteEligibility
```

The registration initially caused an error:

```json
{
  "message": "The \"brewcraft_quote_eligibility\" section source isn't supported."
}
```

The mapping had been added to the existing global:

```text
etc/di.xml
```

The final fix was to move the customer-data mapping into the frontend-area DI file:

```text
etc/frontend/di.xml
```

The global DI file continued to contain:

```text
Repository preferences
Admin grid collection mapping
```

The frontend file contains:

```text
Customer-data section registration
```

After recompilation, Magento recognized the custom section.

---

## 22. Mini-cart layout integration

File:

```text
view/frontend/layout/default.xml
```

The custom component was added as a child of:

```text
minicart_content
```

Its display area is:

```text
extraInfo
```

The configured component is:

```text
BrewCraft_RequestQuote/js/view/minicart-quote
```

The Knockout template is:

```text
BrewCraft_RequestQuote/minicart/quote-action
```

The component was verified in `uiRegistry` as:

```text
minicart_content.brewcraft.quote.action
```

This confirmed:

```text
Layout merged successfully
JavaScript component loaded
Template was assigned
extraInfo display area was registered
```

---

## 23. Mini-cart JavaScript component

File:

```text
view/frontend/web/js/view/minicart-quote.js
```

The component observes:

```text
brewcraft_quote_eligibility
cart
```

The first section answers:

```text
Is this customer allowed to request a quote?
```

The standard cart section answers:

```text
Does the cart have any products?
```

The button is displayed only when:

```text
can_request_quote = true
and
summary_count > 0
and
request_quote_url is available
```

Conceptually:

```javascript
return eligibility.can_request_quote === true
    && itemCount > 0
    && Boolean(eligibility.request_quote_url);
```

---

## 24. Knockout mini-cart template

File:

```text
view/frontend/web/template/minicart/quote-action.html
```

The template uses a Knockout condition:

```text
Show action only when canRequestQuote() is true
```

The action links to:

```text
requestquote/request/create
```

From there, the approved customer can enter:

```text
Requested quantities
Expected prices
Quote name
Message
```

---

## 25. Mini-cart debugging and correction

The UI Component was successfully found in `uiRegistry`, but its eligibility observable contained:

```text
{}
```

The customer-data endpoint returned HTTP 400.

Direct endpoint response:

```json
{
  "message": "The \"brewcraft_quote_eligibility\" section source isn't supported."
}
```

This showed that:

```text
The layout and JavaScript component were correct.
The customer-data section registration was the failing part.
```

After moving the mapping into:

```text
etc/frontend/di.xml
```

and rebuilding DI:

```text
The customer-data endpoint returned valid JSON.
The eligibility observable received data.
The visibility function returned true for approved customers.
The mini-cart button appeared.
```

---

## 26. Final mini-cart behavior

### Approved Business Customer with cart items

```text
Request a Quote button visible
```

### Approved Business Customer with empty cart

```text
Button hidden
```

### Guest customer

```text
Button hidden
```

### Normal registered customer

```text
Button hidden
```

### Pending Business Account customer

```text
Button hidden
```

### Rejected Business Account customer

```text
Button hidden
```

This matches the same eligibility rules used by:

```text
Full cart RFQ button
Direct Request Quote page
Quote submission service
Customer My Quote Requests navigation
```

---

## 27. Complete improved RFQ journey

The current flow is now:

```text
Approved Business Customer
        ↓
Adds products to cart
        ↓
Can use:
    Full cart Request Quote button
    or
    Mini-cart Request Quote button
        ↓
Opens Request Quote form
        ↓
Reviews current prices and cart quantities
        ↓
Enters requested quantities
        ↓
Optionally enters expected unit prices
        ↓
Adds quote name and message
        ↓
Submits request
        ↓
Admin sees:
    Requested quantity
    Original price
    Customer expected price
        ↓
Admin marks request Under Review
        ↓
Admin enters proposed prices
        ↓
Status becomes Quoted
        ↓
Customer compares:
    Original price
    Their expected price
    BrewCraft proposed price
        ↓
Customer accepts or rejects
```

---

## 31. Interview-ready explanation

> Initially, the RFQ feature copied the product quantities directly from the shopping cart, and customers could mention their target price only in a free-text message. I improved this by adding editable requested quantities and an optional expected unit price for every quote item.

> The expected price is optional because some customers know their target price, while others simply want the seller’s best offer. I added item-level expected price and expected row-total fields, as well as a header expected subtotal. The submission service validates the customer’s input, processes only items from the customer’s real active cart, calculates totals using the requested quote quantity, and saves the request inside a transaction.

> I also improved discoverability by adding Request a Quote to the mini-cart. Instead of overriding Magento’s core mini-cart template, I added a Knockout UI Component through the existing `extraInfo` region. Customer eligibility is exposed through a custom Magento customer-data section, which is private and safe with Full Page Cache. The mini-cart action appears only for approved Business Customers with products in the cart and remains hidden for guests, normal customers, and pending or rejected applicants.
