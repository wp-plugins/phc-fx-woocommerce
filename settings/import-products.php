<?php
  // get form options settings
  $settings = get_option(PHCFXWOOCOMMERCE_PLUGIN_NAME);
?>

<h3>PHC FX Import Products</h3>

<h4><em>Online Shop</em></h4>
<p>Check to new insert products presented in your PHC FX on your Online Shop.</p>

<div id="loader"></div>
<div id="importToShop" class="button button-primary" title="List all products from PHC FX">
	<img id="list" src="<?php echo plugins_url('/../images/list.png', __FILE__) ?>" title="List all products from PHC FX"> List Products
</div>

<!-- Table of products -->
<table id="tableOfProducts" class="table table-striped table-bordered" cellspacing="0" width="100%"></table>

<!-- Buttons to "show list, save products, update stocks and update all fields" -->
<div id="loader2"></div>
<div id="saveProductInShop" class="button button-primary" title="Import new products from PHC FX">
	<img id="plus" src="<?php echo plugins_url('/../images/plus.png', __FILE__) ?>" title="Save selected product in yout online store"> Import New Products
</div>

<div id="updateStocks" class="button button-primary" title="Update stocks in your online store">
	<img id="plus" src="<?php echo plugins_url('/../images/update.png', __FILE__) ?>" title="Save selected product in yout online store"> Update Stocks
</div>

<div id="updateAllFields" class="button button-primary" title="Update all fields of products from PHC FX">
	<img id="plus" src="<?php echo plugins_url('/../images/update.png', __FILE__) ?>" title="Update all fields of products in your online store"> Update all fields
</div>