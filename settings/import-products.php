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

<table id="tableOfProducts" class="table table-striped table-bordered" cellspacing="0" width="100%"></table>

<div id="loader2"></div>
<div id="saveProductInShop" class="button button-primary" title="List all products from PHC FX">
	<img id="plus" src="<?php echo plugins_url('/../images/plus.png', __FILE__) ?>" title="Save selected product in yout online store"> Import New Products
</div>

<!--To save and update stocks-->
<!--<a id="saveAndUpdate" href="#" title="Save and update information of selected product in your online store">Save and Update</a>-->

<div id="updateStocks" class="button button-primary" title="Update stocks in your online store">
	<img id="plus" src="<?php echo plugins_url('/../images/update.png', __FILE__) ?>" title="Save selected product in yout online store"> Update Stocks
</div>
<!--<a id="updateStocks" href="#" title="Update stocks in your online store">Update Stocks</a>-->

<div id="updateAllFields" class="button button-primary" title="List all products from PHC FX">
	<img id="plus" src="<?php echo plugins_url('/../images/update.png', __FILE__) ?>" title="Update all fields of products in your online store"> Update all fields
</div>
<!--<a id="updateAllFields" href="#" title="Update all fields of products in your online store">Update all fields</a>-->