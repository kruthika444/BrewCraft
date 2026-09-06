
# 1. Development Log: BrewCraft Custom Magento Theme
**DATE** 11th AUG
### Objective

Create a completely separate BrewCraft Magento storefront theme based on the BrewCraft design references while retaining Magento's native storefront functionality.

The goal was not to modify Luma directly.

We wanted:

```text
Magento / Luma
      +
BrewCraft child theme
      +
BrewCraft design system
```

This allows us to customize:

```text
Header
Navigation
Footer
Homepage
PLP
PDP
Cart
Checkout
Customer account
B2B
RFQ
```

without modifying vendor files.

---

## Phase 1: Created the Custom Theme

Theme location:

```text
app/design/frontend/BrewCraft/supply
```

Initial structure:

```text
BrewCraft/
└── supply/
    ├── registration.php
    ├── theme.xml
    ├── composer.json
    └── web/
        └── css/
            └── source/
```

---

## `registration.php`

Created:

```php
<?php

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::THEME,
    'frontend/BrewCraft/supply',
    __DIR__
);
```

Purpose:

```text
Tell Magento that BrewCraft/supply is a frontend theme.
```

---

## `theme.xml`

Created with Luma as the parent:

```xml
<?xml version="1.0"?>
<theme xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
       xsi:noNamespaceSchemaLocation="urn:magento:framework:Config/etc/theme.xsd">

    <title>BrewCraft Supply</title>

    <parent>Magento/luma</parent>

</theme>
```

The inheritance is therefore:

```text
Magento Blank
     ↓
Magento Luma
     ↓
BrewCraft Supply
```

This lets BrewCraft reuse Magento's existing:

```text
Layouts
Templates
JavaScript
UI components
LESS
Search
Mini cart
Navigation
Customer session functionality
```

while overriding only what we need.

---

## Phase 2: Added BrewCraft Design Tokens

We started creating reusable BrewCraft variables for:

```text
Colors
Fonts
Spacing
Container width
Border radius
```

Examples:

```less
@brewcraft-espresso: #2e1e14;
@brewcraft-coffee: #5c3a21;
@brewcraft-cream: #f6f1e8;
@brewcraft-charcoal: #1f1f1f;
@brewcraft-green: #1f6845;
@brewcraft-gold: #c8a66a;
```

Typography:

```less
@brewcraft-font-heading: 'Poppins', sans-serif;
@brewcraft-font-body: 'Inter', sans-serif;
```

Spacing followed an 8px system:

```less
@bc-space-1: 8px;
@bc-space-2: 16px;
@bc-space-3: 24px;
@bc-space-4: 32px;
```

and so on.

---

## Phase 3: Initial LESS Structure

Initially we created files such as:

```text
_variables.less
_theme.less
_extend.less
_buttons.less
_forms.less
_header.less
```

The idea was reasonable:

```text
_variables.less
    → BrewCraft variables

_theme.less
    → Magento variable overrides

_extend.less
    → import custom components
```

However this exposed an important Magento theme inheritance behavior.

---

## Error 1: `@icon-print` Undefined

During static-content deployment Magento failed with an error similar to:

```text
variable @icon-print is undefined
```

The error occurred inside Magento's inherited core LESS.

This immediately indicated that a core Magento variable definition had disappeared.

---

## Error 2: `@sidebar__background-color` Undefined

Next Magento failed with:

```text
variable @sidebar__background-color is undefined
```

from:

```text
Magento_Theme/css/source/module/_collapsible_navigation.less
```

Example:

```less
@collapsible-nav-background:
    @sidebar__background-color;
```

Again, this was not a BrewCraft variable.

It was a Magento/Luma variable.

---

## Investigation

The important realization was that child-theme LESS files with Magento core filenames can participate in fallback/override behavior.

Our small custom:

```text
_theme.less
```

was interfering with the parent theme's complete variable definitions.

We originally expected something conceptually like:

```text
Luma _theme.less
        +
BrewCraft _theme.less
```

But the custom file could effectively replace what Magento expected to inherit.

That caused Magento variables required by other modules to disappear.

---

## First Correction

We removed our custom:

```text
_theme.less
```

and allowed BrewCraft to inherit Luma's full theme variable set.

The intention became:

```text
Do not replace Magento's core theme definitions unnecessarily.

Use BrewCraft-specific extension files instead.
```

---

## Error 3: `@icon-error` Undefined

After that correction, static deployment progressed further but failed with:

```text
variable @icon-error is undefined
```

inside inherited `_theme.less`:

```less
@message-error-icon: @icon-error;
@message-success-icon: @icon-success;
```

This exposed another collision.

We still had our custom:

```text
_variables.less
```

containing only BrewCraft values.

It was again using a filename Magento itself relies upon.

---

## Second Correction: Unique BrewCraft Variable File

We stopped using:

```text
_variables.less
```

for our own tokens.

Instead we moved toward a unique filename:

```text
_brewcraft-variables.less
```

That avoids accidentally replacing/influencing Magento's parent variable file.

The intended structure became:

```text
web/css/source/
├── _brewcraft-variables.less
├── _extend.less
├── _header.less
├── _buttons.less
└── _forms.less
```

---

## Error 4: `@form-field__vertical-indent__desktop` Undefined

Deployment then failed again with:

```text
variable @form-field__vertical-indent__desktop is undefined
```

inside:

```text
Magento_GiftRegistry/css/source/_module.less
```

At that point we made an important debugging decision:

**Stop fixing variables individually.**

We had already encountered:

```text
@icon-print
@sidebar__background-color
@icon-error
@form-field__vertical-indent__desktop
```

Adding each variable manually would only hide the real theme-resolution problem.

---

## Phase 4: Reset to a Minimal Child Theme

To isolate the problem, all custom LESS was temporarily moved out.

The theme was reduced to:

```text
app/design/frontend/BrewCraft/supply/
├── registration.php
├── theme.xml
├── composer.json
└── web/
    └── css/
        └── source/
            └── _extend.less
```

And `_extend.less` contained only:

```less
body {
    background: #ffffff;
}
```

No:

```text
_theme.less
_variables.less
_header.less
_buttons.less
_forms.less
```

during this test.

---

## Cleaned Generated Assets

Magento had already generated/preprocessed copies of the broken LESS tree.

So we cleared:

```bash
rm -rf var/view_preprocessed/*
rm -rf pub/static/frontend/BrewCraft/*
bin/magento cache:flush
```

Then static deployment was run again.

---

## Result

Static-content deployment completed successfully.

This was an important confirmation.

It proved:

```text
Magento itself             ✅
Luma parent theme          ✅
BrewCraft registration     ✅
BrewCraft theme.xml        ✅
Theme inheritance          ✅
Static deployment          ✅
```

Therefore the earlier compilation problems were caused by our custom LESS structure, not by Magento core.

---

## Phase 5: Reintroduced BrewCraft Styling Safely

After the clean deployment, we added BrewCraft styling back gradually.

First:

```text
_brewcraft-variables.less
```

Then:

```text
_header.less
```

And `_extend.less` became the BrewCraft custom-style entry point.

Conceptually:

```less
@import '_brewcraft-variables.less';
@import '_header.less';
```

The important rule became:

```text
Use unique BrewCraft filenames for our custom design-system files.
```

---

## Phase 6: Header Customization

We began matching the BrewCraft reference design.

The header used Magento's existing functional blocks:

```text
Logo
Search
Mini cart
Navigation
```

instead of rebuilding them from scratch.

---

## Initial Header Template Attempt

At one point we attempted to create our own:

```text
Magento_Theme/templates/html/header.phtml
```

and recreated blocks such as:

```text
Logo
Search
Customer links
```

manually.

That caused a fatal error from Magento's mini-search template:

```text
Call to a member function
getSearchHelperData() on null
```

inside:

```text
Magento_Search/templates/form.mini.phtml
```

---

## Root Cause of Search Error

Magento's search template is not just plain HTML.

It expects the correct Magento search block/view-model dependencies.

We had created the search template using a generic block:

```text
Magento\Framework\View\Element\Template
```

which did not contain the search-specific functionality required by:

```text
form.mini.phtml
```

---

## Header Architecture Decision

We abandoned the idea of rebuilding Magento's entire header block tree.

Instead:

```text
Keep Magento native blocks
+
Style them
+
Move only when necessary
```

This preserves:

```text
Search functionality
Search autocomplete
Customer session
Sign-in/sign-out
Mini-cart customer-data
Cart counter
Magento navigation JavaScript
```

and significantly reduces frontend breakage.

---

## Phase 7: Header LESS

The BrewCraft header styling now handles:

```text
Page header
Top panel
Logo
Search
Mini cart
Navigation
Dropdowns
Responsive/mobile behavior
```

The visual system uses BrewCraft's:

```text
Espresso brown
Coffee brown
Cream
Charcoal
Soft borders
```

---

## Header Order Issue

Magento initially rendered the blocks visually as:

```text
Logo
Cart
Search
```

while the BrewCraft layout required:

```text
Logo
Search
Cart
```

Navigation was correctly below the main header.

Instead of moving Magento blocks through layout XML, we used CSS Flexbox ordering.

Example:

```less
.logo {
    order: 1;
}

.block-search {
    order: 2;
}

.minicart-wrapper {
    order: 3;
}
```

This let Magento retain its original DOM/block structure while displaying:

```text
┌───────────────────────────────────────────────┐
│ BREWCRAFT     Search................     Cart │
├───────────────────────────────────────────────┤
│ Coffee  Machines  Grinders  Brewing ...      │
└───────────────────────────────────────────────┘
```

The user confirmed this header arrangement was correct.

---

## Current Theme Status

At this point:

```text
Custom BrewCraft theme       ✅
Luma inheritance             ✅
Theme registration           ✅
LESS compilation             ✅
BrewCraft color tokens       ✅
Basic responsive foundation  ✅
Native Magento header        ✅
Logo placement               ✅
Search placement             ✅
Cart placement               ✅
Navigation below header      ✅
```

---

## Key Magento Theme Lessons Learned

### 1. Never edit vendor theme files

We kept all BrewCraft work under:

```text
app/design/frontend/BrewCraft/supply
```

so Magento updates do not overwrite customizations.

### 2. Child themes inherit functionality

Using:

```xml
<parent>Magento/luma</parent>
```

lets us reuse Magento storefront functionality while changing the design.

### 3. Be careful with Magento core LESS filenames

Files such as:

```text
_theme.less
_variables.less
```

have special meaning in Magento theme inheritance.

A small custom version can unintentionally remove parent definitions.

### 4. Use unique project-specific LESS partial names

Safer pattern:

```text
_brewcraft-variables.less
_brewcraft-header.less
_brewcraft-footer.less
```

or similarly unique project prefixes.

### 5. `_extend.less` is our main customization entry point

Instead of copying huge parent-theme LESS files, BrewCraft additions can be loaded through:

```text
_extend.less
```

### 6. Debug from the first compilation error

When Magento says:

```text
@icon-error undefined
```

the solution is not necessarily:

```less
@icon-error: ...;
```

If several Magento variables disappear together, investigate theme inheritance first.

### 7. Resetting to a minimal theme is a useful debugging technique

The most useful diagnostic step was:

```text
Remove all custom styling
        ↓
Deploy minimal child theme
        ↓
Confirm Magento works
        ↓
Add custom files gradually
```

That clearly separated:

```text
Magento problem
```

from:

```text
Our theme customization problem
```

### 8. Preserve Magento's functional blocks

Instead of rewriting:

```text
Search
Mini cart
Customer links
Navigation
```

from scratch, it is safer to retain Magento's native components and customize their presentation.

---

## Theme Development Flow So Far

```text
Create BrewCraft child theme
        ↓
Set Magento/luma parent
        ↓
Create design variables
        ↓
Create initial LESS architecture
        ↓
Static deploy
        ↓
Core LESS variables missing
        ↓
Investigate inheritance
        ↓
Remove custom _theme.less
        ↓
More variable errors
        ↓
Remove/rename custom _variables.less
        ↓
More inherited variable errors
        ↓
Stop individual fixes
        ↓
Reset to minimal child theme
        ↓
Static deployment succeeds
        ↓
Reintroduce BrewCraft files safely
        ↓
Style native Magento header
        ↓
Avoid custom search-block recreation
        ↓
Use Flexbox to arrange Logo → Search → Cart
        ↓
Header confirmed working
```

---
---




# 2. BrewCraft Homepage — Development Log

### 1. Objective

The goal was to replace the default Magento CMS homepage with a custom **BrewCraft Supply desktop homepage** matching the provided Figma design as closely as practical.

The implementation needed to:

* follow the BrewCraft design system;
* use the existing custom BrewCraft theme;
* preserve Magento functionality;
* use real ERP-imported categories and products;
* allow merchandising content to be maintained from Magento Admin where possible;
* avoid hard-coded catalog data;
* integrate existing B2B and RFQ functionality;
* remain isolated from the already-completed global header and footer.

The final page structure became:

```text
Header
↓
Hero Banner
↓
Benefits / Trust Strip
↓
Shop by Category
↓
Featured Products
↓
B2B / Commercial Banner
↓
Why BrewCraft
↓
Footer
```

---

## 2. Homepage-Specific Magento Layout

We created a homepage-specific layout:

```text
app/design/frontend/BrewCraft/supply/
Magento_Cms/layout/cms_index_index.xml
```

This was preferred over placing all homepage components in `default.xml`.

### Why?

`default.xml` affects almost every storefront page.

But:

```text
cms_index_index.xml
```

only affects the Magento homepage.

This allowed us to keep:

```text
Header               global
Footer               global
Homepage components  homepage only
```

and prevented homepage styling from unnecessarily affecting PLP, PDP, account pages, checkout, etc.

---

## 3. Removed Magento's Default Homepage Heading

Magento originally displayed:

```text
Home Page
```

above our custom hero.

This came from Magento's standard:

```text
page.main.title
```

block.

We removed it specifically on the homepage using the layout XML.

The result was:

```text
Header
↓
Hero immediately begins
```

instead of:

```text
Header

Home Page

Hero
```

This made the page much closer to the Figma.

---

## 4. Hero Banner

We created a custom homepage hero template:

```text
Magento_Cms/templates/home/hero.phtml
```

The hero contains:

```text
Premium Coffee Equipment

Elevate Your Craft.
Every Cup.

Supporting description

[Shop Machines] [Shop All Products]

                         Espresso machine image
```

A custom high-quality espresso machine image was created specifically for the BrewCraft visual style.

---

## 5. Hero Image Treatment

Initially the text panel had a flat cream background while the generated image had a slightly different warm tone.

This created a visible division:

```text
CREAM BLOCK | IMAGE
```

which looked artificial.

We corrected it using:

* BrewCraft cream/beige tones;
* a subtle gradient;
* carefully matched image placement;
* a soft transition between content and media.

The result became visually closer to:

```text
Content → warm transition → Image
```

rather than two unrelated panels.

---

## 6. Hero CTA Button Issue

Magento's default Luma styles initially affected the primary hero button.

The button appeared with:

```text
Magento blue styling
```

instead of BrewCraft brown.

It also had text-alignment issues because Magento's `.action.primary` styles were still contributing padding and line-height.

We overrode the homepage-specific CTA styles so the final buttons use:

```text
Primary:
Dark espresso background
White text

Secondary:
Transparent/light background
Coffee-brown border
Dark text
```

The button content was also vertically centered correctly.

---

## 7. Hero Benefits / Trust Strip

Directly beneath the hero we implemented four quick trust points:

```text
Free Shipping
Expert Support
Secure Payments
Brew Better
```

Each uses a compact icon plus:

```text
Main label
Supporting text
```

Example:

```text
Free Shipping
On orders over ₹999
```

This mirrors the benefits strip in the Figma and gives the hero a natural visual ending.

---

## 8. Shop by Category

The next homepage component was:

```text
EXPLORE BREWCRAFT

Shop by Category

Everything you need to brew,
serve and enjoy better coffee.
```

We displayed six important storefront categories:

```text
Coffee
Machines
Grinders

Brewing
Accessories
Commercial
```

in a desktop 3 × 2 grid.

---

## 9. Categories Come From Magento, Not Hard-Coded HTML

A custom block was created:

```text
BrewCraft\ErpIntegration\Block\Home\CategoryGrid
```

It retrieves actual Magento categories using our existing ERP category identifier:

```text
erp_category_code
```

The flow is:

```text
ERP db.json
↓
Category Import
↓
Magento Category
↓
erp_category_code
↓
Homepage CategoryGrid
↓
Category Card
```

Therefore the homepage category link is the actual Magento category URL.

---

## 10. Homepage Category Selection

For homepage merchandising we selected:

```text
COFFEE
MACHINES
GRINDERS
BREWING
ACCESSORIES
COMMERCIAL
```

The ERP still contains other valid categories such as:

```text
Parts
```

but not every root category needs to appear as a homepage promotional card.

This keeps navigation architecture and homepage merchandising separate.

---

## 11. Category Images — Important Architecture Improvement

Originally we considered mapping category images inside PHP:

```text
COFFEE → coffee.jpg
MACHINES → machines.jpg
...
```

We changed this approach.

Instead, category images are managed through:

```text
Magento Admin
→ Catalog
→ Categories
→ Content
→ Category Image
```

The homepage reads Magento's category image.

### Why this is better

If an administrator changes:

```text
Coffee category image
```

the homepage automatically uses the new image.

No code change is required.

This gives us:

```text
ERP
→ category hierarchy/data

Magento Admin
→ merchandising/category image

Homepage
→ automatically renders both
```

which is much more maintainable.

---

## 12. Category Image URL Issue

Initially the Shop by Category cards displayed Magento's placeholder image even though the correct category images appeared on the PLP.

That confirmed:

```text
Admin image upload      ✅
Magento category image  ✅
Our custom URL handling ❌
```

The issue was caused by manually constructing the category media URL.

We changed the block to use Magento's resolved category image URL directly.

After that, the correct Admin-uploaded category images appeared on the homepage.

---

## 13. Category Grid Layout Issue

At first the category cards appeared vertically:

```text
Coffee

Machines

Grinders
...
```

instead of horizontally.

The content itself existed, so the PHP/template was working.

The problem was frontend layout/CSS compilation.

After confirming `_homepage.less` was correctly included through `_extend.less`, the grid was explicitly defined as:

```text
3 columns × 2 rows
```

The final category section now displays correctly and matches the intended desktop design.

---

## 14. Featured Products Architecture

Next we implemented:

```text
BREWCRAFT PICKS

Featured Products
```

Instead of hard-coding product SKUs inside PHP, we created a normal Magento merchandising category:

```text
Featured Products
```

with:

```text
Enable Category: Yes
Include in Menu: No
URL Key: featured-products
```

Products can then be assigned from Magento Admin.

---

## 15. Why We Used a Hidden Category

The hidden merchandising category provides an easy Admin interface:

```text
Admin
↓
Featured Products category
↓
Products in Category
↓
Assign / remove products
↓
Homepage automatically updates
```

This means marketing/admin users can change homepage featured products without editing:

```text
PHP
PHTML
LESS
ERP JSON
```

At the same time:

```text
Include in Menu = No
```

prevents `Featured Products` from appearing in the main navigation.

---

## 16. Featured Products Block

We created:

```text
BrewCraft\ErpIntegration\Block\Home\FeaturedProducts
```

The block:

* finds the `featured-products` category;
* loads products assigned to it;
* loads visible/enabled products;
* limits the homepage to four products;
* retrieves Magento product URLs;
* retrieves product images;
* retrieves regular and final prices;
* provides Add-to-Cart URLs;
* generates the form key needed by Magento POST requests.

---

## 17. Featured Product Cards

Each homepage product card displays:

```text
Product image
SKU
Product name
Current price
Original price when discounted
SALE badge
Add to Cart
```

The cards use actual Magento catalog data rather than static mock values.

Product images are therefore manageable through:

```text
Admin
→ Catalog
→ Products
→ Images and Videos
```

---

## 18. SALE Badge Logic

The SALE badge is not manually assigned.

We determine it by comparing:

```text
Regular Magento price
vs.
Magento final price
```

Conceptually:

```text
Regular Price = ₹450
Final Price   = ₹399

₹399 < ₹450
↓
SALE
```

Therefore the badge reflects a real Magento discount.

An important benefit is that `finalPrice` can reflect more than just a product's `special_price`; Magento pricing logic can influence the effective final price.

---

## 19. View All Products

We also added:

```text
View All Products →
```

under the Featured Products cards.

It links to the hidden Featured Products category.

Even though the category is:

```text
Include in Menu = No
```

its storefront URL remains available.

This gives users a dedicated landing page for the larger featured collection.

---

## 20. B2B / Commercial Homepage Section

The next major section was designed around the BrewCraft business functionality already developed.

It contains:

```text
FOR BUSINESS

Built for Cafés, Offices
& Hospitality

Professional coffee equipment,
wholesale pricing...

✓ Wholesale Pricing
✓ Commercial Equipment
✓ Custom Quotations
✓ Dedicated Support

[Create Business Account]
[Request a Quote]
```

---

## 21. B2B Layout Direction

The initial design placed:

```text
Image left
Content right
```

but we changed it to:

```text
Content left
Image right
```

based on the desired page composition.

This created a stronger transition from the Featured Products section.

---

## 22. B2B Image

A separate dark commercial café image was created for this section.

It contains:

* commercial espresso machine;
* grinders;
* café equipment;
* warm lighting;
* professional coffee environment.

This intentionally differs from the light hero imagery and visually communicates:

```text
B2C / home brewing
vs.
Commercial / professional
```

---

## 23. Commercial Image Alignment Issue

Initially the B2B image showed:

* an unwanted dark/shadow edge;
* awkward positioning inside its half of the section.

The dark edge came from a gradient overlay that had been added to blend the image into the dark panel.

Since the final image already worked well against the dark section, we removed the overlay.

We also corrected:

```text
object-fit
object-position
section height
```

so the image fills the right side cleanly.

---

## 24. Why BrewCraft Section

The final content section was:

```text
WHY BREWCRAFT

Crafted for Better Coffee
```

with supporting brand copy and three principles:

```text
01 Curated Equipment
02 Reliable Supply
03 Expert Support
```

It uses:

```text
Lifestyle coffee image left
Light cream content panel right
```

This intentionally alternates with the previous B2B section:

```text
B2B:
Dark content left
Image right

Why BrewCraft:
Image left
Light content right
```

creating visual rhythm.

---

## 25. Why BrewCraft Alignment Refinement

The first version had slightly excessive top spacing in the content panel and the image/content heights were not perfectly aligned.

We corrected this by:

* reducing content top padding;
* making both grid columns stretch together;
* removing unnecessary fixed independent image height;
* letting the image fill the exact content height.

The final result has flush top/bottom edges.

---

## 26. Homepage Spacing Problem

Once all components existed, the page technically worked but looked fragmented because every section added its own large vertical margins/padding.

For example:

```text
Hero
        large gap

Categories
        large gap

Featured Products
        large gap

B2B
```

This made each component look like an independent widget instead of one designed homepage.

---

## 27. Homepage Spacing Cleanup

We introduced a final homepage spacing/rhythm pass.

We:

* removed unnecessary bottom margins;
* reduced duplicate top/bottom padding;
* removed gaps between B2B and Why BrewCraft;
* removed footer separation;
* kept reasonable internal padding within each section;
* used section background changes as natural visual separators instead of empty whitespace.

The final visual progression became:

```text
Hero               warm cream
Benefits           white
Categories         white
Featured Products  soft cream
B2B                dark espresso
Why BrewCraft      cream
Footer             dark
```

This made the page feel like one cohesive storefront.

---

## 28. Isolation From Header and Footer

During development we had previously experienced header instability when unrelated styles interfered with Magento/Luma header rules.

For the homepage, we therefore followed an important rule:

```text
Homepage styles
→ _homepage.less

Header styles
→ _header.less

Footer styles
→ _brewcraft-footer.less
```

Homepage work did not intentionally modify the global header/footer architecture.

This made later debugging significantly easier.

---

## 29. Main Homepage Files

The final homepage implementation primarily uses:

```text
Theme:

Magento_Cms/
├── layout/
│   └── cms_index_index.xml
│
└── templates/
    └── home/
        ├── hero.phtml
        ├── category-grid.phtml
        ├── featured-products.phtml
        ├── business-banner.phtml
        └── why-brewcraft.phtml
```

Homepage styles live in:

```text
web/css/source/_homepage.less
```

and are included through:

```text
_extend.less
```

Custom Magento data blocks include:

```text
BrewCraft\ErpIntegration\Block\Home\CategoryGrid
BrewCraft\ErpIntegration\Block\Home\FeaturedProducts
```

---

## 30. Dynamic vs Static Content Decisions

An important part of the homepage architecture was deciding what should be dynamic.

### Dynamic Magento data

```text
Category names
Category URLs
Category images
Featured products
Product names
Product images
SKU
Prices
Special/final prices
Product URLs
Add to Cart
Product availability
```

### Theme-managed content

```text
Hero copy
Hero image
Benefits text
B2B promotional text
Commercial image
Why BrewCraft content
Brand lifestyle image
```

This gives us a good separation between:

```text
Catalog/merchandising data
```

and:

```text
Brand/design content
```

---

## 31. Admin Manageability

After the implementation, several homepage changes can now be made without touching code.

### Category images

```text
Admin
→ Catalog
→ Categories
→ Category Image
```

### Featured products

```text
Admin
→ Catalog
→ Categories
→ Featured Products
→ Products in Category
```

### Product images

```text
Admin
→ Catalog
→ Products
→ Images and Videos
```

### Product price

In our project the main selling prices can continue being updated through:

```text
ERP Price Sync
```

The homepage automatically reflects the resulting Magento price.

---

## 32. ERP Integration Connection

The homepage is not disconnected from the ERP work we completed earlier.

The flow is now:

```text
ERP
│
├── Categories
│      ↓
│   Magento Categories
│      ↓
│   Homepage Shop by Category
│
├── Products
│      ↓
│   Magento Products
│      ↓
│   Featured Products
│
├── Inventory
│      ↓
│   Magento Stock
│
└── Prices
       ↓
    Magento Price / Special Price
       ↓
    Homepage Price / SALE Badge
```

So the homepage is displaying catalog information generated by the actual integration architecture rather than separate demo data.

---

## 33. Final Homepage Result

The completed desktop homepage now contains:

```text
BrewCraft Header
        ↓
Hero
        ↓
Trust Benefits
        ↓
Shop by Category
        ↓
Featured Products
        ↓
Commercial / B2B
        ↓
Why BrewCraft
        ↓
BrewCraft Footer
```

All major Figma homepage concepts have therefore been implemented.

---



# 3. BrewCraft PLP / Category Page — Development Log

### 1. Objective

The goal was to replace Magento/Luma’s default category product listing appearance with the BrewCraft storefront design while preserving Magento’s native catalog functionality.

The PLP needed to support:

```text
Breadcrumbs
Category banner
Category title
Category description

Shopping Options / Layered Navigation
Product count
Grid/List switcher
Sort By

Product grid
Product image
Product name
Price
Add to Cart

Pagination
```

The important architectural decision was:

> **Keep Magento's native catalog collection, layered navigation, toolbar and product actions. Customize the presentation rather than recreating Magento catalog logic.**

---

## 2. PLP-Specific Theme Files

We created:

```text
app/design/frontend/BrewCraft/supply/
Magento_Catalog/
└── layout/
    └── catalog_category_view.xml
```

and:

```text
web/css/source/_plp.less
```

Then `_plp.less` was included through the theme's:

```text
_extend.less
```

This keeps PLP styling isolated from:

```text
Homepage
Header
Footer
PDP
Checkout
```

---

## 3. Magento Two-Column Layout

The category page uses:

```xml
layout="2columns-left"
```

because the desired BrewCraft PLP structure is:

```text
Shopping Options   Product Listing
```

Magento already provides this architecture natively through:

```text
.sidebar-main
.column.main
```

---

## 4. First Major Layout Mistake

Initially we added our own:

```text
CSS Grid on .columns
```

while Magento was already applying its own two-column layout rules.

This caused competing layout systems:

```text
Magento 2columns-left
        +
Our CSS Grid
```

and resulted in:

* sidebar moving too far inside;
* toolbar appearing in strange positions;
* Compare Products and Wishlist interfering;
* product area collapsing;
* products becoming barely visible.

### Lesson learned

Do not unnecessarily replace Magento’s major page-layout containers.

We removed the custom `.columns` grid and allowed Magento to retain control of:

```text
sidebar-main
column.main
```

while styling the components inside them.

---

## 5. Category Hero Requirement

The initial category page displayed:

```text
Coffee

Category Image

Description
```

But the intended BrewCraft design required:

```text
┌─────────────────────────────────────┐
│ Coffee                              │
│ Category description                │
│                       Category Image│
└─────────────────────────────────────┘
```

So category title and description needed to become part of the category image/banner.

---

## 6. Reordered Magento Category Blocks

We used:

```text
catalog_category_view.xml
```

to move:

```text
page.main.title
category.description
category.image
```

into the same category-view container.

This allowed us to use Magento’s existing category data instead of creating duplicate title/description values in a custom PHTML template.

---

## 7. Category Hero Design

The category container became a fixed-height banner.

Important characteristics:

```text
Width: 100% of PLP content area
Height: fixed
Image: cover
Title: overlay
Description: overlay
Dark gradient behind text
```

This solved a major consistency issue.

Previously the banner size changed depending on the image uploaded through Admin.

Now:

```text
Coffee
Machines
Grinders
Brewing
Accessories
Commercial
```

all use the same visual banner dimensions.

---

## 8. Why `object-fit: cover` Was Used

Category images can have different source dimensions:

```text
Landscape
Square
Large
Small
Different aspect ratios
```

The PLP should not change height based on those images.

Therefore category banners use:

```text
object-fit: cover
```

Conceptually:

```text
Any uploaded image
        ↓
Fixed BrewCraft banner
        ↓
Image cropped appropriately
```

This allows category images to continue being managed entirely from Magento Admin.

---

## 9. Category Content Comes From Magento Admin

No category title, description or banner image is hard-coded into the theme.

They continue to come from:

```text
Admin
→ Catalog
→ Categories
```

The page uses:

```text
Category Name
Category Description
Category Image
```

directly.

This means changing:

```text
Coffee description
```

or:

```text
Coffee category image
```

automatically changes the PLP hero.

---

## 10. Category Hero Text Overlay

A dark left-to-right image overlay was added so white title/description text remains readable regardless of the uploaded image.

The result is approximately:

```text
┌───────────────────────────────────────────────┐
│                                              │
│  Coffee                                      │
│                                              │
│  Description...                              │
│                              brighter image  │
│                                              │
└───────────────────────────────────────────────┘
```

The left area is darker while the actual category image remains visible toward the center/right.

---

## 11. Layered Navigation / Shopping Options

Magento's native layered navigation was retained.

This currently displays filters such as:

```text
Shopping Options

CATEGORY
PRICE
```

This is important because layered navigation is functional Magento catalog behavior rather than static frontend markup.

The BrewCraft styling was added around the existing filter blocks.

---

## 12. Removed PLP Sidebar Clutter

Magento/Luma was also displaying:

```text
Compare Products
My Wish List
```

inside the sidebar.

These were not part of the BrewCraft PLP design and visually cluttered the filters.

They were hidden from the category-page presentation.

The actual Magento functionality was not globally removed from the system.

---

## 13. Toolbar

Magento's native toolbar was retained.

Current functionality includes:

```text
Grid / List mode
Product count
Sort By
Sort direction
```

Example:

```text
[Grid] [List]     5 Items               Sort By [Position] ↑
```

This means we did not recreate sorting or pagination logic.

Magento continues handling:

```text
collection sorting
URL parameters
view mode
pagination
```

---

## 14. Product Grid

For the desktop PLP we selected:

```text
3 products per row
```

because the page also contains a left filter sidebar.

The layout is therefore:

```text
┌───────────────┐  ┌──────────┐ ┌──────────┐ ┌──────────┐
│ Shopping      │  │ Product  │ │ Product  │ │ Product  │
│ Options       │  │          │ │          │ │          │
│               │  └──────────┘ └──────────┘ └──────────┘
└───────────────┘
```

Four products would make each card too narrow once the layered-navigation column is present.

---

## 15. Product Height Problem

The first product styling used Magento's image wrapper with:

```text
100% aspect ratio
```

which effectively created large square product-image areas.

The cards became excessively tall.

We initially reduced the image height too aggressively, which created another problem.

---

## 16. Product Image Height Refinement

A more balanced product image area was introduced.

Instead of forcing a huge square image, the image container now uses a controlled fixed height.

For product imagery we use:

```text
object-fit: contain
```

instead of `cover`.

This is important because product photos should not crop important parts of:

```text
Coffee machines
Grinders
Kettles
Coffee bags
Brewing equipment
```

The distinction is:

```text
Category banner → object-fit: cover
Product image   → object-fit: contain
```

---

## 17. Add to Cart Was Getting Cut Off

Once product cards became shorter, Magento's default Luma hover behavior caused another issue.

Luma normally places part of the product actions inside a floating:

```text
.product-item-inner
```

container.

Conceptually:

```text
Normal card
        ↓ hover
Expanded/floating actions
        ↓
Add to Cart
Wishlist
Compare
```

But our BrewCraft card used:

```text
overflow: hidden
```

so Magento's floating action area was clipped.

Result:

```text
Add to Cart only partially visible
```

---

## 18. Removed Luma Floating Product-Action Behavior

Instead of trying to make the default floating hover box work, we changed the PLP cards so:

```text
.product-item-inner
```

participates in the normal card layout.

Now:

```text
Product image
Product name
Price
Add to Cart
```

are all part of one stable card.

This is much closer to the Featured Products design already used on the homepage.

---

## 19. Product Card Direction

The current card architecture is approximately:

```text
┌─────────────────────┐
│                     │
│    Product Image    │
│                     │
├─────────────────────┤
│ Product Name        │
│                     │
│ Price               │
│                     │
│ [   Add to Cart   ] │
└─────────────────────┘
```

rather than Luma's expandable hover card.

---

## 20. Duplicate Navigation Underline

When viewing the currently selected category, for example Coffee, two underlines appeared beneath the navigation item.

One came from:

```text
Magento/Luma active-navigation styling
```

and the second from:

```text
BrewCraft custom active-navigation styling
```

We disabled the inherited active border while keeping BrewCraft's own underline.

The desired result is only one active indicator.

---

## 21. PLP Alignment Problem

One of the major visual issues throughout the PLP work was inconsistent outer margins.

Different elements initially appeared to start at different horizontal points:

```text
Breadcrumb
Category hero
Shopping Options
Product listing
Header
```

We standardized the PLP outer container using the shared BrewCraft container width and horizontal padding.

---

## 22. Common Global Container

We ultimately established the principle:

```text
@bc-container-width
+
32px horizontal page padding
```

as the global desktop content alignment.

This should be shared by:

```text
Header
Homepage
PLP
Future PDP
Cart
Footer
```

---

## 23. PLP Became the Alignment Reference

After correcting the category page, the PLP alignment looked correct.

That exposed an existing homepage problem: homepage content was more inset because it effectively had two container layers:

```text
Magento page-main
        +
BrewCraft section container
```

Instead of moving the PLP inward again, we treated the PLP alignment as the correct reference and brought the homepage outward.

This was the correct architectural decision.

---

## 24. Homepage Container Correction Triggered by PLP Work

Although this came from PLP testing, it resulted in an important global theme improvement.

Homepage sections:

```text
Hero
Shop by Category
Featured Products
B2B
Why BrewCraft
```

were normalized so they no longer add another complete outer container inside Magento's `.page-main`.

The intended architecture now becomes:

```text
One outer page container
        ↓
Page-specific sections
```

rather than:

```text
Outer Magento container
        ↓
Another BrewCraft container
        ↓
Content
```

---


## 28. Important Lessons From Today

The most useful lesson was:

> **Do not immediately replace Magento's structural containers just because the default appearance is wrong.**

For example, replacing:

```text
.columns
```

with our own Grid looked simple but broke Magento's:

```text
sidebar
main content
toolbar
product listing
```

behavior.

A better approach was:

```text
Keep Magento structure
        +
Understand the existing markup
        +
Override the visual behavior selectively
```

The same applied to product cards. Instead of rebuilding product listing logic, we changed how Magento's existing product-action container participates in the card.

---


# 4. BrewCraft PLP — Development Log 

## 1. Goal of Today's Work

We continued the BrewCraft Product Listing Page from the structural implementation completed previously.

Today's work focused on finishing the actual storefront experience:

```text
Product images
Sale badge
Product-card sizing
Product information
Toolbar
Layered navigation
Pagination
Final PLP verification
```

The principle remained:

> Keep Magento's native catalog functionality and customize its appearance instead of rebuilding catalog behavior.

---

## 3. Investigated Magento Image Roles

Magento commonly uses different image attributes depending on context:

```text
image
small_image
thumbnail
```

For PLP, `small_image` is particularly important.

We checked the product EAV values directly using SQL for:

```text
BEAN001
```

and found:

```text
store_id = 0

image        = /actual/image.jpg
small_image  = /actual/image.jpg
thumbnail    = /actual/image.jpg
```

which was correct.

But we also found:

```text
store_id = 1

image        = no_selection
small_image  = no_selection
thumbnail    = no_selection
```

---

## 4. Root Cause — Magento Store Scope

This exposed an important Magento EAV concept.

Magento resolves store-scoped attributes approximately like this:

```text
Requested Store View
        ↓
Does store-specific value exist?
        ↓
YES
        ↓
Use store-specific value
```

The value:

```text
no_selection
```

is still a real stored value.

Therefore Magento did **not** fall back to:

```text
store_id = 0
```

even though the global value contained the correct image.

Instead:

```text
Store 1
small_image = no_selection

        ↓

PLP requests small_image

        ↓

Magento placeholder
```

---

## 5. Confirmed the Image Fix With One Product

Before modifying every product, we tested only:

```text
BEAN001
```

We deleted its erroneous Store View `no_selection` override for:

```text
image
small_image
thumbnail
```

This did **not** remove the actual image.

It simply allowed Magento to fall back to:

```text
store_id = 0
```

Immediately after doing this:

```text
BEAN001 PLP image appeared ✅
```

This confirmed the root cause.

---

## 6. Cleaned Invalid Store-View Image Overrides

Once the test succeeded, the same cleanup could be applied to the affected catalog records.

Conceptually:

```text
BEFORE

Store 0 → image.jpg
Store 1 → no_selection ❌


AFTER

Store 0 → image.jpg
Store 1 → no explicit override

               ↓

Store 1 inherits Store 0

               ↓

PLP image ✅
```

This was an important backend finding rather than a CSS fix.

---

## 7. Future ERP Image Requirement Identified

This image problem also highlighted something we should solve properly in the ERP integration.

Our future ERP product-image importer must ensure:

```text
Product image downloaded
        ↓
Attached to Magento media gallery
        ↓
image role assigned
small_image role assigned
thumbnail role assigned
        ↓
Correct store scope
```

and should avoid accidentally creating:

```text
store_id = X → no_selection
```

overrides.

This is now the **next major feature after PLP**.

---


## 10. Product Image Scaling

The PLP image container now uses a controlled fixed area so every product card remains aligned regardless of source image dimensions.

Important concept:

```text
Different source images
        ↓
Same PLP image frame
        ↓
Consistent product cards
```

The product image area also retains overflow handling and consistent positioning.

---

## 11. SALE Badge

We added custom SALE detection to the Magento product listing.

The logic compares:

```text
Regular Price
vs
Final Price
```

Conceptually:

```text
finalPrice < regularPrice
        ↓
Display SALE
```

Example:

```text
Regular: ₹450
Final:   ₹399

        ↓

SALE badge
```

---

## 12. SALE Badge Styling Problem

Initially:

```text
Sale
```

appeared as plain text.

The PHP logic was therefore working, but the LESS selector/style was not being applied correctly.

We corrected the styling and positioned the badge relative to:

```text
.product-item-info
```

The final presentation is:

```text
┌─────────────────────────┐
│ [ SALE ]                │
│                         │
│      PRODUCT IMAGE      │
│                         │
└─────────────────────────┘
```

with BrewCraft colors and typography.

---

## 13. Duplicate Active Navigation Underline

Another issue returned during PLP testing.

For the active category:

```text
Coffee
```

Magento/Luma displayed its active-category border while BrewCraft also displayed our custom underline.

Result:

```text
Coffee
────────
────────
```

Two active lines appeared.

We added a stronger header override targeting Magento's:

```text
.level0.active
.level0.has-active
```

and removed Luma's inherited border.

The BrewCraft custom underline remains.

Result:

```text
Coffee
────────
```

✅ Single active indicator.

---

## 14. Product Card Add-to-Cart Status

The Add to Cart issue from the earlier PLP work remained fixed.

Magento's floating Luma product action behavior had previously caused the button to be clipped.

The final product-card structure keeps actions in the normal card flow:

```text
Image
Name
Price
Add to Cart
```

and the complete button remains visible.

---

## 15. Product Card Content Polish

We cleaned up the information hierarchy inside each product card.

The intended structure became:

```text
Product Image

Product Name

₹399   ₹450

[ Add to Cart ]
```

rather than Magento's more verbose default pricing presentation.

---

## 16. Special Price Styling

Magento can display labels similar to:

```text
Special Price ₹399
Regular Price ₹450
```

which looked too default/Luma-like.

The PLP styling was adjusted so the visual presentation focuses on:

```text
₹399    ₹450
```

where:

```text
₹399 → current price
₹450 → smaller crossed-out old price
```

The SALE badge already communicates that the product is discounted, so repeating verbose price labels was unnecessary.

---

## 17. SKU Decision

We considered displaying SKU inside product cards.

We decided **not to add SKU at this stage** because it would increase visual clutter.

The PLP should focus primarily on:

```text
Image
Product name
Price
CTA
```

SKU can remain more prominent on PDP/B2B areas where it has more value.

---

## 18. Top Toolbar Redesign

Magento's original toolbar layout was approximately:

```text
[Grid] [List]    5 Items       Sort By [Position] ↑
```

The desired structure was:

```text
5 Items             Sort by [ Position ] [Grid] [List]
```

We therefore reordered the existing Magento components with CSS rather than changing Magento's toolbar functionality.

---

## 19. Toolbar Final Order

The final logical order is:

```text
.toolbar-amount
        ↓
order: 1

.toolbar-sorter
        ↓
order: 2

.modes
        ↓
order: 3
```

The sorter is pushed toward the right using automatic margin spacing.

Final layout:

```text
5 Items                      Sort by [ Position ] [▦] [☷]
```

This matched the desired BrewCraft arrangement.

---

## 20. Removed Sort Direction Arrow

Magento normally shows a separate ascending/descending action beside the Sort By dropdown:

```text
Sort By [ Position ] ↑
```

We did not want this separate arrow in the BrewCraft design.

It was hidden while keeping the actual sort dropdown operational.

Final:

```text
Sort by [ Position ]
```

---

## 21. Grid / List Mode Styling

Magento's native:

```text
Grid
List
```

controls were retained.

They now have a cleaner BrewCraft visual treatment with:

```text
consistent dimensions
border
active state
hover state
theme colors
```

No functionality was removed.

---

## 22. Layered Navigation Styling

Next we redesigned:

```text
Shopping Options
```

while preserving Magento's native layered-navigation functionality.

The sidebar includes filters such as:

```text
CATEGORY
PRICE
```

and can automatically support more filterable product attributes later.

---

## 23. Filter Card Styling

The filter area was converted into a cleaner BrewCraft card using:

```text
Cream Shopping Options heading
Subtle border
Rounded corners
Separated filter groups
BrewCraft typography
Clean filter counts
Hover states
```

This removed much of the default Luma appearance.

---

## 24. Layered Navigation Was Not Reimplemented

This is important architecturally.

We did **not** manually build filtering functionality.

Magento still controls:

```text
filter URLs
product collection filtering
filter counts
selected values
price ranges
attribute filters
```

Our theme only changes how those controls look.

---

## 25. Pagination Initially Did Not Appear

The category had:

```text
5 products
```

while Magento was configured for:

```text
Show 12 per page
```

Therefore:

```text
5 <= 12
    ↓
Everything fits on page 1
    ↓
No pagination
```

This was correct Magento behavior.

---

## 26. Created a Pagination Test Scenario

To test our pagination design, we temporarily changed Magento's grid configuration.

For example:

```text
Allowed Values:
2,3,5,12

Default:
2
```

With:

```text
5 total products
2 products/page
```

Magento created:

```text
Page 1 → 2
Page 2 → 2
Page 3 → 1
```

so pagination became visible.

---

## 27. Pagination Styling

We styled Magento's native pager to use:

```text
square page buttons
subtle borders
BrewCraft brown current page
hover states
styled next/previous actions
```

The visual direction became:

```text
[1] [2] [3] [>]
```

rather than the default Magento pager.

---

## 28. Bottom Toolbar / Limiter Issue

Pagination exposed another Magento CSS behavior.

The bottom toolbar also displayed:

```text
Show [2] per page
```

The first attempts to reposition it using:

```text
.toolbar-bottom
```

did not affect the element at all.

This led us to inspect Magento's actual compiled CSS.

---

## 29. Important CSS Selector Discovery

Using browser Inspect, we found Magento was positioning the bottom limiter with:

```css
.products.wrapper ~ .toolbar .limiter
```

This selector was the important part.

The `~` is the **general sibling combinator**.

It means approximately:

> Find a `.toolbar` that occurs later as a sibling of `.products.wrapper`, then select its `.limiter`.

So Magento was specifically targeting the bottom product-list toolbar.

This explained why several of our earlier generic selectors did not move it as expected.

---

## 30. Pagination Position Found Through DevTools

We also inspected the actual rule controlling:

```text
.pages
```

and manually tested margin values directly in the browser.

A working position was found using approximately:

```text
margin:
34px 50px 50px
```

for the pagination block.

For the limiter, testing something similar to:

```text
margin-left: 100px
```

proved that we had finally identified the correct selector and positioning context.

Those working values were then transferred from DevTools into `_plp.less`.

---

## 31. Why DevTools Was Useful Here

This was a very good real-world debugging example.

Instead of continuing to guess selectors:

```text
Write LESS
↓
clear static
↓
refresh
↓
nothing changes
↓
guess again
```

we switched to:

```text
Inspect element
↓
Find exact compiled selector
↓
Modify CSS live
↓
See immediate result
↓
Copy working rule into theme LESS
```

That is much faster for frontend debugging.

---

## 32. Pagination Completed

After using the actual Magento selectors and tested margins:

```text
Pagination ✅
Products-per-page limiter ✅
Spacing ✅
```

The pagination area was finally accepted as complete.

---

## 33. Multi-Category PLP Verification

After all the major work was completed, we checked the PLP design generally across the catalog structure.

The important things verified were:

```text
Hero dimensions
Title positioning
Description positioning
Product card dimensions
Image area
Sale badge
Toolbar
Layered navigation
Pagination
Page alignment
```

The PLP currently behaves consistently enough to mark the frontend design complete.

---

## 34. Missing Subcategory Content Identified

One final data issue became visible when viewing subcategories.

If a category has:

```text
no category image
```

the hero falls back to its brown background.

If it also has limited content, the result looks like:

```text
┌────────────────────────────┐
│                            │
│ Category Title             │
│                            │
│       brown background     │
│                            │
└────────────────────────────┘
```

This is technically valid but visually incomplete.

---

## 35. Decision: Do Not Manually Populate Every Subcategory

Instead of manually opening every Magento category and entering:

```text
description
image
```

we decided that these should also become ERP-managed fields.

This makes sense because category hierarchy is already coming from ERP.

---

## 36. New ERP Category Structure

Our category payload can eventually become something like:

```json
{
    "code": "COFFEE_BEANS",
    "name": "Coffee Beans",
    "parent_code": "COFFEE",
    "description": "Explore whole coffee beans selected for freshness, balanced flavor and consistent brewing.",
    "image_url": "..."
}
```

Magento category import would then handle:

```text
ERP code
ERP parent_code
ERP name
ERP description
ERP image
         ↓
Magento Category
```

---

## 37. Next ERP Media Work

The next development stage is now larger than only product images.

It will cover:

```text
ERP MEDIA / CONTENT SYNC

1. Product images
2. Product image roles
3. Category images
4. Category descriptions
5. Subcategory images
6. Subcategory descriptions
```

This means Magento Admin should not require manual image assignment for every imported catalog entity.

---

## 38. Final PLP Feature Status

At the end of today's session:

```text
PLP page structure                 ✅
Global alignment                   ✅

Breadcrumb                         ✅
Category hero                      ✅
Category title overlay             ✅
Category description overlay       ✅
Fixed hero dimensions              ✅

Layered navigation                 ✅
Shopping Options styling           ✅
Category filter                    ✅
Price filter                       ✅

Product grid                       ✅
3 products per row                 ✅
Product card sizing                ✅
Larger image area                  ✅
Real Magento image rendering       ✅
Image store-scope issue diagnosed  ✅
Image store-scope issue fixed      ✅

Sale detection                     ✅
SALE badge                         ✅
Product name styling               ✅
Regular price                      ✅
Special price                      ✅
Old-price styling                  ✅
Add to Cart                        ✅

Toolbar                            ✅
Product count position             ✅
Sort By styling                    ✅
Sort direction arrow removed       ✅
Grid/List styling                  ✅

Pagination functionality           ✅
Pagination styling                 ✅
Limiter positioning                ✅

Active-category underline          ✅
Duplicate underline removed        ✅

Multi-category visual check        ✅
```

## PLP Status

### ✅ BrewCraft PLP Design — COMPLETE

There is no major PLP design work left at this point.

Any changes from here should be treated as later refinement rather than unfinished PLP implementation.

---

## Next Development Stage

Tomorrow/next session, the priority is:

```text
PLP COMPLETE
      ↓
ERP CATALOG MEDIA & CONTENT SYNC
      ↓
Product Images
      ↓
Product Image Roles
      ↓
Category Images
      ↓
Category Descriptions
      ↓
Subcategory Images
      ↓
Subcategory Descriptions
```

One especially useful backend requirement for that implementation will be making the sync **idempotent**: repeatedly running the ERP import should update/reuse existing media instead of attaching duplicate images every time.

That gives us a natural transition from the storefront/frontend work back into Magento backend integration.

# 5. BrewCraft PDP Development Log
**DATE** 14 AUG 
### Objective

The goal is to transform Magento's default Luma PDP into the BrewCraft Figma design while preserving Magento's native product functionality.

We kept Magento functionality for:

```text
product gallery
pricing
reviews
stock
quantity
Add to Cart
wishlist
detailed information
```

and changed presentation/layout around it.

---

## Initial PDP State

The initial Magento PDP was mostly default Luma:

```text
large horizontal gallery
thumbnails under image
very large thin product title
default price styling
SKU visible
stock far to the right
default Qty field
blue Add to Cart
Wishlist + Compare links
default Details / Reviews tabs
huge default review form
```

---

## ERP Media Already Working

Before PDP styling started, ERP media sync had already populated multiple product images.

Magento could display:

```text
main image
secondary image
third image
fourth image
```

This gave us real gallery content to work with.

---

## PDP Container Alignment

We aligned the PDP with the same BrewCraft global page width used elsewhere.

The PDP uses:

```text
max-width: 1440px
```

with consistent horizontal padding.

This keeps:

```text
header
PLP
PDP
footer
```

visually aligned.

---

## Main PDP Two-Column Layout

The product area was adjusted roughly to:

```text
52% → product gallery
48% → product information
```

This created a better balance between image and product information.

---

## Product Gallery Styling

Magento's native Fotorama gallery was retained.

We customized:

```text
main gallery size
background
thumbnail styling
active thumbnail
image fit
```

The main gallery uses:

```text
object-fit: contain
```

so the full product remains visible.

---

## Vertical Figma-Style Thumbnail Gallery

The Figma uses:

```text
thumbnail
thumbnail
thumbnail     main image
thumbnail
```

instead of thumbnails below the image.

We configured Magento's gallery through:

```text
etc/view.xml
```

using:

```text
nav = thumbs
navdir = vertical
```

This made thumbnails appear vertically on the left.

---

## Thumbnail Active Border Problem

After switching to vertical thumbnails, clicking different images caused Fotorama's native moving active rectangle to appear in the wrong place.

The issue came from:

```text
.fotorama__thumb-border
```

because Fotorama calculates that element's position based on its own thumbnail dimensions.

Our custom dimensions caused the calculated border to become visually displaced.

We hid Fotorama's moving border and styled the active thumbnail directly.

Conceptually:

```text
native moving border ❌

actual active thumbnail border ✅
```

---

## Product Title Styling

The default Luma title was too light and oversized.

It was changed to BrewCraft styling:

```text
Poppins
espresso color
stronger weight
controlled line height
```

---

## Reviews + Stock Row

Originally Magento displayed:

```text
review link
```

near the title while:

```text
IN STOCK
SKU
```

appeared separately.

The target was:

```text
Reviews     IN STOCK
```

directly below the product name.

We created:

```text
brewcraft.product.meta
```

inside `product.info.main`.

Then moved:

```text
product.info.review
product.info.stock.sku
```

into that container.

The SKU block was removed.

Current result:

```text
Product Name

Be the first to review this product     IN STOCK
```

---

## SKU Removed

The BrewCraft design did not need SKU prominently displayed.

So:

```text
product.info.sku
```

was removed from PDP presentation.

---

## Price Styling

Magento initially displayed:

```text
₹399
Regular Price ₹950
```

We wanted:

```text
₹399    ₹950
```

where:

```text
₹399 → main price
₹950 → smaller strikethrough price
```

The Magento:

```text
Regular Price
Special Price
```

labels were hidden.

---

## Short Description

We added sample short-description content for `BEAN001`:

```text
Smooth, aromatic Arabica coffee with balanced sweetness,
gentle acidity, and a clean finish...
```

Initially Magento rendered the overview below Wishlist.

The reason was that the wrong layout sibling was used as the move target.

We corrected:

```xml
product.info.overview
```

to move before:

```text
product.info
```

because those two are siblings within:

```text
product.info.main
```

Final intended hierarchy:

```text
Title

Reviews + Stock

Price

Short Description

Qty

Add to Cart

Wishlist
```

---

## Quantity Stepper

Magento normally provides only:

```text
Qty [1]
```

The Figma uses:

```text
[-] [1] [+]
```

So we overrode:

```text
Magento_Catalog/templates/product/view/addtocart.phtml
```

and added:

```text
minus button
qty input
plus button
```

---

## Qty JavaScript Problem

Initially we created:

```text
Magento_Catalog/js/brewcraft-qty
```

as a separate RequireJS component.

But the browser produced:

```text
Script error for "Magento_Catalog/js/brewcraft-qty"
```

and the quantity did not change.

We simplified it.

Instead of a custom external RequireJS module, the qty logic now uses Magento's already-loaded jQuery directly inside `addtocart.phtml`.

Final behavior:

```text
+ → increase quantity
- → decrease quantity
minimum → 1
```

You confirmed Qty now works correctly.

---

## Add to Cart Problem

At one point Add to Cart also stopped working.

Root cause:

The `<script type="text/x-magento-init">` block contained **two separate JSON objects**, making the initializer invalid.

Example of the broken pattern:

```text
{ validation config }

{ qty config }
```

Magento could not parse it.

Therefore:

```text
validate-product.js
```

never initialized and the button remained disabled.

We corrected the initializer.

Final result:

```text
Add to Cart ✅
```

You confirmed products can successfully be added to cart.

---

## Add to Cart Styling

The default Magento blue button was replaced with the BrewCraft style:

```text
full-width
espresso background
white text
theme hover state
```

---

## Wishlist

The Figma shows Wishlist as a full secondary button under Add to Cart.

Magento's normal Wishlist link was styled as:

```text
[ ♥ Add to Wish List ]
```

with:

```text
white background
espresso border
full width
```

---

## Compare Removed

Magento's:

```text
Add to Compare
```

was removed from the PDP.

---

## Full Description

We added a real long description for testing.

It includes:

```text
product introduction
second paragraph
Highlights
bullet list
```

This content uses Magento's native:

```text
description
```

attribute.

---

## New Structured PDP Tabs Requirement

The Figma lower PDP uses:

```text
Overview
Specifications
What's Included
Shipping & Returns
Reviews
```

Magento originally had only:

```text
Details
More Information
Reviews
```

So we began restructuring the detailed-info area.

---

## Overview

The Magento native description block was retained and renamed conceptually to:

```text
Overview
```

It renders the full product description.

---

## Specifications Tab

A custom template was created:

```text
Magento_Catalog/templates/product/view/specifications.phtml
```

It reads the ERP-backed custom attributes.

Example output for BEAN001:

```text
Bean Type       Espresso/Arabica
Roast Level     Medium
Flavor Profile  Smooth, balanced, mildly sweet
Brew Methods    Espresso, Pour Over, French Press, Filter
```

The template only renders attributes with values.

So irrelevant blank fields are automatically omitted.

---

## What's Included Tab

A custom template was created:

```text
included.phtml
```

It reads:

```text
included_items
```

from the ERP-backed Magento product attribute.

It supports comma-separated values generated from ERP arrays.

If no items exist, it currently displays a fallback message.

---

## Shipping & Returns Tab

A custom template was created:

```text
shipping-returns.phtml
```

This is intentionally not ERP product data.

It contains store-level policy content such as:

```text
Shipping
Returns
```

The long-term recommendation is to move this into a Magento CMS block so merchants can edit it without deployment.

---

## Reviews Tab

Magento's native Reviews functionality is retained.

That means Magento still handles:

```text
Nickname
Summary
Review
Submit Review
```

and later existing customer reviews.

We are only changing presentation.

---

## Tab JavaScript Issue

We temporarily introduced an additional custom tab initializer:

```text
pdp-tabs.js
tabs-init.phtml
```

on top of Magento's native detailed-info tabs.

This created competing tab behavior.

The custom tab initializer was then removed so Magento's native tab mechanism could remain the source of truth.

---

## Major Tab CSS Problem

During tab refinement, several different CSS strategies were added one after another:

```text
display: block
display: flex
display: grid
inline-block titles
flex titles
grid titles
```

all targeting the same:

```text
.product.data.items
.item.title
.item.content
```

This caused:

```text
tabs overlapping
titles collapsing left
different active-tab widths
review form appearing on right
empty content appearing offset
```

We identified that the core issue was **competing CSS blocks**, not missing data.

---

## Tab CSS Cleanup

The duplicated tab CSS was removed and replaced with one consistent layout approach.

The current goal is:

```text
5 equal-width tabs
↓
one full-width content area
```

Conceptually:

```text
┌──────────┬──────────┬──────────┬──────────┬──────────┐
│ Overview │ Specs    │ Included │ Shipping │ Reviews  │
├──────────┴──────────┴──────────┴──────────┴──────────┤
│                                                       │
│                  ACTIVE CONTENT                       │
│                                                       │
└───────────────────────────────────────────────────────┘
```

---

## Current Remaining Tab Problem

This is where we stopped today.

Although the tabs now appear and switch correctly, their internal content is **still visually inconsistent**.

Examples:

### Specifications

fills most of the content area.

### What's Included

when empty, its message may appear too far right/small.

### Reviews

Magento's native review form has its own width/float behavior, so it can appear aligned differently.

The next fix is to normalize all active tab content so every tab uses:

```text
same width
same padding
same minimum height
same left alignment
```

This is **not complete yet**.

We agreed to continue this tomorrow morning.

---



# 6. BrewCraft PDP Development Log
**DATE** 15 AUG
## Phase: Tabs, Specifications, Benefits, Related Products, Reviews, Image Roles

### 1. Starting point

The top half of the PDP was already mostly complete:

* product gallery on the left
* vertical thumbnails
* product name
* review link + stock status
* current/old price styling
* short description
* quantity stepper
* Add to Cart
* Wishlist
* Compare removed

The remaining Figma-driven sections were mainly:

```text
Benefits strip

Overview
Specifications
What's Included
Shipping & Returns
Reviews

You May Also Like
```

The goal was to build these sections while keeping Magento’s native product functionality working.

---

## 2. PDP detailed-information section

Magento already has a native detailed-information area on the PDP.

By default it contains blocks such as:

```text
Details
More Information
Reviews
```

Internally, Magento groups these blocks under:

```text
product.info.details
```

and blocks that belong to:

```text
group="detailed_info"
```

are rendered as tabs/collapsible sections.

Instead of building a completely separate tabs framework, we decided to **reuse Magento’s native detailed-info mechanism**.

This was important because Magento already handles:

* active tab
* accessibility attributes
* show/hide state
* native reviews
* native description block

So our job was mainly:

```text
reorder blocks
add custom blocks
style them
```

rather than rebuild tab JavaScript from scratch.

---

## 3. `catalog_product_view.xml`

The main layout file used for PDP customization was:

```text
app/design/frontend/BrewCraft/supply/
Magento_Catalog/layout/catalog_product_view.xml
```

### What is a Magento layout XML file?

Magento pages are assembled from:

```text
Containers
Blocks
Templates
```

Layout XML tells Magento:

* which blocks exist
* where blocks should render
* which blocks should move
* which blocks should be removed
* what templates blocks use
* what arguments/configuration blocks receive

It does **not** normally contain the final HTML itself.

Think of it like:

```text
Layout XML
    ↓
decides page structure

PHTML
    ↓
renders HTML

LESS/CSS
    ↓
controls appearance

JS
    ↓
controls interaction
```

For PDP pages, Magento loads layout handles including:

```text
catalog_product_view
```

so overriding:

```text
Magento_Catalog/layout/catalog_product_view.xml
```

inside the child theme lets us modify PDP structure without touching Magento vendor files.

---

## 4. Moving existing Magento PDP blocks

We reused several native blocks.

For example:

```text
product.info.review
product.info.stock.sku
product.info.overview
```

Instead of copying their HTML, we moved them using:

```xml
<move ... />
```

This is preferable because Magento keeps ownership of the functionality.

Example concept:

```xml
<move
    element="product.info.overview"
    destination="product.info.main"
    before="product.info"
/>
```

This moved the short description above:

```text
Qty
Add to Cart
Wishlist
```

without rewriting Magento's short-description template.

---

## 5. Creating custom PDP tabs

The Figma required:

```text
Overview
Specifications
What's Included
Shipping & Returns
Reviews
```

Magento did not provide all of these natively.

So custom blocks were added beneath:

```text
product.info.details
```

with:

```text
group="detailed_info"
```

Example concept:

```xml
<block
    class="Magento\Catalog\Block\Product\View"
    name="brewcraft.product.specifications"
    template="Magento_Catalog::product/view/specifications.phtml"
    group="detailed_info">
```

The key part is:

```xml
group="detailed_info"
```

because Magento's detailed-info renderer looks for child blocks belonging to that group and renders them as individual tabs.

---

## 6. Overview tab

Magento already provides the product long description through:

```text
product.info.description
```

and the native product attribute:

```text
description
```

So we did not create another description system.

We simply reused Magento’s description block and treated it as:

```text
Overview
```

This kept ERP-synced long description content compatible with Magento’s native PDP architecture.

---

## 7. Specifications tab

A custom template was created:

```text
Magento_Catalog/templates/product/view/specifications.phtml
```

The template reads the new ERP-backed Magento attributes, such as:

```text
bean_type
roast_level
flavor_profile
brew_methods

material
power
voltage
warranty
water_tank_capacity
bean_hopper_capacity
pump_pressure
dimensions
```

The important behavior was:

```text
attribute has value
→ show specification row

attribute empty
→ skip it
```

So different product types can display completely different specifications.

Example coffee:

```text
Bean Type        Arabica
Roast Level      Medium
Flavor Profile   Smooth, balanced...
Brew Methods     Espresso, Pour Over...
```

Example machine:

```text
Material              Stainless Steel
Power                 1850 W
Voltage               230 V
Warranty              2 Years
Water Tank Capacity   2 L
Bean Hopper Capacity  250 g
```

This works because the attributes were previously created using a Data Patch and populated through ERP sync.

---

## 8. Why specifications were dynamically filtered

We specifically did **not** want empty rows like:

```text
Voltage:
Warranty:
Pump Pressure:
```

for a coffee product.

The template therefore checked the actual Magento product value before rendering each row.

This gave us one reusable Specifications template for many product types.

---

## 9. What's Included tab

A custom product attribute:

```text
included_items
```

was introduced earlier.

ERP can send:

```json
"included_items": [
    "Portafilter",
    "Milk Jug",
    "Filter Baskets"
]
```

The importer converts it into a Magento-storable string.

The PDP template then converts that string back into a list for display.

We initially showed an empty message when no values existed:

```text
No additional items are listed for this product.
```

Later we decided this was not useful.

The correct behavior became:

```text
included_items empty
→ render nothing
→ What's Included tab should not appear
```

This is a useful Magento pattern:

> Optional frontend sections should disappear completely when the product does not have relevant data.

---

## 10. Shipping & Returns

Shipping & Returns was intentionally **not stored in ERP product data**.

Reason:

Shipping policy is usually store/business-level content, not product-master data.

So the ownership model became:

```text
ERP
→ product-specific data

Magento
→ store policy / CMS content
```

Initially we rendered a simple theme template with:

```text
Shipping
Returns
```

content.

Long-term, a Magento CMS block would be even better because a merchant could edit policy text without code deployment.

---

## 11. Reviews tab

Magento’s native review system was retained.

We did not build our own review database or form.

Magento already provides:

```text
review submission
rating
nickname
summary
review body
moderation
approved review display
```

This is important because review functionality has real Magento business logic behind it.

---

## 12. How Magento product reviews work

The flow we verified was:

```text
Customer submits review
        ↓
Magento stores review as Pending
        ↓
Admin approves review
        ↓
Approved review becomes visible on storefront
```

Admin areas include review management under Magento's user-content/review sections.

We verified that approved reviews appear on the PDP.

---

## 13. Rating / stars

Magento uses its native rating system.

The review form can show:

```text
★★★★★
```

using Magento's rating control.

The native widget uses overlapping labels/radio controls rather than five ordinary image icons.

Because of that, styling the star input is more sensitive than normal form elements.

We encountered an issue where the stars became positioned near the next field / appeared visually detached.

This came from Magento’s positioned rating-widget CSS interacting with our custom review-form styling.

We added a positioning container to keep the native rating control anchored correctly.

The main lesson:

> Do not treat Magento’s review rating widget as five ordinary stars. It has its own structural CSS and positioning logic.

---

## 14. Review list template override discovery

When we tried changing the review display order, we first targeted the wrong module path.

The actual Magento source template is:

```text
vendor/magento/module-review/
view/frontend/templates/product/view/list.phtml
```

Therefore the correct theme override path is:

```text
app/design/frontend/BrewCraft/supply/
Magento_Review/templates/product/view/list.phtml
```

### Important Magento theme override rule

To override a module template in a theme:

```text
vendor/magento/module-xxx/view/frontend/templates/path/file.phtml
```

becomes:

```text
app/design/frontend/Vendor/theme/
Magento_Xxx/templates/path/file.phtml
```

So:

```text
module-review
```

maps to:

```text
Magento_Review
```

This same rule is useful across Magento modules.

---

## 15. Why copying vendor files should be done carefully

The safest way to override a native Magento template is:

```text
1. locate actual vendor template
2. copy it into theme override path
3. verify override is loaded
4. change only necessary markup
```

rather than writing a replacement from memory.

We used a temporary HTML comment idea such as:

```html
<!-- BREWCRAFT REVIEW TEMPLATE -->
```

to verify Magento was actually loading the theme override.

This is a very useful debugging technique.

---

## 16. Review redesign was intentionally dropped

We considered redesigning the Reviews tab into:

```text
Reviews list       | Write a Review form
```

but the Magento review markup and the existing tab styling made this more invasive than worthwhile.

Since the native review functionality already works, we decided:

```text
functionality > unnecessary visual complexity
```

and stopped the redesign.

This is an important development decision:

> Not every Figma variation is worth overriding heavily if Magento's native behavior is already functional and the customization increases maintenance risk.

The PDP review section is therefore considered acceptable for this phase.

---

## 17. Tab CSS problems encountered

The tab section was the most troublesome part of the PDP.

At different points we tried:

```text
display: block
display: flex
display: grid
inline-block tab titles
custom tab JS
native Magento tab JS
```

Multiple CSS blocks ended up targeting the same selectors:

```text
.product.data.items
.item.title
.item.content
```

This caused:

* overlapping tab titles
* tabs moving position
* review form aligned differently
* varying content widths
* content appearing in unexpected places

The root cause was not Magento data.

It was **multiple competing frontend layout rules**.

The fix was to remove duplicate tab CSS and keep a single layout strategy.

---

## 18. Custom tabs JavaScript experiment

We temporarily created a custom:

```text
pdp-tabs.js
```

to initialize Magento tabs manually.

However Magento's product detailed-info section was already initialized natively.

So we effectively had:

```text
Magento native tab JS
+
our custom tab JS
```

operating on the same DOM.

That created inconsistent active states.

We removed the custom initializer and returned to Magento's native tab functionality.

Lesson:

> Before initializing a Magento widget manually, check whether the core component already initializes that DOM element.

---

## 19. PDP benefits strip

The Figma also contained a service-benefits strip.

We created a custom template with four benefits:

```text
Free Shipping
Reliable Supply
Expert Support
Business Solutions
```

This was inserted between the product purchase section and the detailed tabs.

The strip uses a simple standalone PHTML template because these values are mostly store-level marketing/service content.

---

## 20. Benefits strip styling

Initially the strip had a cream/beige background and visually touched the product image area.

We changed it to:

```text
white background
light border
very subtle shadow
space above
space below
```

This made it feel like an independent section instead of being attached to the gallery.

---

## 21. Related Products

Magento's native Related Products functionality was used for:

```text
You May Also Like
```

instead of building a completely separate recommendation system.

Products were assigned in Magento Admin using native product relationships.

This allows merchandising relationships to remain Magento-owned.

---

## 22. Related Products vs ERP

We deliberately did not add related-product SKUs to ERP yet.

Ownership decision:

```text
ERP
→ product master data

Magento
→ merchandising relationships
```

Why?

Because a merchandising/admin team may want to change:

```text
related products
upsells
cross-sells
```

without changing ERP.

ERP relation syncing could be added later as a separate learning feature.

---

## 23. Related Products layout

The native related-products block initially rendered:

* helper text
* selection checkboxes
* wishlist icon
* compare icon
* many products in a loose row

We simplified it into:

```text
5 cards
single desktop row
compact image
product name
price
old price
```

The sixth and later items were hidden for the current storefront design.

---

## 24. Why we did not keep Add to Cart on hover

We considered showing Add to Cart on card hover.

However the native related-products template did not output the Add to Cart button markup we needed.

CSS can only style an existing element.

CSS cannot create:

```html
<button>Add to Cart</button>
```

So instead of overriding the related-products template just for hover cart behavior, we decided to keep the section simpler.

This reduced unnecessary customization.

---

## 25. Magento product image problem

The most useful technical discovery today involved Magento image processing.

Uploaded product images looked correct originally.

But in storefront cached URLs, they sometimes contained large white borders.

Example:

```text
original image
→ no white border

Magento cached image
→ white space around image
```

At first this looked like a CSS issue.

It was not.

Magento was generating resized cached images according to theme image configuration.

---

## 26. What is `etc/view.xml`?

The theme file:

```text
app/design/frontend/BrewCraft/supply/etc/view.xml
```

is a frontend configuration file.

It controls several view-related settings, especially product images and gallery configuration.

It can define:

```text
product image roles
image width/height
image framing behavior
aspect ratio
transparency
gallery thumbnail settings
gallery direction
```

So it is not ordinary page layout XML.

It configures how Magento should render/generate frontend assets for the theme.

---

## 27. Magento image roles

Magento does not necessarily serve the original uploaded product image directly everywhere.

Different frontend locations use different image roles.

Examples include:

```text
product_page_image_medium
product_page_image_large
related_products_list
```

Each role can have its own:

```text
width
height
frame
aspect ratio
constrain
transparency
```

Magento then generates cached versions under:

```text
pub/media/catalog/product/cache/
```

---

## 28. Why Magento added white space

Magento's image-generation settings can preserve the entire image inside a requested canvas.

That can result in:

```text
requested canvas
┌──────────────────────┐
│      white space     │
│   original image     │
│      white space     │
└──────────────────────┘
```

This behavior is useful in some catalog designs because every card gets a consistent image canvas.

But it did not match BrewCraft's design.

---

## 29. `frame=false`

The important `view.xml` configuration was:

```xml
<frame>false</frame>
```

This told Magento not to create the unwanted surrounding frame for that image role.

We first fixed:

```text
related_products_list
```

and confirmed the related products looked much better.

Then we applied the same concept to PDP image roles such as:

```text
product_page_image_medium
product_page_image_large
```

---

## 30. `view.xml` gallery configuration

Earlier in PDP work, `view.xml` was also used for Fotorama gallery configuration.

For example:

```text
nav = thumbs
navdir = vertical
```

This changed the product gallery from:

```text
main image
thumbnails horizontally underneath
```

to:

```text
thumbnail
thumbnail    main image
thumbnail
thumbnail
```

matching the Figma direction.

So `view.xml` served two purposes in this project:

```text
gallery configuration
+
catalog image role configuration
```

---

## 31. Cached product images

Changing `view.xml` does not automatically alter an already-generated cached JPG.

Magento stores generated versions inside:

```text
pub/media/catalog/product/cache/
```

So after image-role changes we had to clear the product image cache:

```bash
rm -rf pub/media/catalog/product/cache/*
```

and regenerate images:

```bash
bin/magento catalog:images:resize
```

This is an important Magento workflow.

---

## 32. `catalog:images:resize`

This command generates resized product images according to the theme's configured image roles:

```bash
bin/magento catalog:images:resize
```

It is especially useful after:

* changing image dimensions
* changing framing
* changing image-role configuration
* changing themes

---

## 33. CSS `object-fit`

After Magento stopped adding the white frame, CSS still needed to decide how the real image fits the HTML image area.

We used:

```css
object-fit: cover;
```

for areas where the image should completely fill the frame.

Difference:

```text
contain
→ shows full image
→ may leave empty area

cover
→ fills entire area
→ may crop image edges
```

For the BrewCraft PDP and related cards, `cover` produced the stronger visual result.

---

## 34. Why CSS alone could not initially fix the white border

This is an important concept.

If Magento generated this JPG:

```text
[ white border + actual photo + white border ]
```

then CSS sees all of that as **one image**.

`object-fit` cannot distinguish:

```text
real photo pixels
vs
Magento-generated white pixels
```

Therefore the proper fix was:

```text
view.xml image generation
first

CSS object-fit
second
```

not CSS alone.

---

## 35. `i18n/en_US.csv`

We also needed to rename:

```text
Related Products
```

to:

```text
You May Also Like
```

An XML title argument did not work because the Magento template itself used a translated string for the heading.

Instead of overriding a large template just to change two words, we used theme translation.

File:

```text
app/design/frontend/BrewCraft/supply/
i18n/en_US.csv
```

Example:

```csv
"Related Products","You May Also Like"
```

---

## 36. What is the `i18n` folder?

`i18n` means:

```text
internationalization
```

Magento uses translation dictionaries to translate frontend strings.

A theme can contain:

```text
i18n/en_US.csv
i18n/fr_FR.csv
i18n/de_DE.csv
```

etc.

The CSV format is conceptually:

```text
"Original text","Replacement/translation"
```

So:

```csv
"Related Products","You May Also Like"
```

means whenever that translatable string is rendered within the theme scope, Magento can display:

```text
You May Also Like
```

instead.

---

## 37. Why use `i18n` instead of overriding PHTML?

Suppose a Magento template contains hundreds of lines but the only thing we want to change is:

```text
Related Products
```

Overriding the entire PHTML would mean:

```text
copy 200+ lines
maintain them forever
risk missing future Magento fixes
```

Translation override means:

```text
1 CSV line
```

This is much safer.

Use theme `i18n` when:

```text
only the displayed translatable text needs to change
```

Use a template override when:

```text
HTML structure / logic needs to change
```

---

## 38. LESS variable compile error

During review styling we used:

```less
@brewcraft-text-muted
```

but this variable did not exist in the theme.

Magento LESS compilation therefore failed with:

```text
variable @brewcraft-text-muted is undefined
```

The existing valid variable was:

```less
@brewcraft-muted
```

This reinforced an earlier theme lesson:

> Always reuse defined BrewCraft variables instead of inventing similarly named variables while writing new LESS.

---

## 39. Magento static/theme cleanup commands

Throughout PDP development we repeatedly used:

```bash
rm -rf var/view_preprocessed/*
rm -rf pub/static/frontend/BrewCraft/*
bin/magento cache:flush
```

Why?

### `var/view_preprocessed`

Magento creates preprocessed LESS/theme files here.

If stale files remain, updated LESS may not be reflected.

### `pub/static/frontend/BrewCraft`

Contains generated frontend theme assets.

Deleting this forces Magento developer-mode asset generation to use the newest source.

### `cache:flush`

Clears Magento caches that may preserve stale layout/template/configuration output.

---

## 40. Final PDP structure achieved

The current PDP now effectively contains:

```text
Breadcrumbs

Main product area
├── vertical image thumbnails
├── main product image
├── product name
├── review / stock
├── current / old price
├── short description
├── Qty - / +
├── Add to Cart
└── Wishlist

Benefits strip

Detailed information
├── Overview
├── Specifications
├── What's Included (only when applicable)
├── Shipping & Returns
└── Reviews

You May Also Like
└── native Magento Related Products

Footer
```

---

## 41. Important files involved

### PDP page structure

```text
Magento_Catalog/layout/catalog_product_view.xml
```

Purpose:

```text
move blocks
remove blocks
create custom PDP sections
add detailed-info tabs
```

---

### Product gallery/image configuration

```text
etc/view.xml
```

Purpose:

```text
PDP image size
related-product image size
frame handling
gallery navigation
vertical thumbnails
```

---

### Product tabs templates

```text
Magento_Catalog/templates/product/view/
specifications.phtml

Magento_Catalog/templates/product/view/
included.phtml

Magento_Catalog/templates/product/view/
shipping-returns.phtml

Magento_Catalog/templates/product/view/
benefits.phtml
```

---

### Quantity / cart template

```text
Magento_Catalog/templates/product/view/addtocart.phtml
```

Purpose:

```text
custom +/- quantity UI
native Magento Add to Cart
```

---

### Review-list override

```text
Magento_Review/templates/product/view/list.phtml
```

Purpose:

```text
customize existing review list markup
```

Though the final deeper review redesign was abandoned.

---

### Theme translations

```text
i18n/en_US.csv
```

Purpose:

```text
change frontend translatable labels without copying templates
```

Example:

```text
Related Products
→ You May Also Like
```

---

### PDP LESS

```text
web/css/source/_pdp.less
```

Purpose:

```text
gallery
product details
prices
quantity
buttons
benefits
tabs
specifications
reviews
related products
```

---

## 42. Major debugging lessons from this PDP

This phase gave us several very useful Magento lessons:

### 1. Structure problem ≠ CSS problem

If a block appears in the wrong place:

```text
check layout XML first
```

not CSS.

---

### 2. Image white border ≠ CSS padding

If the actual cached JPG contains white pixels:

```text
check view.xml
```

not only `.product-image-photo`.

---

### 3. Text-only change ≠ template override

If all you need is:

```text
Related Products → You May Also Like
```

use:

```text
i18n CSV
```

instead of copying a large template.

---

### 4. Native Magento JS should be reused

Avoid initializing the same native widget twice.

We learned this during tab customization.

---

### 5. Optional product content should disappear

For fields like:

```text
included_items
```

empty value should mean:

```text
no tab
```

not:

```text
empty tab with placeholder message
```

---

### 6. Always locate the real vendor template

Before overriding:

```bash
find vendor/magento -path '*review*list.phtml'
```

or inspect the actual module path.

Then mirror that path under the Magento module namespace in the theme.

---

## 43. PDP Completion Status

### Complete

```text
PDP page container/alignment          ✅
Two-column product layout             ✅
Vertical gallery                      ✅
Multiple ERP images                   ✅
Main PDP image framing                ✅
Product title                         ✅
Review + stock placement              ✅
SKU removed                           ✅
Price design                          ✅
Short description                     ✅
Quantity - / +                        ✅
Add to Cart                           ✅
Wishlist                              ✅
Compare removed                       ✅

Benefits strip                        ✅

Overview data                         ✅
Specifications                        ✅
ERP specification rendering           ✅
Optional What's Included              ✅
Shipping & Returns                    ✅
Reviews functionality                 ✅

Related Products                      ✅
5 products single row                 ✅
Related image sizing/frame            ✅
You May Also Like translation         ✅
```

### Intentionally not pursued further

```text
Major review-form redesign
Reviews-left / form-right layout
Heavy custom tab redesign
Hover Add to Cart on related cards
```

These were stopped because native functionality was working and the additional overrides were adding complexity without enough benefit.

## Final Status

### ✅ BREWCRAFT PDP DESIGN — CLOSED FOR THIS PHASE

The page is now functionally complete and substantially customized from Luma, while still retaining Magento's native product, gallery, cart, review, relationship and EAV behavior.

Any remaining differences from the Figma should be treated as part of a later **final storefront visual-polish pass**, not as unfinished PDP functionality.

# 7. BrewCraft – Cart & Mini Cart Development Log

### 1. Development Scope

The objective was to replace the largely default Magento/Luma shopping cart experience with a BrewCraft storefront implementation matching the established storefront design system.

The work covered:

* Shopping Cart page layout
* Product rows
* Product images
* Product information
* Stock indicator
* Price and subtotal presentation
* Quantity controls
* Edit/Delete/Wishlist actions
* Update Shopping Cart
* Coupon handling decision
* Order Summary
* Shipping estimator
* Checkout CTA
* Empty cart state
* Mini Cart
* Mini Cart automatic quantity updates
* Mini Cart checkout/view-cart buttons
* Business-only Request a Quote integration
* Interaction between Request Quote and Shipping estimator

The implementation retained Magento's native quote/cart functionality as much as possible and changed presentation/interaction through theme overrides and targeted frontend logic.

---

## 2. Main Files Involved

### Theme

```text
app/design/frontend/BrewCraft/supply/
```

Primary cart LESS:

```text
web/css/source/_cart.less
```

Mini Cart LESS:

```text
web/css/source/_minicart.less
```

Theme import:

```text
web/css/source/_extend.less
```

with:

```less
@import '_cart.less';
@import '_minicart.less';
```

---

### Full Cart item template

```text
Magento_Checkout/templates/cart/item/default.phtml
```

Used for:

* stock indicator
* custom quantity control
* native cart item data
* retaining Magento update behavior

---

### Empty Cart

```text
Magento_Checkout/templates/cart/noItems.phtml
```

Used for the custom empty-cart experience.

---

### Cart quantity JS

```text
web/js/brewcraft-cart-qty.js
```

Responsible for:

```text
[-] [quantity] [+]
```

on the full Shopping Cart page.

---

### Mini Cart Knockout override

```text
Magento_Checkout/web/template/minicart/item/default.html
```

Used for:

* compact minicart product rows
* custom `− / +` controls
* native Magento asynchronous quantity update
* removing unnecessary Edit action

---

### Request Quote module

Existing custom module:

```text
app/code/BrewCraft/RequestQuote/
```

Important files:

```text
view/frontend/templates/cart/request-quote.phtml
```

and:

```text
view/frontend/layout/checkout_cart_index.xml
```

The Request Quote block is rendered conditionally through:

```php
$block->canRequestQuote()
```

so the quote functionality remains business-customer-specific.

---

## 3. Shopping Cart Main Layout

The original Magento cart appeared with:

* oversized product rows
* default Magento visual styling
* large unused areas
* default blue CTA
* summary placement that did not match BrewCraft
* inconsistent actions
* large shipping estimator
* coupon block visually disconnected from the page

The design was changed into a desktop two-column layout:

```text
----------------------------------------------------------
| CART PRODUCTS                         | ORDER SUMMARY |
|                                       |               |
| Product 1                             | Subtotal      |
| Product 2                             | Shipping      |
|                                       | Total         |
|                     Update Cart       | Checkout      |
|                                       | Shipping      |
| Request Quote                         | Estimator     |
----------------------------------------------------------
```

Main target width:

```less
max-width: 1440px;
```

with desktop page padding around:

```less
padding-left: 32px;
padding-right: 32px;
```

The summary was given a fixed-width right-side card around:

```text
360px
```

while the product area takes the remaining available space.

---

## 4. Cart Product Table

The default Magento table was restyled into a cleaner BrewCraft card.

Columns retained:

```text
Item | Price | Qty | Subtotal
```

Product rows include:

```text
Product image
Product name
In Stock
Unit price
Quantity
Subtotal
Wishlist
Edit
Delete
```

The table received:

* BrewCraft border color
* rounded corners
* white background
* consistent vertical spacing
* centered price/quantity/subtotal columns
* reduced Luma visual noise

---

## 5. Product Image Handling

Magento's original cart images were not proportioned correctly for the design.

The cart image container was normalized to approximately:

```text
96px × 120px
```

using:

```less
object-fit: cover;
object-position: center;
```

This ensured ERP-synchronized product media could display consistently without stretching the row.

---

## 6. Product Name Styling

Product names were changed from default Magento link styling to BrewCraft typography.

Approximate styling:

```text
17–18px
medium weight
charcoal text
```

Hover:

```text
BrewCraft coffee brown
```

The native product URL remains intact.

---

## 7. Custom “In Stock” Indicator

The cart originally had no visual stock indicator.

A small stock badge was added below the product name through the cart item template.

Conceptually:

```php
<?php if ($_item->getProduct()->isAvailable()): ?>
    <div class="cart-stock-status">
        In Stock
    </div>
<?php endif; ?>
```

Final presentation:

```text
BrewCraft Signature Arabica Beans 2kg

[ In Stock ]
```

Badge style:

* pale green background
* green text
* rounded pill
* small font

This provides useful availability feedback without overloading the row.

---

## 8. Quantity Stepper – Full Cart

Magento's default cart displayed:

```text
[ 1 ]
```

We changed this to:

```text
[ − ][ 1 ][ + ]
```

The control was implemented using:

```text
Magento_Checkout/templates/cart/item/default.phtml
```

plus:

```text
web/js/brewcraft-cart-qty.js
```

Important design decision:

The `− / +` controls modify the cart input but retain Magento's native **Update Shopping Cart** workflow.

Flow:

```text
Click +
   ↓
Input changes
1 → 2
   ↓
Click Update Shopping Cart
   ↓
Magento submits cart form
   ↓
Subtotal / totals recalculated
```

The quantity cannot go below:

```text
1
```

The original Magento input naming was preserved:

```text
cart[item_id][qty]
```

which is important because Magento's cart update controller expects this structure.

---

## 9. Update Shopping Cart Button

The default Update Shopping Cart button was redesigned into a BrewCraft secondary CTA.

Final characteristics:

* white background
* coffee/brown border
* BrewCraft brown text
* approximately 44–46px high
* aligned to the right below product rows
* subtle cream hover state

Example:

```text
                           [ Update Shopping Cart ]
```

---

## 10. Wishlist Action

Magento rendered:

```text
Move to Wishlist
```

as text.

This became visually excessive next to Edit/Delete.

We converted the wishlist action into an icon-only circular control.

Final actions:

```text
♡   ✎   🗑
```

So the row now has three consistent compact actions:

* Wishlist
* Edit
* Delete

All use the same rounded icon-button visual system.

---

## 11. Edit and Delete Actions

Initially Magento's Edit/Delete icons overlapped because native Magento positioning was still active.

We reset:

```less
position
margin
display
alignment
```

and converted the action toolbar to flex.

Final layout:

```text
♡   ✎   🗑
```

with spacing between each action.

Delete received a subtle destructive/red hover treatment.

Edit retained BrewCraft brown styling.

---

## 12. Coupon / Discount Code

A significant amount of work was spent trying to make Magento's native coupon block visually align with:

```text
Apply Discount Code                 Update Shopping Cart
```

The challenge was Magento's DOM structure:

```text
form-cart
cart-summary
cart-discount
```

The coupon is rendered separately from the Update Cart action.

Several approaches were tested:

### CSS grid placement

Attempted to place:

```text
cart      summary
discount  summary
```

Problem:

When Summary changed height, especially when Shipping expanded, the coupon could move.

### Negative margin positioning

Attempted to visually pull the coupon upward beside Update Cart.

Problem:

This was fragile and depended too heavily on surrounding layout heights.

### JS DOM relocation

A wrapper/relocation experiment was also considered.

Problem:

It introduced unnecessary structural complexity and briefly broke the working cart layout.

### Final decision

The coupon feature was removed from the BrewCraft cart UI:

```less
.cart-discount {
    display: none !important;
}
```

This was an intentional product/design decision to avoid spending additional development time on an element that was not essential to the learning storefront.

This also significantly simplified the cart layout.

---

## 13. Order Summary Card

The default Magento gray summary was redesigned as a BrewCraft card.

Final visual structure:

```text
Summary

Subtotal                         ₹699
Shipping                           ₹5

Order Total                      ₹704

[ Proceed to Checkout ]

-------------------------------------

Estimate Shipping and Tax          ˅
```

The summary card uses:

* white background
* BrewCraft border
* rounded corners
* subtle shadow
* espresso headings
* compact spacing

---

## 14. Summary Content Reordering

Magento originally displayed the Shipping estimator before totals.

That resulted in:

```text
Shipping estimator
Country
State
Zip
Flat rate

Subtotal
Shipping
Total
Checkout
```

which meant users had to scroll through shipping fields before seeing the order total.

The summary was reordered through flex:

```text
1. Summary heading
2. Totals
3. Checkout
4. Shipping estimator
```

using CSS ordering.

Final hierarchy:

```text
Subtotal
Shipping
Order Total
Checkout
Shipping Estimator
```

This is much more appropriate for checkout UX.

---

## 15. Proceed to Checkout Button

Magento's default blue button was replaced by BrewCraft's primary CTA.

Final style:

```text
dark espresso background
white text
full summary width
approximately 50px high
brown hover state
```

Example:

```text
[        Proceed to Checkout        ]
```

The multi-address checkout link was hidden from the storefront design because it was not required for BrewCraft's intended flow.

---

## 16. Shipping Estimator

This was one of the most time-consuming cart areas.

Magento's estimator contains:

```text
Estimate Shipping and Tax

Country
State/Province
Zip/Postal Code

Shipping Methods
```

Initially the section remained permanently expanded.

That made the right summary card extremely tall and pushed other cart content down.

---

## 17. Shipping Radio Alignment

Magento's radio button initially appeared misaligned from:

```text
Fixed ₹5.00
```

The field was converted into flex:

```text
○ Fixed ₹5.00
```

with:

* 16px radio
* aligned label
* consistent spacing
* no native absolute-position side effects

---

## 18. Shipping Collapse Development

Several conflicting CSS rules accumulated while testing the shipping accordion.

At one stage `_cart.less` still contained an old block explicitly forcing:

```less
display: block !important;
```

on shipping content and:

```less
pointer-events: none;
```

on the title.

That meant the later collapsible implementation literally could not work. This conflict was identified from the final uploaded stylesheet. 

The conflicting **SHIPPING ESTIMATOR – ALWAYS OPEN** rules were removed.

We returned to Magento's native collapsible behavior instead of manually controlling content display.

Final behavior:

```text
Estimate Shipping and Tax    ˅
```

Click:

```text
Estimate Shipping and Tax    ˄

Country
State
Zip
Flat Rate
```

The important lesson was:

> Do not use CSS to force `display:block` or `display:none` on Magento's collapsible content if Magento's JS component is supposed to manage the state.

---

## 19. Shipping Chevron

The native/previous arrow styling was inconsistent.

The final arrow is based on the shipping title state and visually communicates:

Closed:

```text
˅
```

Open:

```text
˄
```

This resolved the earlier UX problem where the shipping title was technically clickable but gave no visual indication.

---

## 20. Request Quote Integration

BrewCraft has a custom module:

```text
BrewCraft_RequestQuote
```

The Cart Request Quote block uses:

```php
$block->canRequestQuote()
```

so it only renders for customers eligible to request a business quote.

Template:

```text
BrewCraft_RequestQuote::cart/request-quote.phtml
```

Block:

```php
BrewCraft\RequestQuote\Block\Cart\RequestQuote
```

Initial content:

```text
Need a Custom Business Quote?

Submit the products in your shopping cart to the
BrewCraft business team for a custom price proposal.

[ Request a Quote ]
```

---

## 21. Request Quote Placement Problem

Initially the Request Quote block was rendered directly inside:

```xml
<referenceContainer name="content">
```

using:

```xml
after="-"
```

Therefore it was positioned after the entire cart container.

This caused a major interaction problem:

```text
Shipping closed
→ Request Quote higher

Shipping opened
→ Summary becomes taller
→ entire cart container becomes taller
→ Request Quote gets pushed down
```

This was not desirable.

The requirement became:

> Request Quote must remain below Update Shopping Cart on the LEFT regardless of whether Shipping is opened or closed.

---

## 22. Request Quote Final Placement

The Request Quote block was moved into the cart's left-side layout instead of being globally appended after content.

The final design is:

```text
LEFT                                  RIGHT

Cart product rows                     Summary
                                      Totals
                        Update Cart   Checkout
                                      Shipping ▼

Need a Custom Business Quote?
Description
[ Request a Quote ]
```

Opening Shipping:

```text
LEFT                                  RIGHT

Cart                                  Summary
Update Cart                           Shipping ↑

Request Quote                         Country
(stays here)                          State
                                      Zip
                                      Rate
```

The Request Quote section therefore no longer depends on Shipping height.

---

## 23. Request Quote Styling

The business quote block was converted from a plain text section into a BrewCraft B2B callout.

Visual treatment:

* cream background
* subtle border
* rounded corners
* espresso title
* charcoal description
* dark brown CTA

Button:

```text
[ Request a Quote ]
```

uses the same primary BrewCraft styling as the rest of the storefront rather than Magento blue.

---

## 24. Empty Cart State

Magento's default empty cart message was replaced with a custom BrewCraft state.

Template:

```text
Magento_Checkout/templates/cart/noItems.phtml
```

Design:

```text
              shopping bag icon

              Your cart is empty

Looks like you haven't added anything yet.
Explore our coffee, machines and brewing essentials.

            [ Continue Shopping ]
```

The icon is drawn through CSS rather than requiring another image asset.

Styling includes:

* cream circular icon background
* BrewCraft brown bag outline
* centered typography
* espresso heading
* muted supporting copy
* BrewCraft primary CTA

This gives an intentional state instead of Magento's basic empty message.

---

## 25. Mini Cart – Initial State

The original Mini Cart had major layout problems:

* enormous product images
* product names falling below images
* excessive vertical height
* default Magento blue Checkout button
* unstyled View Cart link
* Qty shown as plain input
* Edit and Delete actions separated awkwardly
* product content did not align as a row

Example original structure:

```text
Huge image

Product Name
Price

Qty [1]

Edit Delete
```

---

## 26. Mini Cart Layout

A dedicated:

```text
_minicart.less
```

was created.

The dropdown width was standardized around:

```text
420px
```

The Mini Cart received:

* BrewCraft border
* rounded corners
* shadow
* fixed product scrolling area
* compact item rows
* white background

---

## 27. Mini Cart Product Rows

Native Magento floats were overridden with flex layout.

Final product structure:

```text
[img]  BrewCraft Signature Arabica Beans 2kg
       ₹699

       Qty [−][1][+]                   🗑
```

This dramatically reduced the required height of each item.

---

## 28. Mini Cart Images

Images were initially still too large.

They were reduced further to approximately:

```text
66px × 82px
```

with:

```less
object-fit: cover;
```

This gave enough room to product information without making the Mini Cart excessively wide or tall.

---

## 29. Mini Cart Product Information

Product information was normalized:

* product name: ~13–14px
* BrewCraft charcoal text
* price: bold espresso
* compact vertical spacing
* adequate width after shrinking the image

This eliminated the earlier situation where product names wrapped into a very narrow column.

---

## 30. Mini Cart Edit/Delete Decision

Initially both native Magento actions were kept:

```text
✎   🗑
```

Once direct quantity editing was implemented inside the Mini Cart, the Edit action became unnecessary.

Final decision:

```text
Remove Edit
Keep Delete
```

Final row action:

```text
🗑
```

This simplifies the interaction considerably.

---

## 31. Mini Cart Quantity Control

The plain native:

```text
Qty: [1]
```

was replaced with:

```text
Qty [−][1][+]
```

through:

```text
Magento_Checkout/web/template/minicart/item/default.html
```

The first implementation updated the visible number only.

Example:

```text
1 → 2
```

but:

```text
₹699
```

did not update.

That exposed an important Magento behavior.

---

## 32. Mini Cart Native Update Mechanism

Magento's Mini Cart includes a native:

```text
.update-cart-item
```

control.

Magento's frontend JS expects that action to trigger the asynchronous cart update.

Therefore the final quantity implementation:

```text
click +
   ↓
input quantity changes
   ↓
hidden native update-cart-item triggered
   ↓
Magento updates quote asynchronously
   ↓
product amount changes
   ↓
Cart Subtotal changes
   ↓
customer-data/minicart refreshes
```

This allowed quantity updates without navigating to the full cart.

---

## 33. Hidden Native Update Button

While testing, Magento briefly displayed:

```text
Update
```

when `+ / -` was clicked.

The native action still needed to exist functionally, but it was unnecessary visually.

Therefore it was hidden permanently:

```less
.update-cart-item {
    display: none !important;
    visibility: hidden !important;
}
```

while our JavaScript/Knockout interaction still programmatically triggers:

```javascript
updateButton.click();
```

Final UX:

```text
Qty [−][2][+]   🗑
```

and Magento silently updates the price.

---

## 34. Mini Cart Subtotal

The Mini Cart subtotal area was redesigned into:

```text
2 Items in Cart            Cart Subtotal
                             ₹1,398
```

with a stronger visual hierarchy on the amount.

The subtotal automatically updates after quantity changes because the native Magento customer-data refresh was retained.

---

## 35. Mini Cart Checkout Button

The default Magento blue:

```text
Proceed to Checkout
```

was replaced with BrewCraft espresso.

Final styling:

```text
dark espresso
white text
full width
rounded corners
```

Hover:

```text
coffee brown
```

---

## 36. Mini Cart View Cart Button

The default plain:

```text
View and Edit Cart
```

link was converted into a full-width BrewCraft secondary button.

Final:

```text
[ View and Edit Cart ]
```

with:

* white background
* brown border
* brown text
* cream hover state

---

## 37. Mini Cart Header Icon Bug

When Mini Cart was closed, the header icon appeared correctly:

```text
🛒²
```

When Mini Cart opened:

* the cart icon shifted
* the counter moved away from the icon

The open-state `.showcart` styles were normalized so both closed and open states use the same:

```text
width
height
position
counter top/right
```

The badge remains:

* coffee/brown background
* white number
* circular

So opening the Mini Cart no longer visually changes the cart icon position.

---

## 38. Request a Quote – Mini Cart

Business customers also see:

```text
Request a Quote
```

inside the Mini Cart.

The functionality already existed from the custom RequestQuote module.

Only the Magento-blue styling needed to be replaced.

Final Mini Cart quote CTA follows BrewCraft brown styling instead of blue.

---

## 39. Header Category Issue Found During Mini Cart Testing

While opening Mini Cart on the Coffee category page, another issue became visible:

```text
Coffee
 ├ Coffee Beans
 └ Ground Coffee
```

was permanently open.

Cause:

The header CSS included:

```less
.level0.active > .submenu
```

Magento automatically applies:

```text
.active
```

to the current category.

Therefore being on `/coffee.html` caused the Coffee submenu to remain permanently visible.

The rule was corrected to show submenus only on:

```text
:hover
```

or Magento interaction state:

```text
._active
```

but **not** category `.active`.

Result:

Current category receives its underline, but submenu opens only on hover.

---

## 40. Significant Failed/Discarded Approaches

This part is worth documenting because the Cart work took longer mainly due to layout conflicts.

### Nested `.checkout-cart-index`

At one stage sections were written like:

```less
.checkout-cart-index {

    ...

    .checkout-cart-index {
        ...
    }
}
```

LESS compiled that into:

```css
.checkout-cart-index .checkout-cart-index ...
```

which can never match the page because a checkout page doesn't contain another checkout page inside itself.

This explained why several styles appeared to do absolutely nothing.

### Lesson

When the entire `_cart.less` is already wrapped with:

```less
.checkout-cart-index {
```

do not add another same wrapper inside it.

---

### Multiple cart layout systems

During experimentation the same stylesheet temporarily contained:

* CSS Grid cart layout
* float-based Magento layout
* `.brewcraft-cart-left` wrapper layout
* negative-margin coupon positioning

These competed with each other and caused the page to collapse at one point.

### Lesson

Only one structural layout model should control the cart container.

---

### JS wrapper for cart layout

A JavaScript-based wrapper was briefly proposed to create:

```text
brewcraft-cart-left
```

and relocate:

```text
form-cart
cart-discount
```

This added unnecessary DOM complexity and was discarded.

---

### Coupon alignment hacks

Negative margins such as:

```less
margin: -70px ...
```

were tested.

They worked visually in one state but were coupled to Summary height.

They were discarded together with the coupon itself.

---

### CSS-only shipping accordion

Trying to control Magento's shipping widget entirely with:

```less
display:none
display:block
```

caused conflicts with Magento's own collapsible widget.

Final strategy:

Let Magento JS own the state; CSS owns only presentation.

---

### Shipping “Always Open” block

This was retained accidentally during later collapse testing.

It explicitly contained:

```less
display: block !important;
pointer-events: none;
```

which prevented the accordion from working at all. The conflict was eventually identified from the complete uploaded stylesheet. 

---

## 41. Final Full Cart Functionality

Current functional behavior:

```text
Add products
    ↓
Shopping Cart
    ↓
Product image/name/stock shown
    ↓
[-][qty][+]
    ↓
Update Shopping Cart
    ↓
Magento recalculates totals
```

Actions:

```text
Wishlist
Edit
Delete
```

Summary:

```text
Subtotal
Shipping
Grand Total
Proceed to Checkout
Shipping estimator accordion
```

Business customer:

```text
Request a Quote block
```

Normal customer:

```text
No Request Quote block
```

---

## 42. Final Mini Cart Functionality

Current flow:

```text
Open Mini Cart
    ↓
Compact products displayed
    ↓
[-][qty][+]
    ↓
native hidden Magento Update action triggered
    ↓
Ajax cart update
    ↓
price/subtotal refresh
```

Available actions:

```text
Delete
Proceed to Checkout
View and Edit Cart
```

Business customer additionally gets:

```text
Request a Quote
```

---

## 43. Cache / Static Cleanup Used During Development

Because most work involved LESS/templates/Knockout, the repeated local cleanup command was:

```bash
rm -rf var/view_preprocessed/*
rm -rf pub/static/frontend/BrewCraft/*
bin/magento cache:flush
```

For layout-specific changes we also used:

```bash
bin/magento cache:clean layout block_html
```

followed by a browser hard refresh:

```text
Ctrl + Shift + R
```

---

## 44. Final Design State

### Full cart

```text
Shopping Cart

┌──────────────────────────────────────┐  ┌─────────────────────┐
│ Item        Price    Qty   Subtotal │  │ Summary             │
│                                      │  │                     │
│ [img] Product                        │  │ Subtotal            │
│       In Stock     ₹699 [-][1][+]   │  │ Shipping            │
│                        ♡  ✎  🗑       │  │ Order Total         │
│                                      │  │                     │
└──────────────────────────────────────┘  │ Proceed to Checkout │
                 [ Update Cart ]          │                     │
                                          │ Shipping & Tax   ˅ │
┌──────────────────────────────────────┐  └─────────────────────┘
│ Need a Custom Business Quote?        │
│ Submit cart for custom pricing.      │
│                                      │
│ [ Request a Quote ]                  │
└──────────────────────────────────────┘
```

### Mini Cart

```text
2 Items in Cart                 ₹1,398

[ Request a Quote ]    business only
[ Proceed to Checkout ]

───────────────────────────────────

[img] Arabica Beans 2kg
      ₹699
      Qty [−][2][+]             🗑

───────────────────────────────────

[img] Arabica Beans 1kg
      ₹399
      Qty [−][1][+]             🗑

───────────────────────────────────

[ View and Edit Cart ]
```

---

## 45. Final Status

### Shopping Cart

**Complete ✅**

### Product presentation

**Complete ✅**

### In Stock indicator

**Complete ✅**

### Quantity stepper

**Complete ✅**

### Wishlist/Edit/Delete

**Complete ✅**

### Update Shopping Cart

**Complete ✅**

### Order Summary

**Complete ✅**

### Shipping estimator

**Collapsible and working ✅**

### Coupon

**Intentionally removed ✅**

### Empty cart

**Designed ✅**

### Mini Cart

**Complete ✅**

### Mini Cart automatic quantity update

**Complete ✅**

### Request Quote integration

**Business-customer conditional behavior retained ✅**

### Request Quote cart placement

**Decoupled from Shipping estimator and placed with left cart content ✅**

---

### Key development takeaway

The biggest lesson from this Cart implementation was that **Magento's native frontend structure should be preserved whenever possible**.

The implementations that became stable were the ones where we:

```text
Magento owns:
    cart data
    quote calculations
    cart forms
    customer-data
    minicart Ajax updates
    shipping estimator behavior

BrewCraft owns:
    visual structure
    typography
    spacing
    buttons
    quantity controls
    icons
    product presentation
    business-specific UI placement
```

Whenever we tried to make CSS or custom DOM manipulation replace Magento's structural behavior, the page became fragile. Once we let Magento keep the functional responsibility and limited the theme to presentation and controlled interaction extensions, the Cart became significantly more stable.

Given how much iteration this page required, this is one of the more useful implementation logs for the BrewCraft project because it documents not only **what we built**, but also **which Magento frontend patterns should not be repeated in the remaining Checkout and Request Quote work**.

# 8. BrewCraft Request Quote — Development Log

---

## 1. Objective

The goal of this phase was to redesign and improve the custom `BrewCraft_RequestQuote` customer quote-request flow while preserving the functionality already developed in the module.

The feature is intended for eligible business/B2B customers, allowing them to submit their shopping cart products to BrewCraft for customized pricing.

**Customer flow covered in this phase:**

```
Shopping Cart
      ↓
Request a Quote
      ↓
Request a Business Quote form
      ↓
Enter requested quantity / expected price
      ↓
Submit Quote Request
      ↓
Quote Request Submitted
```

---

## 2. Existing Cart Integration

The custom module already provided a business-only block on the Shopping Cart page:

```php
<?php if ($block->canRequestQuote()): ?>
```

This ensured that Request Quote functionality was shown only when the customer met the business quote eligibility conditions.

**The cart CTA initially appeared as:**

```
Need a Custom Business Quote?

Submit the products in your shopping cart to the
BrewCraft business team for a custom price proposal.

[ Request a Quote ]
```

---

## 3. Request Quote Cart Placement

Originally the block was declared under `<referenceContainer name="content">` with `after="-"`, meaning the quote block appeared after the complete cart container.

**That created a layout dependency:**

```
Shipping estimator opens
        ↓
Summary becomes taller
        ↓
Cart container gets taller
        ↓
Request Quote gets pushed downward
```

**Requirement established:** The Request Quote box needed to remain directly below the left-side cart content / Update Shopping Cart, regardless of whether the Shipping estimator on the right was open or closed.

The block was repositioned into the cart layout so the left and right sides behaved independently.

**Final concept:**

```
LEFT                              RIGHT

Products                          Summary
                                  Totals
Update Shopping Cart              Checkout
                                  Shipping ▼

Request Quote
```

Opening Shipping now affects only the right-side Summary.

---

## 4. Request Quote Cart CTA Design

The cart Request Quote block received BrewCraft styling:

- Cream surface
- Subtle border
- Rounded corners
- Espresso heading
- Supporting description
- Dark BrewCraft primary CTA

Final CTA uses `[ Request a Quote ]` instead of Magento blue.

---

## 5. Request Quote Page — Original State

The original page was functional but largely unstyled.

**It contained:**

```
Request a Business Quote

Products Included

Product | SKU | Current Unit Price | Cart Quantity | Requested Quantity | Expected Unit Price

Current Cart Subtotal

Quote Request Details

Quote Name
Message

Submit Quote Request
Back to Shopping Cart
```

The main functional fields were already implemented correctly.

---

## 6. Existing Backend Contract Preserved

A major decision was made to **not redesign** the save/controller/service contract.

**The existing form submitted:**

| Field | Input Name |
|---|---|
| Requested quantity | `items[ITEM_ID][requested_qty]` |
| Expected price | `items[ITEM_ID][expected_price]` |
| Quote name | `quote_name` |
| Customer message | `customer_message` |

These names were retained exactly so the existing RequestQuote save flow continued to work.

---

## 7. Fields Removed From the UI

After reviewing the actual customer use case, two fields were identified as unnecessary for the customer-facing form:

- SKU
- Cart Quantity

The cart quantity was still used internally as the default value for **Requested Quantity**, so functionality was retained without showing redundant information.

---

## 8. Final Product Request Structure

The product table was simplified to:

```
Product | Current Unit Price | Requested Qty | Expected Price

┌──────────────────────────────────────────────────────────────┐
│ [image]      ₹699            [ 10 ]           [ 500 ]        │
│ Arabica 2kg                                                   │
└──────────────────────────────────────────────────────────────┘
```

This more closely reflects what a business buyer actually needs to provide.

---

## 9. Product Images Added

The original Request Quote page did not render product images.

Instead of accessing Magento services directly from PHTML, image handling was added to `BrewCraft\RequestQuote\Block\Request\Create` through Magento's catalog image helper.

The template therefore only needs to request:

```php
$block->getProductImageUrl($item)
```

This kept dependency/service logic out of the template. The page now visually matches the rest of the BrewCraft cart/product experience.

---

## 10. Current Unit Price

The existing `$item->getCalculationPrice()` continues to provide the current Magento cart item unit price. This value represents the current storefront price, not the customer's requested business price.

```
Current Unit Price
₹699.00
```

---

## 11. Requested Quantity

Requested Quantity remains a required numeric field. Existing validation was preserved:

- Required
- Number
- Greater than zero

The cart quantity provides its initial value. The business customer can then request `10`, `50`, or `200` without changing the actual Magento shopping cart quantity.

> **Important:** Shopping Cart quantity and Quote Request quantity represent different business intents.

---

## 12. Expected Unit Price

Expected Unit Price remains **optional**.

```
Expected Unit Price
[ Optional ]
```

If left blank: *"Leave blank to request our best offer."* — BrewCraft's business team decides the proposal price.

---

## 13. Cart Value vs Quote Value Problem

During development, an important UX problem was identified.

**Example:**

```
Current Unit Price  = ₹699
Requested Quantity  = 10
Expected Unit Price = ₹500

Yet the page still displayed:
Current Cart Subtotal = ₹699
```

Technically correct (the customer's actual cart still contained `1 × ₹699`), but confusing after the customer had entered quote values.

---

## 14. Two Different Financial Concepts

We therefore separated these into two distinct values:

**Current Cart Value** — What the actual Magento cart is worth:
```
1 × ₹699 = ₹699
```

**Requested Quote Estimate** — What the current quote request approximately represents:
```
10 × ₹500 = ₹5,000
```

---

## 15. Requested Quote Estimate Logic

**Final business rule:**

```
IF expected price exists:
    estimate = requested quantity × expected price

ELSE:
    estimate = requested quantity × current unit price
```

**For multiple products:**

```
Product A  →  10 × ₹500  = ₹5,000
Product B  →   5 × ₹1,000 = ₹5,000

Requested Quote Estimate = ₹10,000
```

---

## 16. Dynamic Quote Estimate

A frontend Magento-compatible JS component was introduced:

```
web/js/request-quote-estimate.js
```

It listens to both **Requested Quantity** and **Expected Unit Price** using `input` and `change` events, recalculating while the customer types without needing a button or page refresh.

**Example flow:**

```
Requested Qty: 1 → 10
Estimate: ₹699 → ₹6,990

Expected Price: blank → 500
Estimate: ₹6,990 → ₹5,000

Expected Price: deleted
Estimate falls back to Current Unit Price automatically
```

---

## 17. Magento JS Initialization

Initially the quote estimate remained at `₹0.00` even though values were entered, indicating the custom JS component was not being initialized correctly.

**The initialization was separated:**

Magento validation remained:
```html
data-mage-init='{"validation":{}}'
```

The quote calculator used:
```html
<script type="text/x-magento-init">
{
    ".brewcraft-quote-request__form": {
        "js/request-quote-estimate": {}
    }
}
</script>
```

This made the responsibilities clearer — Magento validation and BrewCraft quote calculator run independently without combining both behaviors into the same initializer.

---

## 18. Quote Details Section

The existing fields were retained:

- **Quote Name** — Required (e.g. `August Coffee Equipment Order`)
- **Message** — Optional. Additional context such as expected volume, delivery requirements, pricing requirement, or business context. 5000 character limit retained.

---

## 19. Duplicate Page Heading

After the new design was applied, the page contained two headings:

- `Request a Quote` — from Magento's native `page.main.title`
- `Request a Business Quote` — from the custom template

The native page title was removed through the route layout XML:

```xml
<referenceBlock
    name="page.main.title"
    remove="true"
/>
```

Final page has only: **Request a Business Quote**

---

## 20. Submit Quote Request CTA

The default Magento-blue submit button was replaced.

**Final design:** `[ Submit Quote Request ]`

- Espresso background
- White text
- BrewCraft typography
- Coffee-brown hover
- Rounded corners
- Text-only (experimental icon removed)

---

## 21. Back to Shopping Cart

The original Back link suffered from overlapping Magento pseudo-elements and an unattractive small arrow. The markup was simplified.

**Final design:** `← Back to Shopping Cart`

- BrewCraft brown color
- Properly sized arrow
- Consistent vertical alignment
- No Magento-generated extra icon
- Subtle hover treatment

---

## 22. Request Form Final Design

```
Request a Business Quote

Review the products in your cart and let us know your
requested quantity and expected unit price.
You can leave the expected unit price blank...

Products Included

┌────────────────────────────────────────────────────────────┐
│ Product | Unit Price | Requested Qty | Expected Unit Price │
│                                                            │
│ [img]      ₹699          [10]             [500]            │
├────────────────────────────────────────────────────────────┤
│ Current Cart Value                            ₹699.00      │
├────────────────────────────────────────────────────────────┤
│ Requested Quote Estimate                    ₹5,000.00      │
└────────────────────────────────────────────────────────────┘

Quote Request Details
──────────────────────────────────────────────────────────────

Quote Name *
[ August Coffee Equipment Order ]

Message
[                                                      ]
[                                                      ]

[ Submit Quote Request ]       ← Back to Shopping Cart
```

---

## 23. Quote Submission

The original server-side save process was preserved. When the user submits, the request continues to the existing custom route:

```
requestquote/request/save
```

No controller/service/repository contract was changed during the redesign. This was an important architectural decision because the quote workflow already functioned correctly.

---

## 24. Quote Submission Success Page

After successful creation, the customer is redirected to the quote confirmation page.

**Original page contained:**
- `Quote Request Submitted` — Magento success alert
- `Your Quote Request Has Been Submitted` — custom heading
- Quote Number, Quote Name, Status
- Continue Shopping / Go to My Account

There was unnecessary duplicate messaging.

---

## 25. Success Page Redesign

The new success state was simplified:

```
             ✓

      BREWCRAFT BUSINESS

      Quote Request Submitted

Our business team will review your request
and prepare a custom price proposal.

┌─────────────────────────────────────┐
│ Quote Number        BCQ-...         │
│ Quote Name          ...             │
│ Status              [ Pending ]     │
└─────────────────────────────────────┘

[ Continue Shopping ]   [ View My Quotes ]
```

---

## 26. Status Badge

Instead of plain `Status: Pending`, the status is presented as a visual status pill:

```
[ Pending ]
```

Using a warm neutral/yellow treatment. This status language should be reused on My Quotes, Quote Detail pages.

---

## 27. Quote Number

The generated business quote identifier is displayed prominently:

```
BCQ-20260816-20541133
```

This gives the customer a reference for future communication with BrewCraft's business team.

---

## 28. Success Page Buttons

| Button | Type |
|---|---|
| `[ Continue Shopping ]` | Primary BrewCraft CTA |
| `[ View My Quotes ]` | Secondary outlined action |

**"View My Quotes"** is more useful than **"Go to My Account"** because it communicates exactly where the customer should go next.

---

## 29. Success Page Duplicate Heading

Like the create page, Magento's native `page.main.title` created another heading. It should likewise be removed through the success route layout XML so the custom success content owns the page title.

---

## 30. Files Involved

**Custom module:** `app/code/BrewCraft/RequestQuote/`

| File | Responsibilities |
|---|---|
| `Block/Request/Create.php` | Active customer cart, visible cart items, cart subtotal, price formatting, product image URL, form action, back-to-cart URL |
| `view/frontend/templates/request/create.phtml` | Request form, product display, requested quantity, expected unit price, quote name, message, submit, back link |
| `Block/Request/Success.php` | Load quote request by quote number, Continue Shopping URL, Customer/My Quotes URL |
| `view/frontend/templates/request/success.phtml` | Confirmation, quote number, quote name, status, post-submit actions |

---

## 31. Theme Files

**Request Quote styling:**
```
app/design/frontend/BrewCraft/supply/web/css/source/_request-quote.less
```

Imported from `_extend.less`:
```less
@import '_request-quote.less';
```

**Dynamic estimate:**
```
web/js/request-quote-estimate.js
```

---

## 32. Existing Backend Logic Preserved

This phase intentionally did not modify:

- Quote repository
- Save controller
- Quote persistence
- Admin processing
- Quote status business logic
- Customer eligibility
- Request Quote ACL/business rules

The work focused on UI, UX, presentation, dynamic quote estimation, navigation, and customer clarity. This reduces regression risk.

---

## 33. Current Request Quote Customer Journey

```
Business Customer
      ↓
Shopping Cart
      ↓
Request a Quote
      ↓
Request a Business Quote
      ↓
Review cart products
      ↓
Enter requested quantity
      ↓
Optionally enter expected unit price
      ↓
See Requested Quote Estimate
      ↓
Enter Quote Name / Message
      ↓
Submit Quote Request
      ↓
Quote created
      ↓
Quote Request Submitted
      ↓
View My Quotes / Continue Shopping
```

---

## 34. Functional Status

| Feature | Status |
|---|---|
| Business-only Request Quote | ✅ |
| Cart Request Quote CTA | ✅ |
| Request Quote placement | ✅ |
| Product images | ✅ |
| Current Unit Price | ✅ |
| Requested Quantity | ✅ |
| Expected Unit Price | ✅ |
| SKU removed from customer UI | ✅ |
| Cart Quantity removed from customer UI | ✅ |
| Current Cart Value | ✅ |
| Dynamic Requested Quote Estimate | ✅ |
| Quote Name validation | ✅ |
| Customer Message | ✅ |
| Submit Quote Request | ✅ |
| Back to Shopping Cart | ✅ |
| Quote creation | ✅ |
| Success page | ✅ |
| Quote Number display | ✅ |
| Pending status | ✅ |
| Continue Shopping | ✅ |
| View My Quotes direction | ✅ |

---

## 35. Important Technical Lessons

### Separate cart data from quote-request data

| Cart | Quote Request |
|---|---|
| Cart qty | Requested quote qty |
| Cart price | Expected business price |
| Cart subtotal | Requested quote estimate |

Changing quote request values must **never** modify the customer's Magento cart.

### Quote estimate is informational

The frontend calculation (`requested quantity × requested/current price`) is an estimate for user clarity only. It is **not** an approved quotation — the business team still controls the final proposed price.

### Preserve existing backend contracts

Because RequestQuote functionality already worked, redesigning only the frontend minimized regression risk.

### Use Magento services in Blocks, not PHTML

Product image handling belongs in the block/service layer — not through ObjectManager or ad-hoc media URL construction in templates.

---

## Next B2B Quote Phase

With this section complete, the next design phase should be:

```
Create Quote ✅
      ↓
Submission Success ✅
      ↓
My Quotes             ← NEXT
      ↓
Quote Details
      ↓
Admin Proposal
      ↓
Accept / Reject
      ↓
Accepted Quote → Cart / Checkout
```

**My Quotes** should be designed first because it establishes the status system and navigation that the Quote Detail page will reuse.

# 9.BrewCraft Checkout — Review & Payments Development Log

### 1. Objective

The goal of this phase was to redesign Magento’s default **Review & Payments** step so it visually matches the BrewCraft storefront while preserving the native Magento payment flow.

The step needed to support the real payment options available in the project, not just copy the Figma reference blindly.

The final payment methods used were:

* Razorpay
* Cash On Delivery
* Bank Transfer Payment
* Check / Money Order

The page also needed to include:

* payment-method selection
* billing-address handling
* Place Order action
* discount/coupon entry
* Order Summary
* Ship To
* Shipping Method
* edit actions
* BrewCraft typography, spacing and colors

---

## 2. Starting State

The default Magento payment page initially looked very plain:

```text
Payment Method

○ Check / Money Order

○ Razorpay

Apply Discount Code

                         Order Summary
                         Ship To
                         Shipping Method
```

Problems:

* only Razorpay + Check/Money Order were initially visible
* payment methods looked like simple default Magento rows
* Place Order button was Magento blue
* coupon form looked unfinished
* right-side information looked like loose text rather than designed cards
* typography did not fully match the BrewCraft theme
* Ship To and Shipping Method were visually disconnected
* right-side layout was not consistent with the already-designed Shipping step

---

## 3. Payment Methods Review

Before styling, we reviewed what payment methods could realistically be enabled without adding another third-party integration.

Magento Open Source includes offline payment methods such as:

```text
Check / Money Order
Bank Transfer Payment
Cash On Delivery
Purchase Order
Zero Subtotal Checkout
```

For the BrewCraft test flow, we chose:

```text
Razorpay
Cash On Delivery
Bank Transfer Payment
Check / Money Order
```

We intentionally did not enable methods just for appearance if they required another real gateway integration.

For example, we did not add fake:

```text
PayPal
Google Pay
Apple Pay
Credit Card
Installments
```

unless the actual payment gateway supports them.

This was important because the design should reflect real functionality.

---

## 4. Enabling Magento Native Payment Methods

The additional methods were enabled from:

```text
Admin
→ Stores
→ Configuration
→ Sales
→ Payment Methods
```

We enabled:

```text
Cash On Delivery Payment
Bank Transfer Payment
Check / Money Order
```

Razorpay was already installed and configured through the Razorpay extension.

The result was four real payment choices on Checkout:

```text
○ Razorpay

○ Cash On Delivery

○ Bank Transfer Payment

○ Check / Money Order
```

This gave us a realistic payment page to design.

---

## 5. Why We Enabled Native Methods First

This was done before styling because designing against only one payment method can create a fragile UI.

With four methods visible, we could properly test:

* multiple rows
* active payment state
* long method names
* payment method switching
* billing-address expansion
* Place Order position
* card spacing

This made the payment design more production-ready.

---

## 6. Payment Page Design Direction

The reference design showed payment methods as clean selectable sections.

We adapted that concept to the payment methods actually available in BrewCraft.

Target structure:

```text
Payment Method

┌───────────────────────────────┐
│ ○ Razorpay                   │
└───────────────────────────────┘

┌───────────────────────────────┐
│ ○ Cash On Delivery           │
└───────────────────────────────┘

┌───────────────────────────────┐
│ ○ Bank Transfer Payment      │
└───────────────────────────────┘

┌───────────────────────────────┐
│ ○ Check / Money Order        │
│                               │
│ ☑ Billing = Shipping          │
│                               │
│ Billing Address               │
│                               │
│ [ Place Order ]               │
└───────────────────────────────┘
```

---

## 7. Payment Method Cards

Each payment method was converted from a plain Magento row into a card.

Key visual treatment:

```text
white background
thin BrewCraft border
rounded corners
consistent vertical spacing
hover border
active-state highlight
```

Active payment method behavior was kept native.

Magento still controls:

```text
selected method
active state
method content
billing address
Place Order availability
```

Only the appearance changed.

---

## 8. Payment Method Alignment

One of the first visible problems was that:

```text
○
    Cash On Delivery
```

did not align properly.

Razorpay was even more noticeable because its logo sat lower than the radio button.

We corrected the method title to use:

```text
display: flex
align-items: center
gap
```

so the layout became:

```text
○  Cash On Delivery
```

and:

```text
○  [Razorpay logo]
```

This was a small change but made the method cards look much more polished.

---

## 9. Razorpay Logo Handling

The Razorpay payment method uses its own image/logo.

We deliberately did not replace it.

Instead, we constrained:

```text
max-width
max-height
vertical alignment
```

so it sits correctly inside the payment card.

This preserved the official gateway branding while keeping the card consistent.

---

## 10. Selected Payment Method Content

When a payment method is selected, Magento expands its content area.

For offline methods, that can include:

```text
Billing address checkbox
Billing address details
Place Order button
```

We styled this area separately from the payment title.

The selected section now uses:

```text
border-top
white background
clean padding
consistent spacing
```

instead of Magento’s default content layout.

---

## 11. Billing Address — Same as Shipping

Magento provides:

```text
My billing and shipping address are the same
```

with a checkbox.

We retained that native functionality.

The layout was improved to align:

```text
☑  My billing and shipping address are the same
```

in a single clean row.

No checkout logic was replaced.

---

## 12. Billing Address Card

When the billing address is displayed, it was initially plain text.

We styled it into a BrewCraft information card:

```text
cream background
stone border
rounded corners
compact body typography
```

This matched the visual language already established in:

* Cart
* Request Quote
* Shipping Address

---

## 13. Place Order Button

Magento’s default Place Order button was blue.

That did not match BrewCraft.

We changed it to:

```text
espresso background
white text
BrewCraft body font
full width
48px height
rounded corners
coffee hover state
```

Final behavior:

```text
[             Place Order             ]
```

The button remains Magento’s original button.

We did not replace:

```text
placeOrder()
payment validation
checkout submission
order creation
gateway logic
```

Only the styling changed.

---

## 14. Discount / Coupon Design

The coupon form became one of the most specific debugging points on the payment page.

Initially it looked like:

```text
Apply Discount Code

[ Enter discount code ]

                [ Apply Discount ]
```

The button did not line up with the input.

---

## 15. Why Coupon Styling Initially Failed

At first, we assumed the form structure was simpler than it actually was.

The user inspected the real Magento DOM and supplied:

```html
<form class="form form-discount">

    <div class="payment-option-inner">

        <div class="field">

            <label class="label">
                Enter discount code
            </label>

            <div class="control">
                <input class="input-text">
            </div>

        </div>

    </div>

    <div class="actions-toolbar">

        <div class="primary">

            <button class="action action-apply">
                Apply Discount
            </button>

        </div>

    </div>

</form>
```

This revealed the actual reason the button was difficult to align.

The input and button were not siblings inside one simple flex row.

---

## 16. Correct Coupon Solution

Once the real DOM was known, we changed strategy.

Instead of forcing nested wrappers with Flexbox, we made the actual:

```text
.form.form-discount
```

a CSS Grid.

Conceptually:

```text
row 1:
Enter discount code

row 2:
[ input                     ][ Apply Discount ]
```

We used:

```text
payment-option-inner → display: contents
field                → display: contents
```

so the label, control and actions toolbar could participate directly in the grid.

This was the cleanest solution because it matched the actual Magento HTML.

---

## 17. Final Coupon Layout

The resulting design became:

```text
Apply Discount Code

Enter discount code
┌──────────────────────────────┬─────────────────┐
│ Enter discount code          │ Apply Discount  │
└──────────────────────────────┴─────────────────┘
```

This is much more aligned with the BrewCraft form language.

---

## 18. Order Summary

The right-side Order Summary was already partially styled during the Shipping phase.

On the Payment step, Magento now had full totals available:

```text
Cart Subtotal
Shipping
Order Total
```

So we refined rather than rebuilt it.

Final structure:

```text
Order Summary
────────────────────

Cart Subtotal        ₹699
Shipping               ₹5
Flat Rate - Fixed
────────────────────
Order Total          ₹704

1 Item in Cart     Edit Cart

[img] Product
      Qty: 1
      ₹699
```

We kept Magento’s totals and item rendering intact.

---

## 19. Ship To / Shipping Method

Initially these rendered below Order Summary as loose sections:

```text
Ship To:
[address]

Shipping Method:
Flat Rate - Fixed
```

They did not visually match Order Summary.

The goal became:

```text
[ Ship To Card ]

[ Shipping Method Card ]
```

---

## 20. First Right-Side Card Problem

An early CSS attempt styled both:

```text
.opc-block-shipping-information
.shipping-information
.ship-to
.shipping-information
```

as cards.

This created:

```text
outer card
└── inner Ship To card
    └── Shipping Method
```

which looked like a card inside another card.

---

## 21. Actual Magento Shipping Information Structure

The important DOM understanding was:

```text
opc-block-shipping-information
└── shipping-information
    ├── ship-to
    └── ship-via
```

The mistake was treating:

```text
.shipping-information
```

as the actual Shipping Method card.

It is only a wrapper.

The real method section is:

```text
.ship-via
```

---

## 22. Final Right-Side Structure

The corrected CSS keeps these transparent:

```text
.opc-block-shipping-information
.shipping-information
```

and styles only:

```text
.ship-to
.ship-via
```

as cards.

Final structure:

```text
Order Summary

┌──────────────────────────────┐
│ Ship To                   ✎ │
│──────────────────────────────│
│ Customer name                │
│ Address                      │
│ Phone                        │
└──────────────────────────────┘

┌──────────────────────────────┐
│ Shipping Method           ✎ │
│──────────────────────────────│
│ Flat Rate - Fixed            │
└──────────────────────────────┘
```

---

## 23. Edit Icons

Magento already provides edit actions for:

```text
Ship To
Shipping Method
```

We retained these.

Instead of leaving the raw pencil icon, we styled the actions as:

```text
small circular button
stone border
coffee icon
cream hover state
```

This matches the edit/delete icon language already used on the Cart page.

---

## 24. Payment Page Column Layout

The page uses the familiar checkout structure:

```text
LEFT                   RIGHT

Payment Methods        Order Summary
                       Ship To
Coupon                 Shipping Method
```

We refined desktop proportions around:

```text
68% left
30% right
```

while preserving Magento’s two-column checkout.

---

## 25. CSS Cascade Problem

As with Shipping, the payment page temporarily accumulated multiple rounds of CSS.

The uploaded payment stylesheet showed repeated definitions for:

* payment cards
* coupon form
* coupon button
* Order Summary
* shipping information
* Ship To
* Shipping Method

Some components had three different styling strategies in the same file. 

That created unnecessary override complexity.

---

## 26. Final CSS Consolidation

Instead of appending another fix, the payment section was consolidated into one clean version.

The final payment CSS now has one owner for each area:

```text
Payment page columns
Payment heading
Payment cards
Selected payment content
Billing address
Place Order
Coupon
Order Summary
Shipping information wrapper
Ship To
Ship Via
```

This eliminated the cascade conflicts.

---

## 27. What Stayed Native

A major principle of this phase was:

> Style Magento payment behavior; do not rebuild payment behavior.

We retained all of Magento’s native functionality.

Magento still controls:

```text
payment method registration
selected method
billing address state
payment validation
coupon application
totals refresh
shipping information
Place Order
order creation
gateway calls
checkout errors
```

Razorpay remains controlled by the Razorpay extension.

---

## 28. What We Intentionally Did Not Rebuild

We did not create custom:

```text
payment-method UI components
payment JS
place-order controller
coupon controller
shipping-information templates
order total logic
```

This kept the payment step much safer than the earlier Shipping experimentation.

---

## 29. Payment Methods — Technical Purpose

The four methods now serve different purposes in the test project.

### Razorpay

Primary real online payment gateway.

Used to test:

```text
external payment integration
online payment selection
gateway checkout flow
```

### Cash On Delivery

Native Magento offline method.

Useful for testing:

```text
order placement without gateway
offline payment order status
checkout UX
```

### Bank Transfer

Native Magento offline method.

Useful for:

```text
manual bank-payment instructions
offline payment workflow
B2B-style scenarios
```

### Check / Money Order

Native Magento offline method.

Useful as a simple default Magento payment method and for testing Magento’s standard offline payment implementation.

---

## 30. Why We Did Not Enable Every Available Method

Magento also offers other offline capabilities such as:

```text
Purchase Order
Zero Subtotal Checkout
```

but they were not needed for this design.

Zero Subtotal only appears when the total reaches zero.

Purchase Order is more appropriate for a future B2B-focused flow.

We also avoided adding additional third-party payment providers only to make the UI resemble the reference.

---

## 31. What Consumed the Most Time

The most time-consuming areas on this page were:

### Coupon alignment

The main reason was that the actual Magento DOM differed from the assumed layout.

Once the real HTML was inspected, the solution became straightforward.

### Right-side Ship To / Shipping Method cards

The initial selector targeted the wrapper rather than the actual `.ship-via` child.

This created nested cards.

### CSS duplication

Repeated iterations made it harder to know which rule was currently active.

The consolidated replacement solved this.

---

## 32. What Helped Most

### Browser Inspect / DevTools

Inspecting the actual rendered HTML was the single most useful technique.

The coupon fix is the best example.

Once we knew:

```text
form-discount
├── payment-option-inner
│   └── field
│       ├── label
│       └── control/input
└── actions-toolbar
    └── primary/button
```

we could write the correct Grid layout immediately.

---

### Existing Magento class names

Rather than overriding templates, we relied on:

```text
.payment-method
.payment-method-title
.payment-method-content
.form-discount
.action-apply
.opc-block-summary
.opc-block-shipping-information
.ship-to
.ship-via
```

This reduced the number of files we needed to maintain.

---

## 33. Current Review & Payments UX

The final conceptual layout is:

```text
BrewCraft Header

        1                       2
───────○───────────────────────○──────
     Shipping          Review & Payments


LEFT                                  RIGHT

Payment Method                        Order Summary

┌───────────────────────────────┐     Cart Subtotal
│ ○ Razorpay                   │     Shipping
└───────────────────────────────┘     Order Total

┌───────────────────────────────┐     Product
│ ○ Cash On Delivery           │
└───────────────────────────────┘     ┌───────────────────┐
                                      │ Ship To        ✎ │
┌───────────────────────────────┐     │ Address           │
│ ○ Bank Transfer Payment      │     └───────────────────┘
└───────────────────────────────┘
                                      ┌───────────────────┐
┌───────────────────────────────┐     │ Shipping       ✎ │
│ ● Check / Money Order        │     │ Flat Rate         │
│───────────────────────────────│     └───────────────────┘
│ ☑ Billing = Shipping         │
│ Billing Address              │
│                              │
│ [ Place Order ]              │
└───────────────────────────────┘

┌───────────────────────────────┐
│ Apply Discount Code          │
│                              │
│ [ code ][ Apply Discount ]   │
└───────────────────────────────┘
```

---

## 34. Important Lessons From the Payment Page

### Inspect the DOM before styling complex Magento UI components

The coupon work demonstrated this clearly.

### Prefer CSS before template overrides

This page required much less risky customization than Shipping because we preserved the existing components.

### Native Magento payment methods are useful for development

They allow us to test realistic payment layouts without external credentials.

### Real functionality should drive the design

We used actual available payment methods rather than displaying fake payment choices.

### Consolidate CSS after experimentation

Once the layout is accepted, duplicated iteration rules should be deleted.

---

## 35. Remaining Future Enhancements

The current payment step is complete for the present scope, but future improvements could include:

### A. Razorpay sub-payment presentation

If the Razorpay extension exposes:

```text
Cards
UPI
Netbanking
Wallets
```

inside its own payment modal/flow, we can later harmonize that presentation.

We should not fake these as separate Magento payment methods.

---

### B. Purchase Order for Business Customers

Later, when we work on B2B account behavior, we can consider enabling:

```text
Purchase Order
```

for approved business customers.

This would fit naturally with the Request Quote / business-account workflow.

---

### C. Payment method descriptions

Each method could later include helpful supporting text:

```text
Cash On Delivery
Pay when your order arrives.

Bank Transfer
Transfer directly using the bank details provided after checkout.
```

This can improve UX without changing checkout behavior.

---

### D. Payment availability rules

Future business logic could conditionally expose methods based on:

```text
customer group
cart total
country
business approval state
quote order
product type
```

For example:

```text
Retail:
Razorpay
COD

Business:
Razorpay
Bank Transfer
Purchase Order
```

This is a backend/business-rule phase rather than a design phase.

---

### E. Responsive Payment Design

The current implementation is primarily desktop-focused.

A later mobile pass should address:

```text
payment card widths
sidebar stacking
coupon input/button stacking
Ship To / Shipping Method cards
Place Order visibility
```

---

# 10.BrewCraft Storefront – Development Log

## Final UI Cleanup: 7-Point Fix List

**Project:** BrewCraft Supply – Magento 2
**Scope:** Storefront / PLP / PDP / Cart / Checkout UI cleanup
**Status:** ✅ All 7 points completed
**Theme:** `app/design/frontend/BrewCraft/supply`

---

## 1. Empty Cart Icon Styling

### Requirement

The Magento empty-cart state was still using the default Magento visual/icon treatment and did not match the BrewCraft theme.

### Work completed

The empty-cart icon/state was updated to match the BrewCraft storefront styling.

The user completed the final styling directly.

### Result

The empty-cart page now visually belongs to the BrewCraft theme rather than looking like an untouched Magento component.

**Status:** ✅ Completed

---

## 2. PLP – Recently Ordered Block

### Problem

For logged-in customers who had previously placed orders, Magento automatically rendered the native:

```text
Recently Ordered
```

block in the PLP sidebar.

The block used default Magento styling and visually conflicted with the BrewCraft filter/sidebar design.

### Magento DOM identified

The actual component was:

```html
<div class="block block-reorder">
    <div class="block-title">
        <strong>Recently Ordered</strong>
    </div>

    <div class="block-content">
        <form class="form reorder">
            ...
        </form>
    </div>
</div>
```

with products rendered under:

```html
<ol class="product-items product-items-names">
```

and actions:

```html
<button class="action tocart primary">
    Add to Cart
</button>

<a class="action view">
    View All
</a>
```

### Implementation

Scoped the design specifically to:

```less
.catalog-category-view
.sidebar-additional
.block.block-reorder
```

so that Compare Products and Wishlist were unaffected.

The block was styled with:

* BrewCraft white background
* subtle stone border
* rounded card
* espresso heading
* BrewCraft typography
* compact product presentation
* espresso checkbox
* BrewCraft primary Add to Cart button
* coffee-colored View All link
* redundant `Last Ordered Items` subtitle hidden

Example:

```less
.catalog-category-view {

    .sidebar-additional
    .block.block-reorder {
        border: 1px solid @brewcraft-border !important;
        border-radius: 10px !important;
        background: @brewcraft-white !important;
    }

    .sidebar-additional
    .block.block-reorder
    input.checkbox {
        accent-color: @brewcraft-espresso !important;
    }

    .sidebar-additional
    .block.block-reorder
    .action.tocart.primary {
        background: @brewcraft-espresso !important;
        color: @brewcraft-white !important;
    }
}
```

### Result

The native Magento Recently Ordered feature was preserved, but visually integrated into the BrewCraft PLP.

**Status:** ✅ Completed

---

## 3. Review Star Styling

### Problem

Magento review stars were still using the default Magento rating color.

This affected product cards and other rating components.

### Actual Magento structure

```html
<div class="product-reviews-summary short">
    <div class="rating-summary">
        <div class="rating-result">
            <span style="width: 80%;">
                ...
            </span>
        </div>
    </div>
</div>
```

Magento creates the actual stars through pseudo-elements on:

```less
.rating-result
```

### Implementation

The empty stars were changed to a soft stone color and filled stars to BrewCraft gold.

```less
.rating-summary {

    .rating-result {

        &:before {
            color: #D8D1C7 !important;
        }

        > span:before {
            color: @brewcraft-gold !important;
        }
    }
}
```

Theme gold:

```less
@brewcraft-gold: #C8A66A;
```

### Important decision

The rating percentage was left untouched.

For example:

```html
<span style="width: 80%;">
```

still controls whether the product displays 4/5 stars, 3/5 stars, etc.

Only the visual color changed.

### Coverage

The rule applies to standard Magento rating summaries used in:

```text
PLP
PDP
Related Products
Upsell Products
Cross-sell Products
Other product cards
```

### Result

Ratings now use the BrewCraft gold/stone visual language instead of Magento's default styling.

**Status:** ✅ Completed

---

## 4. Breadcrumb and Review Link Colors

### Problem

Some Magento-native links were still blue, particularly:

```text
Breadcrumb links
2 Reviews
Add Your Review
```

This introduced a color outside the BrewCraft design system.

### Breadcrumb implementation

```less
.breadcrumbs {

    a {
        color: @brewcraft-coffee !important;
        text-decoration: none !important;
    }

    a:hover {
        color: @brewcraft-espresso !important;
        text-decoration: underline !important;
    }

    .item strong {
        color: @brewcraft-charcoal !important;
    }
}
```

### PDP review links

```less
.catalog-product-view {

    .product-reviews-summary {

        .reviews-actions {

            .action.view,
            .action.add {
                color: @brewcraft-coffee !important;
                text-decoration: none !important;
            }

            .action.view:hover,
            .action.add:hover {
                color: @brewcraft-espresso !important;
                text-decoration: underline !important;
            }
        }
    }
}
```

### Design behavior

```text
Normal link    → Coffee
Hover          → Espresso
Current item   → Charcoal
```

### Result

Magento blue was removed from these catalog UI elements without globally overriding unrelated links.

**Status:** ✅ Completed

---

## 5. Add-to-Cart Message Styling + Automatic Visibility

This point had two separate requirements.

### A. Message styling

### Problem

Magento's default Add to Cart success message was visually too strong/default and did not match the BrewCraft UI.

### Implementation

Magento messages were styled globally using:

```less
.page.messages,
.messages
```

Success messages use:

* subtle pale green background
* BrewCraft green text
* custom circular success indicator
* rounded corners
* BrewCraft typography

Example:

```less
.page.messages,
.messages {

    .message.success {
        border: 1px solid #BFD8C8 !important;
        background: #F1F8F3 !important;
        color: @brewcraft-green !important;
    }

    .message.success:before {
        content: '✓' !important;
        background: @brewcraft-green !important;
        color: @brewcraft-white !important;
    }
}
```

Error and warning states were also given matching visual treatment.

---

### B. Automatically bring new messages into view

### Problem

When a customer clicked Add to Cart lower down a PDP or PLP, Magento displayed the success message near the top of the page.

The customer might never see it because the browser remained at the current scroll position.

### First implementation

A `MutationObserver` was initially used to watch:

```html
.page.messages
```

for new Magento messages.

However, this did not reliably trigger with the actual Magento Add to Cart lifecycle.

### Final solution

A new theme JavaScript module was created:

```text
app/design/frontend/BrewCraft/supply/web/js/brewcraft-messages.js
```

The final implementation listens directly for Magento's:

```js
ajax:addToCart
```

event.

Core logic:

```js
$(document).on('ajax:addToCart', function () {
    scrollToMessage();
});
```

A `MutationObserver` remains as a fallback.

The script waits for Magento to generate the message and then performs:

```js
window.scrollTo({
    top: Math.max(messageTop, 0),
    behavior: 'smooth'
});
```

A header offset is included so the message is not hidden behind the storefront header.

The script also checks whether the message is already visible:

```js
if (isMessageVisible(message)) {
    return;
}
```

Therefore unnecessary scrolling is avoided.

### RequireJS registration

`requirejs-config.js` now includes:

```js
var config = {
    deps: [
        'js/brewcraft-move-shipping-method',
        'js/brewcraft-cart-qty',
        'js/brewcraft-messages'
    ]
};
```

### Result

Customer action:

```text
Click Add to Cart
        ↓
Magento processes AJAX request
        ↓
Success message is generated
        ↓
ajax:addToCart event detected
        ↓
Page smoothly moves to message
        ↓
Customer immediately sees confirmation
```

The user verified the final version was working.

**Status:** ✅ Completed

---

## 6. Cart Quantity `+ / -` Regression

### Problem

The BrewCraft cart had custom quantity controls:

```text
[-]  1  [+]
```

but both buttons stopped working.

Browser console showed an error similar to:

```text
Refused to execute script ...
brewcraft-cart-qty.js
because MIME type text/plain ...
```

### Investigation

The source JavaScript file expected by RequireJS did not actually exist:

```text
app/design/frontend/BrewCraft/supply/web/js/brewcraft-cart-qty.js
```

The HTML controls themselves were correct:

```html
<div class="brewcraft-cart-qty">

    <button
        type="button"
        data-role="cart-qty-minus">
        −
    </button>

    <input
        class="input-text qty"
        data-role="cart-item-qty">

    <button
        type="button"
        data-role="cart-qty-plus">
        +
    </button>

</div>
```

### Root cause

RequireJS was trying to load:

```text
js/brewcraft-cart-qty
```

but the physical JavaScript file had been removed/missing.

The browser therefore received an invalid static response rather than executable JavaScript.

### Fix

The file was recreated:

```text
web/js/brewcraft-cart-qty.js
```

with delegated event handlers:

```js
$(document).on(
    'click',
    '[data-role="cart-qty-minus"]',
    function (event) {
        ...
    }
);
```

and:

```js
$(document).on(
    'click',
    '[data-role="cart-qty-plus"]',
    function (event) {
        ...
    }
);
```

The script:

* finds the associated input
* reads the current quantity
* respects the minimum quantity
* updates the value
* triggers Magento's `change` event

RequireJS was updated to load it:

```js
'js/brewcraft-cart-qty'
```

### Result

Both cart quantity buttons work again.

The user explicitly verified:

```text
thanks, now it works
```

**Status:** ✅ Completed

---

## 7. Logged-In Checkout – Saved Address + New Address Modal

This was the largest item in the cleanup list.

### A. Saved Address Card

### Requirement

Logged-in customers use saved Magento addresses during checkout.

The native saved-address card did not match the BrewCraft checkout.

Actual DOM:

```html
<div class="shipping-address-items">

    <div class="shipping-address-item selected-item">

        Kruthika SJ
        ...
        India

        <button class="action edit-address-link">
            Edit
        </button>

    </div>

</div>
```

### Implementation

The saved-address block was converted into a BrewCraft-style card with:

* white background
* theme border
* rounded corners
* BrewCraft typography
* espresso/coffee selected state
* proper spacing
* themed New Address action

### Selected address indicator

Magento's selected-address marker originally appeared orange.

It was changed to BrewCraft espresso by overriding the selected item pseudo-element.

```less
.checkout-index-index
.checkout-shipping-address
.shipping-address-item.selected-item:after {
    border-color: @brewcraft-espresso !important;
    background: @brewcraft-espresso !important;
}
```

---

### B. LESS Compilation Issue

During development, the saved-address CSS initially appeared to do absolutely nothing.

We confirmed the source existed:

```bash
grep -n "SAVED ADDRESS AREA" \
app/design/frontend/BrewCraft/supply/web/css/source/_checkout.less
```

Result:

```text
2607: // SAVED ADDRESS AREA
```

We also confirmed `_checkout.less` was imported from:

```text
_extend.less
```

using:

```less
@import '_checkout.less';
```

However:

```bash
grep -R "shipping-address-item.selected-item" \
pub/static/frontend/BrewCraft/supply/en_US/css \
-n
```

returned nothing.

### Root cause

The latest LESS source had not reached the generated storefront CSS.

This proved the problem was not the DOM selectors but static-content/LESS compilation.



This was an important debugging lesson:

> Before repeatedly rewriting selectors, verify whether the CSS actually exists in Magento's generated output.

---

## New Address Popup


### Initial problem

The first custom attempt over-controlled the form using:

```less
grid-column
grid-row
width
max-width
flex
```

This conflicted with the already-customized Shipping Address form because Magento uses the same:

```html
.form.form-shipping-address
```

classes inside the modal.

The result was an awkward layout with fields compressed into incorrect positions.

### Second issue

The New Address button temporarily displayed:

```text
+ + New Address
```

because Magento already generated a plus icon while custom CSS added another:

```less
content: '+';
```

The custom duplicate pseudo-element was removed.

### Final approach

The modal's width and centering were left to Magento/current working styles.

The user intentionally commented out custom rules such as:

```less
// width: 760px !important;
// max-width: calc(100% - 80px) !important;

// margin-left: auto !important;
// margin-right: auto !important;
```

because the native/current modal dimensions looked better.

From that point, styling was restricted to the **inner fields only**.

Final polishing included:

* modal title
* labels
* field spacing
* input height
* border radius
* border colors
* focus state
* street fields
* telephone tooltip
* required validation
* Save in Address Book checkbox
* Cancel button
* Ship Here button

Example field styling:

```less
#shipping-new-address-form
.input-text,
#shipping-new-address-form
select.select {

    width: 100% !important;
    height: 46px !important;

    padding: 0 14px !important;

    border: 1px solid @brewcraft-border !important;
    border-radius: 6px !important;

    background: @brewcraft-white !important;

    color: @brewcraft-charcoal !important;

    box-shadow: none !important;
}
```

Focus state:

```less
#shipping-new-address-form
.input-text:focus,
#shipping-new-address-form
select.select:focus {

    border-color: @brewcraft-coffee !important;

    box-shadow:
        0 0 0 1px @brewcraft-coffee !important;
}
```

Checkbox:

```less
#shipping-save-in-address-book {
    accent-color: @brewcraft-espresso !important;
}
```

### Result

Logged-in checkout now has:

```text
Saved address card
        ✓ BrewCraft selected indicator

+ New Address

New Address Modal
        BrewCraft typography
        BrewCraft inputs
        BrewCraft checkbox
        Cancel
        Ship Here
```

The final layout was confirmed visually acceptable.

**Status:** ✅ Completed

---

Current RequireJS dependencies include:

```js
var config = {
    deps: [
        'js/brewcraft-move-shipping-method',
        'js/brewcraft-cart-qty',
        'js/brewcraft-messages'
    ]
};
```

---

## Static Asset Workflow Used

During these changes, the reliable clean-build sequence was:

```bash
rm -rf var/view_preprocessed/*
rm -rf pub/static/frontend/BrewCraft/*
rm -rf var/cache/*
rm -rf var/page_cache/*

bin/magento cache:flush
```

When Magento did not regenerate newer LESS/JS correctly:

```bash
bin/magento setup:static-content:deploy \
-f \
en_US \
--theme BrewCraft/supply
```

followed by:

```text
Ctrl + Shift + R
```

in the browser.

---

## Key Technical Lessons from This Cleanup

### Verify compiled CSS before changing selectors

A correct selector can appear broken simply because Magento has not compiled the latest LESS.

Useful check:

```bash
grep -R "selector-name" \
pub/static/frontend/BrewCraft/supply/en_US/css \
-n
```

If the source has the selector but generated CSS does not, the issue is compilation/static assets — not selector specificity.

### Inspect Magento's exact DOM first

This was especially important for:

```text
Recently Ordered
Saved addresses
New Address modal
Review stars
```

Styling the actual rendered classes was much more reliable than assuming Magento's structure.

### Shared Magento classes can create unexpected conflicts

The normal Shipping page and the New Address popup both use:

```html
<form class="form form-shipping-address">
```

So page-level rules can unintentionally affect the popup.

Modal-specific selectors should therefore begin with:

```less
.modal-popup.new-shipping-address-modal
```

when appropriate.

### Don't duplicate Magento-generated icons

The double:

```text
+ + New Address
```

came from custom CSS adding an icon Magento already supplied.

Before using:

```less
content: '+';
```

check whether Magento's `:before` / `:after` already creates the icon.

### Use Magento events when available

For the Add to Cart scroll behavior, observing the DOM alone was unreliable.

The final reliable solution used:

```js
ajax:addToCart
```

because it directly represents the Magento action we care about.

### Diagnose missing JavaScript from the source upward

The cart quantity regression initially appeared to be a behavioral bug, but the actual cause was simply that:

```text
brewcraft-cart-qty.js
```

was missing while RequireJS still referenced it.

The correct debugging path was:

```text
Browser error
→ RequireJS module
→ static file
→ theme source file
→ discovered missing file
→ recreate file
```

---


# 11. BrewCraft Supply – Login & Account Creation Development Log

### Module / Section

**Customer Login & Account Creation**

### Reference

BrewCraft Supply Figma – **Login & Create Account Flow**

---

## 1. Work Completed

### 1.1 Magento Login Page Structure Reviewed

Reviewed the existing Magento customer login HTML and identified the main components:

* Registered Customers section
* Email field
* Password field
* Show Password checkbox
* Sign In button
* Forgot Your Password link
* New Customers section
* Create an Account button

The existing Magento login functionality and routes were kept intact.

---

### 1.2 BrewCraft Login Styling Structure Added

The BrewCraft theme's LESS structure was used instead of modifying Magento core CSS.

The main custom stylesheet:

```text
app/design/frontend/BrewCraft/supply/web/css/source/_extend.less
```

was already importing:

```less
@import '_login.less';
@import '_register.less';
```

This gives us separate files for login and registration styling.

---

### 1.3 Login Page Styled According to BrewCraft Design

The login area was brought toward the BrewCraft visual style.

The styling direction established was:

* BrewCraft brown as the primary action color
* White/light content areas
* Rounded input fields
* BrewCraft typography
* Consistent button styling
* Clean spacing
* BrewCraft border and shadow treatment
* Consistent styling with the rest of the website

The goal was to keep Magento's functionality while changing the presentation to match the Figma.

---

## 2. Account Creation Flow Defined

The Figma flow was reviewed and the required customer journey was established.

### Step 1 – Login

```text
Login Page
   ↓
Create an Account
```

### Step 2 – Account Type Selection

```text
Create an Account
       ↓
 ┌───────────────┐
 │               │
Retail Customer  Business Customer
```

### Step 3A – Retail Customer

Retail registration form containing the customer information required for a retail account.

### Step 3B – Business Customer

Business registration form containing business/customer-specific information.

### Step 4A – Retail Account Created

After successful retail registration:

```text
Retail Account Created
        ↓
Continue Shopping / Homepage
```

### Step 4B – Business Account Created

After successful business registration:

```text
Business Account Created
        ↓
Business Dashboard
```

---

## 3. Checkout / Address Popup Styling Work Related to the Current Flow

While working through the customer/checkout experience, the **Shipping Address / New Address popup** was also customized to follow the BrewCraft design.

The popup was changed to use:

* White background
* Rounded corners
* BrewCraft shadow
* BrewCraft brown buttons
* Custom field styling
* BrewCraft typography
* Proper modal spacing
* Centered modal layout

The relevant checkout LESS section was identified around:

```text
SAVED ADDRESS AREA
```

inside:

```text
app/design/frontend/BrewCraft/supply/web/css/source/_checkout.less
```

---

## 4. Problems Encountered & Solutions

### Problem 1 – New Address Popup Was Not Centered

### Issue

The modal was initially appearing with incorrect width/positioning.

The following CSS had been commented out:

```less
.modal-inner-wrap {
    // width: 760px !important;
    // max-width: calc(100% - 80px) !important;

    // margin-left: auto !important;
    // margin-right: auto !important;
}
```

### Solution

The modal width and automatic horizontal margins were restored/adjusted so that the popup could use the intended width and remain centered.

The final result matched the intended modal positioning much better.

---

### Problem 2 – Modal Fields Became Misaligned

### Issue

When the modal layout was being changed too aggressively, the form fields became incorrectly positioned.

For example:

* First Name / Last Name widths became too small
* Fields appeared in unexpected columns
* Street Address was pushed to the right
* Labels wrapped unnecessarily
* Country/City and State/Postal Code became visually cramped

### Solution

Instead of changing Magento's underlying form layout, the styling was restricted primarily to the **visual appearance of the existing fields**.

This was important because the Magento checkout form already has its own UI-component/grid structure.

### Result

The existing field positions were preserved and only the styling was customized.

---

### Problem 3 – Checkbox Used the Wrong Color

### Issue

The **Save in address book** checkbox was appearing in Magento's default blue/orange-style appearance instead of matching BrewCraft.

### Solution

The checkbox styling was overridden so that its appearance followed the BrewCraft design rather than the browser/Magento default.

---

### Problem 4 – "New Address" Button Had Two Plus Signs

### Issue

The New Address button displayed:

```text
+ + New Address
```

instead of:

```text
+ New Address
```

This happened because both the existing Magento/generated icon/content and the custom CSS were adding a plus symbol.

### Solution

The duplicate pseudo-element/content was identified and removed so that only one plus icon remains.

### Final expected result

```text
+ New Address
```

---

### Problem 5 – Popup Styling Initially Looked Too Compressed

### Issue

An earlier version of the popup caused:

* Very narrow fields
* Wrapped labels
* Poor spacing
* Incorrect column distribution
* Unbalanced visual hierarchy

### Solution

The approach was changed from restructuring the form to styling the existing Magento form.

The modal container was handled separately from the individual field styling.

This allowed the Magento layout to remain functional while the BrewCraft styles were applied on top.

---

### Problem 6 – Scrolling Issue

### Issue

At one stage, scrolling inside/around the modal experience was not working correctly.

### Solution

The modal/overflow styling was adjusted and tested again.

### Result

Scrolling was confirmed to be working.

---

### Problem 7 – Existing Magento Styling Interfering With Custom Styling

### Issue

Some Magento default styles were still affecting the BrewCraft design.

An example was searching generated static CSS for:

```text
shipping-address-item.selected-item
```

but no matching result was found in the expected generated CSS location.

### Investigation

The source LESS files were checked directly, including:

```text
app/design/frontend/BrewCraft/supply/web/css/source/_checkout.less
```

and:

```text
app/design/frontend/BrewCraft/supply/web/css/source/_extend.less
```

The custom checkout stylesheet was confirmed to be imported through:

```less
@import '_checkout.less';
```

### Result

The source theme LESS became the main place for maintaining the custom BrewCraft checkout styling instead of editing generated static files.

---

## 5. Important Implementation Lesson

During this work, we established an important rule for the BrewCraft theme:

> **Do not fight Magento's existing UI-component/form layout unnecessarily.**

For Magento forms and checkout modals:

1. Keep Magento's existing HTML/UI-component structure.
2. Keep Magento's validation and JavaScript behavior.
3. Customize the container styling.
4. Customize fields visually.
5. Only change layout structure when the Figma genuinely requires it.
6. Avoid editing generated files under `pub/static`.
7. Keep custom styling inside the BrewCraft theme LESS files.

This prevents visual customization from breaking Magento functionality.

---

## 6. Current Status

### Completed

* [x] Reviewed Magento login page HTML
* [x] Identified login form structure
* [x] Established BrewCraft login LESS structure
* [x] Applied BrewCraft styling direction to login page
* [x] Reviewed Figma Login & Create Account flow
* [x] Defined Retail vs Business account flow
* [x] Defined Retail account-created flow
* [x] Defined Business account-created flow
* [x] Styled Shipping Address popup
* [x] Corrected popup positioning/width
* [x] Corrected popup field styling
* [x] Styled Save in Address Book checkbox
* [x] Removed duplicate plus sign from New Address button
* [x] Fixed modal scrolling issue
* [x] Verified custom LESS import structure

---

## 7. What Is Still Left

The **Login & Create Account flow is not completely implemented yet**.

The major remaining work is:

### 7.1 Login Page

Finish comparing the actual Magento login page against the Figma and identify any remaining visual differences.

### 7.2 Account Type Selection

Implement the Figma-style:

```text
Create an Account
        ↓
Retail Customer | Business Customer
```

This page needs to be connected to the appropriate registration flow.

### 7.3 Retail Registration

Implement the BrewCraft Retail Customer registration page according to the Figma.

Need to verify:

* Field layout
* Labels
* Required indicators
* Password fields
* Terms & Conditions
* Privacy Policy
* Button styling
* Validation/error presentation
* Responsive behavior

### 7.4 Business Registration

Implement the Business Customer registration page.

Need to handle the business-specific fields and BrewCraft styling.

### 7.5 Account Creation Success Pages

Implement/design:

* Retail account created page
* Business account created page

with the appropriate redirect behavior.

### 7.6 End-to-End Flow Testing

After all pages are implemented, test:

```text
Login
  ↓
Create Account
  ↓
Choose Account Type
  ↓
Retail / Business Registration
  ↓
Account Creation
  ↓
Correct Redirect
```

Also test validation, incorrect credentials, existing email, password validation, and responsive layouts.

---

## 8. Current Next Step

The next logical development task is:

**Implement the "Create an Account – Choose Account Type" page from the Figma.**

Once that is completed, we can move sequentially into:

```text
Choose Account Type
        ↓
Retail Registration
        ↓
Business Registration
        ↓
Account Created / Redirect
        ↓
End-to-End Testing
```

# 12. BrewCraft Supply – Development Log

### Forgot Password Page & Password Functionality

## 1. Forgot Password Page – Styling

The Magento default Forgot Password page was reviewed and customized to match the existing BrewCraft Supply account-creation page design.

### Existing Magento functionality retained

The following Magento functionality was kept unchanged:

* Forgot Password form submission.
* Email validation.
* CAPTCHA validation.
* CAPTCHA image generation.
* CAPTCHA reload functionality.
* Password reset workflow.
* Form validation and Magento error handling.
* "Go back" navigation to the login page.

The work was focused on **frontend styling**, not changing Magento's underlying password-reset logic.

---

### 2. Forgot Password Page Structure

The page contains:

* Forgot Password page title.
* Informational message:

  * "Please enter your email address below to receive a password reset link."
* Email field.
* CAPTCHA field.
* CAPTCHA image.
* CAPTCHA reload button.
* "Reset My Password" button.
* "Go back" link.

The form uses Magento's standard:

```text
form.password.forget
```

and:

```text
#form-validate
```

structure.

---

### 3. Forgot Password Card Styling

The Forgot Password form was converted into a BrewCraft-style centered card.

Implemented:

* Maximum width: **700px**
* Centered horizontally.
* BrewCraft cream background.
* BrewCraft border color.
* Rounded corners.
* Consistent internal padding.
* Removed unnecessary Magento floating behavior.

The styling follows the same structure already established for the customer account creation page.

### Design consistency

The same variables were reused:

```text
@brewcraft-cream
@brewcraft-white
@brewcraft-border
@brewcraft-charcoal
@brewcraft-muted
@brewcraft-espresso
@brewcraft-coffee
@bc-space-*
@bc-radius-sm
@bc-radius-md
```

This prevents the Forgot Password page from looking like a separate design from the rest of the account section.

---

### 4. Email Field Styling

The email field was redesigned to match the account creation inputs.

Implemented:

* Full-width input.
* 44px input height.
* White background.
* BrewCraft border.
* Rounded corners.
* BrewCraft body font.
* 14px font size.
* Consistent horizontal padding.
* No unnecessary box shadow.

Focus state was also customized:

```text
border-color: @brewcraft-coffee
box-shadow: 0 0 0 1px @brewcraft-coffee
```

This replaces the default Magento focus appearance.

---

### 5. CAPTCHA Styling

The Magento CAPTCHA functionality was preserved, but its appearance was brought into the BrewCraft theme.

Styled:

* CAPTCHA input.
* CAPTCHA image.
* CAPTCHA container.
* Reload button.
* Spacing between CAPTCHA elements.
* Border and border-radius.

The CAPTCHA image now visually belongs to the same form instead of appearing as an unstyled Magento component.

The reload button was also styled using the BrewCraft colors.

---

### 6. Action Area Styling

The bottom action area was redesigned to match the customer account creation page.

Actions:

```text
Reset My Password
Go back
```

The action area now has:

* Top border separator.
* Consistent top padding.
* Consistent spacing between buttons.
* Proper horizontal alignment.
* Magento float behavior removed.

The primary button uses:

```text
@brewcraft-espresso
```

with:

```text
@brewcraft-coffee
```

on hover.

The button was given:

* 210px minimum width.
* 46px height.
* Rounded corners.
* White text.
* BrewCraft typography.
* Proper centered text alignment.

---

### 7. Go Back Link

The Magento "Go back" action was styled as a BrewCraft secondary action.

Implemented:

* BrewCraft espresso text.
* 14px font.
* Medium font weight.
* Underline.
* Underline offset.
* Coffee color on hover.

This keeps the secondary action visually lighter than the main Reset Password button.

---

## Password Functionality – Business Registration

The Business Registration page originally contained custom password fields, but the Magento password functionality was not initially working.

The password section contained:

```text
Password
Confirm Password
```

with Magento validation attributes such as:

```text
validate-customer-password
equalTo: #password
```

and password configuration:

```text
data-password-min-length="8"
data-password-min-character-sets="3"
```

---

### 8. Initial Password Problem

Initially, the password field was displayed but:

* Password strength checking was not working.
* Password strength indicator was not updating.
* Show Password functionality was not working.
* The password field behaved like a normal HTML password input.

The issue was not with the HTML input itself.

The Magento JavaScript components responsible for these features had not been correctly initialized.

---

### 9. Password Strength Meter

The Magento password strength indicator was added to the Business Registration form.

The password section was updated to include the Magento strength-meter container:

```text
#password-strength-meter-container
```

with:

```text
data-role="password-strength-meter"
```

and:

```text
data-role="password-strength-meter-label"
```

The existing Magento password requirements were retained:

```text
data-password-min-length="8"
data-password-min-character-sets="3"
```

This means the password follows the configured Magento requirements instead of implementing a separate custom password-strength algorithm.

---

### 10. Magento Password Strength JavaScript Initialization

The Magento password strength component was explicitly initialized using:

```text
Magento_Customer/js/password-strength-indicator
```

through Magento's `text/x-magento-init`.

This was important because simply adding the HTML password meter does not make it functional.

After correcting the initialization target and ensuring the JavaScript was attached to the correct Business Registration form, the password strength indicator started working.

---

### 11. Show Password Functionality

Magento's standard Show Password implementation was added to the Business Registration page.

The field uses:

```text
data-bind="scope: 'showPassword'"
```

and:

```text
data-role="show-password"
```

along with the checkbox:

```text
name="show-password"
id="show-password"
```

This allows Magento's existing password visibility functionality to be reused instead of creating custom JavaScript.

---

### 12. Confirm Password Validation

The Confirm Password field continues to use Magento validation:

```text
"equalTo": "#password"
```

Therefore:

* Confirm Password is required.
* It must match the Password field.
* Magento's validation framework handles the error state.
* No custom password comparison JavaScript was required.

---

## 13. Important Debugging / Mistakes Encountered

### Issue 1 – Password meter displayed but did not work

**Problem:**

The password-strength HTML was present, but the strength meter did not react to password input.

**Cause:**

Magento's password-strength JavaScript was not correctly initialized against the actual form.

**Solution:**

Added/ corrected Magento initialization using:

```text
Magento_Customer/js/password-strength-indicator
```

and ensured the selector matched the actual Business Registration form.

---

### Issue 2 – Show Password was not working

**Problem:**

The checkbox appeared, but password visibility was not changing.

**Cause:**

Magento's `showPassword` component was not correctly connected to the field.

**Solution:**

Used Magento's existing `showPassword` scope and:

```text
data-role="show-password"
```

instead of writing custom JavaScript.

---

### Issue 3 – Incorrect Magento initialization selector

During debugging, the template was checked with:

```bash
grep -n -E "password|showPassword|magento-init|strength|Customer" \
app/code/BrewCraft/BusinessAccount/view/frontend/templates/account/create.phtml
```

This confirmed that the password markup and Magento initialization existed.

The actual form ID was:

```text
#business-account-form
```

so the initialization target had to correspond to the real form rather than an outdated/nonexistent selector.

After correcting this, the Magento password functionality worked.

---

### Issue 4 – Forgot Password CSS initially appeared not to apply

The first Forgot Password styling attempt did not visibly affect the page.

The problem was approached by comparing it with the already-working customer account creation CSS rather than creating an unrelated styling structure.

The final styling was then scoped specifically under:

```text
.customer-account-forgotpassword
```

and:

```text
form.form.password.forget
```

This prevents the Forgot Password styles from unintentionally affecting other Magento forms.

---

## 14. Responsive Behaviour

The Forgot Password page was also given responsive styling.

At widths below 768px:

* Page padding is reduced.
* Page title becomes smaller.
* Form card uses the available screen width.
* Card padding is reduced.
* Reset button becomes full width.
* Actions stack vertically.
* "Go back" becomes centered.
* CAPTCHA elements can wrap when required.

---

## 15. Current Status

### Completed

* [x] Forgot Password page BrewCraft card styling.
* [x] Consistent card width and alignment.
* [x] BrewCraft colors applied.
* [x] Input styling.
* [x] Input focus styling.
* [x] CAPTCHA styling.
* [x] CAPTCHA image styling.
* [x] CAPTCHA reload button styling.
* [x] Reset Password button styling.
* [x] Go Back link styling.
* [x] Responsive Forgot Password layout.
* [x] Business Registration password field.
* [x] Magento password strength meter.
* [x] Magento password strength JavaScript initialization.
* [x] Magento Show Password functionality.
* [x] Confirm Password validation.
* [x] Password matching validation.
* [x] Existing Magento password-reset functionality preserved.

### Verified Working

* [x] Retail registration.
* [x] Business registration.
* [x] Account selection.
* [x] Customer redirect to My Account.
* [x] Business password strength checking.
* [x] Show Password functionality.
* [x] Confirm Password validation.
* [x] Forgot Password page styling.

---

## 16. Development Approach

For these account pages, the implementation intentionally follows this principle:

> **Magento handles the functionality; BrewCraft theme handles the presentation.**

Therefore:

* Magento validation is reused.
* Magento password strength logic is reused.
* Magento Show Password functionality is reused.
* Magento CAPTCHA functionality is reused.
* Custom LESS is used for BrewCraft visual styling.
* Existing BrewCraft spacing and color variables are reused.
* CSS is scoped to the relevant customer/account page to avoid affecting unrelated Magento components.

This keeps the implementation maintainable and reduces the amount of custom JavaScript required.

# 13.Account Page — Development Log

### Customer Account Section Styling

**Date:** 27 August 2026
**Area:** Magento 2 Customer Account / My Account
**Theme:** `BrewCraft/supply`
**Environment:** Local Magento development environment

---

### 1. Objective

Continued development of the **BrewCraft Supply customer account section**, with the goal of matching the planned Figma design while keeping Magento's existing customer-account functionality and HTML structure intact.

The approach followed during this work was:

* Keep Magento's existing functionality.
* Avoid unnecessarily overriding Magento templates.
* Use the existing Magento-generated HTML/classes wherever possible.
* Apply styling through the BrewCraft theme LESS files.
* Avoid replacing working Magento components with custom HTML/JavaScript.
* Defer the account/sidebar icons until a proper Magento icon-font approach is decided.

---

## 2. Customer Account Sidebar

The existing Magento customer account sidebar was inspected using browser developer tools.

The sidebar structure was confirmed as:

```text
My Account
│
├── My Account
├── My Quote Requests
├── My Orders
├── Business Account
├── My Downloadable Products
├── My Wish List
│
├── Address Book
├── Account Information
├── Stored Payment Methods
│
├── My Product Reviews
├── Newsletter Subscriptions
└── Sign Out
```

The sidebar is Magento's standard:

```html
<div class="sidebar sidebar-main">
    <div class="block block-collapsible-nav">
```

and the individual navigation items are generated using:

```html
<li class="nav item">
```

with the active item represented by:

```html
<li class="nav item current">
```

#### Important decision

No new navigation structure was created.

The existing Magento account navigation is being retained so that:

* URLs continue working.
* Active navigation states continue working.
* Magento customer-account functionality remains intact.
* Future account pages automatically inherit the same sidebar styling.

The sidebar styling was adjusted so that it behaves as a **proper narrow account-navigation column**, rather than allowing the sidebar styling to visually occupy too much of the page.

---

## 3. Additional Sidebar

The additional sidebar was inspected separately.

Magento currently generates three relevant blocks:

```text
Additional Sidebar
│
├── Compare Products
│   └── You have no items to compare.
│
├── Recently Ordered
│   └── hidden when there are no items
│
└── My Wish List
    └── You have no items in your wish list.
```

The relevant HTML structure was identified as:

```html
<div class="sidebar sidebar-additional">
```

with:

```html
<div class="block block-compare">
```

```html
<div class="block block-reorder">
```

and:

```html
<div class="block block-wishlist">
```

---

## 4. Recently Ordered Empty-Block Issue

A visual issue was found with Magento's **Recently Ordered** block.

When the customer had no recently ordered items, Magento correctly applied:

```html
class="block-title no-display"
```

and:

```html
class="block-content no-display"
```

However, the parent:

```html
<div class="block block-reorder">
```

still occupied visual space.

This resulted in an unwanted **empty visible box** in the additional sidebar.

#### Initial solution

A page-specific selector was initially used:

```less
.customer-account-index {
    .sidebar-additional {
        .block.block-reorder {
            .block-title.no-display+.block-content.no-display {
                margin: 0 !important;
                padding: 0 !important;
                border: 0 !important;
                min-height: 0 !important;
            }
        }
    }
}
```

and:

```less
.customer-account-index {
    .sidebar-additional {
        .block.block-reorder {
            &:has(.block-title.no-display) {
                display: none !important;
            }
        }
    }
}
```

This successfully removed the empty block on the **My Account dashboard**.

#### Problem discovered

The selector:

```less
.customer-account-index
```

only applies to the My Account dashboard.

When navigating to another customer-account page such as:

```text
My Orders
```

Magento changes the body/page class, so the CSS no longer matched.

As a result, the empty Recently Ordered block appeared again on other account pages.

---

## 5. Final Recently Ordered Solution

The CSS was simplified to target the common Magento account layout instead of a single account page.

Final implementation:

```less
/* Hide Recently Ordered block when there are no ordered items */
.account {

    .sidebar-additional {

        .block.block-reorder:has(.block-title.no-display) {
            display: none !important;
        }

    }
}
```

#### How it works

Magento itself tells us when the block has no content through:

```html
.block-title.no-display
```

Therefore:

```less
.block.block-reorder:has(.block-title.no-display)
```

means:

> Hide the entire Recently Ordered block when Magento marks its title as `no-display`.

The parent:

```less
.account
```

allows the rule to work across the customer account pages instead of only:

```less
.customer-account-index
```

#### Result

The empty Recently Ordered block is now hidden consistently on:

* My Account
* My Orders
* My Quote Requests
* Business Account
* Other customer account pages using the same account layout

When recently ordered products actually exist, Magento removes the `no-display` state and the block can display normally.

---

## 6. My Account Dashboard — Main Section

The Magento My Account dashboard HTML was inspected.

The main content consists of:

```text
My Account
│
├── Account Information
│   ├── Contact Information
│   └── Newsletters
│
├── Address Book
│   ├── Default Billing Address
│   └── Default Shipping Address
│
└── Recent Orders
```

The existing Magento blocks were retained.

Important classes identified include:

```html
.block-dashboard-info
.block-dashboard-addresses
.block-dashboard-orders
```

and the individual cards use:

```html
.box
```

with specific classes such as:

```html
.box-information
.box-newsletter
.box-billing-address
.box-shipping-address
```

The styling work was performed against these existing Magento classes rather than creating replacement markup.

---

## 7. Account Information Section

The Account Information block was styled while preserving Magento's existing structure.

Magento provides:

```html
<div class="block block-dashboard-info">
```

containing:

```html
<div class="box box-information">
```

and:

```html
<div class="box box-newsletter">
```

The existing functionality remains unchanged:

#### Contact Information

Displays:

* Customer name
* Email address
* Edit
* Change Password

#### Newsletter

Displays:

* Subscription status
* Edit link

No custom controller or template was required for this styling work.

---

## 8. Address Book Section

The Address Book area was also retained using Magento's existing structure:

```html
<div class="block block-dashboard-addresses">
```

with:

```html
.box-billing-address
.box-shipping-address
```

The existing functionality remains:

* Manage Addresses
* Default Billing Address
* Default Shipping Address
* Edit Address

The visual treatment was applied through the theme styling without modifying Magento's address rendering logic.

---

## 9. Recent Orders — My Account Dashboard

The Recent Orders section was inspected.

Magento already limits the dashboard's recent orders display, so no custom limitation or scrolling behavior was introduced.

The existing table includes:

```html
<table class="data table table-order-items recent"
       id="my-orders-table">
```

with:

```text
Order #
Date
Ship To
Order Total
Status
Action
```

#### Decision

Initially there was discussion about making the recent-orders area scrollable.

This was later rejected because Magento already provides the desired recent-order behavior.

Therefore:

**No custom scrollable recent-orders container was added.**

Magento's existing behavior is being retained.

---

## 10. My Orders Page

The full **My Orders** page was inspected.

The page contains:

```html
<div class="table-wrapper orders-history">
```

and:

```html
<table class="data table table-order-items history"
       id="my-orders-table">
```

Magento provides:

```text
Order #
Date
Order Total
Status
Action
```

with actions such as:

```text
View Order
Reorder
```

The existing Magento order-history functionality remains untouched.

---

## 11. Order Pagination

The My Orders page contains Magento's standard bottom toolbar:

```html
<div class="order-products-toolbar toolbar bottom">
```

Inside it Magento generates:

```text
Items 1 to 10 of 26 total

Previous
1
2
3
Next

Show 10 per page
```

The pagination HTML was inspected to confirm that the links were already functional.

For example:

```html
<a href=".../sales/order/history/">
```

```html
<a href=".../sales/order/history/?p=2">
```

and:

```html
<a href=".../sales/order/history/?p=3">
```

#### Important decision

The pagination functionality was **not recreated**.

No custom JavaScript was added.

No custom page-number links were added.

No custom next/previous logic was added.

Only visual styling was applied.

This preserves Magento's:

* Page navigation
* Previous/Next behavior
* Current-page state
* Page URL generation
* `limit` handling
* Accessibility labels

---

## 12. Pagination Styling Issue

During styling, an issue occurred where the pagination elements visually overlapped.

The cause was excessive CSS modification of Magento's existing pagination layout.

The approach was corrected by removing the custom positioning/layout rules and keeping the Magento pagination structure intact.

The final styling only changes visual properties such as:

* Colors
* Borders
* Background
* Hover state
* Current-page appearance
* Limiter appearance

The underlying Magento pagination layout and functionality remain unchanged.

#### Result

Pagination continues to work normally while matching the BrewCraft visual design more closely.

---

## 13. Account Sidebar Icons

The Figma design includes icons beside the account-sidebar navigation items and icons inside some account cards.

An attempt was initially made to create the icons through custom CSS/content.

This approach was rejected.

#### Final decision

**Icons are currently deferred.**

Instead, the Magento project was inspected to identify the existing icon resources before implementing them properly.

The following Magento icon definitions were found in:

```text
lib/web/css/source/lib/variables/_icons.less
```

including:

```text
@icon-wishlist-full
@icon-wishlist-empty
@icon-warning
@icon-update
@icon-trash
@icon-star
@icon-settings
@icon-menu
@icon-location
@icon-list
@icon-info
@icon-grid
@icon-comment
@icon-cart
@icon-calendar
@icon-search
@icon-envelope
@icon-compare-full
@icon-compare-empty
@icon-account
```

The available icon fonts were also identified:

```text
lib/web/fonts/Blank-Theme-Icons/
lib/web/fonts/MUI-Icons/
lib/web/fonts/UX-Icons/
```

and Magento Luma's:

```text
vendor/magento/theme-frontend-luma/web/fonts/Luma-Icons.woff2
vendor/magento/theme-frontend-luma/web/fonts/Luma-Icons.woff
vendor/magento/theme-frontend-luma/web/fonts/Luma-Icons.ttf
```

#### Current status

Icons are **not part of the current implementation**.

They will be revisited later using Magento's existing icon/font system rather than manually generated text characters.

---

## 14. Sign Out Functionality Verification

The Magento Sign Out functionality was also verified.

The account sidebar contains:

```html
<a href="https://project1.test/customer/account/logout/">
    <span>Sign Out</span>
</a>
```

Magento's logout-success page was inspected.

The default Magento template:

```text
vendor/magento/module-customer/view/frontend/templates/logout.phtml
```

contains the existing logout-success message and redirect initialization.

Magento's layout file:

```text
vendor/magento/module-customer/view/frontend/layout/customer_account_logoutsuccess.xml
```

was also checked and confirmed to reference:

```text
Magento_Customer::logout.phtml
```

The Sign Out flow is working correctly.

#### Important decision

No custom logout controller or logout template was created.

Magento's default logout functionality is being retained.

---

## 15. Theme LESS Files Used / Investigated

The BrewCraft theme currently contains several dedicated LESS files:

```text
app/design/frontend/BrewCraft/supply/web/css/source/
```

including:

```text
_account-type-selection.less
_brewcraft-footer.less
_brewcraft-variables.less
_business-account-create.less
_cart.less
_checkout.less
_extend.less
_header.less
_homepage.less
_login.less
_minicart.less
_pdp.less
_plp.less
_register.less
_request-quote.less
```

Account-related styling is being integrated through the existing theme LESS structure rather than modifying Magento vendor files.

---

## 16. Magento Vendor Files Were Not Modified

During this work, Magento core/vendor files were inspected for understanding, but the implementation is being kept inside:

```text
app/design/frontend/BrewCraft/supply/
```

This is important because modifying:

```text
vendor/magento/
```

would make the customization difficult to maintain and could be lost during upgrades.

---

## 17. Current Implementation Status

#### Completed

* [x] Customer account sidebar styling
* [x] Sidebar width/layout correction
* [x] Additional sidebar styling
* [x] Compare Products block styling
* [x] My Wish List block styling
* [x] Recently Ordered empty-block issue fixed
* [x] Recently Ordered fix made global across account pages
* [x] My Account main section styling
* [x] Account Information styling
* [x] Address Book styling
* [x] Recent Orders styling
* [x] Confirmed Magento's default recent-order limit is sufficient
* [x] My Orders table styling
* [x] My Orders pagination styling
* [x] Pagination functionality preserved
* [x] Previous/Next/page links preserved
* [x] Sign Out functionality verified
* [x] Magento logout template/layout inspected

#### Deferred

* [ ] Account sidebar icons
* [ ] Account Information card icons
* [ ] Address Book card icons
* [ ] Final icon-font implementation using Magento's existing icon resources

---

## 18. Technical Principle Followed

The main principle established during this work is:

> **Style Magento's existing customer-account components instead of rebuilding them.**

Where Magento already provides:

* HTML structure
* URLs
* navigation
* active states
* pagination
* order limits
* conditional visibility
* logout behavior
* accessibility markup

those features should remain untouched.

The BrewCraft theme should primarily control the:

```text
Visual layer
├── Typography
├── Spacing
├── Borders
├── Backgrounds
├── Colors
├── Hover states
├── Active states
└── Responsive presentation
```

while Magento continues controlling the:

```text
Functional layer
├── Customer navigation
├── Orders
├── Pagination
├── Account data
├── Address data
├── Wishlist
├── Compare
├── Recently Ordered
└── Logout
```

This keeps the customization maintainable and Magento-compatible.

---

### 19. Current Next Step

The **My Account / Customer Account page styling is progressing section by section**.

The account-sidebar and main My Account/Orders areas covered in this session are now in a stable state.

The icon implementation is intentionally postponed.

The next customer-account feature can therefore be started without changing the completed functionality.

# 14. BrewCraft Supply – Customer Address Pages Development Log

### 1. Feature / Section

**Magento 2 Customer Account → Address Book**

Pages covered:

1. Add New Address
2. Address Book / Manage Addresses
3. Default Billing Address
4. Default Shipping Address
5. Additional Address Entries
6. Address Book pagination / toolbar
7. Add New Address button placement and styling

The work was done as part of the BrewCraft Supply customer account frontend customization.

---

## 2. Design / Theme Requirements

There was **no Figma design available for the Address Book pages**, so the styling was designed to remain consistent with the existing BrewCraft customer-account theme.

The following existing BrewCraft design variables were used:

#### Colors

* `@brewcraft-espresso: #2e1e14`
* `@brewcraft-coffee: #5c3a21`
* `@brewcraft-cream: #f6f1e8`
* `@brewcraft-stone: #e6ded3`
* `@brewcraft-charcoal: #1f1f1f`
* `@brewcraft-green: #1f6845`
* `@brewcraft-gold: #c8a66a`
* `@brewcraft-white: #ffffff`
* `@brewcraft-muted: #777572`
* `@brewcraft-border: #ded7ce`
* `@brewcraft-bg-soft: #faf8f4`
* `@brewcraft-bg-dark: #181512`

#### Typography

* Heading: `Poppins`
* Body: `Inter`

#### Spacing

The existing BrewCraft spacing variables were reused:

* `@bc-space-1: 8px`
* `@bc-space-2: 16px`
* `@bc-space-3: 24px`
* `@bc-space-4: 32px`
* `@bc-space-5: 40px`
* `@bc-space-6: 48px`
* `@bc-space-7: 64px`
* `@bc-space-8: 72px`

#### Border Radius

* Small: `4px`
* Medium: `8px`
* Large: `12px`

---

## 3. Add New Address Page

### Initial State

Magento's default customer address form was displayed using the standard Magento Customer module template.

The goal was to make the form visually consistent with BrewCraft rather than changing Magento's underlying address functionality.

### Work Completed

The Add New Address page was styled using the BrewCraft theme.

The following areas were customized:

* Page title
* Form field appearance
* Labels
* Text inputs
* Select/dropdown fields
* Checkboxes
* Form spacing
* Buttons
* Primary/secondary actions
* Address form layout
* Overall typography
* Borders and border radius
* BrewCraft color palette

The existing Magento form structure and functionality were retained.

#### Important principle

We did **not** replace Magento's address-saving logic.

Only the presentation layer was customized.

This keeps Magento's existing:

* customer address validation
* address save action
* default billing handling
* default shipping handling
* country/state handling
* form validation

intact.

---

## 4. Address Book Page

The next page handled was:

**Customer Account → Address Book**

Magento's default Address Book contains:

1. Default Addresses
2. Additional Address Entries
3. Pagination / toolbar
4. Add New Address button
5. Back button

The default Magento layout was retained functionally and redesigned visually.

Magento's customer address model contains the customer's billing/shipping address information and supports default billing/default shipping relationships.

---

## 5. Default Billing + Default Shipping Addresses

### Requirement

Magento normally displays:

* Default Billing Address
* Default Shipping Address

as separate address boxes.

The requirement was to keep these **side by side**, matching Magento's normal desktop behavior while applying the BrewCraft design.

### Final Layout

The default address section was converted into a two-column layout:

```text
Default Addresses
────────────────────────────────────────────────────

┌──────────────────────────┐   ┌──────────────────────────┐
│ Default Billing Address  │   │ Default Shipping Address │
│                          │   │                          │
│ Customer Name             │   │ Customer Name             │
│ Company                   │   │ Street Address            │
│ Street                    │   │ City, State, ZIP          │
│ City, State, ZIP          │   │ Country                   │
│ Country                   │   │ Phone                     │
│ Phone                     │   │                          │
│ Change Billing Address    │   │ Change Shipping Address   │
└──────────────────────────┘   └──────────────────────────┘
```

### CSS Approach

The parent `.block-content` was changed to:

```less
display: flex;
gap: @bc-space-3;
```

The billing and shipping boxes were given:

```less
flex: 1 1 0;
min-width: 0;
```

This allows both boxes to share the available width evenly.

On mobile, the layout changes back to a vertical arrangement.

---

## 6. Address Card Styling

Both address cards were given a BrewCraft card appearance.

Applied styling included:

* White background
* BrewCraft border color
* Medium border radius
* Internal spacing
* Consistent typography
* Proper address line height
* Styled telephone links
* Green action links

The address content itself remains generated by Magento.

Only its presentation was changed.

---

## 7. Change Billing / Shipping Address Links

Magento provides:

* `Change Billing Address`
* `Change Shipping Address`

These links were retained.

They were styled using the BrewCraft green:

```less
color: @brewcraft-green;
```

Hover state uses the darker BrewCraft espresso color.

This keeps action links visually distinct from normal address text.

---

## 8. Additional Address Entries

Magento displays additional saved customer addresses in a table.

The existing Magento table was retained rather than replacing it with a custom data structure.

The table was styled to match the BrewCraft theme.

### Table areas styled

#### Header

* Soft BrewCraft background
* BrewCraft coffee-colored text
* Smaller heading typography
* Bottom border

#### Body

* Consistent padding
* BrewCraft border colors
* Inter font
* Proper vertical alignment
* Row separators

#### Actions

Existing Magento actions were retained:

* Edit
* Delete

The actions were styled as BrewCraft links.

Delete was given a more muted appearance so it doesn't visually compete with the primary Edit action.

---

## 9. Empty Additional Address State

Magento already provides an empty state:

```text
You have no other address entries in your address book.
```

This functionality was preserved.

No custom fake address data or frontend-only placeholder was introduced.

This is important because the page should automatically switch between:

```text
Additional addresses table
```

and:

```text
You have no other address entries in your address book.
```

depending on the customer's actual address data.

---

## 10. Additional Address Pagination

Magento's existing pagination/toolbar was preserved.

The following Magento functionality remains controlled by Magento:

* Current page
* Previous page
* Next page
* Page number
* Items per page
* Total number of addresses

The styling was customized without replacing the pagination logic.

This approach was also used previously for the **My Orders** page: style Magento's existing pagination rather than rebuilding the functionality.

---

## 11. Main Problem – Add New Address Button

### Initial Magento Behavior

Magento's default Address Book template places the:

```text
Add New Address
```

button inside the bottom `.actions-toolbar`.

This caused the button to appear too far down the page.

The desired BrewCraft design was:

```text
Address Book                         Add New Address
```

with the title on the left and the button on the right.

---

## 12. Template Override Investigation

The Magento Customer module templates were inspected.

Relevant templates included:

```text
vendor/magento/module-customer/view/frontend/templates/address/book.phtml
vendor/magento/module-customer/view/frontend/templates/address/edit.phtml
vendor/magento/module-customer/view/frontend/templates/address/grid.phtml
```

The important discovery was that the **Additional Address Entries table and Add New Address functionality are associated with Magento's address grid template**.

The existing Magento JavaScript initialization also had to remain intact:

```javascript
"addAddress": "button[role='add-address']",
"addAddressLocation": "..."
```

Therefore, the button could not simply be deleted from the page.

---

## 13. Moving the Add New Address Button

Instead of creating a completely new button without Magento's expected attributes, the existing Magento button structure was preserved.

The button retained:

```html
role="add-address"
```

and:

```html
class="action primary add"
```

This is important because Magento's frontend initialization uses the button's role selector to attach the Add Address behavior.

The button was moved visually into a custom wrapper:

```html
<div class="brewcraft-address-page-actions">
    <button
        type="button"
        role="add-address"
        title="Add New Address"
        class="action primary add">
        <span>Add New Address</span>
    </button>
</div>
```

The wrapper was placed inside the page title area:

```html
<div class="page-title-wrapper">
    <h1 class="page-title">
        <span class="base">Address Book</span>
    </h1>

    <div class="brewcraft-address-page-actions">
        ...
    </div>
</div>
```

This allowed the button to visually belong to the page header instead of remaining in the bottom action area.

---

## 14. Problem Encountered – Page Title Alignment

The first CSS approach used:

```less
display: flex;
justify-content: space-between;
```

on `.page-title-wrapper`.

However, Magento's existing page layout styles were also affecting the title wrapper.

This caused unexpected spacing and positioning.

The result was that the title and button were not behaving as expected.

---

## 15. Solution – Override Magento Page Title Layout

The final solution was to target the Magento page-main title wrapper more specifically:

```less
.customer-address-index {
    .page-main {
        > .page-title-wrapper {
            position: relative;
            width: 100%;
            float: none;
            clear: both;
            text-align: left;
        }
    }
}
```

The page title itself was kept as a normal inline element:

```less
.page-title {
    display: inline-block;
    width: auto;
    margin: 0;
    padding: 0;
}
```

The Add Address button wrapper was then positioned relative to the title wrapper:

```less
.brewcraft-address-page-actions {
    position: absolute;
    top: 0;
    right: 0;
    margin: 0;
}
```

This avoided relying on Magento's inherited flex behavior.

#### Final result

```text
Address Book                              [Add New Address]
```

The title remains on the left and the button stays at the far right of the same header row.

---

## 16. Why `position: absolute` Was Used

The problem was not the button itself.

Magento's `.page-title-wrapper` already had theme/layout rules affecting its width, floating, alignment and spacing.

Using:

```less
justify-content: space-between;
```

was therefore unreliable in this particular page context.

Instead:

```less
.page-title-wrapper {
    position: relative;
}

.brewcraft-address-page-actions {
    position: absolute;
    right: 0;
    top: 0;
}
```

creates a predictable relationship:

* Title stays naturally positioned.
* Button is anchored to the right edge.
* No extra flex spacing is introduced.
* Magento's existing title layout does not determine the gap between the two elements.

This was the final working solution.

---

## 17. Add New Address Button Theme Styling

The button was changed from the generic Magento appearance to BrewCraft's primary button style.

Primary colors:

```less
background: @brewcraft-espresso;
border-color: @brewcraft-espresso;
color: @brewcraft-white;
```

Hover:

```less
background: @brewcraft-coffee;
border-color: @brewcraft-coffee;
```

Other styling:

* 44px minimum height
* BrewCraft border radius
* Inter font
* 14px font size
* 600 font weight
* BrewCraft spacing

This makes the button consistent with the rest of the customer-account theme.

---

## 18. Responsive Behavior

Desktop:

```text
Address Book                         [Add New Address]

Default Addresses

[ Billing Address ]    [ Shipping Address ]
```

Mobile:

```text
Address Book

[ Add New Address ]

Default Addresses

[ Billing Address ]

[ Shipping Address ]
```

The default billing/shipping cards switch from two columns to one column below the mobile breakpoint.

The page title and Add New Address action also switch to a vertical layout on smaller screens.

---

## 19. Important Magento Functionality Preserved

Throughout the customization, Magento's underlying customer address functionality was intentionally preserved.

The following were **not rewritten**:

* Address save process
* Address edit process
* Address delete process
* Default billing assignment
* Default shipping assignment
* Country selection
* Region/state selection
* Telephone validation
* Customer address validation
* Additional address collection
* Address pagination
* Magento's address JavaScript behavior

This keeps the customization limited primarily to the presentation/template layer.

Magento's customer address data includes fields such as street, city, region, postcode, country and telephone, along with default billing/shipping information.

---

## 20. Problems Encountered and Solutions

| Problem                                                    | Cause                                                            | Solution                                                                |
| ---------------------------------------------------------- | ---------------------------------------------------------------- | ----------------------------------------------------------------------- |
| Add Address button appeared in the middle/bottom           | Magento default `.actions-toolbar` placement                     | Move the existing Add Address button into the page-title area           |
| Button stopped behaving correctly when recreated           | Magento JS expects `role="add-address"`                          | Preserve Magento's original button attributes                           |
| Title and button were not aligned                          | Magento's existing page-title styles conflicted with flex layout | Target `.page-main > .page-title-wrapper` specifically                  |
| `justify-content: space-between` produced unwanted spacing | Existing Magento layout rules affected the wrapper               | Stop relying on flex positioning                                        |
| Button and title appeared on separate lines                | Magento title wrapper inherited width/float/layout behavior      | Use `position: relative` on wrapper and absolute positioning for button |
| Billing and shipping addresses appeared vertically         | Default layout / inherited Magento styles                        | Use flex layout on `.block-addresses-default .block-content`            |
| Address cards did not match BrewCraft                      | Default Magento styling                                          | Apply BrewCraft colors, spacing, borders and typography                 |
| Additional address table looked generic                    | Magento default table styling                                    | Customize table header, body, borders and action links                  |
| Delete/Edit actions looked inconsistent                    | Default Magento action styling                                   | Apply BrewCraft action colors                                           |
| Mobile layout could become cramped                         | Desktop two-column layout                                        | Switch address cards to a vertical layout below 767px                   |

---

## 21. Final Address Book Structure

The completed page now follows this structure:

```text
┌───────────────────────────────────────────────────────────────┐
│ Address Book                           [Add New Address]      │
│                                                               │
│ Default Addresses                                             │
│ ────────────────────────────────────────────────────────────  │
│                                                               │
│ ┌────────────────────────┐  ┌──────────────────────────────┐ │
│ │ Default Billing        │  │ Default Shipping             │ │
│ │ Address                │  │ Address                      │ │
│ │                        │  │                              │ │
│ │ Address information   │  │ Address information           │ │
│ │                        │  │                              │ │
│ │ Change Billing Address│  │ Change Shipping Address       │ │
│ └────────────────────────┘  └──────────────────────────────┘ │
│                                                               │
│ Additional Address Entries                                    │
│ ────────────────────────────────────────────────────────────  │
│                                                               │
│ ┌───────────────────────────────────────────────────────────┐ │
│ │ First │ Last │ Street │ City │ Country │ State │ Actions │ │
│ ├───────────────────────────────────────────────────────────┤ │
│ │ ...                                                       │ │
│ └───────────────────────────────────────────────────────────┘ │
│                                                               │
│ Pagination / Items per page                                   │
│                                                               │
│ Back                                                           │
└───────────────────────────────────────────────────────────────┘
```

---

## 22. Files / Areas Customized

The work involved the Magento Customer frontend address templates and the BrewCraft theme styling.

Relevant Magento source templates investigated:

```text
vendor/magento/module-customer/view/frontend/templates/address/book.phtml
vendor/magento/module-customer/view/frontend/templates/address/edit.phtml
vendor/magento/module-customer/view/frontend/templates/address/grid.phtml
```

The BrewCraft theme override should be maintained under:

```text
app/design/frontend/BrewCraft/supply/
```

rather than modifying Magento files directly under:

```text
vendor/magento/
```

This keeps the customization upgrade-safe.

---

## 23. Current Status

#### Completed

* [x] Add New Address page styling
* [x] Address Book page styling
* [x] Default Billing Address card
* [x] Default Shipping Address card
* [x] Billing/Shipping side-by-side layout
* [x] Additional Address Entries table
* [x] Edit/Delete address actions styling
* [x] Address pagination/toolbar styling
* [x] BrewCraft colors applied
* [x] BrewCraft typography applied
* [x] BrewCraft spacing variables reused
* [x] BrewCraft border radius reused
* [x] Add New Address button moved to page header
* [x] Add New Address button styled with BrewCraft theme
* [x] Magento Add Address JavaScript behavior preserved
* [x] Desktop layout fixed
* [x] Mobile layout handled
* [x] Final title/button alignment resolved

#### Deferred

* Sidebar icons
* Account-section icons
* Any future Figma-specific Address Book refinements

Those can be handled separately later without affecting the current Address Book implementation.

---

## 24. Key Lesson for Future Maintenance

The biggest issue encountered on this page was **Magento's existing CSS affecting the page title wrapper**.

When a Magento element does not respond to apparently correct CSS, first inspect:

```text
.page-main
.page-title-wrapper
.page-title
```

and check Magento's inherited:

* `float`
* `width`
* `display`
* `position`
* `margin`
* `text-align`

before adding more flex rules.

For this page, the reliable solution was to establish our own positioning context:

```less
.page-title-wrapper {
    position: relative;
}

.brewcraft-address-page-actions {
    position: absolute;
    top: 0;
    right: 0;
}
```

rather than fighting Magento's existing flex/spacing behavior.

This is worth remembering when customizing other Magento customer-account pages because Magento's Blank/Luma layout styles can continue to affect seemingly simple elements even after custom styles are added.

# 15. BrewCraft Supply -- Request Quote Page Development Log

**Project:** BrewCraft Supply Magento B2B / Business Account\
**Page:** My Quote Requests / Request Quote / Quote Detail\
**Date:** August 27, 2026\
**Technology:** Magento 2, PHTML, LESS/CSS\
**Design Reference:** BrewCraft Figma/design screenshots and existing
BrewCraft design system

------------------------------------------------------------------------

## 1. Objective

The objective of today's work was to style the **Request Quote flow** in
the BrewCraft Supply Magento project so that it matches the approved
BrewCraft visual design.

The work covered:

1.  My Quote Requests listing page.
2.  Quote status badges.
3.  Quote table styling.
4.  Pagination and "Show per page" controls.
5.  Quote detail page opened through the **View** action.
6.  Quote detail header and quote information.
7.  Requested Products table.
8.  Customer message and BrewCraft/admin feedback sections.
9.  Status-dependent result messages such as Converted, Rejected and
    Expired.
10. Quote action buttons.
11. Responsive behavior.
12. Final PHTML structural adjustment for the two optional message
    sections.

------------------------------------------------------------------------

## 2. Design System Used

The Request Quote pages follow the BrewCraft design variables already
established for the project.

### Colors

``` less
@brewcraft-espresso: #2e1e14;
@brewcraft-coffee: #5c3a21;
@brewcraft-cream: #f6f1e8;
@brewcraft-stone: #e6ded3;
@brewcraft-charcoal: #1f1f1f;
@brewcraft-green: #1f6845;
@brewcraft-gold: #c8a66a;
@brewcraft-white: #ffffff;
@brewcraft-muted: #777572;
@brewcraft-border: #ded7ce;
@brewcraft-bg-soft: #faf8f4;
@brewcraft-bg-dark: #181512;
```

### Typography

``` less
@brewcraft-font-heading: 'Poppins', sans-serif;
@brewcraft-font-body: 'Inter', sans-serif;
```

### Spacing

``` less
@bc-space-1: 8px;
@bc-space-2: 16px;
@bc-space-3: 24px;
@bc-space-4: 32px;
@bc-space-5: 40px;
@bc-space-6: 48px;
@bc-space-7: 64px;
@bc-space-8: 72px;
```

### Other design values

``` less
@bc-container-width: 1440px;
@bc-radius-sm: 4px;
@bc-radius-md: 8px;
@bc-radius-lg: 12px;
```

The existing Magento functionality was retained. The implementation
focused on presentation and layout rather than changing the quote
workflow itself.

------------------------------------------------------------------------

## 3. My Quote Requests Page

### 3.1 Initial state

The original Magento quote-request listing page had the required quote
information, but the default Magento styling did not match the BrewCraft
design.

The page needed:

-   BrewCraft typography.
-   BrewCraft colors.
-   Proper table borders.
-   Better spacing.
-   Status badges.
-   Styled action links.
-   Pagination.
-   Per-page selector.
-   Consistent card/table appearance.
-   Removal of the duplicated page title.

------------------------------------------------------------------------

## 4. Quote Table Styling

The quote listing table was styled using the same approach already used
for the Sales Order History table.

The existing order-history LESS established the pattern:

-   Full-width table.
-   BrewCraft border.
-   Rounded outer container.
-   Soft background for the table header.
-   Clean horizontal row separators.
-   BrewCraft typography.
-   Hover treatment.
-   Styled action links.

This same visual language was applied to the quote table so the customer
account pages remain consistent.

### Table columns

The quote table contains:

-   Quote Number
-   Quote Name
-   Status
-   Original Total
-   Proposed Total
-   Submitted
-   Action

The table was given:

``` less
border: 1px solid @brewcraft-border;
border-radius: @bc-radius-md;
background: @brewcraft-white;
overflow: hidden;
```

The table header uses:

``` less
background: @brewcraft-bg-soft;
border-bottom: 1px solid @brewcraft-border;
```

Table rows use the BrewCraft border color for separation and a subtle
soft-background hover state.

------------------------------------------------------------------------

## 5. Quote Statuses

One issue identified during implementation was that the quote list does
not contain only one or two statuses.

The final design needs to support statuses such as:

-   Pending
-   Quoted
-   Rejected
-   Under Review
-   Converted
-   Expired

The status treatment was therefore designed as reusable badges rather
than styling only the original sample statuses.

### Visual approach

-   **Pending** -- warm/gold treatment.
-   **Quoted** -- neutral BrewCraft treatment.
-   **Under Review** -- neutral/information treatment.
-   **Converted** -- green success treatment.
-   **Rejected** -- red/error treatment.
-   **Expired** -- warning treatment.

The Magento status/functionality remains unchanged; only the visual
presentation is customized.

------------------------------------------------------------------------

## 6. Pagination

Pagination was initially missing from the first visual implementation.

The actual page contains:

-   Item count.
-   Page numbers.
-   Current-page indicator.
-   Previous/next controls.
-   "Show" selector.
-   Per-page text.

The pagination styling was based on the existing order-history toolbar
pattern.

The following visual behavior was retained:

``` less
.order-products-toolbar .pages-items .page
```

for page numbers, with:

-   BrewCraft text color.
-   Rounded page controls.
-   Hover state.
-   Distinct current-page state.
-   Styled previous/next controls.
-   BrewCraft border on the per-page selector.

The pagination therefore matches the rest of the customer account area
instead of looking like unstyled Magento controls.

------------------------------------------------------------------------

## 7. Duplicate Page Title Issue

The Quote Requests page initially displayed two title-like headings:

``` text
My Quote Requests
My Quote Requests
```

This was visually incorrect and did not match the Figma.

The solution was to keep only one visible page title and use the
appropriate heading hierarchy for the content underneath.

The final visual hierarchy is:

``` text
My Quote Requests

Review your submitted business quote requests and track their current status.

[Quote table]
```

This makes the page title clear without unnecessary duplication.

------------------------------------------------------------------------

## 8. Quote Detail Page

After completing the listing page, the work moved to the page opened
through the **View** action.

This page contains quote-specific information and is shared by all quote
statuses.

The common portion of the page contains:

-   Quote header.
-   Quote summary.
-   Customer message, when provided.
-   BrewCraft/admin feedback, when provided.
-   Requested Products.
-   Status-dependent result.
-   Action buttons.

The important requirement was that the page layout should remain stable
regardless of the quote status.

------------------------------------------------------------------------

## 9. Quote Detail Page -- Common Layout

The detail page was designed so that the main sections follow a
predictable vertical flow:

``` text
Quote Header
       ↓
Quote Summary
       ↓
Customer / BrewCraft Messages
       ↓
Requested Products
       ↓
Status Result
       ↓
Actions
```

This was important because the content below Requested Products changes
depending on the quote state.

For example:

-   Converted quotes can display an order conversion message and a View
    Order button.
-   Rejected quotes can display rejection feedback.
-   Expired quotes can display an expiration message.
-   Other states can have different actions.

The common page structure should not move unexpectedly when one of these
blocks appears or disappears.

------------------------------------------------------------------------

## 10. Quote Detail Title / Header

The original title presentation made the quote number and quote name
compete visually.

A long quote number such as:

``` text
BCQ-20260827-80125750
```

should not visually behave like the main page heading.

The hierarchy was therefore adjusted so that:

-   Quote name is easier to read as the main quote identity.
-   Quote number is treated as a supporting identifier.
-   Status is visually separated from the title.
-   The "BrewCraft Business Quote" label acts as supporting context.

This gives the header a cleaner hierarchy and avoids making long
generated quote numbers dominate the page.

------------------------------------------------------------------------

## 11. Customer Message and BrewCraft Message

## 11.1 Original problem

The PHTML originally rendered the two optional sections independently:

``` text
Your Message
```

and

``` text
Message from BrewCraft
```

as separate sibling sections.

Initially, the layout was attempted entirely with CSS by applying a grid
to the larger quote-detail container.

This caused a major layout problem.

Because the two message blocks were siblings of:

-   Requested Products
-   Status message
-   Action toolbar

the grid also affected those sections.

This resulted in problems such as:

-   Requested Products being squeezed into a column.
-   Headings becoming compressed.
-   Text wrapping vertically one character at a time.
-   Status messages moving into incorrect positions.
-   Large empty areas appearing.
-   The page becoming visually broken.

This was the most important layout issue encountered during today's
work.

------------------------------------------------------------------------

## 12. Root Cause

The problem was not the two-column concept itself.

The problem was the **HTML structure**.

The DOM effectively looked like:

``` text
Quote Detail
├── Header
├── Summary
├── Your Message
├── Message from BrewCraft
├── Requested Products
├── Status Message
└── Actions
```

Applying a grid to the entire parent meant all of these children became
grid items.

Therefore CSS had to know the exact row and column position of every
section.

That approach was fragile and unnecessarily complicated.

------------------------------------------------------------------------

## 13. Final Solution -- PHTML Wrapper

Instead of forcing the complete quote-detail container into a grid, the
PHTML was changed slightly.

A new wrapper was introduced around only the two optional message
sections:

``` text
Quote Detail
├── Header
├── Summary
├── Messages
│   ├── Your Message
│   └── Message from BrewCraft
├── Requested Products
├── Status Message
└── Actions
```

The wrapper is:

``` html
<div class="brewcraft-quote-account-view__messages">
```

The existing conditional sections remain inside it.

------------------------------------------------------------------------

## 14. Why the Wrapper Solution Is Better

The wrapper gives CSS a clear semantic target.

Instead of saying:

> Make the entire quote detail page a grid and manually position
> everything.

the implementation now says:

> These two sections belong together. Arrange them side by side.

This makes the layout much safer.

The Requested Products section remains completely outside the grid.

The status message remains completely outside the grid.

The buttons remain completely outside the grid.

Therefore, styling the messages cannot accidentally break the rest of
the page.

------------------------------------------------------------------------

## 15. Message Layout Rules

When both messages exist:

``` text
┌─────────────────────────┐  ┌─────────────────────────┐
│ Your Message            │  │ Message from BrewCraft  │
│                         │  │                         │
│ Customer message...     │  │ Admin feedback...       │
└─────────────────────────┘  └─────────────────────────┘
```

The wrapper uses:

``` less
&__messages {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: @bc-space-3;
}
```

When only one message exists, the single child uses the complete
available width:

``` less
&__messages > :only-child {
    grid-column: 1 / -1;
}
```

This supports both cases without requiring complicated selectors.

------------------------------------------------------------------------

## 16. Optional Message Behavior

Both messages are optional.

#### Case 1 -- Both exist

``` text
Your Message        | Message from BrewCraft
```

### Case 2 -- Only customer message exists

``` text
Your Message
(full width)
```

### Case 3 -- Only BrewCraft feedback exists

``` text
Message from BrewCraft
(full width)
```

### Case 4 -- Neither exists

The wrapper is not rendered.

The page proceeds directly to:

``` text
Requested Products
```

This prevents empty message containers from creating unnecessary
spacing.

------------------------------------------------------------------------

## 17. Message Card Styling

The two message blocks use the BrewCraft card language.

### Customer message

Uses the standard soft BrewCraft background:

``` less
background: @brewcraft-bg-soft;
border: 1px solid @brewcraft-border;
border-radius: @bc-radius-md;
```

#### BrewCraft feedback

Uses the warmer cream background:

``` less
background: @brewcraft-cream;
border: 1px solid @brewcraft-stone;
border-radius: @bc-radius-md;
```

The headings use BrewCraft typography and a divider below the heading.

This produces a clear distinction between:

-   The message submitted by the customer.
-   The feedback provided by the BrewCraft team.

------------------------------------------------------------------------

## 18. Responsive Message Layout

On desktop:

``` text
Your Message | BrewCraft Message
```

On smaller screens:

``` text
Your Message
─────────────

BrewCraft Message
──────────────────
```

The message wrapper changes to one column at the mobile breakpoint:

``` less
&__messages {
    grid-template-columns: 1fr;
}
```

This prevents narrow message cards and excessive text wrapping.

------------------------------------------------------------------------

## 19. Requested Products Table

The Requested Products section contains a relatively wide data table.

Columns include information such as:

-   Product
-   SKU
-   Quantity
-   Original Unit Price
-   Expected Unit Price
-   Proposed Unit Price
-   Original Total
-   Expected/Proposed Total

Because the table can exceed the available content width, horizontal
scrolling is retained.

This is preferable to shrinking every column until the product
information becomes unreadable.

The table follows the same BrewCraft table treatment used on the
quote-list page:

-   White background.
-   Soft header.
-   BrewCraft borders.
-   Consistent typography.
-   Proper cell padding.
-   Rounded outer container.
-   Horizontal overflow where required.

------------------------------------------------------------------------

## 20. Status Result Messages

Another requirement was to handle the different results shown after the
Requested Products section.

The visual treatment needs to distinguish between different outcomes.

### Converted

``` text
✓ This quotation was converted to order #000000051.
```

The converted state uses the BrewCraft green:

``` less
@brewcraft-green
```

with a soft green background.

### Rejected

Rejected messages use a soft red/error treatment so the customer can
immediately understand that the quote was not accepted.

### Expired

Expired messages use a warning/gold treatment.

The status message remains a separate block below Requested Products and
is not part of the two-column message layout.

------------------------------------------------------------------------

## 21. Problem With Default Magento Message Icon

The default Magento message styling could introduce a large/default icon
and positioning that did not match the BrewCraft design.

This created alignment problems where the icon and message text did not
look like a single component.

The styling therefore needed to normalize:

-   Icon size.
-   Icon position.
-   Text padding.
-   Vertical alignment.
-   Background.
-   Border.
-   Border radius.

The result is a compact BrewCraft status component rather than the
default Magento notification appearance.

------------------------------------------------------------------------

## 22. Important Implementation Decision

A key lesson from the implementation was:

> **Do not use the main quote-detail container as the grid for the
> optional messages.**

The correct level for the two-column layout is:

``` text
brewcraft-quote-account-view__messages
```

not:

``` text
brewcraft-quote-account-view
```

This keeps the CSS responsibility limited to the actual components that
need the layout.

------------------------------------------------------------------------

## 23. What Was Not Changed

The following functionality was intentionally left untouched:

-   Quote creation.
-   Quote submission.
-   Quote status calculation.
-   Quote conversion.
-   Admin feedback logic.
-   Customer message storage.
-   Requested product data.
-   Magento pagination functionality.
-   View quote routing.
-   Order conversion behavior.
-   Status-dependent business logic.

The changes were primarily presentation/layout changes, with one small
PHTML structural improvement to provide a proper wrapper for the two
optional message components.

------------------------------------------------------------------------

## 24. Final Request Quote Page Structure

The final intended structure is:

``` text
My Quote Requests
│
├── Quote listing table
│   ├── Quote Number
│   ├── Quote Name
│   ├── Status
│   ├── Original Total
│   ├── Proposed Total
│   ├── Submitted
│   └── View
│
└── Pagination
```

For an individual quote:

``` text
Quote Header
│
├── Quote Name
├── Quote Number
└── Status
│
├── Quote Summary
│
├── Messages
│   ├── Your Message       ┐
│   └── BrewCraft Message  ┘ side-by-side when both exist
│
├── Requested Products
│
├── Status Result
│
└── Actions
    ├── View Order (when applicable)
    ├── Back to My Quote Requests
    └── Continue Shopping
```

------------------------------------------------------------------------

## 25. Testing / Scenarios Considered

  Scenario                            Expected Result
  ----------------------------------- ---------------------------------------------------
  Pending quote                       Pending status styling
  Quoted quote                        Quoted status styling
  Under Review quote                  Review status styling
  Converted quote                     Green converted message + applicable order action
  Rejected quote                      Rejected/error result styling
  Expired quote                       Warning/expired result styling
  Customer message + admin feedback   Two message cards side-by-side
  Customer message only               Full-width customer message
  Admin feedback only                 Full-width BrewCraft message
  No messages                         No empty message wrapper
  Wide product table                  Horizontal scrolling instead of broken columns
  Multiple quote pages                Pagination remains available
  Mobile                              Message cards stack vertically

------------------------------------------------------------------------

## 26. Final Outcome

The Request Quote area now follows the BrewCraft design language instead
of the default Magento presentation.

The major improvements completed today were:

-   BrewCraft styling applied to the Quote Requests page.
-   Quote table aligned with the existing Order History table pattern.
-   All relevant quote statuses accounted for.
-   Pagination styled.
-   Duplicate page title removed.
-   Quote detail page styled.
-   Quote title hierarchy improved.
-   Requested Products section preserved as a stable full-width section.
-   Status-dependent result messages styled.
-   Customer and BrewCraft messages separated visually.
-   Customer/BrewCraft messages made side-by-side when both exist.
-   Single message automatically uses full width.
-   Responsive behavior added for the message layout.
-   HTML/PHTML structure improved by introducing a dedicated message
    wrapper.
-   Avoided using a page-wide grid that could interfere with other
    quote-detail sections.

------------------------------------------------------------------------

## 27. Key Technical Takeaway

The most important technical change was not simply a CSS change.

The final implementation uses a small amount of structural markup to
represent the actual relationship between the two message components:

``` html
<div class="brewcraft-quote-account-view__messages">

    <!-- Your Message -->

    <!-- Message from BrewCraft -->

</div>
```

This allows LESS to remain simple, predictable and maintainable.

The final principle used for the page is:

> **Use PHTML structure to define component relationships, and use LESS
> to define how those components look and respond.**

This prevents layout rules for one component from unintentionally
affecting unrelated Magento components.

# 16. BrewCraft Supply — Wishlist

## Feature: Wishlist Page UI & Product Image Styling

- **Status:** Completed
- **Platform:** Magento 2
- **Area:** Customer Wishlist / Frontend Theme

---

## 1. Magento Wishlist Structure Reviewed

Magento's default Wishlist implementation was reviewed to understand the actual rendering structure before applying the styling.

**Core Magento files inspected:**

- `vendor/magento/module-wishlist/view/frontend/layout/wishlist_index_index.xml`
- `vendor/magento/module-wishlist/view/frontend/templates/item/list.phtml`
- `vendor/magento/module-wishlist/view/frontend/templates/item/column/cart.phtml`
- `vendor/magento/module-wishlist/view/frontend/templates/item/column/edit.phtml`
- `vendor/magento/module-wishlist/view/frontend/templates/item/column/actions.phtml`

> **Important**
> These Magento `vendor/` files were **not modified directly**.

They were inspected to understand:

- Wishlist product grid structure
- Product image rendering
- Product information
- Quantity field
- Add to Cart button
- Edit action
- Remove action
- Wishlist control buttons
- Wishlist pagination toolbar
- Hover details panel

This keeps Magento core files untouched and allows the customization to remain theme-level.

---

## 2. Product Image Configuration

Magento product image rendering was customized through the theme's:

```
app/design/frontend/<Vendor>/<Theme>/Magento_Catalog/etc/view.xml
```

The image configurations were adjusted for the required product-image behavior.

### PDP image configuration

Added/updated:

```xml
<image id="product_page_image_medium" type="image">
    <width>700</width>
    <height>700</height>
    <constrain>false</constrain>
    <aspect_ratio>true</aspect_ratio>
    <frame>false</frame>
    <transparency>true</transparency>
</image>

<image id="product_page_image_large" type="image">
    <width>1200</width>
    <height>1200</height>
    <constrain>false</constrain>
    <aspect_ratio>true</aspect_ratio>
    <frame>false</frame>
    <transparency>true</transparency>
</image>
```

### Related product image

Configured:

```xml
<image id="related_products_list" type="small_image">
    <width>320</width>
    <height>320</height>
    <constrain>false</constrain>
    <aspect_ratio>true</aspect_ratio>
    <frame>false</frame>
    <transparency>true</transparency>
</image>
```

### Additional configuration

```xml
<var name="product_image_white_borders">0</var>
```

This was used to prevent Magento's image processing from adding unwanted white borders around product images.

---

## 3. Wishlist Product Grid

The Wishlist was changed from the default Magento layout into a structured 4-column product grid.

**Main LESS customization:**

```
app/design/frontend/<Vendor>/<Theme>/web/css/source/_module.less
```

The Wishlist styles were scoped to the Wishlist page rather than applying globally.

**Main structure:**

```less
.customer-wishlist-index {

    .products-grid.wishlist {

        .product-items {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            column-gap: 24px;
            row-gap: 40px;
        }
    }
}
```

**Result**

- 4 products per row on desktop
- Consistent column spacing
- Consistent row spacing
- Product items no longer affected by Magento's default grid widths
- Improved alignment across different product images

---

## 4. Product Card Styling

Each Wishlist product was converted into a BrewCraft-style card.

The styling included:

- White card background
- BrewCraft border color
- Rounded corners
- Consistent spacing
- Hover elevation
- Smooth transition
- Proper z-index handling

The card remains `overflow: visible` because Magento's additional Wishlist actions need to appear outside the normal card content.

---

## 5. Product Image Normalization

One of the main problems was that product images had different original dimensions.

For example:

- Square product image
- Landscape image
- Portrait image

Instead of modifying the original uploaded images, the Wishlist image container was normalized.

The important styling was:

```css
.product-item-photo {
    width: 100% !important;
    height: 300px !important;
}

.product-image-container {
    width: 100% !important;
    height: 300px !important;
}

.product-image-wrapper {
    display: flex !important;
    align-items: center;
    justify-content: center;

    width: 100% !important;
    height: 300px !important;
}

.product-image-photo {
    width: 100% !important;
    height: 100% !important;

    object-fit: contain !important;
    object-position: center center;

    background: transparent !important;
}
```

**Why `contain` was used**

The original product image should remain completely visible.

So:

```css
object-fit: contain;
```

was preferred instead of `cover`. This prevents products from being unnecessarily cropped.

---

## 6. Product Information Styling

Product names and prices were customized to follow the BrewCraft design system.

**Product name**

Configured:

- BrewCraft body font
- Espresso text color
- Consistent font size
- Two-line limit
- Consistent minimum height

**Price**

Configured:

- BrewCraft espresso color
- Stronger font weight for current price
- Muted styling for regular/old price
- Consistent spacing

---

## 7. Wishlist Hover Details Panel

The biggest UI issue was the Magento hover area appearing as a separate white block/card underneath the product.

The solution was to treat:

```
.product-item-info
        +
.product-item-inner
```

as one visual component.

The hover panel was positioned relative to the product card:

```css
.product-item-info {
    position: relative;
    overflow: visible;
}
```

and:

```css
.product-item-inner {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;

    width: 100%;
}
```

The panel was then styled to visually continue the same BrewCraft card rather than looking like an unrelated popup.

**Hover behavior**

```css
.product-item-info:hover {
    .product-item-inner {
        visibility: visible;
        opacity: 1;
        transform: translateY(0);
    }
}
```

with:

```css
visibility: hidden;
opacity: 0;
transform: translateY(10px);
```

as the initial state.

---

## 8. Quantity Field

The Wishlist quantity field was redesigned.

Changes included:

- Consistent width
- Fixed height
- BrewCraft border
- Rounded corners
- Centered quantity
- Focus state
- Consistent spacing with Add to Cart

Example:

```css
input.qty {
    width: 58px;
    height: 42px;
    padding: 0 8px;
    text-align: center;

    border: 1px solid #d8c8bb;
    border-radius: 7px;
    background: #fff;
}
```

---

## 9. Add to Cart Button

The Magento default Add to Cart appearance was replaced with the BrewCraft-style button.

**Normal state**

- Espresso background
- White text
- Rounded corners
- Stronger font weight
- Proper button height
- Centered content

**Hover state**

- Lighter coffee brown
- Slight upward movement
- Subtle shadow

```css
.action.tocart.primary {
    min-width: 150px;
    height: 44px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    background: #352117;
    border: 1px solid #352117;
    border-radius: 7px;

    color: #fff;
    font-size: 15px;
    font-weight: 600;

    transition:
        background .2s ease,
        border-color .2s ease,
        transform .2s ease,
        box-shadow .2s ease;
}

.action.tocart.primary:hover {
    background: #4b2d1e;
    border-color: #4b2d1e;

    transform: translateY(-1px);

    box-shadow:
        0 5px 12px rgba(53, 33, 23, .18);
}
```

---

## 10. Edit / Remove Actions

The bottom action area of each Wishlist product was also redesigned.

Magento's default text actions were converted visually into icon-style actions.

**Styled:**

- Edit
- Remove

with:

- Consistent icon size
- BrewCraft muted color
- Hover background
- Hover color change
- Proper spacing
- Divider above the action area

The action area was deliberately kept separate from the Add to Cart button so the two different Magento `.product-item-actions` sections would not interfere with each other.

---

## 11. Wishlist Control Buttons

The buttons below the product grid were styled:

- Update Wish List
- Share Wish List
- Add All to Cart

They received:

- BrewCraft borders
- Rounded corners
- Consistent height
- Proper padding
- Espresso hover state
- White hover text
- Smooth transitions

---

## 12. Wishlist Pagination Toolbar

The bottom Wishlist toolbar was customized.

It contains:

- `Items 1 to 10 of 11 total`
- `1   2   >`
- `Show [10] per page`

Styling was added for:

- Item count
- Page numbers
- Current page
- Next button
- "Show" label
- Per-page select box
- Toolbar spacing
- Top border

The current page uses the BrewCraft espresso treatment:

```
[ 1 ]
```

while inactive pages remain light.

---

## 13. Top Toolbar Handling

The Wishlist contains more than one toolbar in Magento's default structure.

The requirement was to:

> Remove only the unnecessary top toolbar while keeping the bottom pagination toolbar.

Therefore, the selector was based on the actual Magento DOM relationship rather than simply hiding every `.wishlist-toolbar`.

The bottom toolbar remains available for:

- Pagination
- Item count
- Items-per-page selection

---

## 14. Final Styling Approach

The important part of this implementation is that we did not modify Magento's vendor files.

The approach was:

```
Magento core
    ↓
Inspect actual Wishlist structure
    ↓
Theme-level Magento_Catalog image configuration
    ↓
Theme-level Wishlist LESS/CSS
    ↓
Scoped selectors
    ↓
BrewCraft styling
```

This means the Magento core Wishlist functionality remains intact while the visual presentation is controlled by the BrewCraft theme.

---

## Files / Areas Changed

| Area | File / Location | Purpose |
|---|---|---|
| Product image configuration | `Magento_Catalog/etc/view.xml` | Image dimensions, transparency, white-border handling |
| Wishlist styling | `web/css/source/_module.less` | Wishlist grid, cards, hover panel, buttons, actions, toolbar |
| Wishlist layout | Magento Wishlist layout structure | Inspected to understand actual block hierarchy |
| Wishlist templates | Magento Wishlist templates | Inspected to identify actual HTML/action hierarchy |
| Magento vendor files | `vendor/magento/module-wishlist/...` | Inspected only — not modified |

### Core Wishlist files inspected

- `Magento_Wishlist/view/frontend/layout/wishlist_index_index.xml`
- `Magento_Wishlist/view/frontend/templates/item/list.phtml`
- `Magento_Wishlist/view/frontend/templates/item/column/cart.phtml`
- `Magento_Wishlist/view/frontend/templates/item/column/edit.phtml`
- `Magento_Wishlist/view/frontend/templates/item/column/actions.phtml`

---

## Final Result

The Wishlist page now has:

- ✅ BrewCraft 4-column product grid
- ✅ Consistent product-card dimensions
- ✅ Consistent image area
- ✅ Transparent product-image handling
- ✅ No unwanted Magento white image borders
- ✅ Product names/prices styled
- ✅ BrewCraft hover effect
- ✅ Hover details visually connected to the same product card
- ✅ Styled quantity field
- ✅ Styled Add to Cart button
- ✅ Styled Edit/Remove actions
- ✅ Styled Wishlist control buttons
- ✅ Styled pagination
- ✅ Styled items-per-page selector
- ✅ Top unnecessary toolbar hidden
- ✅ Bottom pagination toolbar retained
- ✅ Magento core/vendor files left untouched

**Status:** Wishlist UI customization completed.

# 17 Product review, news letter, account info
## Customer Account Pages Styling

**Project:** BrewCraft Supply Magento Store  
**Scope:** Customer account pages  
**Pages covered:**
- My Product Reviews
- Review Details
- Newsletter Subscription
- Account Information / Edit Account Information

---

## 1. Overview

This development work focused on applying the BrewCraft Supply design system to Magento customer-account pages while preserving Magento's existing functionality, HTML behavior, validation, JavaScript interactions, and responsive layout wherever possible.

The styling was implemented using the existing BrewCraft LESS variables and Magento's existing CSS classes/DOM structure.

The main objective was to make the pages visually consistent with the BrewCraft Figma/design direction without unnecessarily changing Magento's underlying page structure.

## BrewCraft design variables used

The styling follows the existing BrewCraft design system:

- `@brewcraft-espresso`
- `@brewcraft-coffee`
- `@brewcraft-cream`
- `@brewcraft-stone`
- `@brewcraft-charcoal`
- `@brewcraft-green`
- `@brewcraft-gold`
- `@brewcraft-white`
- `@brewcraft-muted`
- `@brewcraft-border`
- `@brewcraft-bg-soft`
- `@brewcraft-bg-dark`
- `@brewcraft-font-heading`
- `@brewcraft-font-body`

Spacing is based on the existing 8px spacing system:

- `@bc-space-1` – 8px
- `@bc-space-2` – 16px
- `@bc-space-3` – 24px
- `@bc-space-4` – 32px
- `@bc-space-5` – 40px
- `@bc-space-6` – 48px
- `@bc-space-7` – 64px
- `@bc-space-8` – 72px

Border radii use:

- `@bc-radius-sm`
- `@bc-radius-md`
- `@bc-radius-lg`

---

## 2. My Product Reviews

## 2.1 Page purpose

The My Product Reviews page displays reviews submitted by the customer in a Magento table.

The existing Magento table contains:

- Created date
- Product name
- Rating
- Review
- Actions / See Details

The page also contains Magento's toolbar for:

- Item count
- Records per page
- Pagination when multiple pages exist
- Back navigation

---

## 2.2 Styling approach

The existing Magento table structure was retained.

The styling was aligned with the table styling already established for the BrewCraft Order History and Quote Request pages.

## Table container

The table was presented as a BrewCraft-style card/table container using:

- `@brewcraft-border`
- `@brewcraft-white`
- `@bc-radius-md`

The table overflow is controlled so the rounded card appearance is maintained.

## Table header

The table header uses:

- `@brewcraft-bg-soft`
- BrewCraft espresso text
- 13px body typography
- Medium/semi-bold header weight
- Bottom border using `@brewcraft-border`

This gives the table the same visual language as the previously styled Magento tables.

## Table rows

Rows use:

- White background
- Subtle horizontal separators
- BrewCraft stone/border colors
- Hover treatment using `@brewcraft-bg-soft`

This keeps the table clean while providing visual feedback when hovering over a record.

## Table cells

Table content uses the BrewCraft body font and approximately 14px body text.

Important values such as product names and actions receive stronger typography.

The date is treated as secondary information using the muted BrewCraft color.

---

## 2.3 Rating styling

Magento's rating structure was preserved.

The existing rating-result element controls the actual rating percentage, so the styling only changes its visual presentation.

The rating stars were increased in size after review of the rendered page because the initial stars appeared too small.

The rating functionality itself was not changed.

The percentage/width generated by Magento remains responsible for determining how much of the rating is filled.

---

## 2.4 Review pagination / records per page

The Magento review toolbar was styled consistently with the previously established pagination pattern.

The styling covers:

- Item count
- Page numbers
- Current page
- Previous/next controls
- "Show" label
- Records-per-page dropdown
- "per page" text

The existing Magento pagination behavior remains unchanged.

Only its visual appearance was customized.

---

## 3. Review Details

## 3.1 Page purpose

Clicking **See Details** from My Product Reviews opens the Review Details page.

The original Magento structure contained:

- Product image
- Product name
- Review heading
- Rating
- Review title
- Review content
- Submitted date
- Back to My Reviews action

---

## 3.2 Layout improvement

The Review Details page was visually reorganized so that the product information and review information are easier to understand.

The desired structure became:

```text
┌─────────────────────────────────────────────────────────────┐
│ Review Details                                               │
│                                                             │
│  Product Image          Product / Review Information         │
│  ┌──────────────┐       Product Name                         │
│  │              │       Rating                               │
│  │ Product      │       Your Review                          │
│  │ Image        │       Review Title                         │
│  │              │       Review Content                       │
│  └──────────────┘       Submitted Date                       │
└─────────────────────────────────────────────────────────────┘
```

The existing product and review content was preserved.

The structure was adjusted where necessary so that the page could achieve the intended visual relationship between the product image and the review.

---

## 3.3 Review styling

The review area was styled using the BrewCraft design system.

## Review title

The customer's review title is displayed with stronger typography so that it is visually distinguishable from the review body.

## Review content

The review body uses BrewCraft body typography and charcoal text.

## Submitted date

The submitted date uses the muted color to make it secondary to the review itself.

## Rating

The rating stars were increased in size after checking the rendered result.

The rating percentage continues to come from Magento.

Only the presentation was changed.

---

## 3.4 Back navigation

The **Back to My Reviews** action was styled to match the BrewCraft secondary/text-action treatment.

Magento's existing link and navigation behavior was retained.

---

## 4. Newsletter Subscription

## 4.1 Page purpose

The Newsletter Subscription page contains Magento's newsletter management form.

The existing structure contains:

- Subscription option
- General Subscription checkbox
- Save button
- Back button

---

## 4.2 Styling approach

The Magento newsletter form was styled to match the BrewCraft customer-account pages.

## Subscription fieldset

The newsletter section was given the BrewCraft card treatment using:

- White background
- BrewCraft border
- Rounded corners
- BrewCraft spacing
- BrewCraft heading typography

## Checkbox

The General Subscription checkbox was styled to be visually consistent with the other customer-account form controls.

The actual checkbox functionality remains Magento-controlled.

---

## 4.3 Save button issue

The first implementation did not correctly style the Magento Save button.

The reason was Magento's existing action/button CSS applying stronger/default styles.

The final styling used more specific selectors and `!important` where required to override Magento's theme rules.

The Save button now follows the BrewCraft primary-button appearance:

- Espresso background
- White text
- BrewCraft border
- Rounded corners
- Appropriate padding
- Hover state

---

## 4.4 Back button issue

The Back button was initially not visually appearing correctly.

The Magento action styles were overriding the custom styles.

The final solution explicitly targeted:

```text
.actions-toolbar
.secondary
.action.back
```

and reset Magento's conflicting properties.

The Back action now uses the BrewCraft secondary-button appearance.

---

## 5. Account Information / Edit Account Information

## 5.1 Page purpose

The Edit Account Information page is Magento's customer account information form.

The page contains:

## Account Information

- First Name
- Last Name
- Change Email
- Change Password
- Allow remote shopping assistance

## Change Email and Password

- Email
- Current Password
- New Password
- Password strength indicator
- Confirm New Password
- Show Password

## Actions

- Save
- Go back

---

## 5.2 Important implementation decision

Magento already provides the required desktop positioning for the two main account fieldsets.

Therefore, the styling intentionally **did not introduce a new desktop grid/flex layout for the fieldsets**.

This was important because forcing a new layout caused positioning problems during earlier styling attempts.

The final approach was:

> Keep Magento's existing layout and style the components around it.

This reduces the chance of breaking Magento's form behavior.

---

## 5.3 Fieldset styling

The existing fieldsets were styled as BrewCraft cards.

The styling includes:

- White background
- `@brewcraft-border`
- `@bc-radius-md`
- BrewCraft spacing
- BrewCraft heading font
- Bottom divider below the fieldset legend

Magento's form structure and validation remain unchanged.

---

## 5.4 Form fields

Input fields were styled consistently across:

- Text inputs
- Email inputs
- Password inputs

The fields use:

- 1px BrewCraft border
- Small BrewCraft radius
- White background
- Charcoal text
- 14px body font
- Appropriate internal padding

## Hover state

The border becomes slightly stronger when the field is hovered.

## Focus state

The focused input uses the BrewCraft coffee accent and a subtle focus ring.

This provides a clear visual indication without changing Magento validation behavior.

---

## 5.5 Required fields

Magento's required-field behavior was preserved.

The visual required marker was styled to match the BrewCraft color system.

---

## 5.6 Checkbox controls

Checkboxes were styled for:

- Change Email
- Change Password
- Allow remote shopping assistance
- Show Password

The existing Magento checkbox behavior remains unchanged.

---

## 5.7 Remote shopping assistance tooltip

The Magento field tooltip was given a BrewCraft treatment.

The tooltip uses:

- BrewCraft white background
- BrewCraft border
- Small rounded corners
- Muted secondary text
- Subtle shadow

The tooltip's functionality remains Magento-controlled.

---

## 5.8 Password strength indicator

Magento's existing password strength meter was preserved.

The visual container was styled using the BrewCraft design system.

The actual password-strength calculation is still handled by Magento/JavaScript.

---

## 6. Problems Encountered and Resolutions

## Issue 1 – Magento default CSS overriding custom styles

Several Magento customer-account components have default theme rules with strong selectors.

## Symptoms

- Buttons did not receive the intended styling.
- Back buttons were not visible or appeared incorrectly.
- Default spacing/positioning returned unexpectedly.

## Resolution

More specific selectors were used for the affected Magento elements.

`!important` was used only where Magento's default CSS made it necessary.

---

## Issue 2 – Extra empty Account Information card

The Edit Account Information page contained an additional Magento-generated fieldset:

```html
<fieldset class="fieldset additional_info">
```

It contained no visible content but still received the custom fieldset card styling.

## Symptoms

An empty bordered card appeared underneath the actual account form.

Initially removing the border was considered, but that would have affected the legitimate fieldsets as well.

## Final resolution

The entire additional fieldset was hidden:

```less
.column.main form.form-edit-account fieldset.additional_info {
    display: none !important;
}
```

This removes the unwanted card completely without changing the styling of the real Account Information or Password fieldsets.

---

## Issue 3 – Save and Go back buttons were separated

Magento's default action toolbar applies its own float/width/margin rules.

## Symptoms

The buttons appeared far apart:

```text
[ Save ]                                      [ Go back ]
```

instead of next to each other.

## Resolution

The action toolbar was explicitly reset:

```less
.column.main form.form-edit-account > .actions-toolbar {
    display: flex !important;
    align-items: center !important;
    justify-content: flex-start !important;
    gap: @bc-space-2 !important;
}
```

The Magento `.primary` and `.secondary` wrappers were also reset:

```less
float: none !important;
width: auto !important;
margin: 0 !important;
```

The final result is:

```text
[ Save ]  [ Go back ]
```

with a small consistent gap.

---

## Issue 4 – Review rating stars were too small

The Review Details page initially displayed the rating stars smaller than desired.

## Resolution

The rating presentation was increased while keeping Magento's rating percentage logic untouched.

This means the visual size changed, but the actual rating value continues to be controlled by Magento.

---

## Issue 5 – Preserving Magento functionality

A major consideration throughout the work was avoiding unnecessary changes to Magento's functional structure.

The styling work therefore focused on:

- Existing classes
- Existing fieldsets
- Existing table structure
- Existing action links/buttons
- Existing pagination
- Existing validation
- Existing JavaScript-generated values

Where structural changes were not necessary, the HTML was left untouched.

---

## 7. Common Styling Pattern Established

The three customer-account areas now follow a consistent BrewCraft visual language.

## Cards

```text
White background
BrewCraft border
8px radius
32px-style internal spacing where appropriate
```

## Typography

Headings:

```text
@brewcraft-font-heading
```

Body:

```text
@brewcraft-font-body
```

## Primary actions

```text
@brewcraft-espresso
background
@brewcraft-white
text
```

## Secondary actions

```text
@brewcraft-white
background
@brewcraft-espresso
text
@brewcraft-border
border
```

## Inputs

```text
@brewcraft-white
background
@brewcraft-border
border
@brewcraft-charcoal
text
```

## Secondary information

```text
@brewcraft-muted
```

## Soft surfaces

```text
@brewcraft-bg-soft
@brewcraft-cream
```

---

## 8. Testing / Verification

The pages were visually checked in the Magento storefront using browser DevTools.

The following were specifically verified during development:

- Customer review table appearance
- Review pagination / records-per-page controls
- Review Details product/review presentation
- Rating star size
- Newsletter checkbox/form appearance
- Newsletter Save button
- Newsletter Back button
- Account Information fieldsets
- Password fieldset
- Extra empty Magento fieldset
- Save/Go back action alignment
- Magento-generated form elements

---

## 9. Final Result

The customer-account pages now use the BrewCraft design system while retaining Magento's existing functionality.

## Completed

- [x] My Product Reviews styling
- [x] Product Reviews table styling
- [x] Review pagination / records-per-page styling
- [x] Review Details styling
- [x] Product image/review presentation
- [x] Rating star sizing
- [x] Newsletter Subscription styling
- [x] Newsletter checkbox styling
- [x] Newsletter Save button
- [x] Newsletter Back button
- [x] Account Information styling
- [x] Account form inputs
- [x] Account checkboxes
- [x] Password strength styling
- [x] Tooltip styling
- [x] Empty `additional_info` fieldset removal
- [x] Save/Go back button alignment

---

## 10. Key Development Principle

The main approach used for these pages was:

> **Style Magento's existing structure first; change the HTML structure only when the existing structure prevents the required design.**

This helped keep the BrewCraft styling consistent while minimizing the risk of breaking Magento's customer-account functionality.

# 18. BrewCraft Supply — My Account page - recent review and order status

**Date:** 02 September 2026

## 1. Recent Orders — My Account Dashboard

### Work completed

* Identified the Recent Orders template being used by the Magento Order Cancellation UI module.
* Updated the Recent Orders PHTML to customize the order table.
* Changed the Recent Orders heading for testing/customization.
* Added custom status badge markup to the order status column.
* Added dynamic status classes based on the order status label.

### Status implementation

The PHTML now generates classes such as:

```text
order-status-badge
order-status-pending
order-status-processing
order-status-complete
order-status-cancelled
```

The status class is generated dynamically using:

```php
$status = strtolower($_order->getStatusLabel());
$statusClass = preg_replace('/[^a-z0-9]+/', '-', $status);
```

This allows CSS to style different order statuses independently.

---

## 2. Magento Template Override

### Issue identified

Initially, the override directory was created incorrectly as:

```text
Magento_Order_Cancellation_Ui
```

The actual Magento module name is:

```text
Magento_OrderCancellationUi
```

Therefore, the correct theme override path is:

```text
app/design/frontend/BrewCraft/supply/
└── Magento_OrderCancellationUi/
    └── templates/
        └── order/
            └── recent.phtml
```

The original vendor template is:

```text
vendor/magento/module-order-cancellation-ui/view/frontend/templates/order/recent.phtml
```

The vendor file should **not** be modified directly.

### Result

After correcting the module directory name, the custom PHTML changes were successfully picked up and the status badges became visible.

---

## 3. My Orders / Order History Page

### Work completed

Inspected the HTML structure of the **My Orders** page and identified the existing Magento elements:

* `.orders-history`
* `#my-orders-table`
* `.col.id`
* `.col.date`
* `.col.total`
* `.col.status`
* `.col.actions`
* `.order-status-badge`
* `.order-products-toolbar`
* `.pager`
* `.pages`
* `.limiter`
* `.actions-toolbar`

The same status badge markup is already present on the My Orders page, for example:

```html
<span class="order-status-badge order-status-pending">
    Pending
</span>
```

### Styling planned/implemented

CSS/LESS styling was prepared for:

* Order history table
* Table headers
* Table rows
* Order numbers
* Order totals
* Status badges
* View Order / Reorder actions
* Pagination
* Current page indicator
* Show-per-page selector
* Back button

Status colors were defined for statuses including:

* Pending
* Processing
* Complete
* Cancelled / Canceled
* Closed
* On Hold
* Received

---

## 4. Icons

### Decision

The account-page icon customization was discussed, including the possibility of using Bootstrap Icons.

For today's work, **icons were intentionally not implemented** and the focus remained on the order pages and status styling.

---

## 5. Current Status

### Completed

* ✅ Recent Orders PHTML customization
* ✅ Correct Magento module override path identified
* ✅ Dynamic order status badge markup
* ✅ Status badge styling
* ✅ My Orders HTML structure inspected
* ✅ My Orders status badge styling prepared
* ✅ Pagination and order-history styling prepared

### Important path

```text
app/design/frontend/BrewCraft/supply/Magento_OrderCancellationUi/templates/order/recent.phtml
```

This is the correct theme override location for the Recent Orders template from `Magento_OrderCancellationUi`.
