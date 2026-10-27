# HariMarket

A pick-up based campus marketplace for Pamantasan ng Lungsod ng Maynila (PLM).
Buyers order from student sellers and collect items at the seller's pick-up point on campus.
Payment is made in person. There is no delivery.

Individual final project for Web Systems Technology (BSIT).

## Features
- Buyer, Seller and Admin roles with hashed passwords
- Product browsing, search, filter and sort; cart; one order per seller
- Pick-up points chosen from an admin-managed list
- QR code pick-up confirmation (buyer shows QR, seller scans it)
- Camera / file upload for product and profile photos
- Live notifications (polling with Fetch)
- Dark / light mode saved in Web Storage
- Seller and admin dashboards with Chart.js

## Tech stack
HTML5, CSS3, JavaScript (ES6+, Fetch API) | PHP 8 (PDO) | MySQL / MariaDB

## Run locally (XAMPP)
1. Copy this project into `htdocs`.
2. Create a database named `harimarket` and import `harimarket/sql/database.sql`.
3. Copy `harimarket/config/config.example.php` to `config.php` and set your DB details.
4. Open `http://localhost/harimarket/`.

## Documentation
Diagrams are in `docs/diagrams/` (architecture, use case, flowchart, ERD, wireframes).

## Live site
TBA after deployment.
