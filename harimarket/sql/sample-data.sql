-- HariMarket DEMO sample records (optional).
-- All demo accounts use the password:  Demo@12345   (stored as a bcrypt hash)
-- Demo accounts:  seller1@demo.test, seller2@demo.test, buyer1@demo.test, buyer2@demo.test
-- Product photos use the default placeholder image (img/elements/placeholder.png).

SET NAMES utf8mb4;

INSERT INTO `users` (`id`, `full_name`, `email`, `password_hash`, `phone`, `role`, `student_number`, `is_approved`) VALUES
  (101, 'Demo Seller One',  'seller1@demo.test', '$2y$10$Im0rk/PLr8j1wVeDwx1ihe305gleSOc18sAHtA5VFj4yFHQbxHDFC', '09170000001', 'seller', '2024-00001', 1),
  (102, 'Demo Seller Two',  'seller2@demo.test', '$2y$10$Im0rk/PLr8j1wVeDwx1ihe305gleSOc18sAHtA5VFj4yFHQbxHDFC', '09170000002', 'seller', '2024-00002', 1),
  (103, 'Demo Buyer One',   'buyer1@demo.test',  '$2y$10$Im0rk/PLr8j1wVeDwx1ihe305gleSOc18sAHtA5VFj4yFHQbxHDFC', '09170000003', 'buyer',  '2024-00003', 1),
  (104, 'Demo Buyer Two',   'buyer2@demo.test',  '$2y$10$Im0rk/PLr8j1wVeDwx1ihe305gleSOc18sAHtA5VFj4yFHQbxHDFC', '09170000004', 'buyer',  '2024-00004', 1);

INSERT INTO `seller_profiles` (`user_id`, `store_name`, `description`, `rating`, `approved`, `pickup_location_id`, `pickup_note`, `pickup_hours`) VALUES
  (101, 'Kape ni Haribon', 'Home-brewed drinks and snacks for students.', 5.00, 1, 1, 'Look for the green tumbler.', 'Mon-Fri 12:00-5:00 PM'),
  (102, 'Haribon Thrift',  'Thrifted clothes, keychains and pre-owned gadgets.', 4.00, 1, 2, 'I wait at the library steps.', 'Mon-Sat 3:00-6:00 PM');

INSERT INTO `products` (`id`, `seller_id`, `category_id`, `name`, `description`, `price`, `stock`, `rating`, `review_count`, `status`) VALUES
  (201, 101, (SELECT id FROM categories WHERE name='Drinks'), 'Iced Coffee 16 oz', 'Cold brew with milk, made fresh.', 65.00, 20, 5.00, 1, 'approved'),
  (202, 101, (SELECT id FROM categories WHERE name='Food'), 'Ham and Cheese Sandwich', 'Toasted sandwich, good for one.', 55.00, 15, 0.00, 0, 'approved'),
  (203, 102, (SELECT id FROM categories WHERE name='Thrifts'), 'Vintage Denim Jacket', 'Size M, lightly used.', 450.00, 3, 4.00, 1, 'approved'),
  (204, 102, (SELECT id FROM categories WHERE name='Keychains & Jewelries'), 'Handmade Beaded Keychain', 'Beaded keychain, assorted colors.', 80.00, 12, 0.00, 0, 'approved'),
  (205, 102, (SELECT id FROM categories WHERE name='Pre-owned Gadgets'), 'Used Bluetooth Earbuds', 'Works well, charging case included.', 600.00, 1, 0.00, 0, 'pending');

-- Orders: one for every status so the demo can show the whole flow.
INSERT INTO `orders` (`id`, `buyer_id`, `seller_id`, `pickup_location_id`, `pickup_note`, `pickup_code`, `status`, `total`) VALUES
  (301, 103, 101, 1, 'Mon-Fri 12:00-5:00 PM. Look for the green tumbler.', 'HK7M-2P3Q', 'picked_up', 65.00),
  (302, 103, 102, 2, 'Mon-Sat 3:00-6:00 PM. I wait at the library steps.', 'RT4N-8W6X', 'ready_for_pickup', 530.00),
  (303, 104, 101, 1, 'Mon-Fri 12:00-5:00 PM. Look for the green tumbler.', 'BC9D-5F2G', 'placed', 110.00),
  (304, 104, 102, 2, 'Mon-Sat 3:00-6:00 PM. I wait at the library steps.', 'JL3Z-7Y4V', 'cancelled', 80.00);

INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`, `unit_price`) VALUES
  (301, 201, 1, 65.00),
  (302, 203, 1, 450.00),
  (302, 204, 1, 80.00),
  (303, 202, 2, 55.00),
  (304, 204, 1, 80.00);

-- A review is only possible for a picked-up order (order 301).
INSERT INTO `reviews` (`product_id`, `user_id`, `order_id`, `rating`, `comment`) VALUES
  (201, 103, 301, 5, 'Cold and fresh, and the pick-up was quick.'),
  (203, 103, 302, 4, 'Good condition. Sample review for the demo.');

INSERT INTO `cart` (`user_id`, `product_id`, `quantity`) VALUES
  (103, 202, 1);

INSERT INTO `messages` (`sender_id`, `receiver_id`, `order_id`, `body`) VALUES
  (103, 102, 302, 'Hi! I will be at the library steps at 4 PM.'),
  (102, 103, 302, 'Okay, see you. Please show the QR code.');

INSERT INTO `notifications` (`user_id`, `message`, `link`) VALUES
  (103, 'Your order #302 is ready for pick-up at Library steps.', 'orders.php'),
  (101, 'New order #303 from Demo Buyer Two.', 'seller-dashboard.php#orders');

INSERT INTO `reports` (`reporter_id`, `product_id`, `reason`, `details`) VALUES
  (104, 204, 'Misleading description', 'Sample report for demonstrating the admin Reports section.');
