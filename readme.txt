=== WooCommerce Plugin for PHC FX ===
Contributors: phcwordpress
Tags: phc, phcfx, fx, invoices, client, internal document, products, plugin, business
Requires at least: 4.1.1
Tested up to: 4.2.2
Stable tag: trunk
License: GPLv2 or later

Sync your online shop with your PHC FX accounting software. Integrate your WooCommerce Orders, Customers, Products and PHC FX stocks.

== Description ==

#### Eliminate your repetitive tasks!

*WooCommerce Integration for PHC FX* 

WooCommerce Integration for PHC FX is an easy-to-use integration that creates Invoices, Orders, Customers in your PHC FX accounting system. You can also integrate the products and stocks in the WooCommerce solution and automatically send the invoices to your customers by mail.

This integration requires a license purchase from PHC FX software (www.phcfx.com) and the WooCommerce plugin installed on your Wordpress site.


#### What does WooCommerce for PHC FX do for you?

- Automaticly create new clients in PHC FX accounting software 
- Generate orders and invoices based on what the client orders in the website
- Option to automatically send invoices via email for new orders
- Sync all your products and correspondent stock from PHC FX software to Woocommerce. 


== Features ==
You can create clients, internal documents and invoices on PHC FX based on what it's done on the WooCommerce store.
Option to automatically send invoices via email for new orders
Sync stocks from PHC FX to Woocommerce
Choose which type of internal document and invoices you want.
Import/Update products from PHC FX software

##### NOTE: minimal PHC FX version: v16

== Installation ==

1. Upload the `plugin` folder to the `/wp-content/plugins/` directory or install via the Add New Plugin menu;
2. Activate the plugin through the 'Plugins' menu in WordPress;
3. Enter your PHC FX backend info in 'Settings' page. Its required to set the username, password, PHC FX backend URL and your generated user's API Key;
4. Import your products from FX to begin integration with PHC FX, because you need use the same reference of your products.

== Frequently Asked Questions ==

= What version of my PHC FX instalation is required to work with this plugin? =
You need to have PHC FX v15.2 or higher on your installation.

= If I have errors in plugin, where are showed? =
You can see erros in `/wp-content/plugins/phcfx-woocommerce/logsErrors/`.

= No clients / internal documents / invoices are being stored in my PHC FX database that i want? =
Possibly you don't choose a specific database in your PHC FX backend. If this is the case, the database used is the first catched. So please write the name of database that you want.

= No clients / internal documents / invoices are being stored in my PHC FX database and i have configured database? =
Possibly you don't choose a specific type of invoice document in your backend settings of plugin. 

= I can create an invoice without internal document? =
Yes. Don't choose a type of order and invoices are generated without an internal document.

= Can i choose some price from my database? =
Yes. Just choose wich field do you want in backend settings.

= When i choose type of order, is showed two new fields. This is for what? =
This fields allows you choose where do you want order status in your PHC FX. If you leave "save field status" empty, you can't able to see order status in your PHC FX.

= When i import products what means "image of right" =
In tab "Import" you can see all your products presented in your PHC FX and import them to your shop. Choose products and click to save them. Now you can seethe products presented in PHC FX and your shop through the image of right. 
Through the checkbox to update products is possible update name of products / prices and stocks you "save / import products"

== Screenshots ==

1. List of All Products
2. Initialize plugin and login
3. Configurations of plugin

 == Changelog ==

 = 1.0 =
 * the first version!
