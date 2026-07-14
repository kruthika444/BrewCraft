# Discovery Meeting 3 Notes

## Date
08 July 2026

## Attendees
- David Carter (Client)
- Jennifer Kruthi (Business Analyst)
- Tech Lead

## Topics Discussed

### Product management 

#### Can products be disabled without being deleted? 
Products should never be physically deleted from Magento once they have been sold. If a product is no longer available for sale, it should simply be disabled.

#### Should discontinued products remain visible on the website?
- If we expect to restock the product, it should remain visible and display Out of Stock.
- If the product has been permanently discontinued, it should no longer appear in product listings or search results.
    - Temporarily unavailable → Show as Out of Stock.
    - Permanently discontinued → Hidden from storefront.
#### Should discontinued products remain in previous orders?
- Customers must always be able to view products they purchased in the past.
- Historical orders always retain product information.
#### Can ERP delete products?
- ERP can only mark products as inactive.
- ERP sends product status. Magento decides product visibility.
#### Should Out of Stock products remain visible?
- Customers should see them.
- We may even allow "Notify Me When Available" in the future.
#### Can Marketing create products directly in Magento?
- Marketing owns product content only. ERP owns products.

#### Is ERP the only place products are created?
- ERP is the master source. Magento is the selling platform.

### Customer Registartion
#### company GST/ Email already exists?
- Reject the registration.
- Inform the customer that the company is already registered.
- Ask them to contact Customer Support.

#### Existing customers after migration
- We cannot migrate passwords.
- After launch, customers will receive an email asking them to reset their password.
#### Can one company have multiple users?
- One company. One login.
- Multiple users can be added in Phase 2.

#### Business approval SLA
- Sales should review every registration within one business day.

### Orders & quotation
####  Can customers reorder previous orders?
- yes, Especially coffee beans.
- Many cafés order the same products every month.

#### Can Business customers make partial payments?
- No.Each Purchase Order should be settled according to agreed payment terms.
- Partial online payments are not required in Version 1.
#### Quote approval SLA
- Sales should review quotations within 8 business hours.
- Urgent quotations may be prioritised manually.

#### Quote validity
- Every quotation is valid for 30 calendar days.
- After expiry, a new quotation is required.

### ERP Intergation 
#### ERP unavailable
- Retry automatically three times.
- If still unsuccessful,
    - notify IT
    - notify Operations
    - record the failure
- No manual intervention should be required unless all retries fail.

### Payments
#### Payment failure
- Order remains Pending Payment.
- Customer can retry payment.
- If payment is not completed within 24 hours, the order is automatically cancelled.

### shipments
#### Multiple shipments?
Yes. If products are shipped from different warehouses or become available on different dates, multiple shipments are allowed.

### REturns 
#### Refund method
- Retail :Refund back to original payment method.
- Business : 
    - Credit Note.
    - Finance may later settle according to contract.

### Gold partner 
#### Can Gold Partner be removed?
- Yes. Reviewed every financial year.
- Loss of eligibility means removal.
- Must continue to satisfy:
    - ₹10 lakh annual purchases
    - No overdue payments
    - Active business relationship

### Maintenance 
#### Reminder or Service Request?
- Only reminder. Customer chooses whether to book service.
### REporting
#### report formate
- management - pdf 
- warehouse - excel 
- marketing - csv 
### prom0tiom
- coupon codes - yes
- festival offers - yes
- buy X get Y - phase 2
- category discout - yes
- cutomer grp discount - yes
- firts order discount - yes - 10% up to 500
- loyalty offers - phase 2

### Security 
#### Who can approve Business Customers?
Sales Managers only.

#### Who can approve Quotations?
Sales Managers.
Regional Managers for high-value quotations above ₹10 Lakhs.

#### Who can change product pricing?
Nobody inside Magento. Pricing comes from ERP.

#### Who can change shipping rules?
Marketing Managers. Operations Managers.

#### Who can access reports?
Sales → Sales reports only
Warehouse → Inventory reports
Marketing → Marketing reports
Finance → Revenue reports
Management → Everything

#### Who can manage promotions?
Marketing Team only.

### Audit trail 
- Every critical business action must be recorded.
The business requires the system to log:
    - Business registration approvals
    - Business registration rejections
    - Quote approvals
    - Quote rejections
    - Gold Partner assignment/removal
    - Shipping rule changes
    - Promotion changes
    - Manual stock corrections (if permitted)
    - Admin login history (optional, Phase 2)
Each audit record should include:
    - User
    - Date & Time
    - Action performed
    - Previous value
    - New value (where applicable)
- This supports accountability, troubleshooting, and compliance.

### notification 
The platform should automatically notify users for key business events.
**Customer Notifications**
- Registration approved
- Registration rejected
- Password reset
- Order confirmation
- Payment successful
- Payment failed
- Order shipped
- Order delivered
- Return approved
- Return rejected
- Refund processed
- Quote approved
- Quote rejected
- Quote expiring in 3 days
- Maintenance reminder
- Warranty expiry reminder (future enhancement)
- Internal Notifications

**Sales Team**
- New business registration awaiting approval
- New quotation request
- Quote awaiting approval for more than 8 business hours

**Warehouse Team**
- New order received
- Order cancelled
- Low stock alert
- Failed ERP inventory synchronization

**Finance Team**
- Refund request
- Failed payment
- Credit note generated

**IT Team**
- ERP synchronization failure
- Scheduled job failures
- Integration errors
