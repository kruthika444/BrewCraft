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
