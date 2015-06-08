<?php
  // check what settings tab to display
  $tab = sanitize_text_field( $_GET['tab'] );

  $tab = isset($tab) ? $tab : 'backend';
  $settings = get_option(PHCFXWOOCOMMERCE_PLUGIN_NAME);
?>

<div class="wrap">
  <!-- plugin's header and description -->
  <div style="width: 70%" class="alignleft">
    <h2><?php echo PLUGIN_NAME_WOOCOMMERCE ?> Settings</h2>
    <div id="descriptionPlugin" style="width: 100%"></div>
    
    <a href="http://pt.phcfx.com//" target="_blank" title="PHC FX" class="alignleft" style="margin-right: 20px;">
      <img src="<?php echo plugins_url('images/logo_phcfx.png', __FILE__) ?>" width="125" height="125">
    </a>

    <h4>Eliminate your repetitive tasks!</h4>
    <p class="description"><strong><?php echo PLUGIN_NAME_WOOCOMMERCE ?></strong> is an easy-to-use integration that creates Invoices, Orders, Customers in your PHC FX accounting system. 
    You can also integrate the products and stocks in the WooCommerce solution and automatically send the invoices to your customers by mail.</p>
    <p>Don't forget to <strong>save your settings</strong> when all done!</p>
    <div class="clear"></div>
  </div>

  <div class="clear"></div>

  <h2 class="nav-tab-wrapper">
    <a href="?page=<?php echo PHCFXWOOCOMMERCE_PLUGIN_NAME ?>&tab=backend" class="nav-tab <?php echo $tab==='backend' ? 'nav-tab-active' : '' ?>">Backend Options</a>
    <?php if(!empty($_SESSION['username'])){ ?>
    <a href="?page=<?php echo PHCFXWOOCOMMERCE_PLUGIN_NAME ?>&tab=import" class="nav-tab <?php echo $tab==='import' ? 'nav-tab-active' : '' ?>">Import Products</a>
    <?php } ?>
  </h2>

  <form method="post" action="options.php">
    <div class="<?php echo $tab==='import' ? '' : 'hidden' ?>">
    <?php
      settings_fields('import-options');
      include(PHCFXWOOCOMMERCE_PLUGIN_DIR.'/settings/import-products.php');
    ?>
    </div>

    <div class="<?php echo $tab==='backend' ? '' : 'hidden' ?>">
    <?php
      settings_fields('backend-options');
      include(PHCFXWOOCOMMERCE_PLUGIN_DIR.'/settings/backend.php');

      submit_button();
    ?>
    </div>
  </form>
</div>

<div class="clear"></div>
