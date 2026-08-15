# 1.BrewCraft Supply — Development Log

### Day 1: ERP Integration Foundation

**Date:** 14 July 2026

---

### Objective

The objective of Day 1 was **not** to synchronize products. Instead, the objective was to build the foundation required for any ERP integration.

In real projects, integrations are never built by directly calling APIs. They require configuration, reusable services, logging, and debugging support before any business logic is implemented.

---

### What We Accomplished

#### 1. Created a Dedicated Magento Module

**Module:** `BrewCraft_ErpIntegration`

**Files created:**
- `registration.php`
- `composer.json`
- `etc/module.xml`

**Why?**
Instead of mixing ERP code into an existing module, we isolated all ERP-related functionality into a dedicated module following Magento's modular architecture. This improves maintainability, scalability, code ownership, and future extensibility.

---

#### 2. Planned the ERP Integration Architecture

Instead of immediately importing products, we designed the overall architecture first:

```
Magento
   ↓
Configuration
   ↓
Helper
   ↓
API Client
   ↓
ERP
   ↓
Logger
   ↓
Cron
   ↓
Product Import
```

This allows every future feature to reuse the same components.

---

#### 3. Created Admin Configuration

**Path:** Stores → Configuration → BrewCraft → ERP Integration

**Configuration fields:**
- Enable Integration
- ERP Base URL
- API Version
- Connection Timeout

**Why?**
URLs, API versions, and credentials change between environments. Hardcoding values inside PHP classes is considered poor practice. Configuration enables administrators to modify connection settings without code deployment.

---

#### 4. Implemented ACL

**File created:** `etc/acl.xml`

**Purpose:**
- Restrict access to ERP configuration
- Allow future role-based permissions
- Follow Magento's security model

---

#### 5. Added Default Configuration

**File created:** `etc/config.xml`

**Default values configured:**
- Enabled
- Base URL
- API Version
- Timeout

**Purpose:** Prevent `NULL` configuration values before the administrator saves settings.

---

#### 6. Designed Configuration Helper

**Class:** `Helper\Config`

**Responsibilities:**
- Read configuration values
- Centralize XML paths
- Avoid duplicated `scopeConfig->getValue()` calls

**Methods:**
- `isEnabled()`
- `getBaseUrl()`
- `getApiVersion()`
- `getTimeout()`

**Why?**
Instead of writing `$scopeConfig->getValue(...)` throughout the project, every class simply calls `$config->getBaseUrl()`. This improves readability and reduces maintenance effort.

---

#### 7. Designed API Client

**Class:** `Model\Api\Client`

**Purpose:**
- Build ERP endpoint URLs
- Execute HTTP requests
- Return responses
- Serve as the single communication layer between Magento and the ERP

**Planned future methods:**
- `getProducts()`
- `getInventory()`
- `getPrices()`
- `sendOrders()`
- `updateShipment()`
- `sendCustomer()`

---

#### 8. Designed Custom Logger

**Files created:**
- `Logger/Handler.php`
- `Logger/Logger.php`

**Log file:** `var/log/erp.log`

**Purpose:** Record the following without polluting `system.log`:
- Request URLs
- API responses
- Errors
- Retry attempts
- Synchronization statistics

---

#### 9. Created CLI Command

**Command:**
```bash
php bin/magento brewcraft:erp:test
```

**Purpose:** Allow developers to test ERP connectivity without running cron jobs, greatly simplifying debugging during development.

---

#### 10. Built the Mock ERP

Instead of integrating with a real ERP such as SAP or Microsoft Dynamics, we created a lightweight mock service using **JSON Server**.

**Purpose:** Simulate a real ERP while maintaining full control over the data.

**Resources exposed:**
- `/products`
- `/inventory`
- `/prices`

**API routes:**
- `/api/v1/products`
- `/api/v1/inventory`
- `/api/v1/prices`

---

#### 11. Defined Initial ERP Data Model

**The ERP owns the following business-critical data:**

| Field | Notes |
|---|---|
| SKU | |
| Name | |
| Brand | |
| Manufacturer | |
| Price | |
| Cost Price | |
| Weight | |
| Barcode | |
| Country of Origin | |
| Tax Code | |
| Status | |
| Category | |
| Updated Timestamp | |

**Magento will later enrich these products with:**
- Images and videos
- CMS content
- SEO metadata
- Product relations

This mirrors how many enterprise eCommerce systems divide responsibilities between ERP and the storefront.

---

#### 12. Encountered Integration Issue

**Problem:** Connection refused

**Cause:** Magento runs inside a Docker container, while the Mock ERP runs on the host machine. When Magento calls `http://localhost:3001`, `localhost` refers to the Docker container itself — not the host machine.

**Investigation:**

We verified that the Mock ERP is reachable from inside the container using:

```bash
curl http://host.docker.internal:3001/api/v1/products
```

This successfully returned the expected JSON response, confirming:
- The ERP is functioning correctly
- Network connectivity to the host exists
- The issue lies in how the Magento module constructs the request URL

---

### Lessons Learned

Throughout Day 1 we reinforced the following Magento development principles:

- Separate configuration from business logic
- Isolate integrations into dedicated modules
- Centralize configuration access through helper classes
- Use custom loggers for integration diagnostics
- Validate architecture before implementing business functionality
- Understand Docker networking when integrating external services

---

### Current Status

#### Completed
- [x] Magento ERP module
- [x] Admin configuration
- [x] ACL
- [x] Default configuration
- [x] Configuration helper
- [x] API client
- [x] Custom logger
- [x] CLI command
- [x] Mock ERP
- [x] Mock API endpoints

#### Pending
- [ ] Resolve API client connection issue — update configured URL from `localhost` to `host.docker.internal`
- [ ] Execute the first successful API request from Magento
- [ ] Begin product synchronization using cron  


---
---
  
  
# 2: ERP Integration Foundation & First Product Synchronization

**Date:** 15 July 2026

---

### Objective

The objective for today's development session was to establish the foundation of the ERP integration by connecting Magento with a mock ERP system, implementing a reusable integration architecture, and successfully importing ERP products into the Magento catalog.

---

### What We Built

#### 1. Mock ERP Environment

Instead of integrating with a real ERP (which is usually unavailable during development), we created our own mock ERP using **JSON Server**.

**Purpose:**
- Simulate a real third-party ERP REST API
- Allow Magento development independent of the ERP team
- Enable testing without affecting production systems

**API Endpoints:**
```
GET /api/v1/products
GET /api/v1/inventory
GET /api/v1/prices
```

---

#### 2. ERP Product Data Structure

We designed the ERP payload based on the BrewCraft business requirements.

**The ERP owns the following fields:**

| Field | Notes |
|---|---|
| SKU | |
| Product Name | |
| Price | |
| Cost Price | |
| Weight | |
| Brand | |
| Manufacturer | |
| Barcode | |
| Country of Origin | |
| Category Code | |
| Tax Code | |
| Product Status | |
| Updated Timestamp | |

Marketing content — descriptions, images, videos, etc. — remains managed within Magento.

---

#### 3. Magento ERP Integration Module

Created a new Magento module:

```
BrewCraft_ErpIntegration
```

This module serves as the integration layer between Magento and external ERP systems.

---

#### 4. Configurable ERP Settings

Added ERP configuration under Magento Admin.

**Configuration fields:**
- Enable / Disable Integration
- ERP Base URL
- API Version
- Request Timeout

**Scope:** Website level

**Reason:** Different websites can communicate with different ERP environments while sharing the same Magento installation.

---

#### 5. HTTP Client Layer

**File created:** `Model/Api/Client.php`

**Responsibilities:**
- Build ERP URLs
- Execute HTTP requests
- Handle timeouts
- Return API responses
- Log requests and responses

This centralizes all ERP communication into one reusable component.

---

#### 6. Product Service

**File created:** `Model/Service/ProductService.php`

**Responsibilities:**
- Fetch products from ERP
- Decode JSON
- Validate ERP response
- Verify required fields
- Return structured PHP arrays

**Architectural Improvement:**

Initially this service also saved synchronization job records. During the session it was refactored to follow the **Single Responsibility Principle**. `ProductService` is now responsible only for retrieving and validating ERP data.

---

#### 7. Product Import Service

**File created:** `Model/Service/ProductImportService.php`

**Responsibilities:**
- Check whether SKU already exists
- Create new Magento products when required
- Update existing products
- Map ERP data to Magento product fields
- Save products using `ProductRepository`

**Current mapped fields:**

| Magento Field | Source |
|---|---|
| SKU | ERP |
| Name | ERP |
| Price | ERP |
| Weight | ERP |
| Status | ERP |
| Visibility | Default |
| Website Assignment | Config |
| Product Type | Default |
| Attribute Set | Default |

---

#### 8. Logging

Added structured logging throughout the integration.

**Logs capture:**
- ERP Request URL
- ERP Response
- Number of products received
- Product import progress
- Successful imports
- Import failures

**Log file:** `var/log/brewcraft_erp.log`

This is the primary troubleshooting log for the integration.

---

#### 9. CLI Command

Enhanced the custom CLI command:

```bash
php bin/magento brewcraft:erp:test
```

**Current workflow:**

```
ERP
 ↓
Fetch Products
 ↓
Validate Response
 ↓
Import Products
 ↓
Display Summary
```

This command acts as the developer testing utility before scheduling automated synchronization.

---

#### 10. Cron Integration

Connected the product synchronization service with Magento Cron.

**Current flow:**

```
Magento Cron
 ↓
ProductService
 ↓
ProductImportService
```

The synchronization can now run automatically without manual execution.

---

### Issues Encountered

#### Issue 1 — Docker Networking: Connection Refused

**Problem:** Failed to connect to `localhost`

**Cause:** Magento runs inside Docker, while the mock ERP runs on the host machine. `localhost` inside a Docker container refers to the container itself, not the host.

**Solution:** Replace `localhost` with `host.docker.internal` to allow the Docker container to communicate with the host machine.

```
## Before
http://localhost:3001

## After
http://host.docker.internal:3001
```

---

#### Issue 2 — Area Code Not Set

**Problem:** `Area code is not set`

**Cause:** Magento CLI commands execute without an application area. Saving catalog products requires the Admin application area.

**Solution:** Set the application area explicitly inside the CLI command:

```php
$this->state->setAreaCode(
    \Magento\Framework\App\Area::AREA_ADMINHTML
);
```

---

#### Issue 3 — Cron Jobs Not Appearing in `cron_schedule`

**Problem:** Cron jobs were not appearing in the `cron_schedule` table.

**Cause:** Incorrect cron configuration file.

**Solution:** Corrected the Magento cron configuration and verified successful scheduling and execution.

---

### Final Result

Successfully synchronized ERP products into Magento.

**Imported products:**

| SKU | Product Name |
|---|---|
| ESP001 | Breville Barista Express |
| BEAN001 | BrewCraft Signature Coffee Beans |

Both products are now visible under **Catalog → Products**.

This marks the **first successful end-to-end synchronization** between the mock ERP and Magento.

---

### Architecture Achieved

```
         Mock ERP
            │
  (JSON Server REST API)
            │
            ▼
       Client.php
    (HTTP Communication)
            │
            ▼
    ProductService
  (Fetch + Validate Data)
            │
            ▼
  ProductImportService
 (Create / Update Products)
            │
            ▼
  Magento ProductRepository
            │
            ▼
     Magento Catalog
```

---

### Key Magento Concepts Learned

- Building a reusable integration architecture
- Configurable system settings with Website scope
- Using Magento's HTTP Client (Curl)
- Service layer design and separation of responsibilities
- `ProductRepository` for product persistence
- Product creation through code
- CLI command development
- Cron integration
- Application Areas — Frontend vs Adminhtml vs CLI
- Structured logging for integrations
- Debugging Docker-to-host communication


---
---
   
# 3. BrewCraft Supply — Development Log

### Day 03: Category Sync, Inventory Sync & Inventory Cron

**Date:** 16 July 2026

---

### Objective

Continue building the ERP Integration module by synchronizing additional master data from the ERP into Magento.

**Completed features:**
- Category Synchronization
- Inventory Synchronization
- Inventory Cron
- ERP Integration Architecture Improvements

---

### 1. Category Synchronization

#### Why?

The Business Requirements specify that ERP is the master system for product information. While products reference categories, those categories must already exist inside Magento before products are imported.

**Without category sync:**

```
ERP
 ↓
Coffee Beans
 ↓
Magento
(Category doesn't exist)
 ↓
Product import fails
or
Product assigned incorrectly
```

Therefore categories must always be synchronized **before** products.

---

#### ERP API

```
GET /api/v1/categories
```

**Sample Response:**

```json
[
    { "code": "COFFEE_BEANS", "name": "Coffee Beans" },
    { "code": "COFFEE_MACHINES", "name": "Coffee Machines" }
]
```

---

#### Components Created

**Client — `getCategories()`**
- Calls ERP endpoint
- Returns raw JSON

**`CategoryService`**

Responsibilities:
- Fetch categories from ERP
- Decode JSON
- Validate payload
- Verify mandatory fields (`code`, `name`)
- Throws `RuntimeException` if validation fails

**`CategoryImportService`**

Import logic:

```
For each ERP category
 ↓
Category exists?
 ├── YES → Update Name
 └── NO  → Create Category
```

Magento interfaces used:
- `CategoryRepositoryInterface`
- `CategoryFactory`

---

#### Current Behaviour

Every imported category is created beneath the Magento Root Category:

```
Default Category
 ├── Coffee Beans
 ├── Coffee Machines
 └── Accessories
```

#### Discussion — Parent-Child Hierarchy

We observed that every category becomes a direct child of Root Category.

**Decision:** No hierarchy in Version 1.

**Reason:** The current ERP payload only provides `code` and `name` — no parent information exists.

**Future Enhancement:** ERP may provide a `parent_code` field, enabling automatic Magento category tree generation:

```
Coffee
 ├── Coffee Beans
 ├── Ground Coffee
 └── Capsules
```

---

#### Result

- New categories imported successfully
- Existing categories updated
- No duplicates created

---

### 2. Inventory Synchronization

#### Business Requirement

ERP owns inventory. Magento only displays current stock.

```
ERP (Quantity)
 ↓
Magento (Stock)
```

---

#### ERP Endpoint

```
GET /api/v1/inventory
```

**Sample Response:**

```json
[
    {
        "sku": "ESP001",
        "warehouse": "BLR",
        "qty": 18
    }
]
```

---

#### Initial Issue — Validation Mismatch

**Problem:** Validation expected `sku`, `qty`, and `is_in_stock`. ERP returned only `sku`, `warehouse`, and `qty`.

**Error:** `Missing "is_in_stock"`

**Discussion:** Should ERP decide stock status?

**Decision:** No.

**Reason:** ERP should only send facts. Magento derives simple business rules. This reduces unnecessary API fields.

**Stock status logic:**

```
qty > 0  → IN STOCK
qty = 0  → OUT OF STOCK
```

---

#### `InventoryService`

**Responsibilities:**
- Fetch inventory from ERP
- Validate JSON
- Validate required fields (`sku`, `qty`)
- Record job history (`INVENTORY_SYNC`) inside `erp_job` table

---

#### `InventoryImportService`

**Purpose:** Update Magento inventory using MSI (Multi Source Inventory)

**Magento interfaces used:**
- `GetSourceItemsBySkuInterface`
- `SourceItemsSaveInterface`
- `SourceItemInterfaceFactory`

**Import logic:**

```
ERP Inventory
 ↓
Find SKU
 ↓
Inventory Exists?
 ├── YES → Update Quantity
 └── NO  → Create Source Item
 ↓
Save
```

**Stock status derivation:**

```
qty > 0  → STATUS_IN_STOCK
qty = 0  → STATUS_OUT_OF_STOCK
```

---

#### Warehouse Field

ERP sends a `warehouse` field (e.g. `BLR`). This is currently **ignored** — inventory is stored in the Default Source.

**Future Enhancement — Warehouse Mapping:**

```
ERP Warehouse  →  Magento Source
BLR            →  blr
CHE            →  che
DEL            →  del
```

This will support full Multi Source Inventory across warehouse locations.

---

#### Testing

Changed ERP quantity from `18` to `99`, then executed:

```bash
php bin/magento brewcraft:erp:inventory:test
```

Magento Catalog → Product → Quantity updated successfully.

---

### 3. Inventory CLI Command

**Command:**

```bash
php bin/magento brewcraft:erp:inventory:test
```

**Purpose:** Manual testing without waiting for cron.

**Flow:**

```
ERP
 ↓
InventoryService
 ↓
InventoryImportService
 ↓
Magento
```

**Console output example:**

```
Synchronization Summary
-----------------------
Updated : 2
Failed  : 0
Total   : 2
```

---

### 4. Inventory Cron

**Business Requirement:** Inventory updates every 15 minutes.

**File created:** `Cron/InventorySync.php`

**Registered in:** `etc/crontab.xml`

| Setting | Value |
|---|---|
| Job Name | `brewcraft_inventory_sync` |
| Production Schedule | `*/15 * * * *` |
| Testing Schedule | `* * * * *` |

**Cron flow:**

```
Magento Cron
 ↓
InventorySync
 ↓
InventoryService
 ↓
InventoryImportService
 ↓
Magento Inventory
```

**Verification:**

Checked `cron_schedule` table — observed status moving from `Pending` to `Success`. Ran `bin/magento cron:run` and confirmed inventory updated automatically.

---

### 5. Cron vs `erp_job` Table

#### Why maintain a separate `erp_job` table?

Magento already provides `cron_schedule` for cron execution history, which captures job name, status, schedule time, and finish time. However it does not store business-specific information.

Our module maintains a separate `erp_job` table for ERP integration history:

| Job Type | Status | Records Processed |
|---|---|---|
| PRODUCT_SYNC | SUCCESS | 3 |
| CATEGORY_SYNC | SUCCESS | 5 |
| INVENTORY_SYNC | SUCCESS | 2 |

This provides business-level visibility into synchronization activity beyond what `cron_schedule` offers.

---

### 6. Architecture After Today's Work

```
            Mock ERP
               │
    ┌──────────┼──────────┐
    │          │          │
Products   Categories  Inventory
    │          │          │
    ▼          ▼          ▼
ProductService  CategoryService  InventoryService
    │          │          │
    ▼          ▼          ▼
ProductImport  CategoryImport  InventoryImport
    │          │          │
    ▼          ▼          ▼
       Magento Catalog & Inventory
    │          │          │
    ▼          ▼          ▼
       CLI Commands + Cron Jobs
```

---

### Key Magento Concepts Learned

#### 1. Master Data Synchronization
ERP owns business data. Magento consumes it.

#### 2. Multi Source Inventory (MSI)
Inventory is managed using Magento's MSI APIs rather than directly updating legacy stock tables.

#### 3. Repository Pattern
Magento entities are created and updated using Repository interfaces instead of direct database access.

#### 4. Cron Architecture
Cron automates synchronization according to business schedules defined in the BRD — every 15 minutes for inventory.

#### 5. Separation of Responsibilities

Each layer has a single, clearly defined purpose:

| Layer | Responsibility |
|---|---|
| Client | Communicates with the ERP API |
| Service | Fetches, validates, and prepares ERP data |
| Import Service | Maps ERP data to Magento entities and saves them |
| CLI | Manual execution for testing and debugging |
| Cron | Automated execution based on schedule |

This separation keeps the module maintainable, testable, and easy to extend.


### ERP → Magento Price Synchronization

### Objective

Implemented synchronization of product pricing from the ERP system into Magento. As per the BRD, ERP is the master source for pricing and prices must never be manually maintained in Magento Admin.

---

### Development Completed

#### 1. ERP API Integration

Extended the ERP API client with a new `getPrices()` method.

**Endpoint consumed:**

```
GET /api/v1/prices
```

Retrieved pricing data successfully from the mock ERP.

---

#### 2. Price Validation Service

**Class created:** `PriceService`

**Responsibilities:**
- Fetch price data from ERP
- Decode and validate the JSON response
- Ensure mandatory fields (`sku`, `price`) are present
- Log the synchronization job in the custom `erp_job` table

---

#### 3. Price Import Service

**Class created:** `PriceImportService`

**Responsibilities:**
- Find Magento products by SKU
- Update the following price fields:
  - Regular Price
  - Special Price
  - Special Price Start Date
  - Special Price End Date
- Save the updated product using `ProductRepository`
- Log successful and failed imports

---

#### 4. CLI Command

**Command:**

```bash
php bin/magento brewcraft:erp:price:test
```

**The command:**
- Fetches pricing data from ERP
- Imports prices into Magento
- Displays a synchronization summary showing updated, failed, and total records

---

#### 5. Cron Job

**Job registered:** `brewcraft_price_sync`

**The cron:**
- Executes price synchronization automatically
- Supports the BRD requirement of hourly price synchronization
- Can be configured to run every minute during development for testing

---

### Business Rules Implemented

- ERP is the single source of truth for all product pricing
- Magento updates product prices based on ERP data only
- Manual price maintenance in Magento is not part of the integration flow
- Supports promotional pricing via:
  - Special Price
  - Special Price From Date
  - Special Price To Date

---

### Testing Performed

- [x] Verified ERP `/prices` endpoint returns valid data
- [x] Successfully updated existing Magento product prices
- [x] Verified special pricing and date ranges in Magento Admin
- [x] Tested CLI execution
- [x] Tested cron execution
- [x] Confirmed successful job logging and application logging

---

### Current Project Status

| Feature | Status |
|---|---|
| Product Sync | ✅ Complete |
| Category Sync | ✅ Complete |
| Inventory Sync | ✅ Complete |
| Price Sync | ✅ Complete |
| Order Integration | 🔜 Next Phase |

---

### Next Phase

**Magento → ERP Order Integration** using the Magento Message Queue Framework.

Newly placed orders will be published to a queue and asynchronously sent to the ERP system.


# 4. BrewCraft ERP Integration — Development Log

### Day 04: Order Synchronization via Magento Message Queue Framework

**Date:** 17 July 2026

---

### Objective

Begin implementing the Order Synchronization feature using Magento's Message Queue Framework. The goal was to decouple order export from the checkout process by publishing a queue message when an order is placed and processing it asynchronously.

---

### What We Built

#### 1. Studied Magento Message Queue Architecture

Before writing code, we understood how Magento's Queue Framework works internally.

**Components learned:**
- Topic
- Publisher
- Exchange
- Queue
- Binding
- Consumer

Instead of simply copying XML files, we discussed how a message travels inside Magento.

**Final architecture:**

```
Customer Places Order
        │
        ▼
    Observer
        │
        ▼
    Publisher
        │
        ▼
      Topic
        │
        ▼
Exchange (magento)
        │
        ▼
      Queue
        │
        ▼
    Consumer
        │
        ▼
 ERP Integration
```

---

#### 2. Implemented Queue Configuration

Created the required Magento queue configuration files.

**`communication.xml`**
- Declares the topic
- Defines the message data type

**`queue_publisher.xml`**
- Maps topic to the Magento exchange
- Uses the default database queue connection

```
Topic      → brewcraft.order.export
Exchange   → magento
Connection → db
```

**`queue_topology.xml`**
- Maps topic to queue

```
brewcraft.order.export
        │
        ▼
brewcraft.order.queue
```

**`queue_consumer.xml`**
- Registers the consumer
- Whenever a message reaches `brewcraft.order.queue`, Magento executes `TestConsumer::process()`

---

#### 3. Created Queue Publisher

Implemented a custom `Publisher` class.

**Responsibilities:**
- Receive message
- Log publishing activity
- Publish to Magento topic

**Flow:**

```
Observer
    │
    ▼
Publisher::publish()
    │
    ▼
PublisherInterface
    │
    ▼
Magento Queue
```

**Log entry added:** `Publishing message to queue:`

---

#### 4. Created Queue Consumer

Implemented the consumer.

**Initial responsibility:** Receive message and log it.

**Later enhanced to:**
- Load Magento order
- Display processing information

---

#### 5. Built Queue Test Command

Created a console command to publish a sample message independently of order placement.

```bash
php bin/magento brewcraft:queue:test
```

**Published:** `Hello Queue!`

**Consumer output:** `QUEUE RECEIVED : Hello Queue!`

This validated the complete queue configuration end-to-end.

---

#### 6. Understood Consumer Behaviour

One important observation — running:

```bash
bin/magento queue:consumers:start brewcraft.order.consumer
```

does **not** process messages once and exit. Instead:

```
Consumer Starts
      │
      ▼
    Waits
      │
      ▼
Listens Forever
      │
      ▼
Processes Incoming Messages
      │
      ▼
  Keeps Waiting
```

A consumer behaves like a **background worker/service**, not a one-time command.

---

#### 7. Integrated Queue with Order Placement

The Observer now publishes an order identifier when an order is placed instead of a test message.

**Current flow:**

```
Customer Places Order
        │
        ▼
    Observer
        │
        ▼
    Publisher
        │
        ▼
      Queue
```

---

#### 8. Investigated Magento Order Events

We experimented with different Magento events to determine the most suitable trigger point.

**`sales_order_place_after`**

| Field | Result |
|---|---|
| Increment ID | ✅ Available |
| Entity ID | ❌ NULL |

**`sales_order_save_after`**

| Field | Result |
|---|---|
| Entity ID | ✅ Available |
| Queue processing | ✅ Successful |
| Checkout behaviour | ❌ `No such entity with cartId...` error |

**Conclusion:** Although `sales_order_save_after` worked technically, it interfered with the checkout lifecycle and is not appropriate for initiating ERP export.

---

#### 9. Design Decision — Use Increment ID

Rather than relying on Magento's internal `entity_id`, we redesigned the integration to use the business-facing **Increment ID**.

**Reason:** ERP systems identify orders using business order numbers, not internal database IDs.

| Approach | Value |
|---|---|
| Internal entity ID | `7` |
| Business Increment ID | `000000014` |

**Benefits of Increment ID:**
- Business-friendly and human readable
- Stable across systems
- Matches invoices and customer communication
- Suitable for ERP integration

---

#### 10. Updated Consumer

The consumer was modified to load orders using the increment ID instead of the internal entity ID.

**Processing flow:**

```
Queue Message
      │
      ▼
Increment ID
      │
      ▼
Load Magento Order
      │
      ▼
Process Order
```

---

#### 11. Cache-Related Debugging

During testing, queue messages were being published but initially appeared not to be processed.

**Logs showed:**
```
Publishing message to queue: 000000014
```

After clearing Magento cache and rebuilding generated metadata, the consumer immediately began processing messages correctly.

**Final logs:**
```
Increment ID: 000000014
Publishing message to queue: 000000014
Received message from queue: 000000014
Processing Order 000000014
```

> **Key Magento lesson:** Changes to XML, DI configuration, or queue setup may not be reflected immediately due to cached or generated metadata. Always clear cache after configuration changes.

---

#### 12. Final Working Flow

The complete asynchronous order processing pipeline is now operational.

```
Customer Places Order
        │
        ▼
sales_order_place_after
        │
        ▼
OrderPlacedObserver
        │
        ▼
Publish Increment ID
        │
        ▼
Magento Message Queue
        │
        ▼
      Consumer
        │
        ▼
Load Order by Increment ID
        │
        ▼
  Process Order
```

---

### How the Queue Works — Step by Step

#### Step 1 — CLI Command Triggers Publisher

You execute:

```bash
bin/magento brewcraft:queue:test
```

Magento executes `QueueTest::execute()`, which calls:

```php
$this->publisher->publish('Hello Kruthi!');
```

---

#### Step 2 — Your Publisher Class

```php
public function publish(string $message): void
{
    $this->publisher->publish(
        self::TOPIC,  // "brewcraft.order.export"
        $message
    );
}
```

Your code is now finished. Nothing else from your module is called. **Magento Framework takes over.**

---

#### Step 3 — Magento Receives the Topic

Magento Framework receives:

```
publish("brewcraft.order.export", "Hello Kruthi!")
```

Magento asks: *I received a topic — where should I send it?*

It looks inside `queue_publisher.xml`.

---

#### Step 4 — `queue_publisher.xml` Resolves the Exchange

```xml
<publisher topic="brewcraft.order.export">
    <connection name="db" exchange="magento"/>
</publisher>
```

Magento now knows: topic `brewcraft.order.export` uses exchange `magento`.

---

#### Step 5 — `queue_topology.xml` Resolves the Queue

```xml
<binding
    topic="brewcraft.order.export"
    destination="brewcraft.order.queue"/>
```

Magento stores the message inside `brewcraft.order.queue`:

```
Queue
--------------------
  Hello Kruthi!
--------------------
```

Nobody has processed it yet. It is simply **waiting**.

---

#### Step 6 — Consumer is Started

You run:

```bash
bin/magento queue:consumers:start brewcraft.order.consumer
```

Magento reads `queue_consumer.xml`:

```xml
<consumer
    name="brewcraft.order.consumer"
    queue="brewcraft.order.queue"
    handler="TestConsumer::process"/>
```

Magento knows: consumer `brewcraft.order.consumer` must listen to `brewcraft.order.queue`.

---

#### Step 7 — Consumer Picks Up the Message

Consumer starts. Magento immediately checks `brewcraft.order.queue`.

Message found: `Hello Kruthi!`

---

#### Step 8 — Magento Calls Your Consumer

```php
TestConsumer::process("Hello Kruthi!")
```

> **Important:** You never called `process()`. **Magento did.**

---

### Complete Queue Flow

```
QueueTest Command
        │
        ▼
  Publisher Class
        │
        ▼
PublisherInterface::publish()
        │
        ▼
================================
      MAGENTO FRAMEWORK
================================
        │
        ▼
 queue_publisher.xml
 (Which Exchange?)
        │
        ▼
 queue_topology.xml
 (Which Queue?)
        │
        ▼
  Store Message in Queue
        │
        ▼
================================
        WAITING...
================================
        │
        ▼
queue:consumers:start
        │
        ▼
 queue_consumer.xml
 (Which Consumer?)
        │
        ▼
TestConsumer::process()
        │
        ▼
      Logger
```

---

### Key Magento Concepts Learned

- Magento Message Queue Framework architecture
- Role of each XML configuration file (`communication.xml`, `queue_publisher.xml`, `queue_topology.xml`, `queue_consumer.xml`)
- How a message travels from publisher to consumer without direct PHP calls
- Consumer behaviour as a persistent background worker
- Why `sales_order_place_after` is the correct event for ERP order export
- Using Increment ID as the business-facing order identifier for ERP integration
- Importance of cache clearing after XML and DI configuration changes


# 5. BrewCraft ERP Integration — Development Log

### Feature: Category Hierarchy Synchronization & Product Category Assignment

**Date:** 18-19 July 2026

---

### Objective

Implement a production-ready category synchronization mechanism between the ERP system and Magento.

**Goals:**
- Import categories from ERP
- Preserve the ERP category hierarchy
- Automatically create parent categories before child categories
- Prevent duplicate categories on subsequent synchronizations
- Allow products to be assigned using ERP category codes instead of Magento IDs
- Make the solution scalable for future ERP category changes

---

### Initial Problem

Initially, the category import logic simply created all categories directly under Magento's root category.

**Result:**

```
Default Category
 ├── Coffee Machines
 ├── Coffee Beans
 ├── Espresso Machines
 └── Automatic Machines
```

Every category became a direct child of the root with no parent-child relationship.

**Issues this caused:**
- ERP hierarchy was lost
- Product categories became difficult to manage
- Future nested categories could not be represented
- The import logic could not determine where child categories belonged

---

### Root Cause

The ERP originally returned categories without any relationship information:

```json
[
    { "code": "COFFEE_MACHINES", "name": "Coffee Machines" },
    { "code": "COFFEE_BEANS", "name": "Coffee Beans" }
]
```

This payload contained only `code` and `name` — no parent reference. Magento therefore had no way to determine which category was the parent and which was the child.

---

### ERP Payload Redesign

To support hierarchical categories, the ERP response was redesigned. Each category now includes a `parent_code` reference.

**Updated payload:**

```json
{
    "code": "ESPRESSO",
    "name": "Espresso Machines",
    "parent_code": "COFFEE_MACHINES",
    "status": "ACTIVE"
}
```

**Field definitions:**

| Field | Purpose |
|---|---|
| `code` | Unique ERP identifier |
| `name` | Magento category name |
| `parent_code` | Parent ERP category (`null` for root categories) |
| `status` | `ACTIVE` or `INACTIVE` |

This change allows Magento to reconstruct the complete category tree.

---

### Sample ERP Hierarchy

```
Coffee
 │
 ├── Coffee Machines
 │     ├── Espresso Machines
 │     └── Automatic Machines
 │
 └── Coffee Beans
       └── Arabica Beans
```

---

### New Category Synchronization Design

The synchronization process was redesigned into four separate responsibilities.

#### 1. Client

Responsible only for communicating with the ERP.

**Responsibilities:**
- Build ERP URL
- Call REST endpoint
- Return JSON response

```
GET /api/v1/categories
```

No business logic exists inside the client.

---

#### 2. `CategoryService`

**Responsibilities:**
- Call the ERP client
- Parse JSON
- Validate required fields
- Return a clean PHP array

**Validation performed:**
- `code` exists
- `name` exists
- `parent_code` exists
- `status` exists

No Magento logic is implemented here.

---

#### 3. `CategoryImportService`

Responsible for synchronizing ERP categories into Magento. This class contains all Magento-specific logic.

**Responsibilities:**
- Find existing category
- Create new category if necessary
- Update category information
- Save ERP category code as a Magento attribute
- Maintain parent-child hierarchy

---

#### 4. `CategoryResolver`

A new resolver class was introduced. Instead of searching categories by name, products now locate categories using the ERP category code.

**Flow:**

```
ERP category_code
        │
        ▼
  COFFEE_MACHINES
        │
        ▼
 CategoryResolver
        │
        ▼
Magento Category
        │
        ▼
    ID = 10
```

Searching by ERP code is significantly safer than searching by category name because **names may change over time**.

---

### Two-Pass Import Algorithm

One of the biggest improvements was redesigning the import algorithm. Instead of creating categories in a single loop, the import now executes in **two passes**.

#### Pass 1 — Root Categories

Import only categories where `parent_code = null`.

```
Default Category
 ├── Coffee
 └── Coffee Beans
```

These become direct children of Magento's Default Category.

#### Pass 2 — Child Categories

Import child categories after all parents are guaranteed to exist.

```
Coffee
 └── Coffee Machines (parent = Coffee)
      └── Espresso Machines (parent = Coffee Machines)
```

This guarantees that every parent already exists before its children are processed.

---

### Category Mapping

Every synchronized category now stores an additional Magento attribute: `erp_category_code`.

This attribute becomes the **permanent mapping** between ERP and Magento.

| Magento Category | ERP Code |
|---|---|
| Coffee | `COFFEE` |
| Coffee Machines | `COFFEE_MACHINES` |
| Espresso Machines | `ESPRESSO` |
| Coffee Beans | `COFFEE_BEANS` |
| Arabica Beans | `ARABICA` |

---

### Product Synchronization Changes

**Previously**, products contained a `category_code` but Magento attempted to locate categories by name — fragile and error-prone.

**Now**, the flow is:

```
ERP Product
     │
     ▼
category_code
     │
     ▼
CategoryResolver
     │
     ▼
erp_category_code attribute
     │
     ▼
Magento Category
     │
     ▼
  Category ID
     │
     ▼
Assign to Product
```

This completely removes dependency on category names.

---

### Final Architecture

```
        ERP
         │
         ▼
      Client
  (HTTP only, no logic)
         │
         ▼
  CategoryService
  (Fetch + Validate)
         │
         ▼
CategoryImportService
  (Magento logic)
    ┌────┴────┐
    │         │
  Pass 1    Pass 2
  (Root)   (Children)
    │         │
    └────┬────┘
         │
         ▼
  erp_category_code
  (stored on category)
         │
         ▼
  CategoryResolver
  (used by products)
         │
         ▼
  Product Category
     Assignment
```

## BrewCraft Supply — Project Status - as of 19 July
### Overall Completion

| Module | Completion |
|---|---|
| Project Setup | ✅ 100% |
| ERP Integration | ✅ 95% |
| Storefront & Catalog | ⚠️ 40% |
| B2C Store | ⚠️ 35% |
| B2B Features | ❌ 5% |
| Admin Features | ❌ 15% |
| ERP Simulation | ⚠️ 70% |

---

### Phase 1 — Project Setup ✅ 100%

| Item | Status |
|---|---|
| Magento installation | ✅ |
| Development environment | ✅ |
| Git | ✅ |
| Docker / Reward | ✅ |
| Module structure | ✅ |
| Sample ERP (json-server) | ✅ |

---

### Phase 2 — ERP Integration ✅ 92–95%

#### Completed

**Imports**
| Feature | Status |
|---|---|
| Categories | ✅ |
| Products | ✅ |
| Inventory | ✅ |
| Prices | ✅ |

**Exports**
| Feature | Status |
|---|---|
| Orders | ✅ |

**Async**
| Feature | Status |
|---|---|
| RabbitMQ | ✅ |
| Observer | ✅ |
| Publisher | ✅ |
| Consumer | ✅ |

**Scheduling & Monitoring**
| Feature | Status |
|---|---|
| Cron | ✅ |
| Console Commands | ✅ |
| Sync History | ✅ |

#### Remaining

| Item | Status |
|---|---|
| Retry mechanism | ⏳ |
| Small configuration improvements | ⏳ |

---

### Phase 3 — Storefront & Catalog ⚠️ 40%

#### Completed

| Feature | Status |
|---|---|
| Product import | ✅ |
| Category hierarchy | ✅ |
| Categories visible under Default Category | ✅ |

#### Remaining

| Feature | Status |
|---|---|
| Theme customization | ❌ |
| Homepage | ❌ |
| CMS Pages | ❌ |
| Navigation | ❌ |
| Search configuration | ❌ |
| Layered Navigation | ❌ |
| Product media import | ❌ |

---

### Phase 4 — B2C Store ⚠️ 35%

#### Completed

| Feature | Status |
|---|---|
| Checkout | ✅ |
| Order placement | ✅ |
| ERP export | ✅ |

#### Remaining

| Feature | Status |
|---|---|
| Customer registration customization | ❌ |
| Wishlist | ❌ |
| Reviews | ❌ |
| Reward Points (if required) | ❌ |
| Email customization | ❌ |

---

### Phase 5 — B2B Features ❌ 5%

None of the following have been built yet:

| Feature | Status |
|---|---|
| Business Registration | ❌ |
| Company Approval | ❌ |
| Gold Partner | ❌ |
| Quote Request | ❌ |
| Purchase Order | ❌ |
| Credit Account | ❌ |

---

### Phase 6 — Admin Features ❌ 15%

| Feature | Status |
|---|---|
| ERP Dashboard | ❌ |
| Import History Grid | ❌ |
| Manual Sync Buttons | ❌ |
| Configuration improvements | ❌ |
| Reports | ❌ |

---

### Phase 7 — ERP Simulation ⚠️ 70%

#### Completed

| Feature | Status |
|---|---|
| json-server | ✅ |
| Products | ✅ |
| Categories | ✅ |
| Inventory | ✅ |
| Prices | ✅ |
| Orders | ✅ |

#### Remaining

| Feature | Status |
|---|---|
| Customers | ❌ |
| Quotes | ❌ |
| Shipments | ❌ |
| Invoices | ❌ |



# 6. Development Log — ERP Retry Mechanism and Admin Configuration
**Date:** 20 July 2026

**Project:** BrewCraft Magento 2 ERP Integration
**Module:** `BrewCraft_ErpIntegration`
**Work completed:** Retry mechanism, Admin configuration, configuration-controlled cron and queue behavior

---

### 1. Objective

Before today’s changes, the ERP integration already supported:

* Category import
* Product import
* Inventory import
* Price import
* Order export through Magento Queue
* Sync success and failure history
* Cron jobs
* Console commands

However, when the ERP API was unavailable, order export behaved like this:

```text
Consumer receives order
        ↓
ERP API request fails
        ↓
Order export marked FAILED
```

There was no retry mechanism.

Also, important settings such as retry attempts and retry delay were hard-coded inside PHP:

```php
private const MAX_RETRY_ATTEMPTS = 3;
private const RETRY_DELAY_SECONDS = 2;
```

The goals of today’s development were:

1. Retry failed ERP order exports automatically.
2. Detect unsuccessful ERP HTTP responses correctly.
3. Store final success or failure in the sync-history table.
4. Make retry values configurable from Magento Admin.
5. Add enable/disable controls for integration components.
6. Connect the Admin fields to the observer, service, and cron classes.

---

### 2. Retry Mechanism Architecture

The new order-export flow is:

```text
Customer places order
        ↓
OrderPlacedObserver
        ↓
Publisher sends increment ID
        ↓
Magento queue stores message
        ↓
Consumer receives message
        ↓
OrderExportService builds payload
        ↓
Attempt 1
        ↓
Failure?
        ↓
Wait configured retry delay
        ↓
Attempt 2
        ↓
Failure?
        ↓
Wait configured retry delay
        ↓
Attempt 3
        ↓
SUCCESS or final FAILED history
```

The retry mechanism was implemented inside:

```text
Model/Service/OrderExportService.php
```

This was the correct layer because the service owns the complete order-export business process.

The queue consumer remains responsible only for:

```text
Receive order increment ID
        ↓
Load Magento order
        ↓
Call OrderExportService
```

The consumer does not contain retry logic.

---

### 3. ERP HTTP Error Detection

### Problem

The Magento Curl client may complete an HTTP request even when the ERP returns an error such as:

```text
400 Bad Request
404 Not Found
500 Internal Server Error
503 Service Unavailable
```

Without manually checking the response status, the application could incorrectly treat an ERP `500` response as successful.

### Change in `OrderClient.php`

After sending the order:

```php
$this->curl->post($url, $jsonPayload);
```

we retrieve:

```php
$statusCode = $this->curl->getStatus();
$response = $this->curl->getBody();
```

Then validate the status:

```php
if ($statusCode < 200 || $statusCode >= 300) {
    throw new \RuntimeException(
        sprintf(
            'ERP order export failed with HTTP status %d. Response: %s',
            $statusCode,
            $response
        )
    );
}
```

### Result

Only HTTP responses in the `2xx` range are considered successful.

Examples:

```text
200 → Success
201 → Success
204 → Success

400 → Exception
404 → Exception
500 → Exception
503 → Exception
```

Connection problems also throw exceptions automatically, such as:

```text
Connection refused
Connection timed out
Could not resolve host
```

These exceptions are passed to the retry mechanism.

---

### 4. Retry Method Implementation

A private method was introduced:

```php
private function exportWithRetry(
    Order $order,
    array $payload,
    int $maxAttempts,
    int $retryDelay
): int
```

### Responsibilities

This method:

* Calls the ERP order API.
* Catches temporary request failures.
* Logs every attempt.
* Waits before the next attempt.
* Returns the successful attempt number.
* Throws a final exception after all attempts fail.

### Retry loop

```php
for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
    try {
        $this->client->exportOrder($payload);

        return $attempt;
    } catch (\Throwable $exception) {
        $lastException = $exception;

        if ($attempt < $maxAttempts && $retryDelay > 0) {
            sleep($retryDelay);
        }
    }
}
```

### Why `return $attempt` is used

When an attempt succeeds, the method returns the attempt number.

For example:

```text
Attempt 1 succeeds → returns 1
Attempt 2 succeeds → returns 2
Attempt 3 succeeds → returns 3
```

This value is used in logs and sync history:

```text
Order 000000041 exported successfully after 3 attempt(s).
```

### Final failure

When every attempt fails:

```php
throw new \RuntimeException(
    sprintf(
        'ERP export failed after %d attempt(s). Last error: %s',
        $maxAttempts,
        $lastException?->getMessage() ?? 'Unknown ERP error'
    ),
    0,
    $lastException
);
```

The previous exception is passed as the third argument so the original failure remains available for debugging.

---

### 5. Retry Delay

Between failed attempts, the service uses:

```php
sleep($retryDelay);
```

Example configuration:

```text
Maximum attempts: 3
Retry delay: 2 seconds
```

Execution timing:

```text
Attempt 1 → immediately
Wait 2 seconds
Attempt 2
Wait 2 seconds
Attempt 3
```

A delay is applied only when:

```php
$attempt < $maxAttempts && $retryDelay > 0
```

This prevents unnecessary waiting after the final attempt.

### Current limitation

`sleep()` pauses the active consumer process during the delay.

For this learning project and local ERP simulation, this is acceptable. A high-volume production integration could instead use a delayed retry queue, but that is outside the current project scope.

---

### 6. Success and Failure History

The existing `brewcraft_sync_job` table continues to store the final result.

### Success

When an API attempt succeeds:

```php
$this->saveJob(
    status: 'SUCCESS',
    recordsProcessed: 1,
    executionTime: $executionTime,
    message: $message
);
```

Example:

```text
job_type: ORDER_EXPORT
status: SUCCESS
records_processed: 1
message: Order 000000041 exported successfully after 3 attempt(s).
```

Only one final success row is stored.

We do not create a database record for each failed attempt. Individual attempts are written to the log instead.

### Final failure

When all attempts fail:

```php
$this->saveJob(
    status: 'FAILED',
    recordsProcessed: 0,
    executionTime: $executionTime,
    message: $message
);
```

Example:

```text
job_type: ORDER_EXPORT
status: FAILED
records_processed: 0
message: Order 000000041 export permanently failed after 3 attempts.
```

This keeps the database history focused on the final synchronization outcome.

---

### 7. Why the Final Exception Is Not Rethrown

Initially, the catch block ended with:

```php
throw $exception;
```

That passes the exception back to Magento’s queue consumer.

The queue then considers message processing unsuccessful. This could cause the same message to remain failed or be processed again, depending on queue behavior.

Since our service already performs all configured retries and saves the final failure, we changed the flow to:

```text
All attempts fail
        ↓
Save FAILED history
        ↓
Log critical error
        ↓
Return normally
        ↓
Queue message is acknowledged
```

Therefore, the final catch block does not rethrow.

This prevents repeated processing of the same order message in our current architecture.

### Important consequence

When all immediate attempts fail, the order will not automatically be retried after the ERP returns later.

It remains available in the sync-history table as `FAILED`.

A delayed retry queue could solve that in a larger production implementation, but it is not required for the BrewCraft learning project.

---

### 8. Local Retry Test

The successful test used order:

```text
000000041
```

Observed logs:

```text
Attempt 1/3 → Connection refused
Wait 2 seconds

Attempt 2/3 → Connection refused
Wait 2 seconds

Attempt 3/3 → HTTP 201
```

Final result:

```text
Order 000000041 exported successfully after 3 attempt(s).
```

This verified that:

* The consumer received the queue message.
* Connection failures were detected.
* Retry delay worked.
* The ERP could recover during the retry window.
* A later attempt succeeded.
* Final success history was stored.

---

### 9. Local Queue and ERP Behavior

The Magento consumer and simulated ERP are separate processes.

```text
Magento Queue Consumer
ERP JSON Server
```

### Consumer stopped, ERP stopped

When an order is placed:

```text
Order placed
        ↓
Message stored in queue
        ↓
No processing because consumer is stopped
```

No retry happens yet because the service has not received the message.

### Consumer started while ERP is stopped

```text
Consumer reads pending message
        ↓
Retry mechanism starts
```

Starting the ERP during the retry window allows a later attempt to succeed.

### Consumer already running while ERP is stopped

The message is consumed immediately, so all attempts happen quickly according to the configured delay.

This is expected behavior in the local environment.

---

### 10. Magento Admin Configuration

New Admin settings were added under:

```text
Stores
→ Configuration
→ BrewCraft
→ ERP Integration
```

The configuration is divided into three groups.

### General Settings

```text
Enable ERP Integration
ERP Base URL
API Version
Connection Timeout
```

### Order Export Settings

```text
Enable Order Export
Enable Queue Processing
Maximum Retry Attempts
Retry Delay
```

### Import Settings

```text
Enable Category and Product Sync
Enable Inventory Sync
Enable Price Sync
```

---

### 11. `system.xml` Changes

File:

```text
etc/adminhtml/system.xml
```

The new groups are:

```xml
<group id="general">
```

```xml
<group id="order_export">
```

```xml
<group id="import">
```

Each field ID forms part of its configuration path.

Example:

```xml
<section id="brewcraft_erp">
    <group id="order_export">
        <field id="retry_attempts">
```

This produces:

```text
brewcraft_erp/order_export/retry_attempts
```

### XML validation issue fixed

Initially, the source model was formatted across multiple lines:

```xml
<source_model>
    Magento\Config\Model\Config\Source\Yesno
</source_model>
```

Magento’s schema only accepts characters matching:

```text
[A-Za-z0-9_\\:]+
```

The newline and indentation became part of the XML value, causing validation failure.

It was corrected to:

```xml
<source_model>Magento\Config\Model\Config\Source\Yesno</source_model>
```

This is required for schema-restricted class-name elements such as:

```xml
<source_model>
<backend_model>
<frontend_model>
```

---

### 12. Default Configuration

File:

```text
etc/config.xml
```

Defaults were added:

```xml
<order_export>
    <enabled>1</enabled>
    <queue_enabled>1</queue_enabled>
    <retry_attempts>3</retry_attempts>
    <retry_delay>2</retry_delay>
</order_export>
```

```xml
<import>
    <product_sync_enabled>1</product_sync_enabled>
    <inventory_sync_enabled>1</inventory_sync_enabled>
    <price_sync_enabled>1</price_sync_enabled>
</import>
```

The local ERP base URL was updated to:

```text
http://host.docker.internal:3001
```

This allows Magento inside Docker to reach the JSON server running on the host machine.

### Configuration priority

Magento uses configuration fallback and stored values.

An Admin value saved in `core_config_data` takes priority over the module default in `config.xml`.

Therefore, changing `config.xml` does not overwrite an existing Admin value.

---

### 13. Config Helper Updates

File:

```text
Helper/Config.php
```

New XML path constants were added:

```php
private const XML_PATH_ORDER_EXPORT_ENABLED =
    'brewcraft_erp/order_export/enabled';

private const XML_PATH_QUEUE_ENABLED =
    'brewcraft_erp/order_export/queue_enabled';

private const XML_PATH_RETRY_ATTEMPTS =
    'brewcraft_erp/order_export/retry_attempts';

private const XML_PATH_RETRY_DELAY =
    'brewcraft_erp/order_export/retry_delay';
```

Import controls:

```php
private const XML_PATH_PRODUCT_SYNC_ENABLED =
    'brewcraft_erp/import/product_sync_enabled';

private const XML_PATH_INVENTORY_SYNC_ENABLED =
    'brewcraft_erp/import/inventory_sync_enabled';

private const XML_PATH_PRICE_SYNC_ENABLED =
    'brewcraft_erp/import/price_sync_enabled';
```

New helper methods:

```php
isOrderExportEnabled()
isQueueEnabled()
getRetryAttempts()
getRetryDelay()
isProductSyncEnabled()
isInventorySyncEnabled()
isPriceSyncEnabled()
```

---

### 14. Defensive Configuration Values

The helper prevents invalid retry values.

### Retry attempts

```php
return $attempts > 0 ? $attempts : 1;
```

Even when configuration is missing or incorrectly set to `0`, the system performs at least one request.

### Retry delay

```php
return max(0, $delay);
```

A negative value is converted to zero.

A zero delay is valid and means:

```text
Retry immediately
```

### Timeout

```php
return $timeout > 0 ? $timeout : 30;
```

An invalid timeout falls back to 30 seconds.

---

### 15. Store-Scope Configuration

The configuration helper uses:

```php
ScopeInterface::SCOPE_STORE
```

This supports Magento’s normal fallback:

```text
Store View value
        ↓
Website value
        ↓
Default value
```

For order export, the service reads the store ID from the order:

```php
$storeId = (int)$order->getStoreId();
```

Then retrieves the appropriate configuration:

```php
$maxAttempts = $this->config->getRetryAttempts($storeId);
$retryDelay = $this->config->getRetryDelay($storeId);
```

This means orders from different stores can use different ERP settings.

---

### 16. `OrderExportService` Configuration Integration

The hard-coded constants were removed:

```php
private const MAX_RETRY_ATTEMPTS = 3;
private const RETRY_DELAY_SECONDS = 2;
```

They were replaced with Admin values:

```php
$maxAttempts = $this->config->getRetryAttempts($storeId);
$retryDelay = $this->config->getRetryDelay($storeId);
```

The service also checks:

```php
$this->config->isEnabled($storeId)
```

and:

```php
$this->config->isOrderExportEnabled($storeId)
```

### Behavior when disabled

When the complete integration is disabled:

```text
Order export skipped because ERP integration is disabled.
```

When only order export is disabled:

```text
Order export skipped because order export is disabled.
```

No ERP request is made.

---

### 17. Queue Configuration Integration

File:

```text
Observer/OrderPlacedObserver.php
```

The observer now checks:

```php
$this->config->isEnabled($storeId)
```

```php
$this->config->isOrderExportEnabled($storeId)
```

```php
$this->config->isQueueEnabled($storeId)
```

The order increment ID is published only when all three return `true`.

Flow:

```text
Order placed
        ↓
ERP integration enabled?
        ↓
Order export enabled?
        ↓
Queue processing enabled?
        ↓
Publish increment ID
```

When queue processing is disabled:

```text
Order remains successfully placed in Magento
No queue message is published
No ERP export happens
```

The setting does not switch to synchronous export. The BrewCraft order-export architecture remains queue-based.

---

### 18. Cron Configuration Integration

The following cron classes were updated:

```text
Cron/ProductSync.php
Cron/InventorySync.php
Cron/PriceSync.php
```

Each cron now checks:

1. Is the complete ERP integration enabled?
2. Is this specific scheduled synchronization enabled?

### Product cron

```text
ERP enabled?
        ↓
Product sync enabled?
        ↓
Import categories
        ↓
Import products
```

Categories run before products because product category assignment depends on the Magento categories already existing.

### Inventory cron

```text
ERP enabled?
        ↓
Inventory sync enabled?
        ↓
Fetch and import inventory
```

### Price cron

```text
ERP enabled?
        ↓
Price sync enabled?
        ↓
Fetch and import prices
```

When disabled, the cron writes a skipped message and returns without calling the ERP.

---

### 19. Why Console Commands Were Not Changed

We intentionally decided that the individual import settings control **automatic scheduled synchronization**, not deliberate manual execution.

Current behavior:

| Execution method        | Checks individual sync switch |
| ----------------------- | ----------------------------: |
| Product cron            |                           Yes |
| Inventory cron          |                           Yes |
| Price cron              |                           Yes |
| Manual console commands |                            No |

This design allows:

```text
Scheduled sync disabled
        ↓
Cron does not run the integration
        ↓
Developer can still execute a manual command
```

Manual commands remain useful for:

* Testing
* Debugging
* Emergency synchronization
* Checking ERP responses
* Running synchronization without waiting for cron

To make this intention clearer, the Admin labels can be understood as:

```text
Enable Scheduled Category and Product Sync
Enable Scheduled Inventory Sync
Enable Scheduled Price Sync
```

The configuration paths do not need to change.

---

### 20. Logging Improvements

The retry mechanism now produces clear operational logs.

Example:

```text
Export attempt 1/3 for order 000000041.
Sending order to ERP.
Attempt 1/3 failed: Connection refused.
Waiting 2 seconds before retrying.

Export attempt 2/3 for order 000000041.
Attempt 2/3 failed: Connection refused.
Waiting 2 seconds before retrying.

Export attempt 3/3 for order 000000041.
ERP response status: 201.
Order exported successfully after 3 attempts.
```

Exception objects are not passed directly to the logger where a full stack trace is unnecessary.

Instead of:

```php
$this->logger->critical($exception);
```

we prefer:

```php
$this->logger->critical(
    sprintf(
        'Order export failed: %s',
        $exception->getMessage()
    )
);
```

This keeps integration logs easier to read.

---

### 21. Commands Used After Configuration Changes

After modifying XML and configuration:

```bash
bin/magento setup:upgrade
bin/magento cache:clean config
bin/magento cache:flush
```

When constructor dependencies changed:

```bash
rm -rf generated/code/*
rm -rf generated/metadata/*
bin/magento setup:di:compile
```

The queue consumer also had to be restarted because it is a long-running PHP process:

```bash
bin/magento queue:consumers:start brewcraft.order.consumer
```

Without restarting, the active consumer might continue using old code or cached configuration.

---

### 22. Final Result

Before today:

```text
Order export fails
        ↓
One API attempt
        ↓
FAILED history
```

After today:

```text
Order export starts
        ↓
Read Admin retry configuration
        ↓
Attempt ERP export
        ↓
Retry temporary failures
        ↓
Store final SUCCESS or FAILED result
```

The integration is now configurable through Magento Admin:

```text
Global ERP enable/disable
Order export enable/disable
Queue enable/disable
Retry attempts
Retry delay
Scheduled product sync
Scheduled inventory sync
Scheduled price sync
```

The completed functionality includes:

```text
✅ Configurable retry mechanism
✅ HTTP failure detection
✅ Connection-error handling
✅ Retry logging
✅ Success and failure history
✅ Admin configuration
✅ Store-scope configuration
✅ Queue publishing controls
✅ Cron execution controls
✅ Manual console commands preserved
```

### Development Status

The retry mechanism and configuration-improvement phase is now complete.

The ERP integration has moved from:

```text
Functional integration
```

to:

```text
Configurable and failure-aware integration
```

The main remaining ERP work is code cleanup and final module documentation.





# 7. Development Log 1: Category Import Enhancement
**DATE:** 11th AUG 
### Objective

Improve the BrewCraft ERP category import so that:

* ERP categories can be imported into Magento correctly
* Top-level ERP categories are attached to the Magento storefront root category
* Multi-level category hierarchies are handled safely
* Category import does not depend on ERP JSON ordering
* Parent-category errors are detected clearly
* Existing categories can be moved if ERP hierarchy changes

---

### Initial Category Import Flow

The ERP provided category data in this structure:

```json
{
  "code": "COFFEE",
  "name": "Coffee",
  "parent_code": null,
  "status": "ACTIVE"
}
```

Child categories looked like:

```json
{
  "code": "COFFEE_BEANS",
  "name": "Coffee Beans",
  "parent_code": "COFFEE",
  "status": "ACTIVE"
}
```

And deeper levels:

```json
{
  "code": "ARABICA",
  "name": "Arabica Beans",
  "parent_code": "COFFEE_BEANS",
  "status": "ACTIVE"
}
```

The intended Magento hierarchy was:

```text
Default Category
└── Coffee
    └── Coffee Beans
        └── Arabica Beans
```

---

### Existing Implementation

The importer already had several useful pieces:

```php
$category = $this->categoryResolver
    ->getByErpCode($erpCategory['code']);
```

This checked whether a category already existed using the custom ERP code.

If it did not exist:

```php
$category = $this->categoryResolver->create();

$category->setStoreId(0);
$category->setParentId($parentId);
```

ERP values were mapped into Magento:

```php
$category->setName(
    $erpCategory['name']
);

$category->setData(
    'erp_category_code',
    $erpCategory['code']
);

$category->setIsActive(
    $erpCategory['status'] === 'ACTIVE'
);
```

The category was then saved through Magento's repository:

```php
$this->categoryRepository->save($category);
```

---

## Problem Encountered

After replacing the small test ERP dataset with the larger final BrewCraft category hierarchy, category sync failed.

Log:

```text
Fetched 31 categories from ERP.

Magento\Framework\Exception\NoSuchEntityException:
No such entity with id = 0
```

The exception came from:

```text
Magento\Catalog\Model\CategoryRepository
```

during:

```php
$this->categoryRepository->save($category);
```

---

## Root Cause

The existing method used:

```php
$this->storeManager
    ->getStore()
    ->getRootCategoryId();
```

for ERP categories with:

```json
"parent_code": null
```

That looks reasonable during a normal storefront request.

However, the category synchronization was running through:

```text
Cron / CLI
```

In that execution context Magento can use:

```text
Admin store
store_id = 0
```

The Admin store is not a storefront store view with a normal catalog root category.

Therefore the resolved root category ID became:

```text
0
```

Then Magento effectively received:

```php
$category->setParentId(0);
```

When `CategoryRepository` validated the category, Magento attempted to retrieve parent category:

```text
ID 0
```

which does not exist.

Result:

```text
No such entity with id = 0
```

---

## Fix 1: Resolve the Default Store View Root Category

Instead of:

```php
$this->storeManager
    ->getStore()
    ->getRootCategoryId();
```

we changed the logic to explicitly resolve the default storefront:

```php
$defaultStore = $this->storeManager
    ->getDefaultStoreView();
```

Then:

```php
$rootCategoryId = (int)$defaultStore
    ->getRootCategoryId();
```

Now a top-level ERP category such as:

```text
COFFEE
```

gets:

```text
Magento Root Category ID
```

instead of:

```text
0
```

Result:

```text
Default Category
├── Coffee
├── Machines
├── Grinders
├── Brewing
├── Accessories
├── Parts
└── Commercial
```

---

## Important Magento Concept Learned

These two values are completely different:

```php
$category->setStoreId(0);
```

and:

```php
$category->setParentId(0);
```

`store_id = 0` is valid.

It means:

```text
Admin/default attribute scope
```

So this can remain:

```php
$category->setStoreId(0);
```

But:

```text
parent_id = 0
```

was invalid for our imported storefront categories.

---

## Fix 2: Removed Dependence on Simple `usort()`

The original implementation tried to import parents first using:

```php
usort(...)
```

with logic roughly equivalent to:

```text
parent_code = null
    → first

parent_code != null
    → later
```

This only distinguishes:

```text
Top-level categories
vs
Child categories
```

It does not truly understand multiple hierarchy levels.

For example:

```text
Coffee
└── Coffee Beans
    └── Arabica
```

Both:

```text
Coffee Beans
Arabica
```

have a non-null `parent_code`.

Therefore a simple sorter cannot guarantee:

```text
Coffee Beans
```

is processed before:

```text
Arabica
```

---

## New Hierarchy Processing Strategy

We changed the importer to use multiple passes.

Initial pending list:

```text
Coffee
Arabica
Coffee Beans
Machines
Espresso Machines
...
```

#### Pass 1

Import categories whose parents can already be resolved:

```text
Coffee
Machines
Grinders
Brewing
Accessories
Parts
Commercial
```

These use Magento's root category.

#### Pass 2

Now their immediate children can be imported:

```text
Coffee Beans
Ground Coffee
Espresso Machines
Electric Grinders
Pour Over
...
```

#### Pass 3

Deeper categories can now resolve their parents:

```text
Arabica Beans
Espresso Roast
```

This means the importer no longer depends on the ERP returning categories in a specific order.

---

## Fix 3: Added Category Map

A local map is maintained during the import:

```php
private array $categoryMap = [];
```

Example:

```php
[
    'COFFEE' => 25,
    'COFFEE_BEANS' => 32,
    'ARABICA' => 41
]
```

So when importing:

```text
ARABICA
```

with:

```json
"parent_code": "COFFEE_BEANS"
```

Magento parent ID can be quickly resolved as:

```text
32
```

instead of repeatedly searching the database.

---

## Fix 4: Existing Parent Lookup

If the parent was not created during the current execution, the importer checks existing Magento data using:

```php
$this->categoryResolver
    ->getByErpCode($parentCode);
```

This is useful during repeated ERP synchronization.

Example:

```text
COFFEE already exists from yesterday's sync
```

The importer can still correctly create:

```text
COFFEE_BEANS
```

under it.

---

## Fix 5: Detect Broken ERP Hierarchies

Another important improvement was added.

Imagine ERP sends:

```json
{
  "code": "ARABICA",
  "parent_code": "COFFEE_BEANS"
}
```

but ERP never sends:

```text
COFFEE_BEANS
```

Previously that could fail less clearly.

Now if an entire processing pass imports zero categories, we know there is an unresolved hierarchy.

Possible reasons:

```text
Missing parent
Incorrect parent_code
Circular parent relationship
```

Example circular data:

```text
A parent = B
B parent = A
```

The importer throws a clear error containing unresolved categories.

---

## Fix 6: Category Parent Updated for Existing Categories

Previously:

```php
$category->setParentId($parentId);
```

was mainly done while creating a category.

We changed the flow so parent ID is assigned for both:

```text
New categories
Existing categories
```

This matters if ERP changes:

```text
Accessories
└── Filters
```

to:

```text
Brewing
└── Filters
```

On the next sync Magento can follow the ERP hierarchy change.

---

## Fix 7: Added ERP Validation

Before processing a category, required data is checked.

At minimum:

```text
code
name
```

must exist.

Invalid ERP data now throws a meaningful exception instead of producing unpredictable Magento errors.

---

## Fix 8: Improved Logging

Instead of logging only:

```text
Category "Coffee" synchronized.
```

we now log useful synchronization information such as:

```text
Category "Coffee" [COFFEE] synchronized.
Magento ID: 25
Parent ID: 2
```

This makes ERP debugging much easier.

---

## Final Category Import Flow

```text
ERP
 ↓
CategoryService
 ↓
Fetch category array
 ↓
Validate ERP data
 ↓
Keep categories in pending queue
 ↓
Can parent be resolved?
 ├── NO → wait for next pass
 └── YES
       ↓
Find category by ERP code
       ↓
Existing?
 ├── YES → update it
 └── NO → create it
       ↓
Resolve Magento parent
       ↓
Map ERP fields
       ↓
CategoryRepository->save()
       ↓
Store ERP code → Magento ID in categoryMap
       ↓
Continue until pending list is empty
```

---

## Outcome

The category importer is now more production-like because it supports:

```text
✔ Root-category resolution in cron/CLI
✔ Multi-level category hierarchy
✔ Unordered ERP category feeds
✔ Existing category updates
✔ Parent-category changes
✔ Missing-parent detection
✔ ERP-code mapping
✔ Better validation
✔ Better logging
```


# 8. BrewCraft ERP Media & Category Content Sync

#### Development Log

### 1. Objective

After completing the PLP, we moved the next catalog requirement into ERP integration.

Previously the ERP handled core catalog data such as:

```text
Products
Categories
Prices
Inventory
```

The new goal was to enrich Magento with:

```text
Product images
Category images
Category descriptions
Subcategory images
Subcategory descriptions
```

without manually uploading every image or description through Magento Admin.

The resulting ownership is:

```text
ERP
│
├── Product/catalog data
├── Inventory
├── Pricing
├── Product images
├── Category hierarchy
├── Category images
└── Basic category descriptions
        ↓
      Magento

Magento
├── Storefront presentation
├── CMS/marketing content
├── SEO
├── Merchandising
└── Product relations
```

---

## 2. ERP Media Architecture

We decided that the storefront should **not load product/category images directly from ERP URLs**.

Instead:

```text
ERP
↓
Magento sync
↓
Download image
↓
Store image in Magento media
↓
PLP / PDP / Category hero
```

This is preferable to:

```text
Browser
↓
ERP image URL
```

because Magento's storefront should continue working even when ERP is temporarily unavailable.

---

## 3. Mock ERP Static Media Structure

The JSON Server mock ERP was extended to expose static media.

The recommended structure became:

```text
mock-erp/
├── db.json
└── public/
    └── media/
        ├── products/
        │   ├── BEAN001/
        │   │   ├── BEAN001-01.jpg
        │   │   ├── BEAN001-02.jpg
        │   │   └── BEAN001-03.jpg
        │   │
        │   ├── ESP001/
        │   │   ├── ESP001-01.jpg
        │   │   ├── ESP001-02.jpg
        │   │   └── ESP001-03.jpg
        │   └── ...
        │
        └── categories/
            ├── COFFEE.jpg
            ├── COFFEE_BEANS.jpg
            ├── ARABICA.jpg
            └── ...
```

JSON Server exposes `public/` as static content.

---

## 5. Multiple Product Images

The ERP product structure was expanded to support an `images` array rather than only one image.

Example:

```json
"images": [
    {
        "url": ".../BEAN001-01.jpg",
        "roles": [
            "image",
            "small_image",
            "thumbnail"
        ],
        "position": 1,
        "disabled": false
    },
    {
        "url": ".../BEAN001-02.jpg",
        "roles": [],
        "position": 2,
        "disabled": false
    },
    {
        "url": ".../BEAN001-03.jpg",
        "roles": [],
        "position": 3,
        "disabled": false
    }
]
```

This maps naturally to Magento's media gallery.

---

## 6. Magento Product Image Roles

We explicitly handled Magento's three important image roles:

```text
image
small_image
thumbnail
```

Their practical usage is approximately:

```text
image
→ main/base product image

small_image
→ PLP/catalog image

thumbnail
→ thumbnail contexts/gallery
```

Normally only the primary ERP image receives all three roles:

```text
BEAN001-01
├── image
├── small_image
└── thumbnail
```

while:

```text
BEAN001-02
BEAN001-03
```

remain additional gallery images.

This directly addresses the PLP problem we previously discovered where a PDP could have an image while the PLP lacked a usable `small_image`.

---

## 7. Magento Container Connectivity Test

The mock ERP was running on:

```text
localhost:3001
```

from the host.

We first tested:

```bash
curl -I http://localhost:3001/media/products/BEAN001/BEAN001-01.jpg
```

and received:

```text
HTTP/1.1 200 OK
Content-Type: image/jpeg
```

The image could also be downloaded successfully using `curl`.

---

## 8. Docker Networking Consideration

A critical Docker concept surfaced here.

From the Magento PHP container:

```text
localhost
```

means:

> the PHP container itself

not the Linux host.

Therefore Magento could not use the host-oriented ERP URL directly.

We tested:

```text
http://host.docker.internal:3001/
```

from inside the Magento PHP container.

The test returned:

```text
HTTP 200
Content-Type: image/jpeg
```

confirming:

```text
Magento PHP container
        ↓
host.docker.internal
        ↓
Host port 3001
        ↓
JSON Server
        ↓
Product image
```

---

## 9. `ImageDownloader`

We introduced a dedicated media service:

```text
BrewCraft\ErpIntegration\Model\Media\ImageDownloader
```

Its responsibility is intentionally limited:

```text
ERP URL
↓
HTTP GET
↓
Check HTTP status
↓
Validate response
↓
Ensure actual image
↓
Store temporary file
```

The temporary location is:

```text
var/import/brewcraft_erp_media/
```

This separation prevents product/category importer classes from containing raw HTTP-download logic.

---

## 10. Image Validation

The downloader validates that the HTTP response is actually an image.

This prevents situations such as:

```text
ERP URL returns
404 HTML page
```

from accidentally being written into Magento as if it were:

```text
product.jpg
```

Invalid media results in logging rather than silently corrupting the media gallery.

---

## 11. Product Media Service

We created:

```text
ProductImageImporter
```

whose responsibility is:

```text
Magento Product
+
ERP images array
↓
Product media gallery
```

It handles:

```text
multiple images
image-role assignment
duplicate detection
ERP-controlled images
error isolation
```

---

## 12. Product Import Integration

`ProductImportService` was extended so image synchronization happens as part of normal ERP product synchronization.

The resulting sequence is:

```text
ERP product
↓
Load/create Magento product
↓
Map core attributes
↓
Save product
↓
ProductImageImporter
↓
Save media changes
```

A separate manual image-import process is therefore unnecessary.

---

## 13. Product Import Scope

We also explicitly used:

```text
store_id = 0
```

for ERP-owned product synchronization.

This was important because we previously found the exact image bug:

```text
store_id = 0 → valid image
store_id = 1 → no_selection
```

and Magento preferred the Store View override.

ERP synchronization should write global catalog values at the intended default/Admin scope unless there is a deliberate store-specific requirement.

---

## 14. First Product Media Error

The initial product-image sync failed even though images successfully downloaded into:

```text
var/import/brewcraft_erp_media/
```

The log showed:

```text
Path "/var/www/html/var/import/..."
cannot be used with directory
"/var/www/html/pub/media/"
```

This was a very useful Magento filesystem issue.

---

## 15. Root Cause of Product Media Error

We were passing the downloaded file directly to:

```php
$product->addImageToMediaGallery(...)
```

from:

```text
var/import/...
```

But Magento's product gallery processor was working within the:

```text
pub/media
```

filesystem context.

Magento's filesystem abstraction correctly prevented using an arbitrary file outside that directory.

---

## 16. Why Category Image Sync Worked

Category media did not suffer from this problem because the category importer already performed:

```text
ERP
↓
download temporary image
↓
copy image to pub/media/catalog/category
↓
assign category image
```

So the actual storefront category file already existed inside Magento's media directory.

---

## 17. Product Media Flow Corrected

The product flow was changed to:

```text
ERP
↓
ImageDownloader
↓
var/import/brewcraft_erp_media
↓
copy
↓
pub/media/import/brewcraft_erp_media
↓
Magento addImageToMediaGallery()
↓
pub/media/catalog/product
```

This satisfied Magento's media filesystem requirements.

---

## 18. Media Staging Concept

We therefore use two stages:

```text
var/import
```

as the **external-download staging area**, and:

```text
pub/media/import
```

as the **Magento media-gallery staging area**.

Then Magento owns the final copy inside:

```text
pub/media/catalog/product
```

This distinction is particularly useful when debugging.

---

## 19. Product Media Error Isolation

A deliberate design decision was made:

> One bad ERP image should not fail the entire product synchronization.

So:

```text
32 products
↓
1 unavailable JPG
```

does not mean:

```text
whole ERP sync fails
```

Instead:

```text
product/catalog data continues ✅
other media continues ✅
failed image logged ❌
```

Logs use messages similar to:

```text
[ERP MEDIA] Failed product image sync
SKU: ...
URL: ...
Error: ...
```

---

## 20. Category Description Sync

`CategoryImportService` was extended to map:

```text
description
```

from ERP to Magento.

Example:

```json
{
    "code": "ARABICA",
    "name": "Arabica Beans",
    "description": "Discover smooth and aromatic Arabica beans..."
}
```

becomes:

```text
ERP description
↓
Magento category description
↓
Existing PLP category hero
```

No additional theme work was required.

---

## 21. Safe Description Updating

We chose not to blindly clear Magento descriptions when older ERP payloads do not contain a description field.

Instead, the importer only updates the description when:

```text
"description"
```

is actually present in the ERP response.

This provides safer backward compatibility with existing mock ERP records.

---

## 22. Category Image Sync

We created:

```text
CategoryImageImporter
```

for category images.

Its flow is:

```text
ERP category image URL
↓
ImageDownloader
↓
temporary image
↓
pub/media/catalog/category/erp/
↓
Magento category image attribute
```

The existing category hero then automatically displays it.

---

## 23. Category Media Payload

ERP categories can now contain:

```json
{
    "code": "COFFEE_BEANS",
    "name": "Coffee Beans",
    "parent_code": "COFFEE",
    "status": "ACTIVE",
    "description": "...",
    "image_url": "..."
}
```

This means parent and child categories can both be fully populated from ERP.

---

## 24. Subcategory Problem Solved Architecturally

Before this work, subcategories without manually assigned images appeared as:

```text
brown hero background
+
category title
```

Instead of manually populating every category in Magento Admin, the category importer can now own:

```text
Category description
Category image
```

for the entire imported hierarchy.

---

## 25. Category Hierarchy Remains Unchanged

The media work did not replace or disturb the previously working multi-pass category hierarchy logic.

The importer still resolves:

```text
Parent
↓
Child
↓
Grandchild
```

independently of ERP response order.

Media/content enrichment happens on top of that existing category structure.

---

## 26. Duplicate Protection / Idempotency

A major requirement was:

```text
ERP sync #1
→ image added

ERP sync #2
→ same image

ERP sync #3
→ same image
```

must not produce:

```text
image
image duplicate
image duplicate
```

The importer creates deterministic ERP-managed filenames and checks existing Magento media gallery entries before adding them.

---

## 27. Idempotency Test Result

You reran the ERP product synchronization without changing the media.

Result:

```text
No duplicate images ✅
```

This is a very important integration milestone.

It means the import can safely run repeatedly through cron without continuously expanding the Magento media gallery.

---

## 28. Current Media Sync Flow

The resulting overall architecture is now:

```text
                    ERP
                     │
          ┌──────────┴──────────┐
          │                     │
       Products              Categories
          │                     │
       images[]          description/image_url
          │                     │
          ▼                     ▼
 ProductImportService    CategoryImportService
          │                     │
          ▼                     ▼
 ProductImageImporter    CategoryImageImporter
          │                     │
          └──────────┬──────────┘
                     ▼
              ImageDownloader
                     │
                     ▼
                 Magento
                Media Storage
                     │
          ┌──────────┴──────────┐
          │                     │
          ▼                     ▼
     catalog/product       catalog/category
          │                     │
          ▼                     ▼
       PLP / PDP          Category Hero
```

---


## 30. Important Integration Concepts Covered

From an interview/project-design perspective, this feature demonstrates:

#### Source of truth

ERP owns the catalog media/content fields that we decided are operational catalog data.

#### Local media copy

Magento does not depend on ERP availability during customer page requests.

#### Idempotency

Repeated jobs do not create duplicate records.

#### Fault tolerance

One broken media asset doesn't stop the catalog import.

#### Separation of concerns

```text
ImageDownloader
```

does HTTP/filesystem work.

```text
ProductImageImporter
```

does product-gallery work.

```text
CategoryImageImporter
```

does category-media work.

```text
ProductImportService
CategoryImportService
```

orchestrate ERP entity synchronization.

---



```text
Category description            ✅
Category image URL              ✅
Image download                  ✅
Magento category media          ✅
Parent categories               ✅
Subcategories                   ✅
Existing PLP hero integration   ✅
```

### ERP Media Sync — Core Feature COMPLETE ✅

There is nothing blocking us from moving on.

Future enhancements can include:

```text
ERP media version/checksum
Image replacement when content changes at same URL
Actual gallery label synchronization
Explicit gallery position synchronization
Removal when ERP deletes an image
Retry queue for temporary media failures
Image-size/file-size restrictions
Old staging-file cleanup
```

I would treat those as a later **hardening/production-readiness phase**, rather than expanding this feature indefinitely right now.

The next project feature can now start from a stable point: **ERP catalog products, categories, inventory, prices, product media, category media, and category descriptions are all integrated.**


# 9. ERP Product Attributes + Data Patch + Sync
**DATE** 14th AUG

### Objective

After completing ERP media synchronization, we needed richer structured product data for the PDP.

The Figma PDP requires sections like:

```text
Overview
Specifications
What's Included
Shipping & Returns
Reviews
```

The existing ERP product payload already handled core fields such as:

```text
SKU
Name
Price
Weight
Category
Manufacturer
Barcode
Country of Origin
Cost Price
Status
```

but did not have enough structured technical/product attributes for a proper Specifications section.

So we extended the ERP integration.

---

### Architecture Decision

We decided on this ownership model:

```text
Data Patch
→ creates Magento attributes

ERP
→ provides values for those attributes

ProductImportService
→ maps ERP values into Magento

PDP
→ reads Magento attributes and renders them
```

So:

```text
Magento schema
        +
ERP values
        =
structured PDP specifications
```

This is cleaner than manually creating attributes one by one in Admin.

---

## Why a Data Patch?

Magento product attributes are part of the application/data model.

Instead of manually creating:

```text
Bean Type
Roast Level
Voltage
Warranty
...
```

through Admin, we created them in code with a Data Patch.

Benefits:

```text
repeatable
version-controlled
portable across environments
easy to deploy
easy to understand
```

So if another developer installs the module:

```bash
bin/magento setup:upgrade
```

Magento automatically creates the required attributes.

---

## Data Patch Created

File:

```text
app/code/BrewCraft/ErpIntegration/
Setup/Patch/Data/CreateProductSpecificationAttributes.php
```

The patch creates attributes such as:

```text
Coffee attributes

bean_type
roast_level
flavor_profile
brew_methods


Equipment attributes

capacity
material
power
voltage
warranty

grinder_type
burr_type

water_tank_capacity
bean_hopper_capacity
pump_pressure

dimensions


PDP content

included_items
```

---

## Attribute Group

We grouped these attributes under:

```text
BrewCraft Specifications
```

inside the product attribute set.

This keeps Magento Admin organized instead of mixing custom ERP fields randomly with all core Magento attributes.

---

## Attributes Are Optional

A very important requirement was:

> Missing ERP attributes must never break the product import.

So every custom attribute was created with:

```php
'required' => false
```

This allows different product types to use different subsets.

For example:

```text
Coffee Beans

Bean Type         Arabica
Roast Level       Medium
Flavor Profile    Smooth...
Brew Methods      Espresso...
```

while:

```text
Espresso Machine

Pump Pressure        15 Bar
Voltage              230 V
Water Tank Capacity  2 L
Warranty             2 Years
```

There is no requirement that the machine must have:

```text
bean_type
roast_level
```

or that coffee beans must have:

```text
pump_pressure
voltage
```

---

## Why We Did Not Create New Description Attributes

Magento already provides native product attributes:

```text
short_description
description
```

So we reused them instead of creating:

```text
erp_short_description
erp_long_description
```

This keeps us aligned with Magento's native PDP behavior.

---

## Description Ownership Decision

For this learning project, we decided:

```text
ERP owns
→ short_description
→ description
→ product specifications

Magento owns
→ homepage CMS
→ promotional CMS blocks
→ SEO/editorial merchandising later
```

The short description is used near the purchasing section.

The long description is used in the PDP Overview tab.

---

## ERP Payload Expanded

Example for a coffee product:

```json
{
  "sku": "BEAN001",

  "short_description":
    "Smooth, aromatic Arabica coffee with balanced sweetness, gentle acidity, and a clean finish.",

  "description":
    "<p>BrewCraft Signature Arabica Beans...</p>",

  "bean_type": "Arabica",

  "roast_level": "Medium",

  "flavor_profile":
    "Smooth, balanced, mildly sweet",

  "brew_methods": [
    "Espresso",
    "Pour Over",
    "French Press",
    "Filter"
  ]
}
```

For machines, ERP can instead send:

```json
{
  "pump_pressure": "15 Bar",
  "water_tank_capacity": "2 L",
  "bean_hopper_capacity": "250 g",
  "material": "Stainless Steel",
  "voltage": "230 V",
  "power": "1850 W",
  "warranty": "2 Years"
}
```

---

## ProductImportService Extended

The existing:

```text
ProductImportService
```

was extended to map the new fields.

We did **not** directly access optional values like:

```php
$erpProduct['bean_type']
```

because that could cause problems when ERP doesn't send the field.

Instead we introduced a helper:

```text
mapOptionalAttribute()
```

---

## Safe Optional Mapping

The helper follows this logic:

```text
Field missing?
→ skip

Field null?
→ skip

Scalar value?
→ save

Array?
→ normalize and save

Unsupported complex value?
→ log warning and skip
```

Conceptually:

```php
if (!array_key_exists($attributeCode, $erpProduct)) {
    return;
}
```

So:

```text
ERP doesn't send warranty
↓
Magento does nothing
↓
product import continues
```

No undefined-index problem.

No broken synchronization.

---

## Preserve Existing Magento Values

Another important decision:

If ERP does not send an optional field, we **do not clear Magento's existing value**.

Example:

```text
Magento warranty = "2 Years"
```

ERP next run accidentally omits:

```text
warranty
```

Our importer:

```text
missing field
→ skip
→ keep "2 Years"
```

instead of:

```text
missing field
→ save NULL
→ warranty disappears
```

This makes the integration safer.

---

## Arrays From ERP

For fields like:

```text
brew_methods
included_items
```

ERP can send arrays:

```json
"brew_methods": [
  "Espresso",
  "Pour Over",
  "French Press"
]
```

Our helper converts them to:

```text
Espresso, Pour Over, French Press
```

before storing in Magento.

So JSON remains clean on the ERP side while Magento receives a simple displayable value.

---

## Data Patch Execution

We ran:

```bash
bin/magento setup:upgrade
```

which created the attributes.

Then:

```bash
bin/magento indexer:reindex
bin/magento cache:flush
```

---

## ERP Sync Verification

After updating the mock ERP product data, the regular existing ERP product sync was executed.

Result:

```text
Custom attributes created       ✅
ERP values received             ✅
ProductImportService mapping    ✅
Magento values saved            ✅
Missing fields safe             ✅
Arrays normalized               ✅
Descriptions synced             ✅
```

You confirmed that all values synchronized successfully.

---

## Final Data Flow

```text
ERP
│
├── short_description
├── description
├── bean_type
├── roast_level
├── flavor_profile
├── brew_methods
├── material
├── voltage
├── warranty
├── pump_pressure
└── included_items
        ↓
ProductImportService
        ↓
mapOptionalAttribute()
        ↓
Magento EAV product attributes
        ↓
PDP
```

---

## Important Concepts Learned

This feature covered:

```text
Magento EAV attributes
Data Patches
Attribute Sets
Attribute Groups
Optional attributes
ERP-to-Magento mapping
Defensive integration coding
array_key_exists()
Null handling
Preserving existing values
Array normalization
Native Magento description fields
Separation of schema vs data
```

---

## ERP Product Attribute Sync Status

```text
Attribute Data Patch       ✅
Custom attributes          ✅
Attribute group            ✅
Optional fields            ✅
ERP payload extension      ✅
Short description          ✅
Long description           ✅
Specifications             ✅
Safe missing-field logic   ✅
ERP synchronization        ✅
Magento values verified    ✅
```

