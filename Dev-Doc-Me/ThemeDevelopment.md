
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

#### 1. Never edit vendor theme files

We kept all BrewCraft work under:

```text
app/design/frontend/BrewCraft/supply
```

so Magento updates do not overwrite customizations.

#### 2. Child themes inherit functionality

Using:

```xml
<parent>Magento/luma</parent>
```

lets us reuse Magento storefront functionality while changing the design.

#### 3. Be careful with Magento core LESS filenames

Files such as:

```text
_theme.less
_variables.less
```

have special meaning in Magento theme inheritance.

A small custom version can unintentionally remove parent definitions.

#### 4. Use unique project-specific LESS partial names

Safer pattern:

```text
_brewcraft-variables.less
_brewcraft-header.less
_brewcraft-footer.less
```

or similarly unique project prefixes.

#### 5. `_extend.less` is our main customization entry point

Instead of copying huge parent-theme LESS files, BrewCraft additions can be loaded through:

```text
_extend.less
```

#### 6. Debug from the first compilation error

When Magento says:

```text
@icon-error undefined
```

the solution is not necessarily:

```less
@icon-error: ...;
```

If several Magento variables disappear together, investigate theme inheritance first.

#### 7. Resetting to a minimal theme is a useful debugging technique

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

#### 8. Preserve Magento's functional blocks

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

#### Why?

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

#### Why this is better

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

#### Dynamic Magento data

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

#### Theme-managed content

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

#### Category images

```text
Admin
→ Catalog
→ Categories
→ Category Image
```

#### Featured products

```text
Admin
→ Catalog
→ Categories
→ Featured Products
→ Products in Category
```

#### Product images

```text
Admin
→ Catalog
→ Products
→ Images and Videos
```

#### Product price

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

#### Lesson learned

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

### 1. Goal of Today's Work

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
