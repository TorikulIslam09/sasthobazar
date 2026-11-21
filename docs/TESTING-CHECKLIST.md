# SasthoBazar – full testing checklist

Use this before going live and after every big change. Tick each box. Test in **both languages** (বাংলা / English switch in the header) and on a **phone-sized screen** for the storefront and vendor panel.

**Test accounts** (demo data, password `demo1234`):
* Customer `01700000099`.
* Vendors `01700000101` (Dhanmondi) and `01700000102` (Chattogram, 10% commission).
* Super admin: the account made by the installer.

Install the demo data with `php database/demo.php`; remove it before launch with `php database/demo.php --remove`.

**Where to look when something is wrong:** `storage/logs/php-error.log`, Admin → Audit logs, Settings → SMS & email log.

---

## 1. Customer flow

### Browse
- [ ] Home loads: slider, categories, flash sale with a running countdown, featured / new / offer rows, brands, home banners, popup (once a day).
- [ ] Search suggestions appear while typing; the results page shows the right products; "no results" message for nonsense.
- [ ] Category, brand and seller pages; filters (price, brand, in stock) and sorting work; pagination works.
- [ ] Product page: price, MRP crossed out, % off, variants change the price, stock text, Rx badge, offer / buy-X-get-Y box, reviews, alternatives (same generic).
- [ ] FAQ (search works), Health tips list and article, footer pages (About, Privacy, Terms, Return policy, Contact).

### Account
- [ ] Register with a new phone number; a duplicate phone is refused; the 6th sign-up from one network within an hour is refused.
- [ ] Log in / log out; 5 wrong passwords lock the account for 15 minutes.
- [ ] Profile edit, photo upload, password change (wrong current password refused).
- [ ] Addresses: add, edit, delete, set default; division → district → area lists load.

### Cart and checkout
- [ ] Add, change quantity and remove items as a guest, then log in: the cart is kept.
- [ ] Quantity above stock or above "max per order" shows a problem and blocks checkout.
- [ ] Coupon `WELCOME10` (first order, 10%, up to ৳150) and `FLAT50` (৳50 off ৳500+): correct discount; wrong / expired / used-up codes show the right message.
- [ ] First-order offer applies automatically when no coupon is used; a coupon replaces it.
- [ ] Buy 1 get 1: 2 in cart = 1 free; 1 in cart shows "add 2 to get the free item".
- [ ] Delivery charge changes with district (Dhaka city / sub-area / outside), with express, and with store pickup (in-house items only).
- [ ] An order with items from two sellers shows two delivery fees and "ships in 2 parcels".
- [ ] "Add ৳X more for free delivery" disappears once the threshold is reached.
- [ ] COD charge is added for cash on delivery only.
- [ ] Rx product: checkout asks for a prescription (JPG / PNG / PDF up to 5 MB; a renamed `.exe` or `.php` is refused).
- [ ] bKash / Nagad manual payment: transaction ID required; reusing the same ID is refused.
- [ ] Place order: success page, order number; stock goes down; the cart is empty.
- [ ] Refresh or double-click on "Place order" does not create a second order.

### After the order
- [ ] My orders → order page shows status timeline, items, totals (discounts, VAT, delivery), invoice prints.
- [ ] Cancel while pending: stock comes back; a paid order gets a refund record.
- [ ] Track order without logging in (order number + phone); a wrong phone shows "not found".
- [ ] After delivery: request a return within the return window; review the product (once per product).
- [ ] Help & support: open a ticket (linked to an order), see the staff reply, reply back, close it.
- [ ] Notifications (bell) arrive for status changes; SMS / email entries appear in the message log.

## 2. Vendor flow

- [ ] Register a shop (documents upload; 6th try within an hour is refused); the admin gets a notification.
- [ ] While pending, only the dashboard and profile open; after approval everything opens.
- [ ] Products page lists only the vendor's own products.
- [ ] Orders: only the vendor's sub-orders. Change confirmed → processing → shipped; cancel needs a reason; another vendor's order number gives 404.
- [ ] Packing slip prints.
- [ ] Offers: request a discount or buy-X-get-Y on own products only (another vendor's SKU is refused). The request is pending, then approved / rejected by the admin with a notification. Pause and resume work.
- [ ] Wallet: after delivery the earning is "pending" until the hold period (7 days), then "available".
- [ ] Payout request: below the minimum or above the balance is refused; one open request at a time; the admin gets a notification.
- [ ] Profile: bank / mobile banking details, pickup address.
- [ ] Trying to open `/admin` shows the "wrong panel" page.

## 3. Admin flow

Test with the super admin **and** with a limited employee, e.g. Content Editor or Support Agent: menu items and pages outside their role must be hidden or give 403.

- [ ] **Dashboard:**
  - sales today and this month with comparisons, commission income
  - 30-day chart and status doughnut
  - low stock and expiring lists
  - the bell updates without reloading (wait about 30 s after a new order)
- [ ] **Products:** create with variants and images, edit, CSV export / import (bad rows reported), approve vendor products.
- [ ] **Inventory:**
  - stock in (batch, expiry, cost) and stock out
  - adjust a batch, write off expired stock
  - movements list and valuation report
- [ ] **Purchase orders:** create → receive (GRN) → stock and batches update.
- [ ] **Orders:**
  - list filters
  - confirm, then move each sub-order
  - prescription approve / reject
  - verify a manual payment
  - add a note, cancel
  - invoices print
- [ ] **Returns & refunds:** approve with restock → stock back; mark refunded with a reference.
- [ ] **Shipping:**
  - zones and rates
  - book a parcel with a courier (mock mode); labels and manifest print
  - failed delivery → return → stock back
  - COD collected → settle
- [ ] **Vendors:** approve, reject, suspend, commission rate.
- [ ] **Payouts:** approve and pay a request.
- [ ] **Offers:**
  - coupons, offers (price / BOGO / first order / free delivery), approve a vendor request
  - flash sale with items, countdown on the storefront
  - banners and popup
- [ ] **Reports:** each of the 8 reports with date presets and filters; CSV, Excel and PDF download and open correctly.
- [ ] **Accounting:**
  - profit & loss
  - add / edit / delete income and expense entries
  - commission ledger, payouts, COD, refunds and VAT registers
  - VAT settings (a non-super-admin accountant cannot change them)
- [ ] **Content:** pages (a `<script>` typed into the content is removed when saved), FAQ, blog with cover image and scheduled date.
- [ ] **Support:** reply, internal note (not visible to the customer), assign, resolve; **Reviews:** publish / reject / delete, auto-publish switch.
- [ ] **Settings:**
  - general (logo appears in the header), orders & payments
  - SMS / email (test message shows "logged" while sending is off), notification switches
- [ ] **Backup:** download a backup, keep one on the server, restore one on a **test copy** of the site; wrong password / missing "RESTORE" are refused.
- [ ] **Employees & roles:** create a role with a few permissions, log in as that employee, check what is visible.
- [ ] **Audit logs:** recent actions appear with old / new values.

## 4. Commission math

Commission rate priority: **product → category (nearest parent with a rate) → vendor → global 8%**. It is fixed on the order item when the order is placed and booked when the vendor's sub-order is delivered.

The vendor is paid on: **regular price − the part of every discount the vendor bears**. Commission = that amount × rate. Platform-funded discounts do not lower the vendor's earning.

Examples below use a 5% commission rate:

| Case | Customer pays | Vendor bears | Commission base | Commission | Vendor earning |
|---|---|---|---|---|---|
| ৳100 item, no discount | 100 | 0 | 100 | 5.00 | 95.00 |
| 10% off, **platform** funded | 90 | 0 | 100 | 5.00 | 95.00 |
| 20% off, **vendor** funded | 80 | 20 | 80 | 4.00 | 76.00 |
| 20% off, **shared 50%** | 80 | 10 | 90 | 4.50 | 85.50 |
| Buy 1 get 1 on ৳50 (2 in cart), vendor funded | 50 | 50 | 50 | 2.50 | 47.50 |
| Flash ৳200 → ৳150, vendor funded | 150 | 50 | 150 | 7.50 | 142.50 |
| ৳200 item, VAT 5% **added on top** | 210 | 0 | 200 | 10.00 | 200.00 (190 + 10 VAT passed to the vendor) |

- [ ] Place orders for these cases. Admin → order page shows each discount and who bears it; the vendor's order page shows "discounts paid by the platform".
- [ ] After delivery: vendor wallet "pending" = vendor earning; the commission ledger shows the commission.
- [ ] Return 1 of the 2 buy-1-get-1 units: vendor earning −22.50, commission −1.25.
- [ ] After 7 days (or set hold days to 0 and run cron): pending → available.
- [ ] Payout: available balance goes down when requested; it comes back if the payout is rejected.

## 5. Stock math

- [ ] **FEFO:** stock in batch A (30, expiry next year) and batch B (5, expiry in 2 months). Order 8: 5 come from B, then 3 from A (order page shows the batches).
- [ ] Cancel that order: 5 go back to B and 3 to A.
- [ ] Return with "restock": the returned quantity goes back to the batches it came from; without restock it does not.
- [ ] Product stock = sum of its active batches; the valuation report matches (quantity × batch cost).
- [ ] **Low-stock alert:** stock 12, threshold 10, order 3 → stock 9 and a "Low stock" notification (once, not on every order). Order to 0 → "Out of stock".
- [ ] Out of stock: "Add to cart" is disabled; a cart line above stock shows "only N left".
- [ ] **Flash sale:** stock cap 5 → after 5 sold the regular price applies; "max per order" 2 blocks a quantity of 3.
- [ ] Expiry report and dashboard list show batches expiring within 90 days; the daily expiry notification arrives (cron).
- [ ] Write-off of an expired batch removes its quantity and appears in movements.

## 6. Security spot-checks (on the live server)

- [ ] `https://your-site/config/database.php`, `/database/schema.sql`, `/storage/logs/php-error.log`, `/cron/cron.php`, `/.htaccess`, `/README.md` → **403 / 404**.
- [ ] `/install/` → 404 (and the folder deleted).
- [ ] A file named `x.php` or `x.php.jpg` placed in `public/uploads/` is refused, never run.
- [ ] Searching for `<script>alert(1)</script>` shows the text, no alert box.
- [ ] Log in as a customer and open `/admin/orders` → wrong panel / 403.
- [ ] The session cookie has `Secure; HttpOnly; SameSite=Lax` (browser dev tools → Application → Cookies).
- [ ] A broken page shows the friendly 500 page with a reference, never PHP details.
- [ ] Settings → Backup shows "Scheduled tasks last ran … " within the last 5–10 minutes.

## 7. After every deployment (5-minute smoke test)

- [ ] Home, a product page, cart, checkout page load.
- [ ] Place and cancel one test order.
- [ ] Admin dashboard and orders load; the bell works.
- [ ] Vendor panel loads.
- [ ] `storage/logs/php-error.log` has no new errors.
