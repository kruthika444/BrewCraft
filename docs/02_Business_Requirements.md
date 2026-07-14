# Business Requirements Document

## 1. Document Information

| Field     | Value                         |
|-----------|-------------------------------|
| Project   | BrewCraft Supply              |
| Document  | Business Requirements Document|
| Version   | 1.0                           |
| Status    | Draft                         |
| Author    | Kruthi                        |
| Date      | 07 July 2026                  |

## 2. Purpose
The purpose of this document is to define the business requirements for the BrewCraft Supply eCommerce platform. It captures the business needs, operational challenges, and expected system capabilities identified during the discovery workshops. This document serves as the primary reference for functional analysis, solution design, development, testing, and stakeholder validation throughout the project lifecycle.

## 3. Business Context

BrewCraft Supply is a growing supplier of specialty coffee products and commercial coffee equipment, serving both retail (B2C) and business (B2B) customers across India.

The company currently manages several business operations manually using spreadsheets, emails, and disconnected software systems. As the business continues to grow, these manual processes have become increasingly inefficient, resulting in delays, duplicated effort, and limited visibility across departments.

The objective of this project is to establish Magento as the central customer-facing eCommerce platform while integrating with existing business systems to improve operational efficiency, customer experience, and scalability.

## 4. Stakeholders

| Stakeholder      | Responsibility                            |
|----------------- |-------------------------------------------|
| Business Owner   | Project Sponsor                           |
| Sales Team       | Customer approval, quotations, pricing    |
| Warehouse Team   | Inventory and order fulfillment           |
| Customer Support | Customer enquiries and after-sales support|
| Marketing Team   | Product content and promotions            |
| Finance Team     | Payments and invoicing                    |
| IT Team          | System maintenance and integrations       |
| Development Team | Design, development and deployment        |
| Business Customer| Purchase products, request quotations, place bulk orders |
| Retail Customer  | Purchase prodicts through the online store |
## 5. Business Goals
- Reduce manual effort across sales, inventory, and order management.
- Improve customer experience for both B2C and B2B customers.
- Automate quotation approval and business workflows.
- Improve inventory accuracy and operational visibility.
- Increase online sales and customer retention.
- Support future business expansion through a scalable eCommerce platform.
- Centralize customer and order information across departments.

## 6. Business Requirements

### BR-001 - Product Synchronization

| Field             | Details                                 |
|-------------------|-----------------------------------------|
| Business Area     | Product Management                      |
| Priority          | High                                    |
| Stakeholders      | Warehouse Team, Marketing Team, IT Team |

#### Description

The platform shall synchronize product information, pricing, and inventory from the ERP system while preserving marketing-managed content within Magento.

#### Business Rules
- If a product is no longer available for sale, it should simply be disabled.
- ERP is the source of truth for products, pricing, and inventory.
- ERP can only mark products as inactive. ERP sends product status. 
- Product information shall synchronize every hour.
- Inventory shall synchronize every 15 minutes.
- Marketing-managed content (images, videos, buying guides) must not be overwritten during synchronization.
- Temporarily unavailable → Show as Out of Stock.
- Permanently discontinued → Hidden from storefront.
#### Business Value

- Eliminates duplicate product maintenance.
- Ensures accurate stock availability.
- Reduces manual work.
- Keeps marketing content independent from ERP.

#### Dependencies

- ERP System
- Warehouse Operations

#### Out of Scope

- Product creation inside Magento.
- Inventory updates from Magento to ERP.
- "Notify me when available"option in future 
#### Risks

- ERP synchronization failure may result in outdated stock information.
- Incorrect synchronization timing may affect customer orders.

#### Assumptions

- ERP data is accurate.
- ERP APIs are available.
- Warehouse updates inventory in ERP. 

### BR-002 - customer registrations

| Field             | Details                                 
|-------------------|-----------------------------------------
| Business Area     | Customer registration                      
| Priority          | High                                    
| Stakeholders      | Business customer,reatil customer,sales teamcustomer support, IT Team 

#### Description
The platform shall support two types of customer registration — Retail and Business. Retail customers can self-register using basic personal details and gain immediate access. Business customers must submit company-specific information and undergo an internal approval process before accessing wholesale pricing and business-level features.

#### Business Rules
**Retail Customer**
- Registration is self-service.
- Account becomes active immediately after successful registration.

**Business Customer**
- Registration requires company information.
- Account status is initially 
- If GST/ email already exists, reject registartion, and inform customer account already exists
- One company can have 1 login only
**Pending**.
- Business features remain unavailable until approval.
- Only approved business customers can view wholesale pricing.
- Sales should review every registration within one business day.

#### Business Value
- Structured dual registration ensures the right access level is granted to the right customer type.
- Approval workflow protects wholesale pricing from unauthorized access.
- Capturing GST details at registration supports accurate invoicing and compliance.

#### Dependencies
- Customer Management
- Business Customer Approval Process
- Wholesale Pricing Policy
- Notification Process

#### Out of Scope
- Social media login or SSO.
- OTP-based mobile verification.
- Automated GST validation against government databases.
- multiple login for business account - phase 2

#### Risks
- Business customers submitting incomplete registration details
- Delays in business account approval impacting customer onboarding
- Duplicate business registrations using the same GST number.

#### Assumptions
- Each business customer has a valid GST number.
- Retail customers are individual consumers.
- Business account approval is performed by an authorized Sales team member.

#### Success Criteria

- Retail customers can successfully register and access the storefront immediately.
- Business customers remain in Pending status after registration.
- Business customers cannot access wholesale pricing before approval.
- Approved business customers gain access to business features.

### BR-004 - Order Processing

| Field | Details |
|-------|---------|
| Business Area | Order processing |
| Priority | High |
| Stakeholders | Business customer,retail customer, Sales Team, Warehouse Team, Finance Team, IT Team|

#### Description
The platform shall support two distinct order flows based on customer type. Retail customers follow a standard checkout process. Business customers have the additional ability to request a formal quotation or submit a purchase order, accommodating the structured procurement processes typical in B2B transactions.

#### Business Rules
- Customer can purchase from previous orders
**Retail Customer**
- Retail customers follow a standard checkout process — cart, delivery details, payment, and order confirmation.

**Business Customer**
- Business Customer Registration & Approval
- Business customers may place orders via standard checkout, request a quotation, or submit a purchase order.
- Quotation requests and purchase orders are only available to approved business accounts.
- Purchase orders are reviewed internally before order fulfillment begins.

#### Business Value
- Supporting quotation and purchase order workflows aligns the platform with standard B2B procurement practices.
- Reduces manual effort for the sales team by managing business order requests through the platform.
#### Dependencies
- Business account must be approved before accessing quotation or purchase order features.
- Quote Management module must be configured and operational.
#### Assumptions
- Approved business customers understand the quotation process.
- Sales team reviews quotation requests during business hours.
#### Risks
- Business customers attempting to place orders before account approval
- Delayed quotation approval may result in lost business opportunities.
- Quotation requests not actioned promptly by the sales team
#### Out of Scope
- Automated quotation generation without sales team review.
- Integration with external procurement or ERP systems (unless specified separately).
- Partial spayments are not allowed in phase1 
#### Success Criteria
- Retail customers can complete a standard checkout without assistance.
- Approved business customers can successfully submit a quotation request or purchase order through the platform.
- All business order requests are routed correctly to the responsible internal team.

### BR-03 - Customer Data Migration

| Field | Details |
|-------|---------|
| Business Area | Customer Data Migration|
| Priority | High |
| Stakeholders |Customer support,Business owner, IT Team, Sales Team, Marketing Team |

#### Description
Existing customer data is currently distributed across multiple sources including Excel, CRM, POS, and the previous website. A structured data migration exercise must be completed before the platform goes live to ensure all customer records are accurately available in the new system.

#### Business Rules
- All existing customer data must be consolidated and migrated from Excel, CRM, POS, and the previous website into the new platform.
- Migration must be completed and validated before go-live.
- Each migrated customer must be mapped to the appropriate account type — Retail or Business.
- For existing customer - After launch, customers will receive an email asking them to reset their password.
#### Business Value
- Ensures continuity of customer relationships without requiring existing customers to re-register.
- Provides the sales and support teams with a complete and accurate customer base from day one.
- Eliminates data silos by consolidating records into a single platform.
#### Dependencies
- Customer account types (Retail and Business) must be defined and configured in the platform before migration begins.
- Data mapping must be agreed upon across all source systems prior to migration execution.
- Migration must be completed before user acceptance testing (UAT) begins.
- Source data must be available from all legacy systems.
- Customer registration model must be finalized.
- Data mapping rules must be approved.
#### Assumptions
- The business will provide clean and complete data exports from all source systems.
- Duplicate customer records across sources will be identified and resolved before migration.
- Every customer record has a unique identifier (Email or Customer ID).
- IT team is responsible for executing the migration.
#### Risks
- Incomplete or inconsistent data across source systems
- Duplicate customer records causing conflicts post-migration
- Migration delays impacting go-live timeline
#### Out of Scope
- Real-time sync between legacy systems and the new platform post-migration.
- Migration of transactional history such as past orders or invoices (unless specified separately).
#### Success Criteria
- All existing customer records are successfully migrated and accessible in the new platform before go-live.
- Migrated customers can log in and access their accounts without re-registering.
- No critical data loss or corruption identified during post-migration validation.

### BR-05 - Customer Support

| Field | Details |
|-------|---------|
| Business Area |Customer Support |
| Priority | High |
| Stakeholders | Support Team, Sales Team, IT Team |

#### Description
Customers currently contact the business by phone for queries related to spare part compatibility, delivery status, warranty, and installation. There is no centralised system in place to view customer interaction history, making it difficult for support staff to provide consistent and informed assistance.

#### Business Rules
- Support staff require access to a consolidated customer history to efficiently respond to customer enquiries.
- Support staff must be able to view relevant customer information from a single location.
#### Business Value
- A centralised customer history view reduces resolution time and improves the quality of support provided.
- Eliminates dependency on individual staff memory or fragmented records when handling customer calls.
- Improves customer satisfaction through faster and more informed responses.
#### Dependencies
- Order and quotation data must be accessible within the support interface.
- Customer data migration (BR-004) must be completed so historical records are available to support staff.
#### Assumptions
- Customer support is currently handled via phone and will continue to be so in the near term.
- A centralised customer history view is sufficient; a full helpdesk ticketing system is not required unless specified separately.
#### Risks
- Support staff not adopting the new centralised view
- Customer interaction history incomplete due to data migration gaps
#### Out of Scope
- Live chat or chatbot support functionality.
- Automated call logging or telephony integration.
- Full helpdesk or ticketing system (unless specified separately).
#### Success Criteria
- Support staff can view a complete customer history — orders, quotations, and interactions — from a single interface.
- Average call handling time is reduced following platform go-live.

### BR-06 - Quotation Approval

| Field | Details |
|-------|---------|
| Business Area | Quotation Approval |
| Priority | high |
| Stakeholders | Sales Team, Finance Team, IT Team |

#### Description
The platform shall enforce a defined approval workflow for quotations submitted by business customers. Quotations meeting specific criteria shall be automatically approved, while all others must go through a manual manager review before being issued to the customer.

#### Business Rules
- A quotation is automatically approved if all of the following conditions are met:
    - The customer is an existing customer, and
    - The customer holds Gold Partner status, and
    - The quotation value is below ₹2,00,000 (2 Lakhs).
- If any one of the above conditions is not met, the quotation must be routed to a manager for manual approval.
- Quotations must not be issued to the customer until approval — automatic or manual — is confirmed.
- An existing customer is a customer with at least one completed order.
- Sales should review quotations within 8 business hours.
- Every quotation is valid for 30 calendar days, after that create new quoation
#### Business Value
- Automatic approval for trusted Gold Partners reduces turnaround time and improves their experience.
- Manual approval for higher value or non-qualified quotes ensures financial oversight and reduces business risk.
- Formalising the approval process eliminates ad hoc decision making and creates an auditable trail.
#### Dependencies
- Gold Partner classification must be configured and assigned correctly in the platform.
- Manager approval interface must be available and accessible.
- Quotation value must be calculable by the system at the point of submission.
#### Assumptions
- A customer is considered "existing" if their account is active and has prior order history in the platform.
- Only one level of manager approval is required — no escalation workflow is needed at this stage.
- The ₹2,00,000 threshold applies to the total quotation value inclusive of all line items.
#### Risks
- Gold Partner status not updated in time, causing incorrect auto-approvals
- Manager not actioning pending approvals promptly
- Quotation value threshold misapplied due to tax or discount calculation differences
#### Out of Scope
- Multi-level approval chains.
- Automatic rejection of quotations — all non-auto-approved quotes go to manager review, not rejection.
#### Success Criteria
- Quotations meeting all three auto-approval criteria are approved instantly without manual intervention.
- All other quotations are correctly routed to the manager for review.
- No quotation is issued to a customer without a confirmed approval status.

### BR-07 - Order Fulfillment Process

| Field | Details |
|-------|---------|
| Business Area | Order Fulfillment Process |
| Priority | high |
| Stakeholders | Warehouse Team, Sales Team, IT Team, Business customer |

#### Description
Upon successful order placement, the fulfillment process involves a series of manual steps carried out by the warehouse team — from invoice printing through to courier handover and customer notification. The current process relies heavily on manual effort and Excel-based tracking, which this platform must support and where possible streamline.

#### Business Rules
- Upon order placement, the warehouse team shall receive an automated email notification.
- The warehouse team manually prints the invoice for the order.
- Products are packed by warehouse staff following invoice receipt.
- The courier partner collects the packed order from the warehouse.
- The tracking number provided by the courier must be recorded against the order.
- Once the tracking number is recorded, the customer shall receive an automated email notification containing shipment details.
#### Business Value
- Automated warehouse notification eliminates delays caused by manual communication.
- Recording tracking numbers against orders replaces Excel-based tracking and provides a single source of truth.
- Automated customer shipment notification improves transparency and reduces inbound delivery status queries to the support team.
#### Dependencies
- Email notification service must be configured and operational.
- Order management interface must allow warehouse staff to record tracking numbers against orders.
- Courier partner integration or manual tracking number entry must be supported by the platform.
#### Assumptions
- Invoice printing remains a manual activity performed by the warehouse team.
- Packing and courier handover remain manual physical processes.
- Tracking numbers are provided by the courier partner and entered manually into the platform by warehouse staff.
#### Risks
- Warehouse staff not recording tracking numbers promptly
- Customer notification sent before tracking number is confirmed
- Warehouse email notification not received due to configuration issues
#### Out of Scope
- Automated courier partner integration or real-time tracking updates.
- Automated invoice generation and dispatch to customers.
- Warehouse management system (WMS) integration.
#### Success Criteria
- Warehouse team receives an email notification immediately upon every order placement.
- Tracking numbers are recorded against orders in the platform, replacing Excel tracking.
- Customers receive an automated shipment notification email once the tracking number is recorded.

### BR-08  -  ERP Synchronization

| Field | Details |
|-------|---------|
| Business Area |  ERP Synchronization |
| Priority | high |
| Stakeholders | IT Team, Warehouse Team, Marketing Team |

#### Description
Magento shall synchronize product, pricing, and inventory data from the ERP system on a scheduled basis. Synchronization frequencies differ based on data type to balance system performance with data accuracy. Marketing-managed content within Magento must be preserved and must not be overwritten during any synchronization cycle.

#### Business Rules
- Product and pricing data shall synchronize from the ERP to Magento every hour.
- Inventory levels shall synchronize from the ERP to Magento every 15 minutes.
- Marketing-managed content in Magento — including banners, descriptions, and promotional content — must not be overwritten or affected during synchronization.
- if ERP is unavaialble, retry automatically 3times
- if still unsuccesful, notify IT, operation team, and record the failure
#### Business Value
- Frequent inventory synchronization reduces the risk of overselling out-of-stock products.
- Hourly pricing updates ensure customers always see accurate and current pricing.
- Preserving marketing content during sync prevents disruption to campaigns and storefront presentation.
#### Dependencies
- ERP system must expose product, pricing, and inventory data via an API or agreed integration method.
- IT team must define and document which fields are ERP-managed versus Magento-managed to prevent content conflicts.
- Synchronization schedule must be configured and tested in staging before go-live.
#### Assumptions
- The ERP is the single source of truth for product data, pricing, and inventory levels.
- Marketing content in Magento is managed exclusively within Magento and does not originate from the ERP.
- The ERP system will be available and stable enough to support the defined synchronization frequencies.
#### Risks
- ERP downtime causing synchronization failures and stale data in Magento
- Marketing content overwritten during synchronization
- High synchronization frequency causing performance impact on Magento or ERP
#### Out of Scope
- Real-time (sub-15-minute) inventory synchronization.
- Bi-directional synchronization — data flows from ERP to Magento only.
- Order data synchronization back to ERP (unless specified separately).
#### Success Criteria
- Product and pricing data in Magento reflects ERP data within one hour of any change.
- Inventory levels in Magento reflect ERP data within 15 minutes of any change.
- No marketing-managed content is overwritten during any synchronization cycle.
- Synchronization failures are logged and the IT team is alerted promptly.

### BR-09 - Platform Access for Pending Business Accounts

| Field | Details |
|-------|---------|
| Business Area | Customer Management |
| Priority | high|
| Stakeholders | Sales Team, IT Team |

#### Description
Business customer accounts that have not yet been approved shall have limited access to the platform. This ensures that wholesale pricing and business-level features are protected until the account has been formally reviewed and approved by the responsible internal team.

#### Business Rules
- Business accounts in a pending approval state shall be permitted to browse the platform with restricted access.
- Pending accounts shall not have visibility of wholesale pricing.
- Pending accounts shall not be able to place orders, request quotations, or submit purchase orders.
- Full access shall only be granted upon formal approval of the business account.
#### Business Value
- Prevents unauthorized access to wholesale pricing prior to account verification.
- Allows prospective business customers to familiarize themselves with the platform while their account is under review.
#### Dependencies
- Business account approval workflow must be in place and operational (refer BR-002).
- Platform access controls must be configurable based on account status.
#### Assumptions
- Pending accounts are aware of their restricted status upon registration.
- The transition from pending to approved access is applied automatically upon approval.
#### Risks
- Wholesale pricing visible to pending accounts due to misconfigured access controls
#### Out of Scope
- Partial or tiered access levels for pending accounts beyond basic browsing.
- Automated approval based on registration details.
#### Success Criteria
- Pending business accounts can browse the platform but cannot view wholesale pricing or place orders.
- Full access is correctly granted immediately upon account approval.

### BR-10 -  Gold Partner Program

| Field | Details |
|-------|---------|
| Business Area |Customer management |
| Priority | meduim |
| Stakeholders | Sales Team, Finance Team, IT Team |

#### Description
The Gold Partner program recognizes long-standing, high-value business customers who meet defined eligibility criteria. Gold Partners receive a set of exclusive benefits designed to reward loyalty and encourage continued business growth. Eligibility is reviewed annually by the Sales Management team.

#### Business Rules
**Eligibility Criteria — all of the following must be met:**
- The customer has been active for a minimum of 12 months.
- The customer has purchased a minimum of ₹10,00,000 (10 Lakhs) worth of products in the last financial year.
- The customer has no outstanding payments at the time of review.

**Review Process:**
- Gold Partner status is reviewed annually by the Sales Management team.
- Status may be revoked if eligibility criteria are no longer met at the time of annual review.

**Gold Partner Benefits:**
- Preferential pricing
- Faster quotation approvals
- Priority support
- Dedicated account manager assignment
**lifecycle**
- Reviewed every financial year.
- Loss of eligibility means removal.
- Must continue to satisfy:
    - ₹10 lakh annual purchases
    - No overdue payments
    - Active business relationship
#### Business Value
- Incentivizes long-term customer retention and higher purchase volumes.
- Rewards financially reliable customers, reducing payment risk.
- Strengthens strategic relationships with key business accounts.
#### Dependencies
- Quotation approval workflow must recognize Gold Partner status (refer BR-006).
- Pricing configuration must support Gold Partner specific pricing tiers.
- Sales Management must have access to an interface to assign and revoke Gold Partner status.
#### Assumptions
- Gold Partner status is assigned manually by the Sales Management team following the annual review.
- Customers are not able to self-apply or self-upgrade to Gold Partner status.
- The financial year used for purchase value calculation aligns with the company's defined financial year.
#### Risks
- Gold Partner status not updated promptly after annual review
- Incorrect pricing applied due to Gold Partner status misconfiguration
- Customers unaware of Gold Partner criteria or benefits
#### Out of Scope
- Automated assignment of Gold Partner status based on system-calculated criteria.
- Tiered partner levels beyond Gold (e.g. Silver, Platinum).
#### Success Criteria
- Gold Partner status is correctly reflected in the platform and applied to pricing, quotations, and support prioritization.
- Annual review process is supported by a clear internal workflow with assigned ownership.
- Gold Partner benefits are consistently applied across all relevant platform features.


### BR-11 - Registration Notification

| Field | Details |
|-------|---------|
| Business Area | Customer management|
| Priority | medium|
| Stakeholders | Sales Team, IT Team |

#### Description
Upon the completion of a business account approval or rejection, the customer shall receive an automated email notification informing them of the outcome. Rejection notifications must include contact details to allow the customer to seek further clarification or assistance.

#### Business Rules
- Upon approval of a business account, the customer shall receive an automated email confirming that their account has been approved and that full access has been granted.
- Upon rejection of a business account, the customer shall receive an automated email informing them that their registration was not approved.
- The rejection email must include contact details for the customer to reach out for further assistance.
#### Business Value
- Keeps customers informed at every stage of the registration process, improving transparency and trust.
- Reduces inbound support queries from customers waiting on registration outcomes.
- Ensures rejected applicants have a clear path to seek assistance rather than disengaging entirely.
#### Dependencies
- Email notification service must be configured and operational.
- Business account approval workflow must be in place (refer BR-002).
- Contact details to be included in the rejection email must be confirmed and provided by the business.
#### Assumptions
- Approval and rejection decisions are made manually by an internal team member.
- Email notifications are triggered automatically by the platform upon status change.
- A single point of contact or team email address will be provided for inclusion in rejection emails.
#### Risks
- Notification emails not delivered due to configuration or spam filtering issues
- Rejection email missing contact details due to incomplete configuration
#### Out of Scope
- SMS or push notification alternatives to email.
- Automated approval or rejection decisions — human review is required.
#### Success Criteria
- Approved business customers receive a confirmation email immediately upon account approval.
- Rejected applicants receive an email with a clear explanation and contact details for further assistance.
- No business account status change goes without a corresponding customer notification.

### BR-12 - Payment Methods

| Field | Details |
|-------|---------|
| Business Area | Payment Methods |
| Priority |high |
| Stakeholders | Finance Team, Sales Team, IT Team, business owner|

#### Description
The platform shall support distinct payment methods for Retail and Business customers. Each customer type shall only see the payment options relevant to them at checkout. Business customers additionally have access to structured payment methods suited to corporate procurement workflows.

#### Business Rules
**Retail Customer Payment Methods:**
    - UPI
    - Credit / Debit Card
    - Net Banking
    - Cash on Delivery — available on selected products only

**Business Customer Payment Methods:**
    - Bank Transfer
    - Purchase Order (PO)
    - Invoice Payment — available to approved corporate customers only

**Access Rules:**
    - Retail customers shall only see retail payment options at checkout.
    - Business customers shall only see business payment options at checkout.
    - Cash on Delivery shall be restricted to selected products and must not appear for ineligible products.
    - Invoice Payment shall only be available to business customers whose accounts have been approved for corporate invoicing.
**payment failure**
    -  Order remains Pending Payment.
    - Customer can retry payment.
    - If payment is not completed within 24 hours, the order is automatically cancelled.
#### Business Value
- Presenting relevant payment options per customer type reduces confusion and improves checkout experience.
- Supporting PO and invoice payment aligns with standard B2B procurement processes, reducing friction for business customers.
- Restricting Cash on Delivery to eligible products protects the business from operational risk on high-value or commercial items.
#### Dependencies
- Payment gateway must support UPI, Credit/Debit Card, and Net Banking for retail transactions.
- Bank Transfer and Invoice Payment workflows must be configured for business accounts.
- Product-level configuration must support Cash on Delivery eligibility flags.
- Business account approval status must be accessible at checkout to determine Invoice Payment eligibility.
#### Assumptions
- Payment method visibility is controlled by customer account type, not by customer choice.
- Invoice Payment terms and credit limits for approved corporate customers are managed offline and reflected in the platform by the Finance team.
- Cash on Delivery eligibility per product is configured and maintained by the IT or catalog team.
#### Risks
- Incorrect payment methods displayed due to customer type misconfiguration
- Cash on Delivery available on ineligible products
- Invoice Payment accessible to unapproved business accounts
#### Out of Scope
- EMI or buy now pay later options.
- Cryptocurrency or wallet-based payments beyond UPI.
- Automated credit limit enforcement for Invoice Payment customers.

#### Success Criteria
- Retail customers see only retail payment options at checkout.
- Business customers see only business payment options at checkout.
- Cash on Delivery appears only for eligible products.
- Invoice Payment is accessible only to approved corporate accounts.


### BR-13 - Shipping Rules

| Field | Details |
|-------|---------|
| Business Area | Shipping Rules |
| Priority | high |
| Stakeholders | Warehouse Team, Sales Team, Finance Team, IT Team|

#### Description
Shipping charges shall be calculated based on a combination of customer type, product type, order value, and any active promotional rules. Retail and Business customers follow different shipping structures, and certain product categories such as commercial espresso machines are subject to additional rules regardless of customer type or order value.

#### Business Rules
**Retail Shipping:**
- Free shipping is applied on retail orders with a total value above ₹2,500.
- Orders below ₹2,500 are subject to standard shipping charges.
**Business Shipping:**
- Shipping terms for business customers are determined by their individual contract terms.
- Standard shipping rules do not apply to business customers unless specified in their contract.
**Commercial Espresso Machines:**
- Free shipping shall never apply to commercial espresso machines regardless of order value, customer type, or active promotions.
- Installation charges may apply and shall be calculated separately.
**Promotional Shipping Rules:**
- Active promotional campaigns may temporarily modify standard shipping rules.
- Promotional shipping rules shall not override the commercial espresso machine shipping restriction.
**General Rule:**
- Shipping charges shall be determined based on all of the following factors: customer type, product type, order value, and active promotional rules.
- If products are shipped from different warehouses or become available on different dates, multiple shipments are allowed.
#### Business Value
- Free shipping threshold for retail customers incentivizes higher order values.
- Contract-based shipping for business customers supports flexible and negotiated commercial arrangements.
- Enforcing shipping rules for commercial machines protects margin on high-cost, logistics-intensive products.
#### Dependencies
- Product catalog must support product-type classification to identify commercial espresso machines.
- Customer type must be accessible during shipping calculation at checkout.
- Promotional rules engine must support temporary shipping rule overrides with defined exceptions.
- Business customer contract terms must be configured in the platform or managed via a linked process.
#### Assumptions
- Business customer shipping terms are agreed upon offline and configured in the platform by the Sales or IT team.
- Installation charges for commercial machines are a separate line item and not included in the product price.
- Only one promotional shipping rule is active at any given time unless otherwise specified.
#### Risks
- Free shipping incorrectly applied to commercial espresso machines
- Promotional rules overriding commercial machine shipping restriction
- Business customer shipping charges misconfigured due to incomplete contract data
#### Out of Scope
- Real-time shipping rate calculation via courier API integration.
- Shipping rules for product categories other than those defined above.
- International shipping.
#### Success Criteria
- Free shipping is correctly applied to retail orders above ₹2,500 and withheld below that threshold.
- Business customer shipping charges reflect their individual contract terms.
- Commercial espresso machines are never eligible for free shipping under any condition.
- Promotional shipping changes apply correctly without affecting commercial machine restrictions.

### BR-14 - Returns and Refunds

| Field | Details |
|-------|---------|
| Business Area | return management |
| Priority | high|
| Stakeholders | Warehouse Team, Finance Team, Sales Team, IT Team |

#### Description
The platform shall support a structured returns and refund process with rules that differ by customer type and product category. Refunds are only processed following a physical inspection by the warehouse team. Commercial machines are subject to stricter return restrictions, and warranty claims for such products follow a separate process.

#### Business Rules
**Retail Returns:**
- Returns are accepted within 7 days of delivery for unused products only.
- for refund - Refund back to original payment method.
**Business Returns:**
- Returns are accepted only in cases of damaged or incorrect deliveries.
- Change-of-mind or surplus returns are not accepted for business customers.
- refund: Credit Note. Finance may later settle according to contract.
**Commercial Machines:**
- No returns are accepted after installation.
- Warranty claims for commercial machines follow a separate warranty process and are not treated as returns.
**Refund Processing:**
- Refunds shall only be initiated after the returned product has been inspected by the warehouse team.
- Refund approval is subject to the returned item meeting the applicable return conditions.
#### Business Value
- Clear return policies per customer type reduce disputes and set accurate expectations.
- Warehouse inspection prior to refund processing protects the business from fraudulent or unjustified return claims.
- Separating warranty claims from returns ensures commercial machine after-sales support is handled through the correct process.
#### Dependencies
- Warehouse team must have an interface to record inspection outcomes against return requests.
- Refund workflow must be triggered by warehouse inspection approval, not by return request submission alone.
- Warranty claim process must be defined and documented separately (refer to Maintenance BRD).
- Return eligibility rules must be enforced based on customer type and product category at the point of return request.
#### Assumptions
- The 7-day return window for retail customers begins from the date of delivery, not the date of order.
- Warehouse inspection is a manual process carried out by warehouse staff.
- Refunds are issued to the original payment method unless otherwise agreed.
- The warranty claim process for commercial machines is handled outside this BRD.
#### Risks
- Returns accepted beyond the eligible window due to system misconfiguration
- Refunds processed without warehouse inspection
- Commercial machine returns accepted post-installation
#### Out of Scope
- Warranty claim processing — covered under a separate process.
- Automated refund processing without warehouse inspection.
- Partial returns or partial refunds (unless specified separately).
#### Success Criteria
- Retail return requests submitted beyond 7 days or for used products are rejected by the platform.
- Business return requests are only accepted for damaged or incorrect deliveries.
- No return request is accepted for a commercial machine post-installation.
- Refunds are only processed after confirmed warehouse inspection approval.


### BR-15 - Maintenance Scheduling

| Field | Details |
|-------|---------|
| Business Area | Maintenance Scheduling |
| Priority |medium |
| Stakeholders | Sales Team, Warehouse Team, Support Team, IT Team|

#### Description
The platform shall support after-sales service management for commercial machines. Every commercial machine sold must be associated with a structured post-sale workflow covering installation, warranty registration, preventive maintenance reminders, and an ongoing service history record.

#### Business Rules
- Every commercial machine sold shall trigger the following post-sale activities:
    - Installation scheduling
    - Warranty registration
    - Preventive maintenance reminders every 6 months from the date of installation
    - Maintenance of a full service history record against the machine
- The platform shall allow customers to submit service requests.
- The platform shall allow internal teams to log and view maintenance history per machine.
- Preventive maintenance reminders shall be sent automatically at the defined 6-month interval. Customer chooses whether to book service.
#### Business Value
- Structured post-sale support improves customer satisfaction and retention for high-value commercial machine purchases.
- Automated maintenance reminders reduce the risk of machines falling out of service compliance, protecting the business from warranty disputes.
- A centralised service history enables the support team to resolve issues faster and with full context.
#### Dependencies
- Commercial machine products must be flagged in the catalog to trigger the post-sale workflow upon order completion.
- Email notification service must be operational for maintenance reminder delivery.
- An interface must be available for internal teams to log service visits and maintenance activity.
- Installation scheduling process must be defined and linked to the order fulfillment workflow.
#### Assumptions
- Preventive maintenance reminders are sent via email to the customer.
- The 6-month reminder cycle begins from the date of installation, not the date of purchase.
- Service requests submitted through the platform are handled by the internal support team.
- Warranty registration is completed by the internal team upon installation confirmation.
#### Risks
- Maintenance reminders not triggered due to missing installation date
- Service history not consistently updated by the support team
- Commercial machines not flagged correctly in the catalog, bypassing post-sale workflow
#### Out of Scope
- Automated service scheduling without internal team involvement.
- Integration with third-party field service management tools.
- Warranty claim processing workflow (unless specified separately).
#### Success Criteria
- Every commercial machine order triggers installation scheduling, warranty registration, and the 6-month maintenance reminder cycle.
- Customers receive preventive maintenance reminders automatically at 6-month intervals from installation date.
- Internal teams can log and view a complete service history for every commercial machine sold.
- Customers can submit service requests through the platform.



### BR-16 - Reports and Analytics

| Field | Details |
|-------|---------|
| Business Area |Reports and Analytics |
| Priority |medium |
| Stakeholders | Sales Team, Warehouse Team, Management, Marketing Team, IT Team|

#### Description
The platform shall provide role-based operational reports accessible on demand. A defined subset of reports shall also be delivered automatically via email on a daily, weekly, or monthly schedule. Each team shall only have access to reports relevant to their function.

#### Business Rules
**Report Access — Role Based:**
- Each team shall only have access to reports within their designated scope.
**Sales Team Reports:**
- Daily sales summary — delivered daily
- Pending quotations — available on demand
- Top customers — available on demand
**Warehouse Team Reports:**
- Low stock alerts — delivered daily
- Backordered products — available on demand
- Dispatch status — available on demand
- in excel sheets
**Management Reports:**
- Revenue summary — delivered monthly
- Profit trends — delivered monthly
- Top-selling categories — available on demand
- Monthly growth — delivered monthly
- in pdf formate
**Marketing Team Reports:**
- Abandoned carts — delivered daily
- Coupon usage — available on demand
- Best-performing promotions — available on demand
- in csv formate reports
**Scheduled Delivery:**
- Scheduled reports shall be delivered automatically via email to the relevant team at the defined frequency.
- On-demand reports shall be accessible through the platform interface at any time.
#### Business Value
- Role-based access ensures each team has visibility of the data relevant to their function without information overload.
- Scheduled report delivery reduces manual effort in compiling and distributing operational data.
- On-demand access empowers teams to make timely, data-driven decisions without dependency on IT.
#### Dependencies
- Email notification service must be operational for scheduled report delivery.
- Role-based access control must be configured per team in the platform.
- Underlying order, inventory, quotation, and promotional data must be accurate and up to date for reports to be reliable.
#### Assumptions
- Scheduled reports are delivered to a defined team email address or individual email addresses configured by the IT team.
- Report formats are standard platform exports — custom visualizations or BI tool integrations are out of scope.
- Report scheduling frequencies are fixed as defined above and are not configurable by end users.
#### Risks
- Scheduled reports not delivered due to email service issues
- Inaccurate report data due to sync or data quality issues
- Incorrect role-based access allowing teams to view restricted reports
#### Out of Scope
- Custom report builder for end users.
- BI tool integration (e.g. Power BI, Tableau).
- Real-time dashboards.
- Historical data reporting prior to platform go-live.
#### Success Criteria
- Each team can access only the reports assigned to their role.
- Scheduled reports are delivered automatically at the correct frequency without manual intervention.
- On-demand reports reflect accurate and current platform data.

### BR-17 -  Promotions and Discounts

| Field | Details |
|-------|---------|
| Business Area | Marketing / Promotions |
| Priority | Medium |
| Stakeholders | Marketing Team, Finance Team, IT Team |

#### Description
The platform shall support a defined set of promotional and discount mechanisms applicable to eligible customers. Certain promotional features are confirmed for the initial launch while others are deferred to Phase 2. All promotions are managed exclusively by the Marketing team.

#### Business Rules
- Coupon codes — customers can apply a valid coupon code at checkout to receive a discount.
- Festival offers — time-bound promotional discounts tied to festivals or seasonal campaigns.
- Category discounts — discounts applied to all products within a defined category.
- Customer group discounts — discounts applied based on the customer's assigned group (e.g. retail, business, Gold Partner).
- First order discount — a 10% discount applied automatically on a customer's first order, capped at ₹500.
- Promotions are managed exclusively by the Marketing team.
- Multiple promotions may be active simultaneously; stacking rules must be defined and configured by the Marketing team before go-live.
- First order discount applies once per customer account and cannot be reused.

#### Business Value
- Targeted promotions drive customer acquisition, retention, and higher order values.
- Customer group discounts support differentiated pricing strategies for retail and business segments.
- First order discount lowers the barrier to first purchase and encourages new customer conversion.

#### Dependencies
- Customer group configuration must be in place for group-based discounts to apply correctly.
- Coupon code management interface must be available to the Marketing team.
- First order status must be trackable per customer account to enforce single-use eligibility.
- Promotional rules engine must support all Phase 1 discount types.

#### Assumptions
- Promotion configuration and activation is the sole responsibility of the Marketing team.
- The first order discount is applied automatically by the platform and does not require a coupon code.
- Festival offer dates and discount values are defined and configured by the Marketing team ahead of each campaign.
- Promotion stacking rules will be defined by the Marketing team prior to go-live.

#### Risks
- First order discount applied more than once per customer
- Unintended discount stacking resulting in excessive discounts
- Promotions applied to ineligible products or customer groups

#### Out of Scope
- Buy X Get Y promotions — Phase 2.
- Loyalty offers and loyalty program — Phase 2.
- Automated promotion suggestions or AI-driven personalization.

#### Success Criteria
- All Phase 1 promotion types function correctly and apply the expected discount at checkout.
- First order discount applies automatically and only once per customer account, capped at ₹500.
- Promotions are only manageable by the Marketing team.
- No unintended discount combinations are possible without explicit configuration.

### BR-18 - Roles, Permissions and Access Control

| Field | Details |
|-------|---------|
| Business Area | Security / Access Management |
| Priority | High  |
| Stakeholders | IT Team, Sales Team, Finance Team, Marketing Team, Management |

#### Description
The platform shall enforce role-based access control across all critical business functions. Each role shall have clearly defined permissions that restrict actions to those relevant to their function. Certain actions are restricted to specific roles only, and pricing management is controlled exclusively via ERP with no manual override permitted within Magento.

#### Business Rules
**Business Account Approval:**
- Only Sales Managers may approve or reject business customer registrations.

**Quotation Approval:**
- Sales Managers may approve standard quotations.
Regional Managers are required to approve high-value quotations above ₹10,00,000 (10 Lakhs).

**Product Pricing:**
- No internal user may modify product pricing within Magento.
All pricing is sourced exclusively from the ERP and updated via synchronization.

**Shipping Rule Changes:**
- Shipping rules may only be modified by Marketing Managers or Operations Managers.

**Report Access:**
- Sales team — Sales reports only.
- Warehouse team — Inventory reports only.
- Marketing team — Marketing reports only.
- Finance team — Revenue reports only.
- Management — Full access to all reports.

**Promotion Management:**
- Promotions may only be created, modified, or deactivated by the Marketing team.

#### Business Value
- Enforcing role-based permissions reduces the risk of unauthorized changes to critical business data.
- Restricting pricing changes to ERP eliminates the risk of manual pricing errors within Magento.
- Clear approval hierarchies for quotations and business accounts ensure accountability and oversight.

#### Dependencies
- All roles must be defined and configured in the platform before go-live.
- ERP synchronization must be the sole mechanism for pricing updates in Magento.
- Quotation value must be calculable at the point of approval to enforce the ₹10 Lakh threshold for Regional Manager review.

#### Assumptions
- Role assignments are managed by the IT team.
- A user may hold only one primary role unless otherwise specified.
- Regional Manager approval is required in addition to Sales Manager review for high-value quotations, not as a replacement.

#### Risks
- Incorrect role assignments granting unauthorized access
- Pricing modified directly in Magento bypassing ERP
- High-value quotations approved by Sales Manager without Regional Manager sign-off

#### Out of Scope
- Single sign-on (SSO) or Active Directory integration.
- Custom permission sets beyond the roles defined above.
- Self-service role management by non-IT users.

#### Success Criteria
- Each role can only access and perform actions permitted under their defined scope.
- Product pricing cannot be modified by any user within Magento.
- Quotations above ₹10 Lakhs are automatically routed to Regional Managers for approval.
- Promotion management is restricted to the Marketing team only.

### BR-19 - Audit Trail

| Field | Details |
|-------|---------|
| Business Area | Security / Compliance |
| Priority | High |
| Stakeholders | IT Team, Management, Finance Team |

#### Description
The platform shall maintain a comprehensive audit trail of all critical business actions. Every logged action must capture sufficient detail to support accountability, troubleshooting, and compliance requirements. Audit records must be tamper-proof and accessible to authorized personnel.

#### Business Rules
**Actions to be Logged**
- Business registration approvals
- Business registration rejections
- Quote approvals
- Quote rejections
- Gold Partner assignment and removal
- Shipping rule changes
- Promotion changes
- Manual stock corrections (if permitted)


- Each audit record must capture all of the following:
    - User who performed the action
    - Date and time of the action
    - Action performed
    - Previous value (where applicable)
    - New value (where applicable)

**General Rules:**
- Audit records must not be editable or deletable by any user including IT administrators.
- Audit logs must be accessible to authorized personnel for review and reporting purposes.

#### Business Value
- Provides a clear and traceable record of all critical decisions and changes made within the platform.
- Supports internal accountability by attributing every action to a specific user.
- Enables faster troubleshooting by providing a chronological history of system changes.
- Supports compliance requirements by maintaining an immutable record of business-critical actions.

#### Dependencies
- Role-based access control must be in place so that audit records can accurately attribute actions to specific users (refer BR-018).
- All listed triggering actions must be identifiable as system events for logging purposes.
- An interface must be available for authorized personnel to search, filter, and export audit logs.

#### Assumptions
- Audit logs are retained for a minimum period to be defined by the business in line with compliance requirements.
- Access to audit logs is restricted to Management and IT team only.
- The platform or a connected module supports immutable audit log storage natively.

#### Risks
- Critical actions not captured due to incomplete event mapping
- Audit logs accessible to unauthorized users
- Audit log storage growing significantly over time impacting performance

#### Out of Scope
- Admin login history — Phase 2.
- Real-time audit log alerts or anomaly detection.
- Integration with external compliance or SIEM tools.

#### Success Criteria
- Every listed Phase 1 action is captured in the audit log without exception.
- Each audit record contains user, date and time, action performed, previous value, and new value where applicable.
- Audit records cannot be modified or deleted by any user.
- Authorized personnel can search, filter, and export audit logs from within the platform.

## 7. Business Rules

## 8. Assumptions

## 9. Dependencies

## 10. Approval


