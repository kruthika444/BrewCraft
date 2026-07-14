# Discovery Meeting 2 Notes

## Date
07 July 2026

## Attendees
- David Carter (Client)
- Jennifer Kruthi (Business Analyst)
- Tech Lead

## Topics Discussed

### ERP synchronization
    Magento shall synchronize product and pricing data from the ERP every hour, while inventory levels shall synchronize every 15 minutes. Marketing-managed content in Magento must be preserved during synchronization.
### place order before approval
    Pending business accounts shall have limited access until approved.
### Gold partners
- They must have been our customer for at least 12 months.
- They should have purchased at least ₹10 lakhs worth of products in the last financial year.
- They must have no outstanding payments.
- Sales Management reviews their account annually.

Gold Partners receive:
- Better pricing
- Faster quotation approvals
- Priority support
- Dedicated account managers
### REgistration notification
- after approval, an email shd be sent
- if rejected, They should also receive an email explaining that their registration wasn't approved and providing contact details for further assistance. 
### payment methods
Retail customers:
- UPI
- Credit/Debit Card
- Net Banking
- Cash on Delivery (selected products only)

Business customers:
- Bank Transfer
- Purchase Order (PO)
- Invoice Payment (for approved corporate customers)

- Different customers should see different payment options.
### Shipping rules
- Retail: Free shipping above ₹2,500.
- Business: Depends on contract terms.

Commercial espresso machines:
- Never free shipping.
- Installation charges may apply.

- Some promotional campaigns may temporarily change shipping rules.
- Shipping charges shall be determined based on customer type, product type, order value, and promotional rules.
### return and refund
- Retail:returns accepted within 7 days for unused products.
- Business:Returns only for damaged or incorrect deliveries.

Commercial machines:
- No returns after installation.
- Warranty claims follow a separate process.

Refunds are processed only after warehouse inspection.
###maintenance requiment 
- The platform shall support equipment maintenance scheduling, service requests, and maintenance history.
Every commercial machine sold should include:
- Installation
- Warranty registration
- Preventive maintenance reminders every 6 months
- Service history

###  report
- The platform shall provide role-based operational reports with scheduled report delivery.
- Reports should be available on demand, and some should also be emailed automatically on a daily, weekly, or monthly basis.
Sales:
- Daily sales
- Pending quotations
- Top customers

Warehouse:
- Low stock
- Backordered products
- Dispatch status

Management:
- Revenue
- Profit trends
- Top-selling categories
- Monthly growth

Marketing:
- Abandoned carts
- Coupon usage
- Best-performing promotions
### Open Questions
- can gold partner status be revoked
- can a busines customer pay partically
- can producst be disabled
- can discontinued product still appear in order history
- can erp delete a product
- during registration for business, if gst or email already in our db, what nedd to do
- what happens for already existing customer, how wll they login to there account 
- can customer, reorder previous orders  
- if erp is unaviable, what shd be done? retry or alert the team?
- how long it will take for the sales to approve quote? and how long quotaion is vaild after creation 
- what happends if the payment is failed
- can a order have multiple shippment
- how shd we refund the amount - what method
- the report shd be sent in which formate
- do we need to show out of stock products or remove them
- Can Marketing create products directly in Magento?
- Is ERP the only place where products can be created?
- can one company have multiple user?
- How quickly should Sales approve business registrations?
- can gold partner be removed? whats the lifecycle 
- for maintenance - shd system send a reminder or create serive request automatially 
- Do you want the marketing team to create:

Coupon Codes
Festival Offers
Buy X Get Y
Category Discounts
Customer Group Discounts
