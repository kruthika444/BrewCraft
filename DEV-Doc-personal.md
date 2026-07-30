# BrewCraft Supply — Development Log

## Day 1: ERP Integration Foundation

**Date:** 14 July 2026

---

## Objective

The objective of Day 1 was **not** to synchronize products. Instead, the objective was to build the foundation required for any ERP integration.

In real projects, integrations are never built by directly calling APIs. They require configuration, reusable services, logging, and debugging support before any business logic is implemented.

---

## What We Accomplished

### 1. Created a Dedicated Magento Module

**Module:** `BrewCraft_ErpIntegration`

**Files created:**
- `registration.php`
- `composer.json`
- `etc/module.xml`

**Why?**
Instead of mixing ERP code into an existing module, we isolated all ERP-related functionality into a dedicated module following Magento's modular architecture. This improves maintainability, scalability, code ownership, and future extensibility.

---

### 2. Planned the ERP Integration Architecture

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

### 3. Created Admin Configuration

**Path:** Stores → Configuration → BrewCraft → ERP Integration

**Configuration fields:**
- Enable Integration
- ERP Base URL
- API Version
- Connection Timeout

**Why?**
URLs, API versions, and credentials change between environments. Hardcoding values inside PHP classes is considered poor practice. Configuration enables administrators to modify connection settings without code deployment.

---

### 4. Implemented ACL

**File created:** `etc/acl.xml`

**Purpose:**
- Restrict access to ERP configuration
- Allow future role-based permissions
- Follow Magento's security model

---

### 5. Added Default Configuration

**File created:** `etc/config.xml`

**Default values configured:**
- Enabled
- Base URL
- API Version
- Timeout

**Purpose:** Prevent `NULL` configuration values before the administrator saves settings.

---

### 6. Designed Configuration Helper

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

### 7. Designed API Client

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

### 8. Designed Custom Logger

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

### 9. Created CLI Command

**Command:**
```bash
php bin/magento brewcraft:erp:test
```

**Purpose:** Allow developers to test ERP connectivity without running cron jobs, greatly simplifying debugging during development.

---

### 10. Built the Mock ERP

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

### 11. Defined Initial ERP Data Model

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

### 12. Encountered Integration Issue

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

## Lessons Learned

Throughout Day 1 we reinforced the following Magento development principles:

- Separate configuration from business logic
- Isolate integrations into dedicated modules
- Centralize configuration access through helper classes
- Use custom loggers for integration diagnostics
- Validate architecture before implementing business functionality
- Understand Docker networking when integrating external services

---

## Current Status

### Completed
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

### Pending
- [ ] Resolve API client connection issue — update configured URL from `localhost` to `host.docker.internal`
- [ ] Execute the first successful API request from Magento
- [ ] Begin product synchronization using cron  


---
---
  
  
## Day 02: ERP Integration Foundation & First Product Synchronization

**Date:** 15 July 2026

---

## Objective

The objective for today's development session was to establish the foundation of the ERP integration by connecting Magento with a mock ERP system, implementing a reusable integration architecture, and successfully importing ERP products into the Magento catalog.

---

## What We Built

### 1. Mock ERP Environment

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

### 2. ERP Product Data Structure

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

### 3. Magento ERP Integration Module

Created a new Magento module:

```
BrewCraft_ErpIntegration
```

This module serves as the integration layer between Magento and external ERP systems.

---

### 4. Configurable ERP Settings

Added ERP configuration under Magento Admin.

**Configuration fields:**
- Enable / Disable Integration
- ERP Base URL
- API Version
- Request Timeout

**Scope:** Website level

**Reason:** Different websites can communicate with different ERP environments while sharing the same Magento installation.

---

### 5. HTTP Client Layer

**File created:** `Model/Api/Client.php`

**Responsibilities:**
- Build ERP URLs
- Execute HTTP requests
- Handle timeouts
- Return API responses
- Log requests and responses

This centralizes all ERP communication into one reusable component.

---

### 6. Product Service

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

### 7. Product Import Service

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

### 8. Logging

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

### 9. CLI Command

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

### 10. Cron Integration

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

## Issues Encountered

### Issue 1 — Docker Networking: Connection Refused

**Problem:** Failed to connect to `localhost`

**Cause:** Magento runs inside Docker, while the mock ERP runs on the host machine. `localhost` inside a Docker container refers to the container itself, not the host.

**Solution:** Replace `localhost` with `host.docker.internal` to allow the Docker container to communicate with the host machine.

```
# Before
http://localhost:3001

# After
http://host.docker.internal:3001
```

---

### Issue 2 — Area Code Not Set

**Problem:** `Area code is not set`

**Cause:** Magento CLI commands execute without an application area. Saving catalog products requires the Admin application area.

**Solution:** Set the application area explicitly inside the CLI command:

```php
$this->state->setAreaCode(
    \Magento\Framework\App\Area::AREA_ADMINHTML
);
```

---

### Issue 3 — Cron Jobs Not Appearing in `cron_schedule`

**Problem:** Cron jobs were not appearing in the `cron_schedule` table.

**Cause:** Incorrect cron configuration file.

**Solution:** Corrected the Magento cron configuration and verified successful scheduling and execution.

---

## Final Result

Successfully synchronized ERP products into Magento.

**Imported products:**

| SKU | Product Name |
|---|---|
| ESP001 | Breville Barista Express |
| BEAN001 | BrewCraft Signature Coffee Beans |

Both products are now visible under **Catalog → Products**.

This marks the **first successful end-to-end synchronization** between the mock ERP and Magento.

---

## Architecture Achieved

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

## Key Magento Concepts Learned

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
   
# BrewCraft Supply — Development Log

## Day 03: Category Sync, Inventory Sync & Inventory Cron

**Date:** 16 July 2026

---

## Objective

Continue building the ERP Integration module by synchronizing additional master data from the ERP into Magento.

**Completed features:**
- Category Synchronization
- Inventory Synchronization
- Inventory Cron
- ERP Integration Architecture Improvements

---

## 1. Category Synchronization

### Why?

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

### ERP API

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

### Components Created

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

### Current Behaviour

Every imported category is created beneath the Magento Root Category:

```
Default Category
 ├── Coffee Beans
 ├── Coffee Machines
 └── Accessories
```

### Discussion — Parent-Child Hierarchy

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

### Result

- New categories imported successfully
- Existing categories updated
- No duplicates created

---

## 2. Inventory Synchronization

### Business Requirement

ERP owns inventory. Magento only displays current stock.

```
ERP (Quantity)
 ↓
Magento (Stock)
```

---

### ERP Endpoint

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

### Initial Issue — Validation Mismatch

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

### `InventoryService`

**Responsibilities:**
- Fetch inventory from ERP
- Validate JSON
- Validate required fields (`sku`, `qty`)
- Record job history (`INVENTORY_SYNC`) inside `erp_job` table

---

### `InventoryImportService`

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

### Warehouse Field

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

### Testing

Changed ERP quantity from `18` to `99`, then executed:

```bash
php bin/magento brewcraft:erp:inventory:test
```

Magento Catalog → Product → Quantity updated successfully.

---

## 3. Inventory CLI Command

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

## 4. Inventory Cron

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

## 5. Cron vs `erp_job` Table

### Why maintain a separate `erp_job` table?

Magento already provides `cron_schedule` for cron execution history, which captures job name, status, schedule time, and finish time. However it does not store business-specific information.

Our module maintains a separate `erp_job` table for ERP integration history:

| Job Type | Status | Records Processed |
|---|---|---|
| PRODUCT_SYNC | SUCCESS | 3 |
| CATEGORY_SYNC | SUCCESS | 5 |
| INVENTORY_SYNC | SUCCESS | 2 |

This provides business-level visibility into synchronization activity beyond what `cron_schedule` offers.

---

## 6. Architecture After Today's Work

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

## Key Magento Concepts Learned

### 1. Master Data Synchronization
ERP owns business data. Magento consumes it.

### 2. Multi Source Inventory (MSI)
Inventory is managed using Magento's MSI APIs rather than directly updating legacy stock tables.

### 3. Repository Pattern
Magento entities are created and updated using Repository interfaces instead of direct database access.

### 4. Cron Architecture
Cron automates synchronization according to business schedules defined in the BRD — every 15 minutes for inventory.

### 5. Separation of Responsibilities

Each layer has a single, clearly defined purpose:

| Layer | Responsibility |
|---|---|
| Client | Communicates with the ERP API |
| Service | Fetches, validates, and prepares ERP data |
| Import Service | Maps ERP data to Magento entities and saves them |
| CLI | Manual execution for testing and debugging |
| Cron | Automated execution based on schedule |

This separation keeps the module maintainable, testable, and easy to extend.


## ERP → Magento Price Synchronization

## Objective

Implemented synchronization of product pricing from the ERP system into Magento. As per the BRD, ERP is the master source for pricing and prices must never be manually maintained in Magento Admin.

---

## Development Completed

### 1. ERP API Integration

Extended the ERP API client with a new `getPrices()` method.

**Endpoint consumed:**

```
GET /api/v1/prices
```

Retrieved pricing data successfully from the mock ERP.

---

### 2. Price Validation Service

**Class created:** `PriceService`

**Responsibilities:**
- Fetch price data from ERP
- Decode and validate the JSON response
- Ensure mandatory fields (`sku`, `price`) are present
- Log the synchronization job in the custom `erp_job` table

---

### 3. Price Import Service

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

### 4. CLI Command

**Command:**

```bash
php bin/magento brewcraft:erp:price:test
```

**The command:**
- Fetches pricing data from ERP
- Imports prices into Magento
- Displays a synchronization summary showing updated, failed, and total records

---

### 5. Cron Job

**Job registered:** `brewcraft_price_sync`

**The cron:**
- Executes price synchronization automatically
- Supports the BRD requirement of hourly price synchronization
- Can be configured to run every minute during development for testing

---

## Business Rules Implemented

- ERP is the single source of truth for all product pricing
- Magento updates product prices based on ERP data only
- Manual price maintenance in Magento is not part of the integration flow
- Supports promotional pricing via:
  - Special Price
  - Special Price From Date
  - Special Price To Date

---

## Testing Performed

- [x] Verified ERP `/prices` endpoint returns valid data
- [x] Successfully updated existing Magento product prices
- [x] Verified special pricing and date ranges in Magento Admin
- [x] Tested CLI execution
- [x] Tested cron execution
- [x] Confirmed successful job logging and application logging

---

## Current Project Status

| Feature | Status |
|---|---|
| Product Sync | ✅ Complete |
| Category Sync | ✅ Complete |
| Inventory Sync | ✅ Complete |
| Price Sync | ✅ Complete |
| Order Integration | 🔜 Next Phase |

---

## Next Phase

**Magento → ERP Order Integration** using the Magento Message Queue Framework.

Newly placed orders will be published to a queue and asynchronously sent to the ERP system.


# BrewCraft ERP Integration — Development Log

## Day 04: Order Synchronization via Magento Message Queue Framework

**Date:** 17 July 2026

---

## Objective

Begin implementing the Order Synchronization feature using Magento's Message Queue Framework. The goal was to decouple order export from the checkout process by publishing a queue message when an order is placed and processing it asynchronously.

---

## What We Built

### 1. Studied Magento Message Queue Architecture

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

### 2. Implemented Queue Configuration

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

### 3. Created Queue Publisher

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

### 4. Created Queue Consumer

Implemented the consumer.

**Initial responsibility:** Receive message and log it.

**Later enhanced to:**
- Load Magento order
- Display processing information

---

### 5. Built Queue Test Command

Created a console command to publish a sample message independently of order placement.

```bash
php bin/magento brewcraft:queue:test
```

**Published:** `Hello Queue!`

**Consumer output:** `QUEUE RECEIVED : Hello Queue!`

This validated the complete queue configuration end-to-end.

---

### 6. Understood Consumer Behaviour

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

### 7. Integrated Queue with Order Placement

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

### 8. Investigated Magento Order Events

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

### 9. Design Decision — Use Increment ID

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

### 10. Updated Consumer

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

### 11. Cache-Related Debugging

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

### 12. Final Working Flow

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

## How the Queue Works — Step by Step

### Step 1 — CLI Command Triggers Publisher

You execute:

```bash
bin/magento brewcraft:queue:test
```

Magento executes `QueueTest::execute()`, which calls:

```php
$this->publisher->publish('Hello Kruthi!');
```

---

### Step 2 — Your Publisher Class

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

### Step 3 — Magento Receives the Topic

Magento Framework receives:

```
publish("brewcraft.order.export", "Hello Kruthi!")
```

Magento asks: *I received a topic — where should I send it?*

It looks inside `queue_publisher.xml`.

---

### Step 4 — `queue_publisher.xml` Resolves the Exchange

```xml
<publisher topic="brewcraft.order.export">
    <connection name="db" exchange="magento"/>
</publisher>
```

Magento now knows: topic `brewcraft.order.export` uses exchange `magento`.

---

### Step 5 — `queue_topology.xml` Resolves the Queue

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

### Step 6 — Consumer is Started

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

### Step 7 — Consumer Picks Up the Message

Consumer starts. Magento immediately checks `brewcraft.order.queue`.

Message found: `Hello Kruthi!`

---

### Step 8 — Magento Calls Your Consumer

```php
TestConsumer::process("Hello Kruthi!")
```

> **Important:** You never called `process()`. **Magento did.**

---

## Complete Queue Flow

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

## Key Magento Concepts Learned

- Magento Message Queue Framework architecture
- Role of each XML configuration file (`communication.xml`, `queue_publisher.xml`, `queue_topology.xml`, `queue_consumer.xml`)
- How a message travels from publisher to consumer without direct PHP calls
- Consumer behaviour as a persistent background worker
- Why `sales_order_place_after` is the correct event for ERP order export
- Using Increment ID as the business-facing order identifier for ERP integration
- Importance of cache clearing after XML and DI configuration changes


# BrewCraft ERP Integration — Development Log

## Feature: Category Hierarchy Synchronization & Product Category Assignment

**Date:** 18-19 July 2026

---

## Objective

Implement a production-ready category synchronization mechanism between the ERP system and Magento.

**Goals:**
- Import categories from ERP
- Preserve the ERP category hierarchy
- Automatically create parent categories before child categories
- Prevent duplicate categories on subsequent synchronizations
- Allow products to be assigned using ERP category codes instead of Magento IDs
- Make the solution scalable for future ERP category changes

---

## Initial Problem

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

## Root Cause

The ERP originally returned categories without any relationship information:

```json
[
    { "code": "COFFEE_MACHINES", "name": "Coffee Machines" },
    { "code": "COFFEE_BEANS", "name": "Coffee Beans" }
]
```

This payload contained only `code` and `name` — no parent reference. Magento therefore had no way to determine which category was the parent and which was the child.

---

## ERP Payload Redesign

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

## Sample ERP Hierarchy

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

## New Category Synchronization Design

The synchronization process was redesigned into four separate responsibilities.

### 1. Client

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

### 2. `CategoryService`

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

### 3. `CategoryImportService`

Responsible for synchronizing ERP categories into Magento. This class contains all Magento-specific logic.

**Responsibilities:**
- Find existing category
- Create new category if necessary
- Update category information
- Save ERP category code as a Magento attribute
- Maintain parent-child hierarchy

---

### 4. `CategoryResolver`

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

## Two-Pass Import Algorithm

One of the biggest improvements was redesigning the import algorithm. Instead of creating categories in a single loop, the import now executes in **two passes**.

### Pass 1 — Root Categories

Import only categories where `parent_code = null`.

```
Default Category
 ├── Coffee
 └── Coffee Beans
```

These become direct children of Magento's Default Category.

### Pass 2 — Child Categories

Import child categories after all parents are guaranteed to exist.

```
Coffee
 └── Coffee Machines (parent = Coffee)
      └── Espresso Machines (parent = Coffee Machines)
```

This guarantees that every parent already exists before its children are processed.

---

## Category Mapping

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

## Product Synchronization Changes

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

## Final Architecture

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

# BrewCraft Supply — Project Status - as of 19 July
## Overall Completion

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

## Phase 1 — Project Setup ✅ 100%

| Item | Status |
|---|---|
| Magento installation | ✅ |
| Development environment | ✅ |
| Git | ✅ |
| Docker / Reward | ✅ |
| Module structure | ✅ |
| Sample ERP (json-server) | ✅ |

---

## Phase 2 — ERP Integration ✅ 92–95%

### Completed

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

### Remaining

| Item | Status |
|---|---|
| Retry mechanism | ⏳ |
| Small configuration improvements | ⏳ |

---

## Phase 3 — Storefront & Catalog ⚠️ 40%

### Completed

| Feature | Status |
|---|---|
| Product import | ✅ |
| Category hierarchy | ✅ |
| Categories visible under Default Category | ✅ |

### Remaining

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

## Phase 4 — B2C Store ⚠️ 35%

### Completed

| Feature | Status |
|---|---|
| Checkout | ✅ |
| Order placement | ✅ |
| ERP export | ✅ |

### Remaining

| Feature | Status |
|---|---|
| Customer registration customization | ❌ |
| Wishlist | ❌ |
| Reviews | ❌ |
| Reward Points (if required) | ❌ |
| Email customization | ❌ |

---

## Phase 5 — B2B Features ❌ 5%

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

## Phase 6 — Admin Features ❌ 15%

| Feature | Status |
|---|---|
| ERP Dashboard | ❌ |
| Import History Grid | ❌ |
| Manual Sync Buttons | ❌ |
| Configuration improvements | ❌ |
| Reports | ❌ |

---

## Phase 7 — ERP Simulation ⚠️ 70%

### Completed

| Feature | Status |
|---|---|
| json-server | ✅ |
| Products | ✅ |
| Categories | ✅ |
| Inventory | ✅ |
| Prices | ✅ |
| Orders | ✅ |

### Remaining

| Feature | Status |
|---|---|
| Customers | ❌ |
| Quotes | ❌ |
| Shipments | ❌ |
| Invoices | ❌ |



# Development Log — ERP Retry Mechanism and Admin Configuration
**Date:** 20 July 2026


**Project:** BrewCraft Magento 2 ERP Integration
**Module:** `BrewCraft_ErpIntegration`
**Work completed:** Retry mechanism, Admin configuration, configuration-controlled cron and queue behavior

---

# 1. Objective

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

# 2. Retry Mechanism Architecture

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

# 3. ERP HTTP Error Detection

## Problem

The Magento Curl client may complete an HTTP request even when the ERP returns an error such as:

```text
400 Bad Request
404 Not Found
500 Internal Server Error
503 Service Unavailable
```

Without manually checking the response status, the application could incorrectly treat an ERP `500` response as successful.

## Change in `OrderClient.php`

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

## Result

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

# 4. Retry Method Implementation

A private method was introduced:

```php
private function exportWithRetry(
    Order $order,
    array $payload,
    int $maxAttempts,
    int $retryDelay
): int
```

## Responsibilities

This method:

* Calls the ERP order API.
* Catches temporary request failures.
* Logs every attempt.
* Waits before the next attempt.
* Returns the successful attempt number.
* Throws a final exception after all attempts fail.

## Retry loop

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

## Why `return $attempt` is used

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

## Final failure

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

# 5. Retry Delay

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

## Current limitation

`sleep()` pauses the active consumer process during the delay.

For this learning project and local ERP simulation, this is acceptable. A high-volume production integration could instead use a delayed retry queue, but that is outside the current project scope.

---

# 6. Success and Failure History

The existing `brewcraft_sync_job` table continues to store the final result.

## Success

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

## Final failure

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

# 7. Why the Final Exception Is Not Rethrown

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

## Important consequence

When all immediate attempts fail, the order will not automatically be retried after the ERP returns later.

It remains available in the sync-history table as `FAILED`.

A delayed retry queue could solve that in a larger production implementation, but it is not required for the BrewCraft learning project.

---

# 8. Local Retry Test

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

# 9. Local Queue and ERP Behavior

The Magento consumer and simulated ERP are separate processes.

```text
Magento Queue Consumer
ERP JSON Server
```

## Consumer stopped, ERP stopped

When an order is placed:

```text
Order placed
        ↓
Message stored in queue
        ↓
No processing because consumer is stopped
```

No retry happens yet because the service has not received the message.

## Consumer started while ERP is stopped

```text
Consumer reads pending message
        ↓
Retry mechanism starts
```

Starting the ERP during the retry window allows a later attempt to succeed.

## Consumer already running while ERP is stopped

The message is consumed immediately, so all attempts happen quickly according to the configured delay.

This is expected behavior in the local environment.

---

# 10. Magento Admin Configuration

New Admin settings were added under:

```text
Stores
→ Configuration
→ BrewCraft
→ ERP Integration
```

The configuration is divided into three groups.

## General Settings

```text
Enable ERP Integration
ERP Base URL
API Version
Connection Timeout
```

## Order Export Settings

```text
Enable Order Export
Enable Queue Processing
Maximum Retry Attempts
Retry Delay
```

## Import Settings

```text
Enable Category and Product Sync
Enable Inventory Sync
Enable Price Sync
```

---

# 11. `system.xml` Changes

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

## XML validation issue fixed

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

# 12. Default Configuration

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

## Configuration priority

Magento uses configuration fallback and stored values.

An Admin value saved in `core_config_data` takes priority over the module default in `config.xml`.

Therefore, changing `config.xml` does not overwrite an existing Admin value.

---

# 13. Config Helper Updates

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

# 14. Defensive Configuration Values

The helper prevents invalid retry values.

## Retry attempts

```php
return $attempts > 0 ? $attempts : 1;
```

Even when configuration is missing or incorrectly set to `0`, the system performs at least one request.

## Retry delay

```php
return max(0, $delay);
```

A negative value is converted to zero.

A zero delay is valid and means:

```text
Retry immediately
```

## Timeout

```php
return $timeout > 0 ? $timeout : 30;
```

An invalid timeout falls back to 30 seconds.

---

# 15. Store-Scope Configuration

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

# 16. `OrderExportService` Configuration Integration

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

## Behavior when disabled

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

# 17. Queue Configuration Integration

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

# 18. Cron Configuration Integration

The following cron classes were updated:

```text
Cron/ProductSync.php
Cron/InventorySync.php
Cron/PriceSync.php
```

Each cron now checks:

1. Is the complete ERP integration enabled?
2. Is this specific scheduled synchronization enabled?

## Product cron

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

## Inventory cron

```text
ERP enabled?
        ↓
Inventory sync enabled?
        ↓
Fetch and import inventory
```

## Price cron

```text
ERP enabled?
        ↓
Price sync enabled?
        ↓
Fetch and import prices
```

When disabled, the cron writes a skipped message and returns without calling the ERP.

---

# 19. Why Console Commands Were Not Changed

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

# 20. Logging Improvements

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

# 21. Commands Used After Configuration Changes

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

# 22. Final Result

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

# Development Status

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



# Development Log — BrewCraft Business Account Storefront Registration

**DATE:** 21 July 

**Project:** BrewCraft Supply
**Magento module:** `BrewCraft_BusinessAccount`
**Phase completed:** Module foundation, persistence layer, repository layer, storefront registration, customer creation, and pending application submission

---

# 1. Business Requirement

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

# 2. Why We Created a Separate Module

We created:

```text
BrewCraft_BusinessAccount
```

instead of placing the functionality inside:

```text
BrewCraft_ErpIntegration
```

The two modules have different responsibilities.

```text
BrewCraft_ErpIntegration
→ Communicates with the ERP
→ Imports catalog data
→ Exports orders
→ Handles queue, cron, and retries
```

```text
BrewCraft_BusinessAccount
→ Accepts company applications
→ Connects applications to customers
→ Controls approval status
→ Enables future B2B features
```

This follows separation of concerns.

A future change to business registration should not require changing ERP integration code. Similarly, ERP API changes should not affect the business-account registration module.

---

# 3. Completed User Journey

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

# 4. Module Foundation

The first files created were:

```text
app/code/BrewCraft/BusinessAccount
├── registration.php
├── composer.json
└── etc
    └── module.xml
```

## `registration.php`

This registers the module with Magento:

```php
ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'BrewCraft_BusinessAccount',
    __DIR__
);
```

Without this file, Magento would not discover the module.

## `composer.json`

This defines:

* Composer package name
* Magento module type
* PHP compatibility
* Required Magento modules
* PSR-4 namespace mapping

The namespace:

```php
BrewCraft\BusinessAccount
```

maps to:

```text
app/code/BrewCraft/BusinessAccount
```

## `module.xml`

The module was configured to load after:

```text
Magento_Customer
```

because the business application depends on Magento customer records.

```xml
<sequence>
    <module name="Magento_Customer"/>
</sequence>
```

---

# 5. Business Application Database Design

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

# 6. Why We Used a Separate Table

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

# 7. Customer Relationship

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

# 8. Database Constraints

## One application per customer

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

## Unique business registration number

A unique constraint was also added to:

```text
registration_number
```

This prevents two applications from using the same legal company-registration number.

The PHP service performs a friendly validation first, while the database constraint remains the final protection against duplicates.

## Indexes

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

# 9. Business Account Model

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

# 10. Resource Model and Collection

## Resource model

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

## Collection

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

# 11. Repository Layer

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

# 12. Why We Used a Repository

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

# 13. Dependency Injection Preference

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

# 14. Repository Loading Methods

## Load by entity ID

```php
getById(int $entityId)
```

loads using:

```text
entity_id
```

## Load by customer ID

```php
getByCustomerId(int $customerId)
```

checks whether a specific Magento customer already has an application.

This method fulfills the business rule:

```text
A customer cannot submit multiple applications.
```

## Load by registration number

```php
getByRegistrationNumber(string $registrationNumber)
```

checks whether a legal company-registration number is already in use.

This fulfills the business rule:

```text
A registered company must not be duplicated.
```

---

# 15. Storefront Route

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

# 16. Registration Page Controller

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

# 17. Layout and Template Rendering

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

# 18. Duplicate Page Title Fix

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

# 19. Registration Form Block

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

# 20. Form Action

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

# 21. CSRF Protection

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

# 22. Business Registration Form Sections

The storefront form is divided into logical business sections.

## Company details

```text
Company Name
Business Registration Number
Tax / VAT Number
Company Type
Years in Business
```

## Primary contact

```text
First Name
Last Name
Business Email
Business Phone
```

## Business address

```text
Street
City
State / Region
Postcode
Country
```

## Account security

For guests only:

```text
Password
Confirm Password
```

## Review and submit

The applicant confirms that the provided information is accurate.

This structure matches a realistic business-onboarding form rather than a standard Magento retail registration form.

---

# 23. Country Options

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

# 24. Logged-In and Guest Behavior

## Logged-in customer

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

## Guest visitor

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

# 25. Frontend Validation

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

# 26. Save Controller

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

# 27. Why Business Logic Was Moved to a Service

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

# 28. Server-Side Validation

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

# 29. Data Normalization

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

# 30. Duplicate Registration Number Validation

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

# 31. Duplicate Customer Application Validation

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

# 32. Existing Guest Email Protection

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

# 33. Magento Customer Creation

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

# 34. Business Application Creation

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

# 35. Pending Status

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

# 36. Contact Name Mapping

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

# 37. Optional Data Handling

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

# 38. Partial Failure Protection

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

# 39. Customer Login After Registration

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

# 40. Success Page

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

# 41. Duplicate Success-Page Heading Prevention

As with the registration page, the default Magento title block is removed:

```xml
<referenceBlock name="page.main.title" remove="true"/>
```

The designed success-page heading is rendered in the template.

The controller title remains available for the browser tab.

---

# 46. Business Value Delivered

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

# 47. Current Business Flow

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

# 48. Current Completion Status

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

# BrewCraft Business Account Administration and Email
**DATE:** 25th July 

**Module:** `BrewCraft_BusinessAccount`
**Magento version:** Magento 2.4.7-based project
**Phase covered:** Storefront business registration, Admin application management, approval/rejection processing, customer-group assignment, and email notification trigger

---

# 1. Business problem we are solving

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

# 2. Complete workflow implemented so far

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

# 3. Magento areas involved

Magento separates functionality into application areas.

The main areas we used are:

```text
frontend
adminhtml
global
```

## Frontend area

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

## Adminhtml area

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

## Global configuration

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

# 4. Storefront customization — general explanation

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

# 5. Frontend route

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

# 6. Storefront Create controller

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

# 7. Storefront layout XML

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

# 8. Frontend block

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

# 9. Frontend template

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

# 10. Form security

The form includes Magento’s form key.

The form key protects against cross-site request forgery.

Without it, another website could potentially cause a logged-in browser to submit unwanted requests to Magento.

The form sends:

```text
form_key
```

and the Save controller validates it before processing any data.

---

# 11. Frontend validation

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

# 12. Save controller

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

# 13. Registration service

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

# 14. Guest and logged-in customer flows

## Guest customer

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

## Existing logged-in customer

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

# 15. Separate business application table

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

# 16. Business application statuses

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

# 17. Repository pattern

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

# 18. Admin customization — general explanation

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

# 19. Admin route

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

# 20. Admin ACL

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

# 21. Admin menu

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

# 22. Admin grid — general explanation

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

# 23. Grid layout

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

# 24. Listing UI Component

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

# 25. Grid data source and DI configuration

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

# 26. Admin status filter

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

# 27. Grid actions column

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

# 28. Admin Index controller

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

# 29. Admin application details page

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

# 30. Admin View controller

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

# 31. Magento Registry usage

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

# 32. Admin View block

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

# 33. Admin details template

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

# 34. Why Approve and Reject use POST

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

# 35. Business Customer group

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

# 36. Why a data patch was used

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

# 37. Why customer-group IDs are not hard-coded

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

# 38. Approval service

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

# 39. Approval flow

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

# 40. Approval consistency handling

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

# 41. Rejection flow

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

# 42. Review-state protection

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

# 43. Approve controller

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

# 44. Reject controller

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

# 45. Email trigger — general explanation

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

# 46. Email template registration

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

# 47. Email HTML templates

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

# 48. Email template variables

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

# 49. Email notifier class

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

## `TransportBuilder`

Builds the Magento email transport.

## `StoreManagerInterface`

Finds the relevant store view and generates the correct My Account URL.

## `CustomerRepositoryInterface`

Loads the linked Magento customer.

## `ScopeConfigInterface`

Reads sender-name and sender-email configuration.

## `LoggerInterface`

Writes email process information to Magento logs.

---

# 50. Constructor issue found and corrected

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

# 51. Email sender configuration

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

# 52. Email recipient resolution

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

# 53. Email store resolution

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

# 54. Why email happens after database updates

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

# 55. Why email failure does not undo approval

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

# 56. Email logging added

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

# 57. Meaning of “sent successfully”

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

# Development Log — Storefront Business Access and My Account Business Status
**Date:** 25 July

**Module:** `BrewCraft_BusinessAccount`
**Work completed:** Storefront business-registration entry points, customer-facing Business Account status page, My Account navigation integration, template troubleshooting, and Admin UI data-source DI correction.

---

# 1. Business objective of this phase

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

# 2. What was completed

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

# Part A — Business registration entry points

# 3. Previous limitation

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

# 4. Decision: keep personal and business registration separate

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

# 5. Extending existing Magento pages

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

# 6. Why layout XML was used

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

# 7. Reusable CTA template

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

# 8. Why one reusable template was used

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

# 9. Create Account page behavior

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

# 10. Login page behavior

The customer Login page also shows the Business Account CTA.

This fulfills two possible journeys.

## New customer

```text
Open Login page
        ↓
See Business Account option
        ↓
Open business registration form
        ↓
Create customer and business application together
```

## Existing customer

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

# 11. Temporary presentation versus final theme

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

# Part B — My Account Business Account status page

# 12. Business requirement

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

# 13. Customer-facing status scenarios

The page supports four states.

## State 1 — No application

```text
Status: Not Applied
```

The customer sees an explanation and an application button.

```text
Apply for a Business Account
```

## State 2 — Pending

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

## State 3 — Approved

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

## State 4 — Rejected

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

# 14. My Account controller

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

# 15. Why authentication is checked in the controller

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

# 16. My Account status block

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

# 17. Security of the customer application lookup

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

# 18. Application caching inside the block

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

# 19. My Account navigation link

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

# 20. Why `customer_account.xml` was used

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

# 21. Business Account page layout

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

# 22. Why the block is non-cacheable

The Business Account page contains customer-specific data.

We used:

```xml
cacheable="false"
```

This prevents full-page cache from serving one customer’s application information to another customer.

Customer-specific pages must be handled carefully because the displayed data changes by session.

---

# 23. Status helper methods

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

# 24. Customer-safe status descriptions

The block also provides a customer-facing explanation.

Examples:

## Pending

```text
Your application has been submitted and is currently
being reviewed by our business team.
```

## Approved

```text
Your BrewCraft Business Account is active.
```

## Rejected

```text
Your business application was not approved.
Your regular customer account remains active.
```

This converts backend status into understandable customer communication.

---

# 25. Status template

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

# 26. Information displayed to customers

The page displays several sections.

## Application Summary

```text
Application number
Status
Submitted date
Approved date, when approved
Last-updated date
```

## Company Information

```text
Company name
Registration number
Tax/VAT number
Company type
Years in business
```

## Primary Contact

```text
Contact name
Contact email
Contact phone
```

## Business Address

```text
Street
City
State/region
Postcode
Country
```

## Review Feedback

Displayed only for a rejected application with an Admin comment.

```text
Reason provided by our business team:
<Admin rejection reason>
```

---

# 27. Why Admin comments are conditionally displayed

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

# 28. Duplicate application protection remains active

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

# Part C — Problems encountered and fixed

# 29. Problem 1: Invalid template file

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

# 30. Template path rules learned

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

# 31. Problem 2: Admin All Customers grid failed

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

# 32. Understanding grid data-source registration

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

# 33. Initial custom grid DI configuration

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

# 34. Diagnostics performed

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

# 35. Root cause

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

# 36. DI fix

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

# 37. Why the grid mapping can remain global

Even though the BrewCraft collection is used by an Admin grid, registering the data-source collection globally is acceptable.

The mapping is only requested when the corresponding UI Component uses:

```text
businessaccount_application_listing_data_source
```

The existence of the mapping does not render or execute the Admin grid on storefront pages.

The important requirement is correct DI array merging.

---

# 38. Rebuilding compiled dependency injection

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

# 39. Important DI lesson

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

# 40. Why the two errors were unrelated

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

## Storefront error

```text
Cause:
Missing or incorrectly located .phtml template
```

## Admin error

```text
Cause:
Dependency-injection array mapping replaced in Admin area
```

This is an important debugging lesson: errors happening after the same development change are not automatically caused by the same file.

Each stack trace and affected area should be investigated independently.

---

# 41. Files added or changed in this phase

## Storefront entry points

```text
view/frontend/layout/customer_account_create.xml
view/frontend/layout/customer_account_login.xml
view/frontend/templates/account/business-registration-link.phtml
view/frontend/web/css/source/_module.less
```

## My Account Business page

```text
Controller/Account/Index.php
Block/Account/Index.php
view/frontend/layout/customer_account.xml
view/frontend/layout/businessaccount_account_index.xml
view/frontend/templates/account/index.phtml
```

## DI correction

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
