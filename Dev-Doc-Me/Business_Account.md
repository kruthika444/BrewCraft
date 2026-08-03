
# 1.Development Log — BrewCraft Business Account Storefront Registration

**DATE:** 21 July 

**Project:** BrewCraft Supply
**Magento module:** `BrewCraft_BusinessAccount`
**Phase completed:** Module foundation, persistence layer, repository layer, storefront registration, customer creation, and pending application submission

---

### 1. Business Requirement

BrewCraft serves both regular retail customers and business customers.

A normal Magento customer can create an account and purchase products immediately. A business customer needs additional capabilities such as:

* Wholesale pricing
* Requesting quotations
* Quick reorder
* Business-specific promotions
* Dedicated support
* Purchase-order or credit-payment options in later phases

These benefits should not be available to every customer automatically. BrewCraft must first collect the company’s details and review the application.

The required business flow is:

```text
Customer submits business details
            ↓
Magento customer account is identified or created
            ↓
Business application is saved
            ↓
Application status is Pending
            ↓
Admin reviews the application
            ↓
Admin approves or rejects it
            ↓
Approved customer receives business benefits
```

The storefront-registration phase implements everything up to:

```text
Application status = pending
```

The Admin approval process will be developed next.

---


### 3. Completed User Journey

The current storefront workflow is:

```text
Visitor opens:
/businessaccount/account/create
            ↓
Business registration page is displayed
            ↓
Visitor enters company, contact, and address information
            ↓
Visitor submits the form
            ↓
Server validates the submitted data
            ↓
Existing logged-in customer?
     ├── Yes → use existing customer ID
     └── No  → create a Magento customer account
            ↓
Check duplicate customer application
            ↓
Check duplicate business registration number
            ↓
Save application with status "pending"
            ↓
Redirect to success page
```

This flow supports two types of users:

1. A guest who does not yet have a Magento customer account.
2. An existing Magento customer who is already logged in.

---



### 5. Business Application Database Design

We created a custom table:

```text
brewcraft_business_account
```

The table stores company-specific information that does not belong directly in Magento’s standard customer entity.

Important columns include:

```text
entity_id
customer_id
company_name
registration_number
tax_number
company_type
business_years
contact_name
contact_email
contact_phone
street
city
region
postcode
country_id
status
admin_comment
approved_at
created_at
updated_at
```

---

### 6. Why We Used a Separate Table

We intentionally did not store all company information as Magento customer EAV attributes.

The two entities represent different concepts:

```text
customer_entity
→ Login identity
→ First name
→ Last name
→ Email
→ Customer group
```

```text
brewcraft_business_account
→ Company identity
→ Registration number
→ Tax number
→ Business address
→ Application status
→ Approval information
```

A business application has its own lifecycle:

```text
pending
approved
rejected
```

It also needs future fields such as:

* Admin comments
* Approval timestamp
* Credit status
* Account manager
* Business tier
* Document-verification status

A separate entity keeps this process independent from the basic Magento customer account.

---

### 7. Customer Relationship

The custom table contains:

```text
customer_id
```

This links the application to:

```text
customer_entity.entity_id
```

The database relationship is:

```text
customer_entity.entity_id
           ↓
brewcraft_business_account.customer_id
```

A foreign key was added with:

```xml
onDelete="CASCADE"
```

This means that when a Magento customer is permanently deleted, their related business-account record is also deleted.

---

### 8. Database Constraints

### One application per customer

A unique constraint was added to:

```text
customer_id
```

This enforces:

```text
One Magento customer
→ One business application
```

It prevents duplicate applications such as:

```text
Customer 25
├── Application 1
├── Application 2
└── Application 3
```

### Unique business registration number

A unique constraint was also added to:

```text
registration_number
```

This prevents two applications from using the same legal company-registration number.

The PHP service performs a friendly validation first, while the database constraint remains the final protection against duplicates.

### Indexes

Indexes were created for:

```text
status
created_at
```

These will help the future Admin grid efficiently filter:

* Pending applications
* Approved applications
* Rejected applications
* Recently submitted applications

---

### 9. Business Account Model

We created:

```text
Model/BusinessAccount.php
```

This model represents one row from:

```text
brewcraft_business_account
```

The model includes status constants:

```php
public const STATUS_PENDING = 'pending';
public const STATUS_APPROVED = 'approved';
public const STATUS_REJECTED = 'rejected';
```

Instead of repeatedly writing string values such as:

```php
$application->setStatus('approved');
```

the code can use:

```php
$application->setStatus(
    BusinessAccount::STATUS_APPROVED
);
```

This avoids inconsistent values and spelling errors.

Helper methods were also added:

```php
isPending()
isApproved()
isRejected()
```

These methods make future business logic easier to read.

Example:

```php
if ($businessAccount->isPending()) {
    // Show approval actions.
}
```

---

### 10. Resource Model and Collection

#### Resource model

We created:

```text
Model/ResourceModel/BusinessAccount.php
```

It maps:

```text
Model: BrewCraft\BusinessAccount\Model\BusinessAccount
Table: brewcraft_business_account
Primary key: entity_id
```

The resource model performs database operations:

```text
INSERT
SELECT
UPDATE
DELETE
```

#### Collection

We created:

```text
Model/ResourceModel/BusinessAccount/Collection.php
```

A collection represents multiple business applications.

It will later support queries such as:

```php
$collection->addFieldToFilter(
    'status',
    BusinessAccount::STATUS_PENDING
);
```

This collection will be useful for the Admin approval grid.

---

### 11. Repository Layer

We created:

```text
Api/BusinessAccountRepositoryInterface.php
Model/BusinessAccountRepository.php
etc/di.xml
```

The repository provides a controlled interface for accessing business applications.

Methods include:

```php
save()
getById()
getByCustomerId()
getByRegistrationNumber()
delete()
deleteById()
```

---

### 12. Why We Used a Repository

Without a repository, controllers and services might directly use the resource model:

```php
$this->resource->save($model);
```

Instead, higher-level classes depend on:

```php
BusinessAccountRepositoryInterface
```

The flow is:

```text
Controller or service
        ↓
Repository interface
        ↓
Repository implementation
        ↓
Resource model
        ↓
Database
```

This provides:

* A clear service contract
* Consistent exception handling
* Easier future replacement
* Cleaner controller and service code
* Better alignment with Magento architecture

---

### 13. Dependency Injection Preference

In `etc/di.xml`, we added:

```xml
<preference
    for="BrewCraft\BusinessAccount\Api\BusinessAccountRepositoryInterface"
    type="BrewCraft\BusinessAccount\Model\BusinessAccountRepository"/>
```

When a class requests:

```php
BusinessAccountRepositoryInterface
```

Magento injects:

```php
BusinessAccountRepository
```

The calling class depends on the interface rather than the concrete implementation.

---

### 14. Repository Loading Methods

#### Load by entity ID

```php
getById(int $entityId)
```

loads using:

```text
entity_id
```

#### Load by customer ID

```php
getByCustomerId(int $customerId)
```

checks whether a specific Magento customer already has an application.

This method fulfills the business rule:

```text
A customer cannot submit multiple applications.
```

#### Load by registration number

```php
getByRegistrationNumber(string $registrationNumber)
```

checks whether a legal company-registration number is already in use.

This fulfills the business rule:

```text
A registered company must not be duplicated.
```

---

### 15. Storefront Route

We created:

```text
etc/frontend/routes.xml
```

The route defines:

```xml
<route id="businessaccount" frontName="businessaccount">
```

This makes the storefront URL begin with:

```text
/businessaccount/
```

The registration page URL is:

```text
/businessaccount/account/create
```

Magento resolves this as:

```text
Front name: businessaccount
Controller: account
Action: create
```

which maps to:

```text
Controller/Account/Create.php
```

---

### 16. Registration Page Controller

We created:

```text
Controller/Account/Create.php
```

It implements:

```php
HttpGetActionInterface
```

because it displays a page through an HTTP GET request.

The controller creates a Magento page result:

```php
$resultPage = $this->pageFactory->create();
```

It does not manually generate HTML. Magento processes the matching layout XML and template.

---

### 17. Layout and Template Rendering

The request flow is:

```text
/businessaccount/account/create
        ↓
Create controller
        ↓
Page result
        ↓
businessaccount_account_create.xml
        ↓
Block/Account/Create.php
        ↓
account/create.phtml
```

We created:

```text
view/frontend/layout/businessaccount_account_create.xml
```

The block was inserted into Magento’s main content container.

```xml
<referenceContainer name="content">
```

The block and template were connected using:

```xml
<block
    class="BrewCraft\BusinessAccount\Block\Account\Create"
    template="BrewCraft_BusinessAccount::account/create.phtml"/>
```

---

### 18. Duplicate Page Title Fix

Initially, the page displayed two headings:

```text
Create Business Account
Create Your Business Account
```

The first came from Magento’s standard page title block. The second came from our custom template.

We removed the visible default heading using:

```xml
<referenceBlock name="page.main.title" remove="true"/>
```

The controller still sets:

```php
$resultPage->getConfig()->getTitle()->set(
    __('Create Business Account')
);
```

That title remains useful for:

* Browser tab title
* SEO metadata
* Page identity

The storefront displays only the designed template heading:

```text
Create Your Business Account
```

---

### 19. Registration Form Block

We created:

```text
Block/Account/Create.php
```

The block prepares data required by the template.

It provides:

```php
getFormAction()
getFormKey()
isCustomerLoggedIn()
getCustomerFirstname()
getCustomerLastname()
getCustomerEmail()
getCountryOptions()
getCompanyTypes()
```

The template does not directly create collections or read customer sessions. This logic stays inside the block.

---

### 20. Form Action

The block returns:

```php
$this->getUrl('businessaccount/account/save');
```

This produces:

```text
/businessaccount/account/save
```

The form submits to:

```text
Controller/Account/Save.php
```

using the POST method.

---

### 21. CSRF Protection

The form contains Magento’s form key:

```html
<input type="hidden"
       name="form_key"
       value="..."/>
```

The Save controller validates it with:

```php
$this->formKeyValidator->validate($this->request)
```

This protects the form from cross-site request forgery.

When the key is invalid or expired, the customer receives:

```text
Your session has expired. Please submit the form again.
```

and is redirected back to the registration page.

---

### 22. Business Registration Form Sections

The storefront form is divided into logical business sections.

#### Company details

```text
Company Name
Business Registration Number
Tax / VAT Number
Company Type
Years in Business
```

#### Primary contact

```text
First Name
Last Name
Business Email
Business Phone
```

#### Business address

```text
Street
City
State / Region
Postcode
Country
```

#### Account security

For guests only:

```text
Password
Confirm Password
```

#### Review and submit

The applicant confirms that the provided information is accurate.

This structure matches a realistic business-onboarding form rather than a standard Magento retail registration form.

---

### 23. Country Options

The block uses Magento’s country collection:

```php
CountryCollectionFactory
```

and calls:

```php
loadByStore()
```

This means the dropdown follows the countries permitted by the Magento store configuration.

We did not hard-code a country list inside the template.

---

### 24. Logged-In and Guest Behavior

#### Logged-in customer

When a customer is already logged in:

* First name is prefilled.
* Last name is prefilled.
* Email is prefilled.
* Password fields are hidden.
* The application is connected to the existing customer ID.

Flow:

```text
Existing Magento customer
        ↓
Submit business information
        ↓
Create only business application
```

#### Guest visitor

When the visitor is not logged in:

* Name and email are entered manually.
* Password fields are displayed.
* A Magento customer account is created.
* The business application is linked to the new customer.
* The customer is logged in after successful completion.

Flow:

```text
Guest visitor
        ↓
Create Magento customer
        ↓
Create business application
        ↓
Log customer in
```

This avoids forcing users to complete two separate registration processes.

---

### 25. Frontend Validation

Magento JavaScript validation was initialized through:

```html
data-mage-init='{"validation": {}}'
```

Individual fields use rules such as:

```text
required
validate-email
validate-digits
validate-zero-or-greater
validate-customer-password
equalTo
maxlength
```

This gives immediate feedback before the form reaches the server.

However, frontend validation alone is not trusted because it can be bypassed. The same important checks are repeated in PHP.

---

### 26. Save Controller

We created:

```text
Controller/Account/Save.php
```

It implements:

```php
HttpPostActionInterface
```

because it accepts submitted form data.

The controller performs request-level responsibilities:

```text
Validate form key
Collect POST data
Preserve form data
Call registration service
Add success/error messages
Redirect the customer
```

It does not contain the main registration business logic.

---

### 27. Why Business Logic Was Moved to a Service

We created:

```text
Model/Service/BusinessAccountRegistrationService.php
```

Instead of putting all logic inside the Save controller.

The controller should deal with HTTP behavior:

```text
Request
Response
Redirect
Messages
```

The service handles the business operation:

```text
Validate information
Check duplicates
Identify/create customer
Create application
Handle partial failures
```

This makes the registration workflow reusable later from:

* REST API
* GraphQL resolver
* Admin action
* Import command
* Integration endpoint

without copying the same logic from the controller.

---

### 28. Server-Side Validation

The registration service validates:

* Required fields
* Valid email format
* Terms confirmation
* Field lengths
* Two-character country code
* Non-negative years in business
* Guest password
* Password confirmation

Example required-field validation:

```php
if (
    !isset($data[$field])
    || trim((string)$data[$field]) === ''
) {
    throw new LocalizedException(
        __('The "%1" field is required.', $label)
    );
}
```

This ensures that invalid requests cannot bypass the browser-side validation.

---

### 29. Data Normalization

Before validation, submitted strings are trimmed:

```php
$normalized[$key] = trim($value);
```

This converts values such as:

```text
"  BrewCraft Traders  "
```

into:

```text
"BrewCraft Traders"
```

This improves consistency and prevents whitespace from affecting duplicate checks.

---

### 30. Duplicate Registration Number Validation

Before saving, the service calls:

```php
getByRegistrationNumber()
```

When an application already exists, it throws a friendly error:

```text
A business account already exists with this registration number.
```

This protects BrewCraft from multiple applications for the same legal company.

The database unique constraint remains the final safeguard.

---

### 31. Duplicate Customer Application Validation

For logged-in customers, the service calls:

```php
getByCustomerId($customerId)
```

When an application exists, registration is stopped with a message such as:

```text
You already have a business account application with status "pending".
```

This fulfills the rule:

```text
One customer
→ One active business-account application
```

---

### 32. Existing Guest Email Protection

A guest may enter an email that already belongs to a Magento customer.

The service checks:

```php
$this->customerRepository->get(
    $email,
    $websiteId
);
```

When the customer exists, it does not attempt to create another account.

The user is instructed to sign in:

```text
A customer account already exists with this email address.
Please sign in before applying for a business account.
```

This protects account identity and prevents duplicate-email errors.

---

### 33. Magento Customer Creation

For a new guest, the service creates a Magento customer using:

```php
CustomerInterfaceFactory
AccountManagementInterface
```

The customer fields include:

```text
First name
Last name
Email
Website ID
Store ID
Password
```

The account is created through:

```php
$this->accountManagement->createAccount(
    $customer,
    $password
);
```

Using Magento’s customer account service ensures that Magento’s normal account-creation behavior is respected.

---

### 34. Business Application Creation

After the customer is available, the service creates:

```php
BusinessAccountFactory->create()
```

and maps the submitted information:

```php
$businessAccount->setData([
    'customer_id' => $customerId,
    'company_name' => $data['company_name'],
    'registration_number' => $data['registration_number'],
    'tax_number' => ...,
    'company_type' => ...,
    'business_years' => ...,
    'contact_name' => ...,
    'contact_email' => $data['contact_email'],
    'contact_phone' => $data['contact_phone'],
    'street' => $data['street'],
    'city' => $data['city'],
    'region' => ...,
    'postcode' => $data['postcode'],
    'country_id' => strtoupper($data['country_id']),
    'status' => BusinessAccount::STATUS_PENDING
]);
```

The application is then persisted through:

```php
$this->businessAccountRepository->save(
    $businessAccount
);
```

---

### 35. Pending Status

Every new application receives:

```php
BusinessAccount::STATUS_PENDING
```

This means:

```text
Customer account exists
Business application exists
Business benefits are not active yet
Admin review is required
```

The application should not immediately receive wholesale pricing or B2B access.

This fulfills the business approval requirement.

---

### 36. Contact Name Mapping

The form collects:

```text
contact_firstname
contact_lastname
```

The custom table stores:

```text
contact_name
```

The service combines them:

```php
return trim(
    $data['contact_firstname']
    . ' '
    . $data['contact_lastname']
);
```

Example:

```text
Jennifer + Kruthi
→ Jennifer Kruthi
```

---

### 37. Optional Data Handling

Optional form values are converted from empty strings to `null`.

For example:

```text
Tax number: ""
```

is saved as:

```text
NULL
```

rather than an empty string.

This is handled by:

```php
nullableValue()
nullableInteger()
```

This makes database data cleaner and easier to query.

---

### 38. Partial Failure Protection

An important case is:

```text
Magento customer created successfully
            ↓
Business application save fails
```

Without protection, the store would contain a newly created normal customer with no business application.

The service tracks whether it created a new customer:

```php
$createdCustomer = $customer;
```

If application persistence fails, it attempts to remove that customer:

```php
$this->customerRepository->deleteById(
    (int)$customer->getId()
);
```

This is a compensating action.

It keeps the customer and business application creation process logically consistent.

---

### 39. Customer Login After Registration

A guest customer is logged in only after both operations succeed:

```text
Magento customer saved
        +
Business application saved
        ↓
Log customer in
```

The code uses:

```php
$this->customerSession->setCustomerDataAsLoggedIn(
    $createdCustomer
);
```

We do not log the customer in immediately after account creation because the business-application save may still fail.

---

### 40. Success Page

After successful registration, the Save controller redirects to:

```text
/businessaccount/account/success
```

We created:

```text
Controller/Account/Success.php
view/frontend/layout/businessaccount_account_success.xml
view/frontend/templates/account/success.phtml
```

The page shows:

```text
Registration Submitted
Application Status: Pending Review
```

It also explains the next stages:

1. Application review
2. Approval or rejection notification
3. Access to business benefits after approval

This gives the applicant clear feedback instead of returning them to a generic Magento page.

---

### 41. Duplicate Success-Page Heading Prevention

As with the registration page, the default Magento title block is removed:

```xml
<referenceBlock name="page.main.title" remove="true"/>
```

The designed success-page heading is rendered in the template.

The controller title remains available for the browser tab.

---

### 46. Business Value Delivered

The storefront-registration phase now allows BrewCraft to:

* Capture structured company details
* Convert guest applicants into Magento customers
* Allow existing customers to apply
* Prevent duplicate customer applications
* Prevent duplicate company registrations
* Maintain legal company information separately from customer identity
* Keep applications pending until reviewed
* Provide a clear registration-success journey
* Prepare the system for Admin approval
* Prepare approved customers for future wholesale and quotation features

This is not merely an additional registration form.

It establishes the foundation for BrewCraft’s full B2B customer lifecycle.

---

### 47. Current Business Flow

```text
Retail customer account
        ↓
Optional business application
        ↓
Pending review
        ↓
Future Admin approval
        ↓
Business customer group
        ↓
Wholesale pricing
        ↓
Quotes, reorders, purchase orders, and credit features
```

---

### 48. Current Completion Status

For the Business Account module:

| Area                                | Status |
| ----------------------------------- | -----: |
| Module foundation                   |   100% |
| Database entity                     |   100% |
| Model/resource/collection           |   100% |
| Repository layer                    |   100% |
| Storefront registration form        |   100% |
| Guest customer creation             |   100% |
| Existing customer application       |   100% |
| Validation and duplicate protection |   100% |
| Pending application persistence     |   100% |
| Success page                        |   100% |
| Admin approval workflow             |     0% |
| Approval/rejection email            |     0% |
| Business customer-group assignment  |     0% |
| Customer business dashboard         |     0% |


---
---
---

# 2.BrewCraft Business Account Administration and Email
**DATE:** 25th July 

**Module:** `BrewCraft_BusinessAccount`
**Magento version:** Magento 2.4.7-based project
**Phase covered:** Storefront business registration, Admin application management, approval/rejection processing, customer-group assignment, and email notification trigger

---

### 1. Business problem we are solving

BrewCraft has two customer types:

```text
Retail customers
Business customers
```

A retail customer can create a normal Magento account and purchase products.

A business customer may receive additional benefits later, such as:

```text
Wholesale prices
Quotation requests
Bulk ordering
Quick reorder
Business-only promotions
Special payment methods
Dedicated support
```

BrewCraft should not give these benefits automatically to everyone who creates an account.

The company must first collect business details and review the application.

The required process is:

```text
Customer submits business application
        ↓
Application is stored as pending
        ↓
Admin reviews submitted information
        ↓
Admin approves or rejects
        ↓
Approved customer moves to Business Customer group
        ↓
Customer is notified
```

This workflow separates:

```text
Magento customer account
```

from:

```text
Business-account approval
```

That distinction is important because a customer can still be a valid retail customer even when their business application is pending or rejected.

---

### 2. Complete workflow implemented so far

The current end-to-end flow is:

```text
Storefront business registration page
        ↓
Server-side validation
        ↓
Use existing customer or create new customer
        ↓
Save business application
        ↓
status = pending
        ↓
Application appears in Magento Admin grid
        ↓
Admin opens application details
        ↓
Admin approves or rejects
        ↓
Approved:
    customer group updated
    status = approved
    approved_at saved

Rejected:
    status = rejected
    rejection reason saved
        ↓
Email notification is triggered
```

---

### 3. Magento areas involved

Magento separates functionality into application areas.

The main areas we used are:

```text
frontend
adminhtml
global
```

#### Frontend area

The frontend area is the customer-facing storefront.

Files under:

```text
view/frontend
etc/frontend
Controller/Account
```

are related to pages such as:

```text
/businessaccount/account/create
/businessaccount/account/success
```

#### Adminhtml area

The `adminhtml` area is for Magento Admin.

Files under:

```text
view/adminhtml
etc/adminhtml
Controller/Adminhtml
Block/Adminhtml
```

are used for:

```text
Admin menu
Admin routes
Admin grids
Admin details pages
Approve/reject actions
```

#### Global configuration

Some files apply across Magento areas:

```text
etc/module.xml
etc/di.xml
etc/db_schema.xml
etc/acl.xml
etc/email_templates.xml
```

For example:

* `db_schema.xml` defines the custom table.
* `di.xml` defines dependency-injection preferences.
* `acl.xml` defines Admin permissions.
* `email_templates.xml` registers email templates.

---

## 4. Storefront customization — general explanation

You mentioned that you had not previously worked with Magento frontend customization.

Magento frontend pages are not normally created as one large PHP file.

A typical storefront page uses:

```text
Route
Controller
Layout XML
Block
Template
```

The flow is:

```text
Browser URL
    ↓
Frontend router
    ↓
Controller
    ↓
Page result
    ↓
Layout XML
    ↓
Block class
    ↓
.phtml template
    ↓
Rendered HTML
```

For our business registration page:

```text
/businessaccount/account/create
        ↓
Controller/Account/Create.php
        ↓
businessaccount_account_create.xml
        ↓
Block/Account/Create.php
        ↓
account/create.phtml
```

Each part has a different responsibility.

---

## 5. Frontend route

We created:

```text
etc/frontend/routes.xml
```

This registered the storefront front name:

```text
businessaccount
```

So the route begins with:

```text
/businessaccount/
```

Magento interprets:

```text
/businessaccount/account/create
```

as:

```text
Front name: businessaccount
Controller folder: Account
Action class: Create
```

and loads:

```text
Controller/Account/Create.php
```

---

## 6. Storefront Create controller

We created:

```text
Controller/Account/Create.php
```

This controller implements a GET action because it displays a page.

Its responsibility is limited to:

```text
Create page result
Set browser/page title
Return the page
```

It does not contain form HTML or business logic.

That separation is intentional.

A controller should coordinate the request, not become responsible for every operation.

---

## 7. Storefront layout XML

We created:

```text
view/frontend/layout/businessaccount_account_create.xml
```

Magento generates the layout handle from:

```text
businessaccount/account/create
```

The format is:

```text
route_controller_action
```

Therefore:

```text
businessaccount_account_create.xml
```

The layout XML connected our block and template to Magento’s main content container.

It also removed the default Magento title block:

```xml
<referenceBlock name="page.main.title" remove="true"/>
```

We added that because the page originally displayed two headings:

```text
Create Business Account
Create Your Business Account
```

One title came from Magento’s standard page title block, while the second came from our custom template.

We kept the custom designed title and removed the default visible title.

The browser tab title is still set by the controller.

---

## 8. Frontend block

We created:

```text
Block/Account/Create.php
```

A Magento block prepares data for the template.

Our block provided:

```text
Form action URL
Form key
Customer login status
Customer first name
Customer last name
Customer email
Country options
Company type options
```

Instead of performing these operations directly inside `create.phtml`, the block prepares the required values.

This keeps the template focused on presentation.

For example:

```php
$block->getFormAction()
```

returns:

```text
/businessaccount/account/save
```

and:

```php
$block->isCustomerLoggedIn()
```

controls whether the password fields are displayed.

---

## 9. Frontend template

We created:

```text
view/frontend/templates/account/create.phtml
```

The `.phtml` file contains:

```text
HTML
Small PHP output expressions
Magento escaping
Form fields
Frontend validation rules
```

The form is divided into:

```text
Company Details
Primary Contact
Business Address
Account Security
Review and Submit
```

The account-security section appears only for guests.

For logged-in customers, Magento already knows their customer identity, so creating another password is unnecessary.

---

## 10. Form security

The form includes Magento’s form key.

The form key protects against cross-site request forgery.

Without it, another website could potentially cause a logged-in browser to submit unwanted requests to Magento.

The form sends:

```text
form_key
```

and the Save controller validates it before processing any data.

---

## 11. Frontend validation

The form uses Magento JavaScript validation.

This provides immediate feedback for fields such as:

```text
Required values
Email format
Password confirmation
Number validation
Maximum lengths
```

However, JavaScript validation is only a user-experience feature.

A malicious or custom request can bypass browser validation.

Therefore, we also validate all important fields in PHP.

The correct approach is:

```text
Frontend validation
        +
Server-side validation
```

not frontend validation alone.

---

## 12. Save controller

We created:

```text
Controller/Account/Save.php
```

This controller implements a POST action because it changes data.

Its responsibility is:

```text
Validate form key
Read POST data
Call registration service
Handle exceptions
Add success/error messages
Redirect
```

It does not create the customer or save the business application directly.

That logic belongs to the service class.

---

## 13. Registration service

We created:

```text
Model/Service/BusinessAccountRegistrationService.php
```

This service contains the actual registration business logic.

It handles:

```text
Data normalization
Required-field validation
Email validation
Password validation
Duplicate application checks
Duplicate registration-number checks
Existing customer flow
Guest customer creation
Business application persistence
Partial-failure cleanup
Customer login after successful registration
```

The service makes the registration logic reusable.

In the future, the same service could potentially be called from:

```text
REST API
GraphQL
Admin action
Import script
CLI command
```

without duplicating all the logic from the storefront controller.

---

## 14. Guest and logged-in customer flows

### Guest customer

For a guest:

```text
Submit business form
        ↓
Validate email does not already exist
        ↓
Create Magento customer
        ↓
Create business application
        ↓
Link using customer_id
        ↓
Log the customer in
```

### Existing logged-in customer

For a logged-in customer:

```text
Submit business form
        ↓
Use existing customer_id
        ↓
Check that no application already exists
        ↓
Create business application
```

No duplicate Magento customer is created.

---

## 15. Separate business application table

We created:

```text
brewcraft_business_account
```

The table stores business information such as:

```text
Company name
Registration number
Tax number
Company type
Years in business
Business contact
Business address
Application status
Admin comment
Approval timestamp
```

This information was not placed directly in the normal customer table because the two entities serve different purposes.

```text
Magento customer
→ login identity

Business application
→ company approval process
```

A customer can exist without a business application.

A business application must be linked to a customer.

---

## 16. Business application statuses

The model defines:

```php
STATUS_PENDING
STATUS_APPROVED
STATUS_REJECTED
```

Every new application begins as:

```text
pending
```

This means:

```text
Application submitted
Admin review not completed
Business benefits unavailable
```

An approved application becomes:

```text
approved
```

A rejected application becomes:

```text
rejected
```

---

## 17. Repository pattern

We created:

```text
Api/BusinessAccountRepositoryInterface.php
Model/BusinessAccountRepository.php
```

The repository provides methods such as:

```text
save
getById
getByCustomerId
getByRegistrationNumber
delete
deleteById
```

Instead of allowing every controller or service to directly use SQL or resource models, they use the repository contract.

The flow is:

```text
Controller/service
        ↓
Repository interface
        ↓
Repository implementation
        ↓
Resource model
        ↓
Database
```

This gives consistent persistence and exception behavior.

---

## 18. Admin customization — general explanation

Magento Admin development follows a flow similar to storefront development, but uses the `adminhtml` area.

The common parts are:

```text
Admin route
ACL permission
Admin menu
Controller
Layout XML
Block or UI Component
Admin template
```

The request flow for our Admin list is:

```text
Admin clicks Business Applications
        ↓
Admin route resolves
        ↓
Index controller runs
        ↓
Admin layout loads
        ↓
Listing UI Component loads
        ↓
Data provider loads collection
        ↓
Grid displays database records
```

For the details page:

```text
Admin clicks View
        ↓
View controller loads application
        ↓
Registers current model
        ↓
Layout loads block and template
        ↓
Details page renders
```

---

## 19. Admin route

We created:

```text
etc/adminhtml/routes.xml
```

This registered the Admin route:

```text
businessaccount
```

Admin URLs include Magento’s Admin front name and security key, so the final URL is generated by Magento rather than hard-coded.

Our action path is:

```text
businessaccount/application/index
```

Magento resolves it to:

```text
Controller/Adminhtml/Application/Index.php
```

The `Adminhtml` folder distinguishes the controller from the storefront controller.

---

## 20. Admin ACL

We created:

```text
etc/acl.xml
```

ACL means:

```text
Access Control List
```

It defines which Admin roles are allowed to perform specific operations.

We created permissions for:

```text
Business Applications
View Business Applications
Approve Business Applications
Reject Business Applications
```

Each Admin controller uses:

```php
public const ADMIN_RESOURCE = '...';
```

Magento checks this permission before allowing the controller to execute.

This is important in real businesses because not every Admin user should be allowed to approve customers.

For example:

```text
Support agent
→ may view applications

Business manager
→ may approve or reject

Catalog manager
→ may have no business-account access
```

---

## 21. Admin menu

We created:

```text
etc/adminhtml/menu.xml
```

This added:

```text
BrewCraft
└── Business Applications
```

The menu item contains:

```text
ID
Title
Parent
Sort order
Action
ACL resource
```

The action points to:

```text
businessaccount/application/index
```

The ACL resource controls whether the logged-in Admin sees and can access it.

---

## 22. Admin grid — general explanation

Magento Admin grids are commonly built using UI Components.

A grid is not simply an HTML table.

It includes built-in behavior such as:

```text
Sorting
Filtering
Pagination
Column controls
Bookmarks
Date filters
Select filters
AJAX reload
```

The main parts are:

```text
Admin layout XML
Listing UI Component XML
Data source
Data provider
Collection
Column definitions
Actions column
```

---

## 23. Grid layout

We created:

```text
view/adminhtml/layout/businessaccount_application_index.xml
```

It loads:

```xml
<uiComponent name="businessaccount_application_listing"/>
```

Magento then finds:

```text
view/adminhtml/ui_component/
businessaccount_application_listing.xml
```

The names must match exactly.

---

## 24. Listing UI Component

We created:

```text
view/adminhtml/ui_component/
businessaccount_application_listing.xml
```

This file defines the grid.

It includes:

```text
Data source
Primary key
Toolbar
Filters
Paging
Bookmarks
Columns
Actions column
```

The visible columns include:

```text
Application ID
Company Name
Registration Number
Contact Name
Contact Email
Customer ID
Status
Submitted At
Actions
```

Some columns such as phone and updated date are available but hidden by default.

Admin users can show them through column controls.

---

## 25. Grid data source and DI configuration

We created:

```text
etc/adminhtml/di.xml
```

This maps the UI Component data-source name to a grid collection.

The data source name is:

```text
businessaccount_application_listing_data_source
```

The UI Component requests this name.

Magento’s collection factory receives the request and returns a SearchResult collection reading from:

```text
brewcraft_business_account
```

The flow is:

```text
Listing UI Component
        ↓
Data provider
        ↓
CollectionFactory
        ↓
Grid collection
        ↓
brewcraft_business_account
```

This is why the submitted applications appeared in the Admin grid.

---

## 26. Admin status filter

We created:

```text
Model/Source/Status.php
```

This implements Magento’s option-source interface.

It returns:

```text
Pending
Approved
Rejected
```

The Admin grid uses this class for the status dropdown filter.

The source class reuses model constants rather than repeating raw strings.

---

## 27. Grid actions column

We created:

```text
Ui/Component/Listing/Column/Actions.php
```

This adds a **View** link to every row.

For each application, it generates a URL containing:

```text
entity_id
```

For example:

```text
businessaccount/application/view/entity_id/5
```

The details page uses that ID to load the selected application.

---

## 28. Admin Index controller

We created:

```text
Controller/Adminhtml/Application/Index.php
```

It:

```text
Creates the Admin page
Sets active menu
Adds breadcrumbs
Sets page title
Returns the page result
```

It does not manually load grid rows.

The UI Component handles the grid data.

This is an important Magento concept:

```text
Controller creates page
UI Component loads grid data
```

---

## 29. Admin application details page

We created:

```text
Controller/Adminhtml/Application/View.php
Block/Adminhtml/Application/View.php
view/adminhtml/layout/businessaccount_application_view.xml
view/adminhtml/templates/application/view.phtml
```

The details page displays:

```text
Application summary
Company information
Primary contact
Business address
Magento customer information
Admin review controls
```

---

## 30. Admin View controller

The View controller reads:

```text
entity_id
```

from the request.

It loads the application using:

```php
$this->businessAccountRepository->getById($entityId);
```

If the application does not exist, it:

```text
Adds an error message
Redirects back to the grid
```

If loading succeeds, it places the model in Magento’s registry.

---

## 31. Magento Registry usage

The View controller registers:

```text
current_brewcraft_business_application
```

The block reads the same registry key.

The data flow is:

```text
View controller loads model
        ↓
Controller registers model
        ↓
Block retrieves model
        ↓
Template displays model
```

The registry is a shared request-level storage mechanism.

It does not permanently store the data.

It only makes the loaded object available during the current page request.

---

## 32. Admin View block

The Admin block prepares:

```text
Current business application
Linked Magento customer
Customer edit URL
Approve URL
Reject URL
Form key
Status label
Status CSS class
Formatted dates
Fallback display values
```

For example, if an optional value is empty, the block displays:

```text
Not provided
```

instead of leaving the page confusingly blank.

---

## 33. Admin details template

The template uses Magento Admin CSS classes such as:

```text
admin__page-section
admin__page-section-title
admin__table-secondary
admin__field
admin__control-textarea
```

These classes help the custom page visually match standard Magento Admin screens.

The page shows Approve and Reject forms only when the application status is pending.

For approved or rejected applications, it displays:

```text
This application has already been reviewed and cannot be processed again.
```

This prevents accidental repeated processing from the user interface.

---

## 34. Why Approve and Reject use POST

Approving and rejecting change application state.

Therefore, they must not be simple GET links.

A GET request is intended for reading data.

A POST request is appropriate for changing data.

Our forms use:

```html
<form method="post">
```

and include the Magento form key.

This protects the actions against CSRF and prevents approval from happening merely by opening a URL.

---

## 35. Business Customer group

We created the customer group:

```text
Business Customer
```

using a data patch.

The patch file is:

```text
Setup/Patch/Data/CreateBusinessCustomerGroup.php
```

A data patch runs during:

```bash
bin/magento setup:upgrade
```

Magento records completed patches in:

```text
patch_list
```

so the same patch is not applied repeatedly.

---

## 36. Why a data patch was used

We could have manually created the group in Magento Admin, but that would only affect one environment.

A data patch ensures the same setup is created in:

```text
Local development
Testing
Staging
Production
```

This makes the configuration part of the codebase and deployment process.

---

## 37. Why customer-group IDs are not hard-coded

We did not use:

```php
$customer->setGroupId(4);
```

because group ID `4` may mean different things in different Magento databases.

Instead, the approval service searches by:

```text
Business Customer
```

and gets the actual ID.

The safer flow is:

```text
Find group by group code
        ↓
Read actual customer_group_id
        ↓
Assign it to customer
```

---

## 38. Approval service

We created:

```text
Model/Service/BusinessAccountApprovalService.php
```

This service handles both approval and rejection business rules.

It does not deal with page rendering.

It handles:

```text
Load application
Validate pending status
Load linked customer
Find Business Customer group
Change customer group
Update application status
Save Admin comment
Save approved timestamp
Trigger notification
```

---

## 39. Approval flow

The approval process is:

```text
Load application
        ↓
Verify status = pending
        ↓
Verify linked customer exists
        ↓
Find Business Customer group
        ↓
Load Magento customer
        ↓
Remember original group
        ↓
Assign Business Customer group
        ↓
Save customer
        ↓
Set status = approved
        ↓
Set approved_at
        ↓
Save Admin comment
        ↓
Save business application
        ↓
Trigger approval email
```

---

## 40. Approval consistency handling

There are two separate records being updated:

```text
customer_entity
brewcraft_business_account
```

Possible failure:

```text
Customer group updated successfully
        ↓
Business application save fails
```

To reduce inconsistency, the service remembers the original group ID.

If the business-application save fails, it attempts to restore the customer’s original group.

This is a compensating action.

It is not a perfect distributed transaction, but it improves reliability.

---

## 41. Rejection flow

The rejection process is simpler:

```text
Load application
        ↓
Validate rejection reason
        ↓
Verify status = pending
        ↓
Set status = rejected
        ↓
Set approved_at = null
        ↓
Save rejection reason as Admin comment
        ↓
Save application
        ↓
Trigger rejection email
```

The customer group is not changed.

The customer remains a normal Magento customer.

---

## 42. Review-state protection

Only pending applications can be processed.

The service checks:

```php
$businessAccount->isPending()
```

If the application is already approved or rejected, it throws a friendly error.

This prevents:

```text
Approving twice
Rejecting twice
Approving a rejected application accidentally
Rejecting an approved application accidentally
```

The current workflow treats approval/rejection as final.

A future reopen or resubmission feature would need separate business rules.

---

## 43. Approve controller

We created:

```text
Controller/Adminhtml/Application/Approve.php
```

It performs request-level operations:

```text
Validate form key
Read entity_id
Read Admin comment
Call approval service
Add success/error message
Redirect back to details page
```

The controller does not directly manipulate the customer group or database.

---

## 44. Reject controller

We created:

```text
Controller/Adminhtml/Application/Reject.php
```

It:

```text
Validates form key
Reads entity_id
Reads rejection reason
Calls rejection service method
Displays result message
Redirects back
```

The rejection reason is required.

If it is empty, the application remains pending.

---

## 45. Email trigger — general explanation

Magento transactional email flow typically looks like:

```text
Business action occurs
        ↓
Email service is called
        ↓
Select template ID
        ↓
Build template variables
        ↓
Resolve sender
        ↓
Resolve recipient
        ↓
TransportBuilder builds message
        ↓
Mail transport sends message
```

Our business action is:

```text
Approve or reject business application
```

Our email service is:

```text
BusinessAccountNotifier
```

---

## 46. Email template registration

We created:

```text
etc/email_templates.xml
```

This registers two template IDs:

```text
brewcraft_business_account_approved
brewcraft_business_account_rejected
```

The IDs connect PHP code to HTML email-template files.

Without this registration, Magento would not know which module and file belong to the template ID.

---

## 47. Email HTML templates

We created:

```text
view/frontend/email/business_account_approved.html
view/frontend/email/business_account_rejected.html
```

The approval template includes:

```text
Customer name
Company name
Approval message
Optional Admin comment
My Account URL
Store name
```

The rejection template includes:

```text
Customer name
Company name
Rejection message
Rejection reason
My Account URL
Store name
```

---

## 48. Email template variables

Instead of passing entire PHP objects into the email template, the notifier passes simple scalar values:

```text
customer_name
company_name
admin_comment
rejection_reason
account_url
store_name
```

This makes the templates safer and easier to understand.

Example:

```php
[
    'customer_name' => 'Lily James',
    'company_name' => 'Lily Coffee Traders',
    'account_url' => 'https://project1.test/customer/account'
]
```

The template reads those values using Magento email directives.

---

## 49. Email notifier class

We created:

```text
Model/Email/BusinessAccountNotifier.php
```

Its dependencies are:

```text
TransportBuilder
StoreManagerInterface
CustomerRepositoryInterface
ScopeConfigInterface
LoggerInterface
```

Each dependency has a specific responsibility.

### `TransportBuilder`

Builds the Magento email transport.

### `StoreManagerInterface`

Finds the relevant store view and generates the correct My Account URL.

### `CustomerRepositoryInterface`

Loads the linked Magento customer.

### `ScopeConfigInterface`

Reads sender-name and sender-email configuration.

### `LoggerInterface`

Writes email process information to Magento logs.

---

## 50. Constructor issue found and corrected

The first notifier version accidentally contained dependencies from the approval service:

```text
BusinessAccountRepositoryInterface
GroupCollectionFactory
DateTime
BusinessAccountNotifier
```

It also injected itself:

```php
private readonly BusinessAccountNotifier $notifier
```

That created a circular dependency:

```text
BusinessAccountNotifier
        ↓
requires BusinessAccountNotifier
        ↓
requires BusinessAccountNotifier
```

The class also used properties that were not injected:

```text
logger
transportBuilder
storeManager
scopeConfig
```

We corrected the constructor so the notifier contains only the dependencies it actually uses.

This was an important dependency-injection lesson:

```text
Each class should receive only its own dependencies.
```

---

## 51. Email sender configuration

The notifier reads Magento’s General Contact configuration:

```text
trans_email/ident_general/name
trans_email/ident_general/email
```

These values represent the sender.

Example:

```text
BrewCraft Support
support@project1.test
```

If the sender email is empty, the notifier logs an error.

---

## 52. Email recipient resolution

The notifier first uses:

```text
business application contact_email
```

If it is empty, it falls back to:

```text
Magento customer email
```

The customer name is built using:

```text
Customer first name + last name
```

If unavailable, it uses:

```text
Business application contact name
```

This makes the email logic more defensive.

---

## 53. Email store resolution

The notifier tries to use the customer’s store ID.

This ensures the email is rendered for the store where the customer was registered.

If no valid store ID is available, it falls back to Magento’s default store view.

This matters in multi-store Magento installations because:

```text
Store name
Base URL
Email design
Configuration values
```

can differ between stores.

---

## 54. Why email happens after database updates

The approval email is triggered only after:

```text
Customer group saved
Business application saved
```

The rejection email is triggered only after:

```text
Rejected status saved
Admin comment saved
```

This prevents sending an email that says:

```text
Your application was approved
```

when the database operation actually failed.

---

## 55. Why email failure does not undo approval

Email is a secondary operation.

Approval is the primary business transaction.

Consider:

```text
Application approved successfully
Customer group updated successfully
SMTP server unavailable
```

The customer should remain approved.

Therefore, the notifier catches email exceptions and logs them instead of rethrowing them.

This means:

```text
Business state remains correct
Email can be investigated separately
```

---

## 56. Email logging added

Initially, we only logged failures.

That meant no log appeared when Magento believed the email was successfully sent.

We added three stages:

```text
Process started
Sent successfully
Failed
```

Example success logs:

```text
BrewCraft approval email process started.
BrewCraft approval email sent successfully.
```

Your test produced:

```text
application_id: 5
customer_id: 6
recipient: lily@yopmail.com
```

This confirms:

```text
Notifier was called
Application was passed correctly
Customer was resolved
Recipient was resolved
Magento mail transport completed without throwing an exception
```

---

## 57. Meaning of “sent successfully”

The log:

```text
BrewCraft approval email sent successfully.
```

means:

```text
Magento TransportBuilder completed
Magento mail transport accepted the message
No exception was thrown
```

It does not necessarily prove final inbox delivery.

The complete external flow may still be:

```text
Magento
    ↓
PHP sendmail or SMTP module
    ↓
SMTP server
    ↓
Recipient mail server
    ↓
Inbox or spam folder
```

For the current learning phase, we confirmed the Magento trigger and transport layer.

A proper SMTP or Mailpit integration can be added later.

---

# 3.Development Log — Storefront Business Access and My Account Business Status
**Date:** 25 July

**Module:** `BrewCraft_BusinessAccount`
**Work completed:** Storefront business-registration entry points, customer-facing Business Account status page, My Account navigation integration, template troubleshooting, and Admin UI data-source DI correction.

---

## 1. Business objective of this phase

Before this phase, the Business Account functionality worked, but customers had to know the direct URL:

```text
/businessaccount/account/create
```

That is technically functional, but it is not a complete customer journey.

A real customer visiting BrewCraft would normally use:

```text
Create an Account
```

or:

```text
Customer Login
```

They would not know that a separate custom URL exists.

We therefore needed to solve two business problems:

```text
Problem 1:
How does a customer discover Business Account registration?

Problem 2:
How does a customer check whether their application is
pending, approved, or rejected?
```

The completed customer journey is now:

```text
Create Account / Login page
        ↓
Business Account registration option
        ↓
Customer submits application
        ↓
Admin reviews application
        ↓
Customer logs in
        ↓
My Account → Business Account
        ↓
Customer sees current application status
```

---

## 2. What was completed

This phase added:

```text
✅ Business Account option on Create Account page
✅ Business Account option on Customer Login page
✅ Reusable storefront CTA template
✅ My Account → Business Account navigation link
✅ Customer-specific Business Account status page
✅ No-application state
✅ Pending state
✅ Approved state
✅ Rejected state
✅ Rejection reason display
✅ Company and application information display
✅ Guest-access protection
✅ Protection against viewing another customer’s application
✅ Fix for missing storefront template
✅ Fix for Magento Admin customer-grid data source
```

---

## Part A — Business registration entry points

## 3. Previous limitation

The business registration page already existed:

```text
/businessaccount/account/create
```

The backend flow also worked:

```text
Submit application
        ↓
Create or use Magento customer
        ↓
Save pending business application
```

However, there was no visible storefront link to the page.

This meant the functionality was accessible only through a manually entered URL.

That did not satisfy the business requirement of a discoverable registration process.

---

## 4. Decision: keep personal and business registration separate

We did not replace Magento’s standard customer-registration form.

Instead, we kept two separate journeys:

```text
Personal Account
→ Magento’s default customer registration
```

```text
Business Account
→ BrewCraft’s custom business application
```

This was the safer architectural choice.

A personal customer needs only basic account data:

```text
Name
Email
Password
```

A business applicant needs additional information:

```text
Company name
Registration number
Tax number
Company type
Years in business
Business contact
Business address
```

Combining both into a single large form would make normal customer registration unnecessarily complicated.

---

## 5. Extending existing Magento pages

We created layout updates for existing Magento Customer pages:

```text
view/frontend/layout/customer_account_create.xml
view/frontend/layout/customer_account_login.xml
```

These files do not create new routes.

Instead, they extend existing Magento pages.

The existing URLs are:

```text
/customer/account/create
/customer/account/login
```

Magento generates the corresponding layout handles:

```text
customer_account_create
customer_account_login
```

A module can create XML files using these handles to add new blocks to the existing pages.

---

## 6. Why layout XML was used

We did not copy or replace Magento’s core customer templates.

We used layout XML to insert an additional block into the page:

```xml
<referenceContainer name="content">
    <block ... />
</referenceContainer>
```

This results in:

```text
Existing Magento page content
        +
BrewCraft Business Account section
```

This is better than overriding the complete Magento registration or login template because:

* Core Magento behavior remains intact.
* Other modules can still extend the same pages.
* Magento upgrades are less likely to break the customization.
* Our code is responsible only for the new Business Account section.
* The final theme can redesign the block later.

---

## 7. Reusable CTA template

We created:

```text
view/frontend/templates/account/
business-registration-link.phtml
```

The same template is rendered on both:

```text
Create Account page
Login page
```

The layouts pass a value called:

```text
context_type
```

For the registration page:

```text
context_type = create
```

For the login page:

```text
context_type = login
```

The template reads that value and changes its content accordingly.

---

## 8. Why one reusable template was used

Without reuse, we might have created:

```text
business-link-create.phtml
business-link-login.phtml
```

Both templates would contain mostly the same markup.

That would create duplicate code.

Using one template gives us:

```text
One component
        ↓
Different text based on context
        ↓
Used on multiple pages
```

This improves maintainability.

For example, if we later change the Business Account button label, we only need to update one template.

---

## 9. Create Account page behavior

The default Magento customer-registration form remains available.

Below or alongside that content, customers see a BrewCraft Business section explaining benefits such as:

```text
Wholesale access
Quote requests
Business support
```

The primary CTA points to:

```text
/businessaccount/account/create
```

A customer can therefore choose:

```text
Create a personal account
```

or:

```text
Apply for a business account
```

---

## 10. Login page behavior

The customer Login page also shows the Business Account CTA.

This fulfills two possible journeys.

### New customer

```text
Open Login page
        ↓
See Business Account option
        ↓
Open business registration form
        ↓
Create customer and business application together
```

### Existing customer

```text
Open Login page
        ↓
Sign in
        ↓
Open business registration
        ↓
Application is attached to existing customer
```

The business registration service already supports both cases, so no duplicate backend flow was required.

---

## 11. Temporary presentation versus final theme

The current CTA is functional UI.

It is intentionally not the final Figma-based storefront design.

The current purpose is to confirm:

```text
Block loads
Template renders
Links work
Pages remain functional
Responsive structure is usable
```

Later we will create a dedicated theme, for example:

```text
app/design/frontend/BrewCraft/default
```

The theme will own final visual presentation:

```text
Colors
Typography
Spacing
Cards
Buttons
Page structures
Responsive design
Figma implementation
```

The module will continue to own business functionality.

```text
Module = what the feature does
Theme = how the feature looks
```

The theme can override the module template without changing the controller, service, repository, or database logic.

---

## Part B — My Account Business Account status page

## 12. Business requirement

Once customers submit a business application, they need a way to check its status.

Previously, status was visible only to Admin users.

The customer did not have a storefront page showing:

```text
Pending
Approved
Rejected
```

This created an incomplete customer experience.

The new requirement was:

```text
My Account
└── Business Account
```

The page should show:

* Whether an application exists
* Current application status
* Company information
* Contact information
* Business address
* Submitted date
* Approved date when applicable
* Rejection reason when applicable

---

## 13. Customer-facing status scenarios

The page supports four states.

### State 1 — No application

```text
Status: Not Applied
```

The customer sees an explanation and an application button.

```text
Apply for a Business Account
```

### State 2 — Pending

```text
Status: Pending Review
```

The customer sees:

```text
Application submitted
Review still in progress
Company and application information
```

They do not see another application button.

### State 3 — Approved

```text
Status: Approved
```

The customer sees:

```text
Business Account active
Approval date
Company information
Optional Admin approval comment
```

Their Magento customer group has already been changed to:

```text
Business Customer
```

### State 4 — Rejected

```text
Status: Rejected
```

The customer sees:

```text
Application not approved
Regular customer account remains active
Admin rejection reason
```

---

## 14. My Account controller

We created:

```text
Controller/Account/Index.php
```

This controller handles:

```text
/businessaccount/account/index
```

Its first responsibility is authentication.

```php
if (!$this->customerSession->isLoggedIn()) {
    // Redirect to login.
}
```

A guest cannot access the page because it contains private customer and company information.

For a logged-in customer, the controller creates a page result and sets the page title:

```text
Business Account
```

---

## 15. Why authentication is checked in the controller

The Business Account status page may contain:

```text
Legal company information
Tax number
Registration number
Business address
Admin feedback
Application status
```

This data must not be publicly visible.

The controller therefore blocks guest access before rendering the page.

The flow is:

```text
Request Business Account page
        ↓
Is customer logged in?
    ├── No → redirect to login
    └── Yes → render page
```

---

## 16. My Account status block

We created:

```text
Block/Account/Index.php
```

The block is responsible for loading and preparing the logged-in customer’s application.

It obtains the customer ID from:

```php
CustomerSession
```

Then it calls:

```php
getByCustomerId($customerId)
```

on the repository.

The important point is that the application is selected using the authenticated customer ID.

---

## 17. Security of the customer application lookup

We intentionally did not use an application ID from the URL.

An unsafe design could look like:

```text
/businessaccount/account/index/entity_id/5
```

A customer might then change the URL:

```text
entity_id/5
entity_id/6
entity_id/7
```

and attempt to view other applications.

Our implementation does this instead:

```text
Logged-in session
        ↓
Current customer_id
        ↓
getByCustomerId(customer_id)
        ↓
Only that customer’s application
```

This prevents insecure direct object-reference behavior.

The customer cannot choose which application ID to load.

---

## 18. Application caching inside the block

The template may call several block methods:

```php
getBusinessAccount()
isPending()
isApproved()
isRejected()
getStatusLabel()
getStatusDescription()
```

Most of these methods depend on the same business application.

Without request-level caching, every method could run another repository query.

We added:

```php
private bool $applicationLoaded = false;
private ?BusinessAccount $businessAccount = null;
```

The first call loads the application.

Later calls reuse the already-loaded object.

The flow is:

```text
First method call
→ query repository
→ store result in block property

Later method calls
→ return stored result
→ no additional query
```

---

## 19. My Account navigation link

We created:

```text
view/frontend/layout/customer_account.xml
```

This layout handle is shared by Magento customer-account pages.

We targeted:

```xml
<referenceBlock name="customer_account_navigation">
```

and inserted a new link:

```text
Business Account
```

The link points to:

```text
businessaccount/account/index
```

This makes the feature discoverable from the standard customer account sidebar.

---

## 20. Why `customer_account.xml` was used

Magento’s customer account navigation is a shared layout structure.

Instead of modifying each account page individually, we add the link once to:

```text
customer_account
```

Then it appears across customer-account pages such as:

```text
My Account dashboard
Account Information
Address Book
My Orders
Business Account
```

This is another example of extending Magento rather than replacing core templates.

---

## 21. Business Account page layout

We created:

```text
view/frontend/layout/businessaccount_account_index.xml
```

The layout contains:

```xml
<update handle="customer_account"/>
```

This imports Magento’s standard My Account structure.

It gives the page:

```text
Customer account navigation
Two-column account layout
Standard account-area structure
```

Our custom block is then added to the content area.

The resulting page is:

```text
Magento My Account layout
        +
BrewCraft Business Account content
```

---

## 22. Why the block is non-cacheable

The Business Account page contains customer-specific data.

We used:

```xml
cacheable="false"
```

This prevents full-page cache from serving one customer’s application information to another customer.

Customer-specific pages must be handled carefully because the displayed data changes by session.

---

## 23. Status helper methods

The block contains methods such as:

```php
isPending()
isApproved()
isRejected()
```

It also maps raw database values to user-friendly text:

```text
pending  → Pending Review
approved → Approved
rejected → Rejected
```

This keeps raw technical values out of the template.

The template asks the block:

```php
$block->getStatusLabel()
```

rather than implementing status mapping itself.

---

## 24. Customer-safe status descriptions

The block also provides a customer-facing explanation.

Examples:

### Pending

```text
Your application has been submitted and is currently
being reviewed by our business team.
```

### Approved

```text
Your BrewCraft Business Account is active.
```

### Rejected

```text
Your business application was not approved.
Your regular customer account remains active.
```

This converts backend status into understandable customer communication.

---

## 25. Status template

We created:

```text
view/frontend/templates/account/index.phtml
```

The template conditionally renders content based on status.

Simplified structure:

```php
if (!$businessAccount) {
    // Show application CTA.
} else {
    // Show status and details.

    if (pending) {
        // Pending notice.
    }

    if (approved) {
        // Approval notice.
    }

    if (rejected) {
        // Rejection notice and feedback.
    }
}
```

---

## 26. Information displayed to customers

The page displays several sections.

### Application Summary

```text
Application number
Status
Submitted date
Approved date, when approved
Last-updated date
```

### Company Information

```text
Company name
Registration number
Tax/VAT number
Company type
Years in business
```

### Primary Contact

```text
Contact name
Contact email
Contact phone
```

### Business Address

```text
Street
City
State/region
Postcode
Country
```

### Review Feedback

Displayed only for a rejected application with an Admin comment.

```text
Reason provided by our business team:
<Admin rejection reason>
```

---

## 27. Why Admin comments are conditionally displayed

The `admin_comment` field can represent two types of messages:

```text
Approval note
Rejection reason
```

For approved applications, it may be displayed as:

```text
Message from Our Business Team
```

For rejected applications, it is displayed as:

```text
Review Feedback
```

The rejection reason is especially important because it explains why the application was not accepted.

---

## 28. Duplicate application protection remains active

When a customer already has a pending, approved, or rejected application, the status page does not show the Apply button.

Even if they manually open:

```text
/businessaccount/account/create
```

the backend registration service still checks:

```php
getByCustomerId($customerId)
```

The database also has a unique constraint on:

```text
customer_id
```

Protection therefore exists at multiple levels:

```text
Storefront UI
        +
Service validation
        +
Database unique constraint
```

---

## Part C — Problems encountered and fixed

## 29. Problem 1: Invalid template file

When a customer without a business application opened the Business Account page, Magento showed:

```text
Invalid template file:
BrewCraft_BusinessAccount::account/index.phtml
```

Magento translated this alias:

```text
BrewCraft_BusinessAccount::account/index.phtml
```

into the expected physical path:

```text
app/code/BrewCraft/BusinessAccount/
view/frontend/templates/account/index.phtml
```

The error meant that Magento could not locate or validate the template at that exact path.

---

## 30. Template path rules learned

For a module template alias:

```text
Vendor_Module::folder/file.phtml
```

Magento looks under:

```text
Vendor/Module/view/<area>/templates/folder/file.phtml
```

For our frontend template:

```text
BrewCraft_BusinessAccount::account/index.phtml
```

the exact path must be:

```text
view/frontend/templates/account/index.phtml
```

Linux paths are case-sensitive.

These would be incorrect:

```text
templates/Account/index.phtml
templates/account/Index.phtml
view/adminhtml/templates/account/index.phtml
view/frontend/template/account/index.phtml
```

After correcting the file location/name and clearing layout/template caches, the Business Account page worked correctly.

---

## 31. Problem 2: Admin All Customers grid failed

A second unrelated error occurred in:

```text
Admin → Customers → All Customers
```

Magento displayed:

```text
Not registered handle customer_listing_data_source
```

The exception came from:

```text
Magento\Framework\View\Element\UiComponent\
DataProvider\CollectionFactory
```

This happened after we registered our custom Business Applications grid.

---

## 32. Understanding grid data-source registration

Magento Admin UI grids use data-source names.

For example:

```text
customer_listing_data_source
```

maps to Magento’s customer-grid collection.

Our custom grid uses:

```text
businessaccount_application_listing_data_source
```

which maps to the BrewCraft business-application grid collection.

The collection registry should contain both:

```text
customer_listing_data_source
→ Magento Customer grid collection

businessaccount_application_listing_data_source
→ BrewCraft Business Application collection
```

---

## 33. Initial custom grid DI configuration

We originally placed our custom collection mapping in:

```text
etc/adminhtml/di.xml
```

The file configured this argument:

```xml
<type name="Magento\Framework\View\Element\UiComponent\DataProvider\CollectionFactory">
    <arguments>
        <argument name="collections" xsi:type="array">
            <item name="businessaccount_application_listing_data_source">
                ...
            </item>
        </argument>
    </arguments>
</type>
```

Our BrewCraft grid worked because its data-source mapping was present.

However, Magento’s core Customer grid mapping disappeared from the final Admin-area DI configuration.

---

## 34. Diagnostics performed

We searched all custom modules for CollectionFactory configuration:

```bash
grep -R -n \
'UiComponent\\DataProvider\\CollectionFactory' \
app/code/*/*/etc
```

Only our Business Account module contained a custom declaration.

We also checked the core Magento mapping:

```bash
grep -R -n \
"customer_listing_data_source" \
vendor/magento/module-customer
```

Magento’s Customer module registered the mapping in:

```text
vendor/magento/module-customer/etc/di.xml
```

This confirmed that:

```text
Magento’s core mapping existed
```

but:

```text
It was missing from the final Admin DI registry
```

---

## 35. Root cause

Magento Customer registered its grid collection in global:

```text
vendor/magento/module-customer/etc/di.xml
```

Our module registered another value for the same `collections` argument in:

```text
BrewCraft/BusinessAccount/etc/adminhtml/di.xml
```

The Admin-area configuration replaced the already-merged global array for that argument.

The effective registry became similar to:

```php
[
    'businessaccount_application_listing_data_source'
        => BrewCraftGridCollection::class
]
```

It no longer contained:

```php
'customer_listing_data_source'
```

Therefore, Magento could display our custom grid but could not open the core Customers grid.

---

## 36. DI fix

We removed:

```text
etc/adminhtml/di.xml
```

and moved the custom grid mapping into:

```text
etc/di.xml
```

The same global `di.xml` now contains:

```text
Repository interface preference
Custom Admin grid SearchResult virtual type
Custom grid data-source collection mapping
```

Because both Magento Customer and BrewCraft now contribute to the same global DI configuration stage, their array items merge correctly.

The final registry contains both mappings.

---

## 37. Why the grid mapping can remain global

Even though the BrewCraft collection is used by an Admin grid, registering the data-source collection globally is acceptable.

The mapping is only requested when the corresponding UI Component uses:

```text
businessaccount_application_listing_data_source
```

The existence of the mapping does not render or execute the Admin grid on storefront pages.

The important requirement is correct DI array merging.

---

## 38. Rebuilding compiled dependency injection

After moving the configuration, we deleted:

```text
generated/code
generated/metadata
var/di
var/cache
var/page_cache
```

Then we ran:

```bash
bin/magento setup:di:compile
bin/magento cache:flush
```

Removing `var/di` was especially relevant because Magento may otherwise continue using stale compiled dependency-injection configuration.

After rebuilding:

```text
Customers → All Customers
```

worked again, and:

```text
BrewCraft → Business Applications
```

continued working.

---

## 39. Important DI lesson

Magento merges configuration from many modules and application areas.

When multiple modules modify an array constructor argument, configuration location matters.

The lesson from this issue is:

```text
Do not assume area-specific DI will always extend a global array.
It may replace the previously merged value for that area.
```

For shared collection registries such as:

```php
CollectionFactory::$collections
```

custom data-source mappings should be configured at the same global stage as the core mappings when they need to merge.

---

## 40. Why the two errors were unrelated

We encountered:

```text
Invalid template file
```

and:

```text
Not registered handle customer_listing_data_source
```

at nearly the same time.

However, they had completely different causes.

### Storefront error

```text
Cause:
Missing or incorrectly located .phtml template
```

### Admin error

```text
Cause:
Dependency-injection array mapping replaced in Admin area
```

This is an important debugging lesson: errors happening after the same development change are not automatically caused by the same file.

Each stack trace and affected area should be investigated independently.

---

## 41. Files added or changed in this phase

### Storefront entry points

```text
view/frontend/layout/customer_account_create.xml
view/frontend/layout/customer_account_login.xml
view/frontend/templates/account/business-registration-link.phtml
view/frontend/web/css/source/_module.less
```

### My Account Business page

```text
Controller/Account/Index.php
Block/Account/Index.php
view/frontend/layout/customer_account.xml
view/frontend/layout/businessaccount_account_index.xml
view/frontend/templates/account/index.phtml
```

### DI correction

Changed:

```text
etc/di.xml
```

Removed:

```text
etc/adminhtml/di.xml
```

The global DI file now owns both the repository preference and custom grid-collection registration.

---


# 4.BrewCraft B2B Business Account — Complete Workflow

You have now completed an end-to-end **B2B customer onboarding and approval workflow** in Magento.

The feature allows a customer to apply for a Business Account, lets an Admin review the application, assigns approved customers to a business customer group, sends notifications, and allows the customer to track the result from My Account.

---

## 1. Overall business workflow

```text
Customer visits BrewCraft storefront
        ↓
Chooses Business Account registration
        ↓
Submits company and contact information
        ↓
Magento validates the request
        ↓
Magento creates or uses an existing customer
        ↓
Business application is stored as Pending
        ↓
Application appears in Magento Admin
        ↓
Admin opens the application details
        ↓
Admin approves or rejects
        ↓
Approved:
    Customer moved to Business Customer group
    Application status = Approved
    Approval timestamp saved
    Approval email triggered

Rejected:
    Customer remains a normal customer
    Application status = Rejected
    Rejection reason saved
    Rejection email triggered
        ↓
Customer logs in
        ↓
My Account → Business Account
        ↓
Customer sees Pending, Approved or Rejected status
```

---

## 2. Customer registration scenarios

The registration workflow supports both guests and existing Magento customers.

### Guest customer

```text
Guest opens Business Account form
        ↓
Enters personal, company and password information
        ↓
Magento validates the form
        ↓
A new Magento customer account is created
        ↓
Business application is created
        ↓
Application is linked using customer_id
        ↓
Status is set to Pending
```

The customer does not need to separately create a personal account first.

### Existing customer

```text
Customer logs in
        ↓
Opens Business Account registration
        ↓
Magento uses existing customer_id
        ↓
No duplicate Magento customer is created
        ↓
Business application is created and linked
```

### Existing customer without an application

When a normal logged-in customer accesses the Business Account feature:

```text
No business application found
        ↓
Customer is allowed to open the Business Account registration page
```

### Customer with an existing application

```text
Pending / Approved / Rejected application exists
        ↓
Customer cannot create another application
        ↓
Customer is redirected to My Account → Business Account
```

---

## 3. Storefront discoverability

Previously, customers needed to know the direct route:

```text
/businessaccount/account/create
```

We extended Magento’s existing customer pages so the feature is visible.

Business Account CTAs were added to:

```text
/customer/account/create
/customer/account/login
```

The pages now show an option such as:

```text
Apply for a Business Account
```

The CTA is status-aware.

### No application

```text
Apply for a Business Account
```

### Pending application

```text
Your Application Is Under Review
View Application Status
```

### Approved application

```text
Your Business Account Is Active
Go to Business Account
```

### Rejected application

```text
Your Business Application Was Reviewed
View Review Feedback
```

---

## 4. Data model

We created a custom table:

```text
brewcraft_business_account
```

It stores business-specific information separately from the Magento customer entity.

Typical information stored includes:

```text
Customer ID
Company name
Registration number
Tax/VAT number
Company type
Years in business
Contact name
Contact email
Contact phone
Street
City
Region
Postcode
Country
Application status
Admin comment
Approved date
Created date
Updated date
```

### Why a separate table was used

Magento customer data represents the login identity.

The business application represents a company approval process.

```text
Magento customer
→ Login, email, password and normal customer account

Business application
→ Company details, approval status and Admin review
```

A customer can exist without a business application, so the business data should not be forced into the standard customer entity.

---

## 5. Application statuses

We implemented three main statuses:

```text
Pending
Approved
Rejected
```

### Pending

```text
Application submitted
Admin review not completed
Business privileges unavailable
```

### Approved

```text
Admin accepted the application
Customer assigned to Business Customer group
Business benefits can be enabled
```

### Rejected

```text
Admin declined the business application
Customer remains a normal retail customer
Rejection reason is available in My Account
```

---

## 6. Duplicate prevention

The system prevents duplicate applications in multiple layers.

### Duplicate customer application

A customer cannot have multiple active business applications.

The service checks by:

```text
customer_id
```

The database also has a unique constraint for the customer relationship.

### Duplicate registration number

Two companies cannot register using the same business registration number.

The service checks whether the registration number already exists before saving.

### Direct URL protection

Even if a customer manually enters:

```text
/businessaccount/account/create
```

Magento checks whether the logged-in customer already has an application.

If one exists, the customer is redirected to the Business Account status page.

This means protection exists at:

```text
Storefront UI
Service layer
Controller
Database
```

---

## 7. Main backend architecture

The implementation follows Magento separation of responsibilities.

```text
Controller
→ Handles HTTP request, validation messages and redirects

Service
→ Handles business rules

Repository
→ Loads and saves business application entities

Resource Model
→ Maps the entity to the database table

Block
→ Prepares data for frontend or Admin template

Template
→ Displays HTML

UI Component
→ Renders the Admin grid

Notifier
→ Sends approval or rejection emails
```

This structure avoids putting all functionality into one controller.

---

## 8. Registration backend flow

The storefront Save controller receives the form submission.

```text
POST request
        ↓
Validate Magento form key
        ↓
Read submitted data
        ↓
Call BusinessAccountRegistrationService
```

The registration service performs:

```text
Input normalization
Required-field validation
Email validation
Password validation
Existing customer detection
Duplicate customer application check
Duplicate registration-number check
Magento customer creation for guests
Business application persistence
Customer/application linking
```

The controller then handles:

```text
Success message
Error message
Redirect
```

---

## 9. Repository pattern

We created:

```text
BusinessAccountRepositoryInterface
BusinessAccountRepository
```

The repository provides operations such as:

```text
save()
getById()
getByCustomerId()
getByRegistrationNumber()
delete()
deleteById()
```

The application code uses the repository instead of writing SQL directly.

Example flow:

```text
Service
        ↓
Repository interface
        ↓
Repository implementation
        ↓
Resource model
        ↓
Database
```

This makes persistence consistent and reusable.

---

## 10. Admin functionality

We created a complete Admin management section:

```text
BrewCraft
└── Business Applications
```

The Admin functionality includes:

```text
Menu
ACL permissions
Application grid
Filtering
Sorting
Pagination
View action
Application details page
Approve form
Reject form
Admin comments
```

---

## 11. Admin grid workflow

```text
Admin opens BrewCraft → Business Applications
        ↓
Admin controller creates the page
        ↓
UI Component listing loads
        ↓
Data provider requests collection
        ↓
Collection loads brewcraft_business_account records
        ↓
Grid displays applications
```

The grid includes columns such as:

```text
Application ID
Company name
Registration number
Contact name
Contact email
Customer ID
Status
Submitted date
Actions
```

---

## 12. Admin ACL

We created Admin permissions for actions such as:

```text
View Business Applications
Approve Business Applications
Reject Business Applications
```

Each Admin controller declares an ACL resource.

Magento checks the logged-in Admin role before allowing access.

This means different roles can be configured, for example:

```text
Support role
→ View only

Business manager
→ View, approve and reject
```

---

## 13. Admin details page

When the Admin clicks View:

```text
Application ID received
        ↓
Repository loads the application
        ↓
Application is placed in request-level registry
        ↓
Admin block reads the application
        ↓
Template displays all submitted information
```

The page includes:

```text
Application summary
Company information
Contact information
Business address
Linked Magento customer
Status
Admin comments
Approve and Reject actions
```

Approve and Reject controls are shown only when the application is pending.

---

## 14. Approval workflow

```text
Admin clicks Approve
        ↓
Form key is validated
        ↓
Approval service loads the application
        ↓
Service checks status is Pending
        ↓
Linked customer is loaded
        ↓
Business Customer group is found
        ↓
Customer group is updated
        ↓
Application status becomes Approved
        ↓
approved_at is saved
        ↓
Admin comment is saved
        ↓
Approval notification is triggered
```

---

## 15. Rejection workflow

```text
Admin enters rejection reason
        ↓
Clicks Reject
        ↓
Form key is validated
        ↓
Rejection reason is validated
        ↓
Service checks status is Pending
        ↓
Application status becomes Rejected
        ↓
Admin comment stores the rejection reason
        ↓
Customer group remains unchanged
        ↓
Rejection notification is triggered
```

The customer remains a normal Magento customer.

---

## 16. Customer group assignment

A custom Magento customer group was created:

```text
Business Customer
```

It was added using a data patch.

Using a data patch ensures the group is created consistently in:

```text
Local
Testing
Staging
Production
```

We did not hard-code the customer group ID.

The approval service searches for the group by name:

```text
Business Customer
```

and uses the actual ID from the current database.

This is safer because IDs can differ between environments.

---

## 17. Consistency handling during approval

Approval updates two different records:

```text
Magento customer
Business application
```

Possible problem:

```text
Customer group update succeeds
        ↓
Business application save fails
```

The service stores the customer’s original group ID.

If the application save fails, it attempts to restore the original customer group.

This is a compensating operation that reduces inconsistent data.

---

## 18. Email notification workflow

We created two email templates:

```text
Business Account Approved
Business Account Rejected
```

The email flow is:

```text
Application successfully approved/rejected
        ↓
BusinessAccountNotifier is called
        ↓
Linked customer is loaded
        ↓
Recipient email is resolved
        ↓
Template variables are prepared
        ↓
Magento TransportBuilder sends the message
        ↓
Success or failure is logged
```

Template variables include values such as:

```text
Customer name
Company name
Admin comment
Rejection reason
Store name
My Account URL
```

---

## 19. Email reliability rule

Email is treated as a secondary operation.

For example:

```text
Customer group updated
Application approved
SMTP server unavailable
```

The application should remain approved.

Therefore, the notifier catches email exceptions and logs them instead of reversing the business transaction.

Logs confirm:

```text
Email process started
Email sent successfully
Email failed
```

Your successful test confirmed that Magento completed the email transport without throwing an exception.

---

## 20. My Account Business Account page

We added:

```text
My Account
└── Business Account
```

The page is customer-specific.

It loads the business application using:

```text
Logged-in customer_id
```

It does not accept an application ID from the URL.

This prevents one customer from attempting to view another customer’s business application.

---

## 21. My Account page states

### No application

The customer sees:

```text
No Business Account application found
Apply for a Business Account
```

### Pending

The customer sees:

```text
Pending Review
Application details
Submitted date
Company information
```

### Approved

The customer sees:

```text
Approved
Business Account active
Approval date
Company information
Optional Admin message
```

### Rejected

The customer sees:

```text
Rejected
Review Feedback
Rejection reason
Online resubmission is not currently available
Contact support for another review
```

---

## 22. Current rejection business rule

Rejected applications cannot currently be resubmitted online.

```text
Rejected customer
        ↓
Can view rejection reason
        ↓
Cannot submit another application
        ↓
Can contact support for another review
```

This is an intentional business rule, not an accidental limitation.

A future resubmission feature would require:

```text
Edit application
Status reset to Pending
Previous decision history
New review cycle
Status history table
```

---

## 23. Final test results

You confirmed:

```text
✅ Guest Business Account CTA works
✅ Normal customer without application can access registration
✅ Pending customer behavior works
✅ Approved customer behavior works
✅ Rejected customer behavior works
✅ Direct registration URL protection works
✅ My Account status page works
✅ Magento Customers grid works
✅ BrewCraft Business Applications grid works
```

---

## 24. What we can say is complete

The following feature is complete:

> **BrewCraft B2B Business Account onboarding and approval lifecycle**

It includes:

```text
Registration
Customer creation/linking
Application persistence
Duplicate prevention
Admin management
Approval/rejection
Customer-group assignment
Email notification
Customer-facing status tracking
Status-aware CTAs
Direct URL protection
Rejection feedback
```

---

## 25. What is not part of this completed phase

The following are larger B2B commerce features and have not yet been implemented:

```text
Wholesale pricing
Request for Quote
Quick reorder
Company sub-users and roles
Credit limits
Purchase order workflow
Business-only catalog
Negotiated pricing
Business-specific shipping
Business-specific payment methods
```

However, the foundation required for them now exists:

```text
Approved customer
        ↓
Business Customer group
        ↓
Future B2B feature availability
```

---
---
---

# 5.BrewCraft Admin Business Applications — Detailed Development Log - in detail for ADMIN page, exp
**DATE** 2nd Aug


This log explains the entire Admin-side flow we built for the Business Account feature:

```text
Admin menu
    ↓
Admin route
    ↓
Admin controller
    ↓
Layout XML
    ↓
UI Component grid
    ↓
Data source and collection
    ↓
Application details page
    ↓
Approve / Reject actions
    ↓
Customer-group update
    ↓
Application status update
    ↓
Email notification
```

The goal is not only to remember the files, but to understand **why each file exists and how Magento connects them**.

---

## 1. What we needed to build

Business applications were already being submitted from the storefront and saved in:

```text
brewcraft_business_account
```

But an application staying in the database is not enough.

The BrewCraft Admin user needed a way to:

```text
See all applications
Filter applications by status
Open one application
Review company/customer details
Approve the application
Reject the application
Add an Admin comment
Assign an approved customer to Business Customer group
Notify the customer
```

So we created a complete Admin management flow.

---

## 2. Final Admin structure

The relevant module structure is approximately:

```text
app/code/BrewCraft/BusinessAccount
├── Controller
│   └── Adminhtml
│       └── Application
│           ├── Index.php
│           ├── View.php
│           ├── Approve.php
│           └── Reject.php
├── Block
│   └── Adminhtml
│       └── Application
│           └── View.php
├── Model
│   ├── Service
│   │   └── BusinessAccountApprovalService.php
│   ├── Source
│   │   └── Status.php
│   └── Email
│       └── BusinessAccountNotifier.php
├── Ui
│   └── Component
│       └── Listing
│           └── Column
│               └── Actions.php
├── Setup
│   └── Patch
│       └── Data
│           └── CreateBusinessCustomerGroup.php
├── etc
│   ├── adminhtml
│   │   ├── menu.xml
│   │   └── routes.xml
│   ├── acl.xml
│   └── di.xml
└── view
    └── adminhtml
        ├── layout
        │   ├── businessaccount_application_index.xml
        │   └── businessaccount_application_view.xml
        ├── ui_component
        │   └── businessaccount_application_listing.xml
        └── templates
            └── application
                └── view.phtml
```

The Admin grid and details page are separate page types:

```text
Grid page
→ Uses Magento UI Component listing

Details page
→ Uses Block + PHTML template
```

---

## 3. Complete Admin request flow

When the Admin clicks:

```text
BrewCraft
└── Business Applications
```

Magento follows this path:

```text
menu.xml
    ↓
businessaccount/application/index
    ↓
etc/adminhtml/routes.xml
    ↓
Controller/Adminhtml/Application/Index.php
    ↓
businessaccount_application_index.xml
    ↓
businessaccount_application_listing.xml
    ↓
Data provider
    ↓
Grid collection
    ↓
brewcraft_business_account table
```

When the Admin clicks **View**:

```text
Actions.php generates View URL
    ↓
businessaccount/application/view/entity_id/5
    ↓
Controller/Adminhtml/Application/View.php
    ↓
Repository loads application 5
    ↓
Application placed in registry
    ↓
businessaccount_application_view.xml
    ↓
Block/Adminhtml/Application/View.php
    ↓
view.phtml
```

When the Admin approves:

```text
Approve form POST
    ↓
Approve controller
    ↓
Form-key validation
    ↓
BusinessAccountApprovalService::approve()
    ↓
Customer group updated
    ↓
Application updated
    ↓
Approval email triggered
```

---

## 4. Admin route

We created:

```text
etc/adminhtml/routes.xml
```

Conceptually, it contains:

```xml
<router id="admin">
    <route id="businessaccount" frontName="businessaccount">
        <module name="BrewCraft_BusinessAccount"/>
    </route>
</router>
```

### What this means

```text
router id="admin"
```

tells Magento this is an Admin route.

```text
frontName="businessaccount"
```

means Admin URLs for this module begin with the `businessaccount` route segment.

Magento’s router resolves URLs in the general pattern:

```text
frontName / controller / action
```

The location `etc/adminhtml/routes.xml` makes the route available only in the Admin area. ([Adobe Developer][1])

For example:

```text
businessaccount/application/index
```

maps to:

```text
Controller/Adminhtml/Application/Index.php
```

Breakdown:

```text
businessaccount
→ frontName

application
→ controller folder

index
→ action/controller class
```

---

## 5. Admin menu

We created:

```text
etc/adminhtml/menu.xml
```

It added:

```text
BrewCraft
└── Business Applications
```

A simplified version looks like:

```xml
<menu>
    <add
        id="BrewCraft_BusinessAccount::root"
        title="BrewCraft"
        module="BrewCraft_BusinessAccount"
        sortOrder="80"
        resource="BrewCraft_BusinessAccount::root"/>

    <add
        id="BrewCraft_BusinessAccount::applications"
        title="Business Applications"
        module="BrewCraft_BusinessAccount"
        parent="BrewCraft_BusinessAccount::root"
        action="businessaccount/application/index"
        sortOrder="10"
        resource="BrewCraft_BusinessAccount::applications"/>
</menu>
```

### Important attributes

#### `id`

Unique identifier for the menu item.

```xml
id="BrewCraft_BusinessAccount::applications"
```

#### `title`

Text shown in Admin.

```xml
title="Business Applications"
```

#### `parent`

Places the menu below another menu item.

```xml
parent="BrewCraft_BusinessAccount::root"
```

#### `action`

URL that opens when clicked.

```xml
action="businessaccount/application/index"
```

#### `resource`

ACL permission required to see and access the menu.

```xml
resource="BrewCraft_BusinessAccount::applications"
```

Magento menu items are connected to routes using the `action` attribute and to permissions using the `resource` attribute. ([Adobe Developer][2])

---

## 6. ACL permissions

We created:

```text
etc/acl.xml
```

ACL means:

```text
Access Control List
```

It defines what different Admin roles are allowed to do.

Our resource structure was conceptually similar to:

```text
BrewCraft
└── Business Applications
    ├── View
    ├── Approve
    └── Reject
```

Example structure:

```xml
<resource id="Magento_Backend::admin">
    <resource
        id="BrewCraft_BusinessAccount::root"
        title="BrewCraft">

        <resource
            id="BrewCraft_BusinessAccount::applications"
            title="Business Applications">

            <resource
                id="BrewCraft_BusinessAccount::view"
                title="View Applications"/>

            <resource
                id="BrewCraft_BusinessAccount::approve"
                title="Approve Applications"/>

            <resource
                id="BrewCraft_BusinessAccount::reject"
                title="Reject Applications"/>
        </resource>
    </resource>
</resource>
```

ACL rules allow Magento to restrict menus, controllers, UI Components and other Admin resources based on the Admin user’s assigned role. ([Adobe Developer][3])

### Real-world purpose

You could configure:

```text
Customer support role
→ View applications only

Business manager role
→ View, approve and reject

Catalog manager
→ No access
```

---

## 7. `ADMIN_RESOURCE` in controllers

Each Admin controller contains a constant like:

```php
public const ADMIN_RESOURCE =
    'BrewCraft_BusinessAccount::applications';
```

or a more specific resource:

```php
public const ADMIN_RESOURCE =
    'BrewCraft_BusinessAccount::approve';
```

Magento checks this before executing the controller.

For example:

```php
class Approve extends Action
{
    public const ADMIN_RESOURCE =
        'BrewCraft_BusinessAccount::approve';
}
```

This means:

```text
Admin requests Approve controller
        ↓
Magento checks Admin role
        ↓
Does role have BrewCraft_BusinessAccount::approve?
    ├── Yes → execute controller
    └── No  → access denied
```

Admin controllers should live under `Controller\Adminhtml` and use `ADMIN_RESOURCE` to require a specific ACL permission. ([Adobe Developer][3])

---

## 8. Admin grid Index controller

We created:

```text
Controller/Adminhtml/Application/Index.php
```

Its responsibility is only to create the grid page.

Conceptually:

```php
class Index extends Action
{
    public const ADMIN_RESOURCE =
        'BrewCraft_BusinessAccount::applications';

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();

        $resultPage->setActiveMenu(
            'BrewCraft_BusinessAccount::applications'
        );

        $resultPage->getConfig()->getTitle()->prepend(
            __('Business Applications')
        );

        return $resultPage;
    }
}
```

## What the controller does

```text
Creates Admin page result
Sets active menu
Sets page title
Returns page
```

## What it does not do

It does not manually run:

```php
SELECT * FROM brewcraft_business_account
```

It also does not build HTML rows.

The UI Component grid handles the data.

This is an important separation:

```text
Controller
→ Creates page

UI Component
→ Defines grid

Data provider/collection
→ Loads rows
```

---

## 9. Grid layout XML

We created:

```text
view/adminhtml/layout/businessaccount_application_index.xml
```

Its main responsibility is to insert the UI Component into the Admin content area.

Example:

```xml
<page ...>
    <body>
        <referenceContainer name="content">
            <uiComponent
                name="businessaccount_application_listing"/>
        </referenceContainer>
    </body>
</page>
```

## Why the filename has this name

The requested action is:

```text
businessaccount/application/index
```

Magento creates the layout handle:

```text
route_controller_action
```

Therefore:

```text
businessaccount_application_index.xml
```

The layout XML loads the UI Component by name:

```xml
<uiComponent name="businessaccount_application_listing"/>
```

Magento then looks for:

```text
view/adminhtml/ui_component/
businessaccount_application_listing.xml
```

Adobe’s UI Component documentation follows the same pattern: Admin layout XML includes a named UI Component, while its detailed grid configuration lives in `view/adminhtml/ui_component`. ([Adobe Developer][4])

---

## 10. What is a UI Component?

A Magento UI Component is a configurable UI system used for elements such as:

```text
Admin grids
Admin forms
Columns
Filters
Pagination
Bookmarks
Mass actions
Buttons
```

For grids, the main component is called a:

```text
Listing
```

A Listing component can provide filtering, sorting, pagination, column control and other grid behavior. ([Adobe Developer][5])

Our file:

```text
businessaccount_application_listing.xml
```

describes:

```text
Where the data comes from
Which database ID is primary
Which columns to show
Which filters to use
Which toolbar items to show
How row actions work
```

---

## 11. UI Component listing file

We created:

```text
view/adminhtml/ui_component/
businessaccount_application_listing.xml
```

Its high-level structure is:

```xml
<listing>
    <argument name="data">
        ...
    </argument>

    <settings>
        ...
    </settings>

    <dataSource>
        ...
    </dataSource>

    <listingToolbar>
        ...
    </listingToolbar>

    <columns>
        ...
    </columns>
</listing>
```

Think of it as:

```text
Listing
├── Data source
├── Toolbar
└── Columns
```

---

## 12. Listing provider configuration

At the top of the listing, we connected the Listing to the data source:

```xml
<argument name="data" xsi:type="array">
    <item name="js_config" xsi:type="array">
        <item name="provider" xsi:type="string">
            businessaccount_application_listing
            .businessaccount_application_listing_data_source
        </item>
    </item>
</argument>
```

The provider name has two parts:

```text
listing component name
.
data source name
```

So:

```text
businessaccount_application_listing
.
businessaccount_application_listing_data_source
```

The Listing needs this provider reference so its JavaScript components know where to obtain grid data. Adobe’s data-source documentation describes this provider relationship between a component and its data provider. ([Adobe Developer][6])

---

## 13. Spinner and dependencies

The listing configuration may contain:

```xml
<settings>
    <spinner>businessaccount_application_columns</spinner>

    <deps>
        <dep>
            businessaccount_application_listing
            .businessaccount_application_listing_data_source
        </dep>
    </deps>
</settings>
```

### `spinner`

Tells Magento which component should display a loading indicator while data loads.

```text
Grid loading
→ Spinner visible

Grid loaded
→ Spinner hidden
```

## `deps`

Defines that the listing depends on the data source.

Magento waits for that component before fully initializing the grid.

---

## 14. Data source

The grid contains:

```xml
<dataSource
    name="businessaccount_application_listing_data_source"
    component="Magento_Ui/js/grid/provider">
```

The DataSource is the bridge between:

```text
PHP/database data
```

and:

```text
JavaScript grid
```

The GridDataProvider supplies data in the format needed by the Listing component. ([Adobe Developer][7])

A common configuration looks like:

```xml
<dataSource
    name="businessaccount_application_listing_data_source"
    component="Magento_Ui/js/grid/provider">

    <settings>
        <storageConfig>
            <param
                name="indexField"
                xsi:type="string">
                entity_id
            </param>
        </storageConfig>

        <updateUrl path="mui/index/render"/>
    </settings>

    <aclResource>
        BrewCraft_BusinessAccount::applications
    </aclResource>

    <dataProvider
        class="Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider"
        name="businessaccount_application_listing_data_source">

        <settings>
            <requestFieldName>entity_id</requestFieldName>
            <primaryFieldName>entity_id</primaryFieldName>
        </settings>

    </dataProvider>
</dataSource>
```

---

## 15. `indexField`, `primaryFieldName` and `requestFieldName`

These names are easy to confuse.

## `indexField`

```xml
<param name="indexField">
    entity_id
</param>
```

Used by the grid’s browser-side storage to uniquely identify each row.

```text
Row 1 → entity_id 1
Row 2 → entity_id 2
```

## `primaryFieldName`

```xml
<primaryFieldName>
    entity_id
</primaryFieldName>
```

The main database/entity field used by the data provider.

## `requestFieldName`

```xml
<requestFieldName>
    entity_id
</requestFieldName>
```

Name used when the grid or actions pass the ID through a request.

For our custom table, the primary key is:

```text
entity_id
```

Adobe’s grid examples configure the data source with `indexField`, `primaryFieldName`, and `requestFieldName` for precisely these roles. ([Adobe Developer][5])

---

## 16. `updateUrl`

The data source contains:

```xml
<updateUrl path="mui/index/render"/>
```

This is used when the grid reloads through AJAX.

For example:

```text
Admin applies a filter
        ↓
Browser calls mui/index/render
        ↓
Data provider applies filter
        ↓
JSON rows returned
        ↓
Grid refreshes without full page reload
```

This is why filtering and paging feel dynamic.

---

## 17. Grid collection registration in `di.xml`

We needed to tell Magento which collection belongs to:

```text
businessaccount_application_listing_data_source
```

We registered that mapping in:

```text
etc/di.xml
```

The working configuration was conceptually:

```xml
<virtualType
    name="BrewCraft\BusinessAccount\Model\ResourceModel\BusinessAccount\Grid\Collection"
    type="Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult">

    <arguments>
        <argument name="mainTable" xsi:type="string">
            brewcraft_business_account
        </argument>

        <argument name="resourceModel" xsi:type="string">
            BrewCraft\BusinessAccount\Model\ResourceModel\BusinessAccount
        </argument>
    </arguments>
</virtualType>

<type name="Magento\Framework\View\Element\UiComponent\DataProvider\CollectionFactory">
    <arguments>
        <argument name="collections" xsi:type="array">

            <item
                name="businessaccount_application_listing_data_source"
                xsi:type="string">
                BrewCraft\BusinessAccount\Model\ResourceModel\BusinessAccount\Grid\Collection
            </item>

        </argument>
    </arguments>
</type>
```

This is one of the most important grid connections.

---

## 18. Virtual type

We created a virtual type named:

```text
BrewCraft\BusinessAccount\Model\ResourceModel\
BusinessAccount\Grid\Collection
```

But we did not necessarily create a physical PHP file with that exact path.

A Magento virtual type means:

```text
Create a configured variation of an existing class
```

The existing base class is:

```php
Magento\Framework\View\Element\UiComponent\
DataProvider\SearchResult
```

We configure it with:

```text
mainTable = brewcraft_business_account

resourceModel =
BrewCraft\BusinessAccount\Model\ResourceModel\BusinessAccount
```

The resulting object behaves like a grid collection for our table.

---

## 19. CollectionFactory mapping

The next DI configuration registers:

```text
businessaccount_application_listing_data_source
```

inside:

```php
Magento\Framework\View\Element\UiComponent\
DataProvider\CollectionFactory
```

Conceptually, the internal array becomes:

```php
[
    'businessaccount_application_listing_data_source'
        => BrewCraftGridCollection::class,

    'customer_listing_data_source'
        => MagentoCustomerGridCollection::class,

    'sales_order_grid_data_source'
        => MagentoSalesOrderGridCollection::class
]
```

When the UI Component asks:

```text
Give me the collection for
businessaccount_application_listing_data_source
```

CollectionFactory returns our configured SearchResult.

---

## 20. The DI mistake we found

Initially, we put this configuration in:

```text
etc/adminhtml/di.xml
```

That caused Magento’s Admin-area DI merge to lose the core mapping:

```text
customer_listing_data_source
```

The error was:

```text
Not registered handle customer_listing_data_source
```

Our grid worked, but:

```text
Admin → Customers → All Customers
```

failed.

### Why it happened

Magento Customer registered its mapping globally in:

```text
vendor/magento/module-customer/etc/di.xml
```

Our Admin-area DI configuration redefined the same constructor array:

```text
CollectionFactory::$collections
```

The result was effectively:

```php
[
    'businessaccount_application_listing_data_source'
        => BrewCraftGridCollection::class
]
```

instead of a merged array containing all grid data sources.

### Fix

We moved the mapping into:

```text
etc/di.xml
```

at the same global configuration level.

Then both mappings were retained:

```text
customer_listing_data_source
businessaccount_application_listing_data_source
```

This was a very useful real Magento debugging issue.

---

## 21. Grid toolbar

Our listing includes a toolbar similar to:

```xml
<listingToolbar name="listing_top">
    <bookmark name="bookmarks"/>
    <columnsControls name="columns_controls"/>
    <filters name="listing_filters"/>
    <paging name="listing_paging"/>
</listingToolbar>
```

The toolbar is a container for grid controls such as:

```text
Bookmarks
Column controls
Filters
Search
Mass actions
Paging
```

Adobe documents `ListingToolbar` as the component that groups these tools above the table. ([Adobe Developer][8])

### Bookmarks

Stores an Admin user’s grid view configuration.

For example:

```text
Selected columns
Filters
Column positions
Saved views
```

### Columns controls

Lets the Admin show or hide optional columns.

### Filters

Allows filtering by:

```text
Status
Company name
Email
Created date
```

### Paging

Controls:

```text
Page number
Rows per page
Total records
```

---

## 22. Grid columns

The grid contains a `<columns>` component.

Example:

```xml
<columns name="businessaccount_application_columns">
    <column name="entity_id"/>
    <column name="company_name"/>
    <column name="registration_number"/>
    <column name="contact_name"/>
    <column name="contact_email"/>
    <column name="status"/>
    <column name="created_at"/>
    <actionsColumn name="actions"/>
</columns>
```

The Columns component renders the table structure and displays the Listing records in table columns. ([Adobe Developer][9])

---

## 23. Standard text columns

A basic text column looks like:

```xml
<column name="company_name">
    <settings>
        <filter>text</filter>
        <label translate="true">
            Company Name
        </label>
    </settings>
</column>
```

### `name`

Must match the key returned by the collection:

```text
company_name
```

### `filter`

Defines the filter control.

```xml
<filter>text</filter>
```

### `label`

Header text shown in the grid.

---

## 24. Date columns

A date column may look like:

```xml
<column
    name="created_at"
    class="Magento\Ui\Component\Listing\Columns\Date">

    <settings>
        <filter>dateRange</filter>
        <dataType>date</dataType>
        <label translate="true">
            Submitted At
        </label>
    </settings>
</column>
```

This tells Magento to:

```text
Display date value
Provide date-range filter
Format it using Admin locale settings
```

---

## 25. Status column and option source

We created:

```text
Model/Source/Status.php
```

The class provides options similar to:

```php
public function toOptionArray(): array
{
    return [
        [
            'value' => BusinessAccount::STATUS_PENDING,
            'label' => __('Pending')
        ],
        [
            'value' => BusinessAccount::STATUS_APPROVED,
            'label' => __('Approved')
        ],
        [
            'value' => BusinessAccount::STATUS_REJECTED,
            'label' => __('Rejected')
        ]
    ];
}
```

The UI Component status column uses it:

```xml
<column
    name="status"
    component="Magento_Ui/js/grid/columns/select">

    <settings>
        <options class="BrewCraft\BusinessAccount\Model\Source\Status"/>
        <filter>select</filter>
        <dataType>select</dataType>
        <label translate="true">
            Status
        </label>
    </settings>
</column>
```

This converts raw database values:

```text
pending
approved
rejected
```

into readable labels and creates a dropdown filter.

---

## 26. Why use constants for statuses?

Our model contains constants:

```php
public const STATUS_PENDING = 'pending';
public const STATUS_APPROVED = 'approved';
public const STATUS_REJECTED = 'rejected';
```

Instead of repeating:

```php
'pending'
'approved'
'rejected'
```

throughout the code.

Benefits:

```text
Avoid spelling mistakes
Keep one source of truth
Make refactoring easier
Reuse in services, source models and templates
```

---

## 27. Actions column

We created:

```text
Ui/Component/Listing/Column/Actions.php
```

This class adds the **View** link to each grid row.

Conceptually:

```php
public function prepareDataSource(array $dataSource): array
{
    if (isset($dataSource['data']['items'])) {
        foreach ($dataSource['data']['items'] as &$item) {
            if (isset($item['entity_id'])) {
                $item[$this->getData('name')]['view'] = [
                    'href' => $this->urlBuilder->getUrl(
                        'businessaccount/application/view',
                        ['entity_id' => $item['entity_id']]
                    ),
                    'label' => __('View')
                ];
            }
        }
    }

    return $dataSource;
}
```

### Input data

Before the action class:

```php
[
    'entity_id' => 5,
    'company_name' => 'ABC Traders',
    'status' => 'pending'
]
```

### Output data

After adding the action:

```php
[
    'entity_id' => 5,
    'company_name' => 'ABC Traders',
    'status' => 'pending',
    'actions' => [
        'view' => [
            'href' => '.../entity_id/5',
            'label' => 'View'
        ]
    ]
]
```

The grid then renders:

```text
View
```

in the Actions column.

---

## 28. Why the actions column needs PHP

Normal columns only display values already present in the collection.

The View action requires a generated Admin URL containing:

```text
entity_id
Admin front name
Secret key
Route
```

Magento’s URL builder handles those details safely.

We should not manually concatenate Admin URLs.

---

## 29. Admin View controller

We created:

```text
Controller/Adminhtml/Application/View.php
```

Its job is to:

```text
Read entity_id
Load application
Handle missing application
Register current application
Create page
Set title
Return page
```

Conceptually:

```php
$entityId = (int)$this->getRequest()->getParam('entity_id');

try {
    $businessAccount =
        $this->businessAccountRepository->getById($entityId);
} catch (NoSuchEntityException) {
    $this->messageManager->addErrorMessage(
        __('The business application no longer exists.')
    );

    return $resultRedirect->setPath(
        'businessaccount/application/index'
    );
}

$this->registry->register(
    'current_brewcraft_business_application',
    $businessAccount
);
```

---

## 30. Why load through the repository?

Instead of:

```php
$model->load($entityId);
```

we used:

```php
$businessAccountRepository->getById($entityId);
```

The repository:

```text
Centralizes loading logic
Throws consistent exceptions
Keeps controller independent from ResourceModel details
Matches the service/repository architecture
```

---

## 31. Magento Registry in the View page

The View controller registers:

```text
current_brewcraft_business_application
```

The Admin block reads the same key.

Flow:

```text
Controller loads application
        ↓
Controller registers object
        ↓
Layout creates block
        ↓
Block retrieves object from registry
        ↓
Template displays object
```

Registry is request-level storage.

It does not save the application to the database.

It only shares the loaded object during that page request.

---

## 32. View layout XML

We created:

```text
view/adminhtml/layout/
businessaccount_application_view.xml
```

It adds our block and template:

```xml
<referenceContainer name="content">
    <block
        class="BrewCraft\BusinessAccount\Block\Adminhtml\Application\View"
        name="brewcraft.business.application.view"
        template="BrewCraft_BusinessAccount::application/view.phtml"/>
</referenceContainer>
```

This means:

```text
Block class
→ prepares application/customer/URLs

PHTML
→ renders the Admin HTML
```

---

## 33. Admin View block

We created:

```text
Block/Adminhtml/Application/View.php
```

Its responsibility is to prepare values needed by the template.

It provides methods such as:

```text
getBusinessAccount()
getCustomer()
getCustomerEditUrl()
getApproveUrl()
getRejectUrl()
getStatusLabel()
getStatusCssClass()
formatDate()
displayNullableValue()
```

The block should not perform approval or rejection.

It only prepares display data.

---

## 34. Loading the linked customer

The business application stores:

```text
customer_id
```

The block uses:

```php
CustomerRepositoryInterface
```

to load the Magento customer.

Conceptually:

```php
$customerId = (int)$businessAccount->getCustomerId();

$customer =
    $this->customerRepository->getById($customerId);
```

This allows the Admin details page to show:

```text
Customer ID
Customer name
Customer email
Customer group
Link to customer edit page
```

---

## 35. Admin details template

We created:

```text
view/adminhtml/templates/application/view.phtml
```

It renders sections such as:

```text
Application Summary
Company Information
Primary Contact
Business Address
Magento Customer
Admin Review
```

The template uses Magento Admin CSS classes such as:

```text
admin__page-section
admin__page-section-title
admin__table-secondary
admin__field
admin__control-textarea
```

The template should mainly contain:

```text
HTML
Escaped values
Conditional display
Forms
```

The actual business rules remain in services.

---

## 36. Why Approve and Reject are forms

We used:

```html
<form method="post">
```

instead of links such as:

```html
<a href="/approve/entity_id/5">
```

Approve and Reject change application state.

State-changing operations should use POST, not GET.

The forms include:

```php
<?= $block->getBlockHtml('formkey') ?>
```

or the equivalent Magento form key output.

This protects the action against CSRF.

---

## 37. Pending-only review controls

The template shows Approve and Reject only if:

```php
$businessAccount->isPending()
```

For approved or rejected applications, it displays a message such as:

```text
This application has already been reviewed.
```

This is UI-level protection.

But we also validate pending status in the service.

That is important because frontend/UI checks can be bypassed by manually submitting a POST request.

Protection exists in:

```text
Template
Controller
Service
```

---

## 38. Approve controller

We created:

```text
Controller/Adminhtml/Application/Approve.php
```

Its responsibility is:

```text
Validate form key
Read entity_id
Read Admin comment
Call approval service
Show success/error message
Redirect
```

Conceptually:

```php
if (!$this->formKeyValidator->validate($this->getRequest())) {
    $this->messageManager->addErrorMessage(
        __('Invalid form key.')
    );

    return $resultRedirect->setPath(
        'businessaccount/application/index'
    );
}

$entityId = (int)$this->getRequest()->getParam('entity_id');
$comment = trim(
    (string)$this->getRequest()->getParam('admin_comment')
);

try {
    $this->approvalService->approve(
        $entityId,
        $comment
    );

    $this->messageManager->addSuccessMessage(
        __('The business application has been approved.')
    );
} catch (LocalizedException $exception) {
    $this->messageManager->addErrorMessage(
        $exception->getMessage()
    );
}
```

---

## 39. Why controller does not update the group directly

We could have placed everything inside the controller:

```php
$customer->setGroupId(...);
$application->setStatus(...);
$repository->save(...);
$email->send(...);
```

But that would make the controller responsible for too much.

Instead:

```text
Controller
→ Request and response

Approval service
→ Business rules
```

This makes approval reusable from:

```text
Admin controller
CLI command
API
Queue consumer
Automated workflow
```

---

## 40. Reject controller

We created:

```text
Controller/Adminhtml/Application/Reject.php
```

Its flow is:

```text
Validate POST/form key
Read entity_id
Read rejection reason
Call service
Show result message
Redirect
```

The rejection reason is required.

If empty:

```text
Service throws validation exception
Application remains pending
```

The controller displays that error to the Admin.

---

## 41. Approval service

We created:

```text
Model/Service/BusinessAccountApprovalService.php
```

It contains both:

```php
approve(...)
reject(...)
```

This class implements the actual business rules.

Its dependencies include:

```text
BusinessAccountRepositoryInterface
CustomerRepositoryInterface
Customer group collection factory
DateTime
BusinessAccountNotifier
```

---

## 42. Approval service dependencies

### `BusinessAccountRepositoryInterface`

Used to:

```text
Load application
Save application
```

### `CustomerRepositoryInterface`

Used to:

```text
Load customer
Save updated group
```

### `GroupCollectionFactory`

Used to find:

```text
Business Customer
```

without hard-coding its numeric group ID.

### `DateTime`

Used to set:

```text
approved_at
```

### `BusinessAccountNotifier`

Used to trigger:

```text
Approval email
Rejection email
```

---

## 43. Complete approval logic

The approval method follows this sequence:

```text
1. Load application
2. Confirm status is Pending
3. Confirm customer_id exists
4. Find Business Customer group
5. Load Magento customer
6. Store customer’s original group ID
7. Assign Business Customer group
8. Save customer
9. Set application status = Approved
10. Set approved_at
11. Save Admin comment
12. Save application
13. Trigger approval email
```

---

## 44. Why check `Pending` again in the service?

The template hides the button after review, but someone could still send:

```text
POST /businessaccount/application/approve
```

manually.

The service therefore checks:

```php
if (!$businessAccount->isPending()) {
    throw new LocalizedException(
        __('Only pending applications can be approved.')
    );
}
```

This prevents:

```text
Approving twice
Approving rejected application
Rejecting approved application
Processing stale Admin pages
```

---

## 45. Finding the Business Customer group

We did not use:

```php
$customer->setGroupId(4);
```

because customer-group IDs vary by database.

Instead, we searched:

```text
customer_group_code = Business Customer
```

Conceptually:

```php
$groupCollection = $this->groupCollectionFactory->create();

$groupCollection->addFieldToFilter(
    'customer_group_code',
    'Business Customer'
);

$group = $groupCollection->getFirstItem();
```

Then:

```php
$customer->setGroupId(
    (int)$group->getId()
);
```

This works even if:

```text
Local ID = 4
Staging ID = 7
Production ID = 9
```

---

## 46. Business Customer group data patch

We created:

```text
Setup/Patch/Data/CreateBusinessCustomerGroup.php
```

It ensures:

```text
Business Customer
```

exists after:

```bash
bin/magento setup:upgrade
```

The patch checks whether the group already exists before creating it.

This makes the setup deployable and repeatable.

Without a patch, someone would need to manually create the group in every environment.

---

## 47. Consistency protection during approval

Approval updates two separate entities:

```text
customer_entity
brewcraft_business_account
```

Possible failure:

```text
Customer group save succeeds
        ↓
Application save fails
```

Then the customer would have business privileges, but the application would still appear pending.

To reduce this risk, we stored the original group ID.

Conceptually:

```php
$originalGroupId = (int)$customer->getGroupId();

try {
    $customer->setGroupId($businessGroupId);
    $this->customerRepository->save($customer);

    $businessAccount->setStatus(
        BusinessAccount::STATUS_APPROVED
    );

    $this->businessAccountRepository->save(
        $businessAccount
    );
} catch (\Throwable $exception) {
    $customer->setGroupId($originalGroupId);

    try {
        $this->customerRepository->save($customer);
    } catch (\Throwable) {
        // Log rollback failure.
    }

    throw $exception;
}
```

This is a compensating action.

It is not exactly the same as one database transaction across all service-layer operations, but it improves consistency.

---

## 48. Rejection service flow

Rejection follows:

```text
1. Validate rejection reason
2. Load application
3. Confirm status is Pending
4. Set status = Rejected
5. Set approved_at = null
6. Save rejection reason as Admin comment
7. Save application
8. Trigger rejection email
```

It does not change the customer group.

So:

```text
Rejected applicant
→ remains General customer
→ can still log in
→ can still shop as retail customer
```

---

## 49. Admin comment usage

We used:

```text
admin_comment
```

for both:

```text
Approval note
Rejection reason
```

On approval:

```text
Optional Admin message
```

On rejection:

```text
Required reason
```

The customer-facing My Account page later displays it differently:

```text
Approved
→ Message from Business Team

Rejected
→ Review Feedback / Rejection Reason
```

---

## 50. Email trigger after persistence

The email is sent only after the application changes are saved.

Approval:

```text
Customer group saved
Application saved
        ↓
Approval email
```

Rejection:

```text
Rejected status saved
Reason saved
        ↓
Rejection email
```

This prevents sending a success email before the database operation succeeds.

---

## 51. Why email failure does not undo approval

The notifier catches email exceptions.

Example:

```text
Application approved
Customer group changed
SMTP unavailable
```

We do not want to return the customer to General group only because the email failed.

So:

```text
Approval/rejection
→ Primary business operation

Email
→ Secondary notification
```

Failure is logged but does not reverse the decision.

---

## 52. Grid data lifecycle

Here is the complete grid data flow:

```text
businessaccount_application_index.xml
        ↓
Loads businessaccount_application_listing
        ↓
Listing looks for
businessaccount_application_listing_data_source
        ↓
DataProvider requests collection by data-source name
        ↓
CollectionFactory checks DI collections array
        ↓
Returns BrewCraft Grid Collection virtual type
        ↓
SearchResult queries brewcraft_business_account
        ↓
Rows returned to UI Component
        ↓
Columns render values
        ↓
Toolbar applies filters, sorting and pagination
```

---

## 53. View page lifecycle

```text
Admin clicks View
        ↓
Actions column builds URL with entity_id
        ↓
View controller receives entity_id
        ↓
Repository loads application
        ↓
Controller registers current application
        ↓
View layout loads Block
        ↓
Block retrieves application from registry
        ↓
Block loads linked customer
        ↓
PHTML displays application and review controls
```

---

## 54. Approval lifecycle

```text
Admin submits Approve form
        ↓
Approve controller validates form key
        ↓
Controller reads entity_id/comment
        ↓
Approval service loads application
        ↓
Service verifies Pending
        ↓
Finds Business Customer group
        ↓
Loads Magento customer
        ↓
Changes customer group
        ↓
Updates application status/date/comment
        ↓
Saves application
        ↓
Triggers approval email
        ↓
Controller displays success message
        ↓
Redirects to details page
```

---

## 55. Rejection lifecycle

```text
Admin enters reason
        ↓
Submits Reject form
        ↓
Reject controller validates form key
        ↓
Service validates reason
        ↓
Service verifies application is Pending
        ↓
Status becomes Rejected
        ↓
Reason saved in admin_comment
        ↓
Application saved
        ↓
Rejection email triggered
        ↓
Customer remains General
```

---

## 56. Why the grid uses UI Component but details uses PHTML

A grid needs built-in features:

```text
Filters
Sorting
Pagination
Bookmarks
Columns controls
AJAX reload
```

Magento UI Components already provide those.

So grid:

```text
UI Component Listing
```

The details page is mostly custom structured content:

```text
Company table
Contact table
Customer information
Approve/reject forms
```

For that, Block + PHTML was simpler and easier to control.

So:

```text
Grid
→ UI Component

Details page
→ Layout + Block + PHTML
```

---

## 57. What we tested

### Admin menu

```text
BrewCraft menu visible
Business Applications submenu visible
Correct route opens
```

### Grid

```text
Grid loads
Application rows appear
Columns display correctly
Filters work
Pagination works
View link works
```

### Details page

```text
Correct application loaded
Company details visible
Customer information visible
Status visible
Approve/reject controls visible only for Pending
```

### Approval

```text
Pending → Approved
approved_at saved
Admin comment saved
Customer group → Business Customer
Approval email trigger called
```

### Rejection

```text
Pending → Rejected
Rejection reason saved
Customer group unchanged
Rejection email trigger called
```

### Security

```text
ACL controls access
POST used for state changes
Form key validated
Already reviewed application cannot be processed again
```

### Regression

```text
Customers → All Customers works
BrewCraft → Business Applications works
```

---

## 58. Main issue we debugged

The strongest real debugging example from this Admin work is:

```text
Not registered handle customer_listing_data_source
```

### Symptoms

```text
BrewCraft grid worked
Magento Customers grid failed
```

### Investigation

We ran:

```bash
grep -R -n \
'UiComponent\\DataProvider\\CollectionFactory' \
app/code/*/*/etc
```

Then checked Magento Customer’s mapping:

```bash
grep -R -n \
"customer_listing_data_source" \
vendor/magento/module-customer
```

We found:

```text
Magento Customer mapping
→ global etc/di.xml

BrewCraft mapping
→ etc/adminhtml/di.xml
```

### Fix

Moved BrewCraft CollectionFactory mapping into:

```text
etc/di.xml
```

Then cleared:

```text
generated/code
generated/metadata
var/di
var/cache
var/page_cache
```

and compiled again.

Both grids then worked.

---

## 59. Interview-ready explanation

> In the BrewCraft project, I built an Admin management flow for B2B Business Account applications. I created an Admin route, menu and ACL resources, then built a UI Component Listing grid connected to the custom `brewcraft_business_account` table. The grid uses a data source name that is mapped through DI to a SearchResult collection, and the UI Component defines filters, paging, columns and a custom Actions column.
>
> When the Admin clicks View, a controller loads the application through the repository, places it in the Magento registry and renders a custom Block and PHTML details page. The page shows company, contact, address and linked customer information.
>
> Approve and Reject are POST actions protected by form-key validation and ACL. The controllers delegate the business logic to an approval service. Approval validates that the application is pending, finds the Business Customer group dynamically, updates the Magento customer group, saves the approved status, timestamp and Admin comment, and triggers an email. Rejection saves the rejected status and rejection reason without changing the customer group.
>
> I also handled a DI issue where registering the custom grid collection in `etc/adminhtml/di.xml` caused Magento’s core customer grid data-source mapping to disappear. I traced the CollectionFactory configuration and moved the mapping into global `etc/di.xml`, which restored both the custom grid and the core Customers grid.

---

## 60. Important follow-up questions

### What is a Magento UI Component grid?

> A UI Component Listing is Magento’s configurable grid system. It provides columns, filters, sorting, paging, bookmarks and AJAX data reload. The XML connects the Listing to a DataSource and PHP DataProvider. ([Adobe Developer][5])

### What is the data source?

> It is the bridge between the PHP collection and the JavaScript grid. The Listing references the DataSource by name, and the DataSource uses a DataProvider to return rows and total records.

### How does Magento know which collection to use?

> The UI Component’s data-source name is registered in `CollectionFactory::$collections` through DI. That name points to our SearchResult grid collection.

### What is a virtual type?

> It is a configured variation of an existing class created through DI. We used it to configure Magento’s SearchResult with our table and ResourceModel without writing another full collection class.

### Why use `SearchResult`?

> It provides a collection compatible with Magento UI Component grid expectations, including searchable/filterable result behavior and total record information.

### Why use ACL?

> ACL controls which Admin roles can see the menu and execute View, Approve or Reject actions.

### Why use POST for approval?

> Approval changes customer and application data. POST plus form-key validation protects the state-changing request.

### Why keep approval logic in a service?

> It keeps the controller small, centralizes business rules and makes the logic reusable and testable.

### How did you avoid hard-coded customer-group IDs?

> I searched the customer group by `customer_group_code` and used the ID found in that environment.

### What happens if application save fails after group update?

> The service remembers the original customer group and attempts to restore it as a compensating action.

### Why did the core customer grid break?

> The custom CollectionFactory array was registered in Admin-area DI and replaced the previously merged global mappings. Moving it to global `etc/di.xml` allowed both the core and custom data-source mappings to coexist.

[1]: https://developer.adobe.com/commerce/php/development/components/routing?utm_source=chatgpt.com "Routing | Commerce PHP Extensions"
[2]: https://developer.adobe.com/commerce/php/tutorials/admin/create-admin-page?utm_source=chatgpt.com "Create an Admin Page | Commerce PHP Extensions"
[3]: https://developer.adobe.com/commerce/php/development/security/authorization?utm_source=chatgpt.com "Authorization | Commerce PHP Extensions"
[4]: https://developer.adobe.com/commerce/frontend-core/ui-components/?utm_source=chatgpt.com "UI components | Commerce Frontend Development"
[5]: https://developer.adobe.com/commerce/frontend-core/ui-components/components/listing-grid?utm_source=chatgpt.com "Listing (grid) |"
[6]: https://developer.adobe.com/commerce/frontend-core/ui-components/concepts/data-source?utm_source=chatgpt.com "Data sourcing | Commerce Frontend Development"
[7]: https://developer.adobe.com/commerce/frontend-core/ui-components/components/grid-data-provider?utm_source=chatgpt.com "GridDataProvider |"
[8]: https://developer.adobe.com/commerce/frontend-core/ui-components/components/toolbar?utm_source=chatgpt.com "ListingToolbar |"
[9]: https://developer.adobe.com/commerce/frontend-core/ui-components/components/columns?utm_source=chatgpt.com "Columns |"

