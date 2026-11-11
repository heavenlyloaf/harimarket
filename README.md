# HariMarket

A pick-up based campus marketplace for Pamantasan ng Lungsod ng Maynila (PLM).
Buyers order from student sellers and collect items at the seller's pick-up point on campus.
Payment is made in person. There is no delivery.

Web Systems Technology final project (BSIT, individual).

## Features
- Buyer, Seller and Admin roles; hashed passwords; admin approval of new accounts and product listings
- Product browsing with live search (Fetch API), category filter and sorting
- Pick-up only ordering: cart grouped by seller, one order per seller
- Sellers choose a pick-up point from an admin-managed list (admin CRUD)
- Order status: Placed, Preparing, Ready for Pick-up, Picked Up (or Cancelled)
- QR code pick-up confirmation: the buyer shows a QR, the seller scans it (camera) or types the code
- Camera / file upload for product and profile photos with server-side validation
- Order chat, notifications, reviews (only after Picked Up) and reports
- Security: PDO prepared statements, output escaping, CSRF tokens, upload validation, role checks

## Tech stack
HTML5, CSS3 (responsive), JavaScript (ES6+, Fetch API) | PHP 8 (PDO) | MySQL / MariaDB

## Run locally (XAMPP or `php -S`)
1. Copy the `harimarket` folder into `htdocs` (or serve it with `php -S localhost:8000 -t harimarket`).
2. Import the database: `mysql -u root -p < harimarket/sql/database.sql` (or import it in phpMyAdmin).
3. Copy `harimarket/config/config.example.php` to `harimarket/config/config.php` and set your database details.
4. Create the first admin account (the password is never stored in the repository):
   `php harimarket/tools/create_admin.php`
5. Open the site. Register a seller and a buyer, then approve them from the admin dashboard (`admin-login.php`).

## Project structure
- `harimarket/` PHP pages, `api/` JSON endpoints, `css/`, `js/`, `partials/`, `sql/`, `tools/`
- `docs/diagrams/` architecture, use case, flowchart, ERD, wireframes

## Live site
TBA after deployment.
