<?php
class PhcFxWoocommerce {
  public $url;
  public $params;
  public $query;
  public $fieldStatus;
  public $extraurl = "";

  private $validSettings = false;

  // backend settings
  private $options = array(
    'backend' => array(
      'username'          => array('label' => 'Username',                                   'type' => 'text',          'required' => true,  'descr' => 'This username allows the backend to accept data sent from this plugin.', 'notice' => 'This can not be empty! Please enter your username...'),
      'password'          => array('label' => 'Password',                                   'type' => 'password',      'required' => true,  'descr' => 'This password allows the backend to accept data sent from this plugin.', 'notice' => 'This can not be empty! Please enter your password...'),      
      'url'               => array('label' => 'Backend URL',                                'type' => 'url',           'required' => true,  'descr' => 'The URL of your PHC FX application.<br>Something like, e.g. https://myurl.com/myphc', 'notice' => 'No backend URL! Please define your backend URL...'),
      'dbname'            => array('label' => 'Database Name',                              'type' => 'text',          'required' => false, 'descr' => 'Enter the name of the PHC FX company where you want save your information.<br>You can leave this empty if you only work with one company.', 'notice' => ''),
      'createInvoice'     => array('label' => 'Create Orders and Invoices',                 'type' => 'checkbox',      'required' => false, 'checkboxDescription' => 'Create invoices for orders that come in, otherwise only the client is created (recommended).', 'notice' => ''),
      'sendInvoice'       => array('label' => 'Send Invoice',                               'type' => 'checkbox',      'required' => false, 'checkboxDescription' => 'Send the client an e-mail with the order invoice attached', 'notice' => ''),
      'typeOfInvoice'     => array('label' => 'Type of Invoice Document',                   'type' => 'select',        'required' => false, 'checkboxDescription' => 'Choose the type of invoice document', 'notice' => ''),
      'warehouseOrder'    => array('label' => 'Order Warehouse',                             'type' => 'select',        'required' => false, 'checkboxDescription' => 'Choose from warehouse do you want import products', 'notice' => ''),
      'nameOfNewOrder'    => array('label' => 'Name of new type of order',                  'type' => 'text',          'required' => false, 'descr' => 'This field is used to create the name of new order in PHC FX.', 'notice' => ''),
      'typeOfOrder'       => array('label' => 'Type of Order',                              'type' => 'select',        'required' => false, 'checkboxDescription' => 'Choose the type of order', 'notice' => ''),
      'statusOfOrder'     => array('label' => 'Field to save status of order in PHC FX',    'type' => 'select',        'required' => false, 'checkboxDescription' => 'Choose field in database of PHC FX that you want to save order status', 'notice' => ''),
      'saveStatusOrder'   => array('label' => 'Save field status',                          'type' => 'text',          'required' => false, 'descr' => 'This field is used to show in PHC FX the status of order.', 'notice' => ''),
      'manageStock'       => array('label' => 'Manage Stock',                               'type' => 'checkbox',      'required' => false, 'checkboxDescription' => 'Manage stock at your online shop', 'notice' => ''),
      'productPriceColumn'=> array('label' => 'Field to obtain product price in PHC FX',    'type' => 'select',        'required' => false, 'checkboxDescription' => 'Choose field in database of PHC FX that you want to obtain product price', 'notice' => ''),
      'warehouse'         => array('label' => 'Warehouse',                                  'type' => 'select',        'required' => false, 'checkboxDescription' => 'Choose from warehouse do you want import products', 'notice' => '')
      )
    );

  private static $instance = null;

  public static function self() {
    if (self::$instance) return $instance;

    return new PhcFxWoocommerce();
  }

  private function __construct () {
    // call init
    $this->init();

    // store default BACKEND fields settings
    add_option(PHCFXWOOCOMMERCE_PLUGIN_NAME, array('backend' => $this->options['backend']));
  }
   
  //Initializes WordPress hooks
  private function init() {
    add_action('admin_menu', array($this, 'init_plugin'));
    add_action('admin_init', array($this, 'register_settings'));

    // handlers to load scripts required by the plugin
    add_action('admin_enqueue_scripts', array($this, 'register_scripts'));

    // handlers to load (render) settings and add link to plugin settings
    add_action('admin_menu', array($this, 'load_settings'));
    add_filter('plugin_action_links_'.PHCFXWOOCOMMERCE_PLUGIN, array($this, 'load_links'));

    // handler to show notices in admin panel
    add_action('admin_notices', array($this, 'render_notices'));
    
    //Obtain status of order in online shop
    add_action( 'woocommerce_order_status_pending',  array($this, 'statusPending'));
    add_action( 'woocommerce_order_status_on-hold',  array($this, 'statusOnHold'));
    add_action( 'woocommerce_order_status_processing',  array($this, 'statusProcessing'));
    add_action( 'woocommerce_order_status_refunded',  array($this, 'statusRefunded'));
    add_action( 'woocommerce_order_status_completed',  array($this, 'statusCompleted'));
    add_action( 'woocommerce_order_status_cancelled',  array($this, 'statusCancelled'));
    add_action( 'woocommerce_order_status_failed',  array($this, 'statusFailed'));

    // HOOKS WOOCOMMERCE - ORDER Status  
    add_action( 'woocommerce_order_status_pending',  array($this, 'addNewOrder'));
    add_action( 'woocommerce_order_status_on-hold',  array($this, 'addNewOrder'));
    add_action( 'woocommerce_order_status_processing',  array($this, 'addNewOrder'));
    
    add_action( 'woocommerce_order_status_completed',  array($this, 'completedOrder'));
    //add_action( 'woocommerce_payment_complete',  array($this, 'teste'));

    add_action( 'woocommerce_order_status_cancelled',  array($this, 'cancelOrder'));
    add_action( 'woocommerce_order_status_failed',  array($this, 'cancelOrder'));
    add_action( 'woocommerce_order_status_refunded',  array($this, 'cancelOrder'));

    //Save field to show status order in PHC FX
    $settings = get_option(PHCFXWOOCOMMERCE_PLUGIN_NAME);
    if(!empty($settings['backend']['typeOfOrder'])){
      add_action('update_option',  array($this, 'saveFieldOrderStatus'));
    }  

    // handler for form submission
    add_action('admin_post_woocommerce_fx', array($this, 'woocommerce_fx')); 

    //Verify if exists token in mysql db
    global $wpdb;
    $table_name = $wpdb->prefix."postmeta"; 

    //If exists GET['fxtoken'], so popup is open to obtain key
    $accessToken = filter_var($_GET['accessToken'], FILTER_SANITIZE_STRING);
    
    if($accessToken != ''){
      $accessToken = str_replace(' ', '+', $accessToken);

      //Save token in Mysql db
      $query = "SELECT * FROM %s WHERE meta_key = %s";
      $resultDB = $wpdb->get_row(str_replace("'".$table_name."'", $table_name, $wpdb->prepare($query, $table_name, '_token')));
	  
      //Verify if token already exists
      if($resultDB->meta_id != ''){
        //Delete value in database mysql
        delete_post_meta($resultDB->post_id, '_token');  
		//Obtain next post_id of order in MySQL
        $query = "SELECT MAX(post_id)+1 as nextPostId FROM %s";
        $token = $wpdb->get_row(str_replace("'", "", $wpdb->prepare($query, $table_name)));
        //add to table postmeta a key of order and stamp of internal document
        add_post_meta($token->nextPostId, '_token', $accessToken);		
      } else {
        //Obtain next post_id of order in MySQL
        $query = "SELECT MAX(post_id)+1 as nextPostId FROM %s";
        $token = $wpdb->get_row(str_replace("'", "", $wpdb->prepare($query, $table_name)));
        if($token->nextPostId == ''){
			add_post_meta(1, '_token', $accessToken);
		} else {
			//add to table postmeta a key of order and stamp of internal document
			add_post_meta($token->nextPostId, '_token', $accessToken);
		}
      }

      //Close popup and refresh parent page
      echo "<script type='text/javascript'>
              function RefreshParent() {
                if(window.opener != null && !window.opener.closed) {
                  window.opener.location.reload();
                }
              }
              window.onbeforeunload = RefreshParent;
              window.close();
            </script>";
    }
  }

  //Obtain info of plugins and save information
  public function init_plugin() {
    $plugins = get_plugins();

    define('PLUGIN_NAME_WOOCOMMERCE',    $plugins[PHCFXWOOCOMMERCE_PLUGIN]['Name']);
    define('PLUGIN_VERSION_WOOCOMMERCE', $plugins[PHCFXWOOCOMMERCE_PLUGIN]['Version']);
  }

  public function register_settings() {
    // Backend settings section
    add_settings_section('backend-section', null, null, 'backend-options');
    add_settings_field(null, null, null, 'backend-options', 'backend-section');
    register_setting('backend-options', PHCFXWOOCOMMERCE_PLUGIN_NAME);

    // Import settings section
    add_settings_section('import-section', null, null, 'import-options');
    add_settings_field(null, null, null, 'import-options', 'import-section');
    register_setting('import-options', PHCFXWOOCOMMERCE_PLUGIN_NAME);
  }

  public function register_scripts() {
    ?>
    <script>
      var pathPlugin = "<?php echo plugins_url('/' , __FILE__ ); ?>";
    </script>
    <?php
    // register scripts that will be used later on
    wp_register_script(PHCFXWOOCOMMERCE_PLUGIN_NAME, plugins_url('/js/'.PHCFXWOOCOMMERCE_PLUGIN_NAME.'.js' , __FILE__ ));
    wp_register_script('datatable_min', plugins_url('/js/datatable_min.js' , __FILE__ ));
    
    // register css that will be used later on
    wp_register_style('style_datatable_jquery', plugins_url('/css/style_datatable_jquery.css' , __FILE__ ));
    wp_register_style('datatable_css', plugins_url('/css/style_datatable.css' , __FILE__ ));
  }

  //Create page of settings
  public function render_settings() {
    include(PHCFXWOOCOMMERCE_PLUGIN_DIR.'phcfx-woocommerce-settings.php');
    // inject our JS script
    wp_enqueue_script(PHCFXWOOCOMMERCE_PLUGIN_NAME);
    wp_enqueue_script('datatable_min');
    //inject our css of datatable
    wp_enqueue_style('style_datatable_jquery');
    wp_enqueue_style('datatable_css');
    // inject data (settings) and default messages to JS
    wp_localize_script(PHCFXWOOCOMMERCE_PLUGIN_NAME, 'settings', $settings);
    // inject also the server-side script that will handle the form submission
    wp_localize_script(PHCFXWOOCOMMERCE_PLUGIN_NAME, 'url', admin_url('admin-post.php'));
  }

  //Access to page of Settings in menu Plugin
  public function load_settings() {
    add_options_page(sprintf('%s Settings', PLUGIN_NAME_WOOCOMMERCE), PLUGIN_NAME_WOOCOMMERCE, 'manage_options', PHCFXWOOCOMMERCE_PLUGIN_NAME, array($this, 'render_settings'));
  }

  //Load links of pages in menu Plugins (Settings, Deactivate and Edit)
  public function load_links($links) {
    // add our settings page
    array_unshift($links, '<a href="'. get_admin_url(null, 'options-general.php?page='.PHCFXWOOCOMMERCE_PLUGIN_NAME) .'">Settings</a>');

    return $links;
  }

  //Show messages of feedback to user in settings
  public function render_notices() {
    $notices = array();
    $settings = get_option(PHCFXWOOCOMMERCE_PLUGIN_NAME);

    // list of notices to be displayed on WP admin panel
    // quit if no notices to display
    if ( $this->checkSettings($notices) ) return;

    // output notices in admin panel
    ?>
    <div class="error">
      <h4><?php echo PLUGIN_NAME_WOOCOMMERCE ?></h4>
      <p>The required settings are not yet configured!</p>
      <blockquote>
      <?php foreach ($notices as $entry => $message): ?>
        <p><strong><?php echo $entry ?></strong> - <?php echo $message ?></p>
      <?php endforeach; ?>
      </blockquote>

      <p><strong>NOTE:</strong> The plugin will not show up in your pages unless all above settings have been setup correctly.</p>
    </div>
    <?php
  }

  //Show feedback to user in settings when connection is not established
  public function messagesError($message){
    ?>
    <div class="error">
      <h4><?php echo PLUGIN_NAME_WOOCOMMERCE ?></h4>
      <p>Failed to send data to backend! Please check your settings or connection!</p>
      <p><strong>Error <?php echo $message ?></strong></p>
    </div>
    <?php
  }

  //Show feedback to user in settings when settings are incompleted
  public function messagesInformation($message){
    ?>
    <div class="error">
      <h4><?php echo PLUGIN_NAME_WOOCOMMERCE ?></h4>
      <p><strong><?php echo $message ?></strong></p>
    </div>
    <?php
  }

  //Verify if backend configurations are configured
  public function checkSettings (&$notices) {
    $settings = get_option(PHCFXWOOCOMMERCE_PLUGIN_NAME);

    // check if required settings are filled up
    foreach ($this->options['backend'] as $id => $opts) {
      if ( $opts['required'] && empty($settings['backend'][$id]) ){
        $notices[$opts['label']] = $opts['notice'];
        $verifyWarning = true;
      }     
    }

    if($verifyWarning != 1) {
      $this->setCommunicationFx();
    }

    return count($notices)==0;
  }

  //Called from "external files"
  public function woocommerce_fx () {
  	$method = sanitize_text_field( $_POST['method'] );
  	
  	if(is_array($_POST['refs'])){
  		$refs = filter_var_array($_POST['refs'], FILTER_SANITIZE_STRING); 
  	} else {
  		$refs = '';
  	}

  	if(isset($_POST['selectItems'])){
  		$selectItems = filter_var($_POST['selectItems'], FILTER_SANITIZE_STRING); 
  	} else {
  		$selectItems = '';
  	}

  	if(isset($_POST['typeOfOrder'])){
  		$typeOfOrder = filter_var($_POST['typeOfOrder'], FILTER_SANITIZE_STRING); 
  	} else {
  		$typeOfOrder = '';
  	}

  	if(isset($_POST['nameTypeOfOrder'])){
  		$nameTypeOfOrder = filter_var($_POST['nameTypeOfOrder'], FILTER_SANITIZE_STRING); 
  	} else {
  		$nameTypeOfOrder = '';
  	}

  	if(isset($_POST['manageStock'])){
  		$manageStock = filter_var($_POST['manageStock'], FILTER_SANITIZE_STRING); 
  	} else {
  		$manageStock = '';
  	}

  	if(isset($_POST['warehouse'])){
  		$warehouse = filter_var($_POST['warehouse'], FILTER_SANITIZE_STRING); 
  	} else {
  		$warehouse = '';
  	}

  	switch ($method) {
  		case 'listOfProducts':
        //Show list of products
  			return $this->listProducts();
  			break;
  		case 'saveProducts':
        //Save products in mysql db
  			return $this->saveProducts($refs);
  			break;
  		case 'updateStocksProducts':
        //Update only stocks of products
  			return $this->updateStocksProducts($refs);
  			break;
  		case 'updateAllFieldsProducts':
        //Update all fields of products
  			return $this->updateAllFieldsProducts($refs);
  			break;	
  		case 'statusOfOrder':
        //Obtain status of order presented in PHC FX
  			return $this->statusOfOrder($selectItems, $typeOfOrder);
  			break;
  		case 'newTypeOfOrder':
        //Create new type of order in PHC FX
  			$this->newTypeOfOrder($nameTypeOfOrder, $manageStock, $warehouse);
  			break;
      case 'updateTypeOfOrder':
        //Update type of order dropdownlist 
        $this->updateTypeOfOrders();
  		default:
  			break;
  	}
  }

  //Obtain settings of administrator of plugin Woocommerce
  public function get_settingsAdmin() {
    $settings = apply_filters('woocommerce_email_settings', array(
      array( 'type' => 'sectionend', 'id' => 'email_recipient_options' ),
      array( 'title' => __( 'Email Sender Options', 'woocommerce' ), 'type' => 'title', 'desc' => __( 'The following options affect the sender (email address and name) used in WooCommerce emails.', 'woocommerce' ), 'id' => 'email_options' ),
      array(
        'title'    => __( '"From" Name', 'woocommerce' ),
        'desc'     => '',
        'id'       => 'woocommerce_email_from_name',
        'type'     => 'text',
        'css'      => 'min-width:300px;',
        'default'  => esc_attr(get_bloginfo('title')),
        'autoload' => false
      ),
      array(
        'title'             => __( '"From" Email Address', 'woocommerce' ),
        'desc'              => '',
        'id'                => 'woocommerce_email_from_address',
        'type'              => 'email',
        'custom_attributes' => array(
          'multiple' => 'multiple'
        ),
        'css'               => 'min-width:300px;',
        'default'           => get_option('admin_email'),
        'autoload'          => false
      ),
      array( 'type' => 'sectionend', 'id' => 'email_options' ),
      array( 'title' => __( 'Email Template', 'woocommerce' ), 'type' => 'title', 'desc' => sprintf(__( 'This section lets you customise the WooCommerce emails. <a href="%s" target="_blank">Click here to preview your email template</a>. For more advanced control copy <code>woocommerce/templates/emails/</code> to <code>yourtheme/woocommerce/emails/</code>.', 'woocommerce' ), wp_nonce_url(admin_url('?preview_woocommerce_mail=true'), 'preview-mail')), 'id' => 'email_template_options' ),
      array(
        'title'    => __( 'Header Image', 'woocommerce' ),
        'desc'     => sprintf(__( 'Enter a URL to an image you want to show in the email\'s header. Upload your image using the <a href="%s">media uploader</a>.', 'woocommerce' ), admin_url('media-new.php')),
        'id'       => 'woocommerce_email_header_image',
        'type'     => 'text',
        'css'      => 'min-width:300px;',
        'default'  => '',
        'autoload' => false
      ),
      array(
        'title'    => __( 'Email Footer Text', 'woocommerce' ),
        'desc'     => __( 'The text to appear in the footer of WooCommerce emails.', 'woocommerce' ),
        'id'       => 'woocommerce_email_footer_text',
        'css'      => 'width:100%; height: 75px;',
        'type'     => 'textarea',
        'default'  => get_bloginfo('title') . ' - ' . __( 'Powered by WooCommerce', 'woocommerce' ),
        'autoload' => false
      ),
      array(
        'title'    => __( 'Base Colour', 'woocommerce' ),
        'desc'     => __( 'The base colour for WooCommerce email templates. Default <code>#557da1</code>.', 'woocommerce' ),
        'id'       => 'woocommerce_email_base_color',
        'type'     => 'color',
        'css'      => 'width:6em;',
        'default'  => '#557da1',
        'autoload' => false
      ),
      array(
        'title'    => __( 'Background Colour', 'woocommerce' ),
        'desc'     => __( 'The background colour for WooCommerce email templates. Default <code>#f5f5f5</code>.', 'woocommerce' ),
        'id'       => 'woocommerce_email_background_color',
        'type'     => 'color',
        'css'      => 'width:6em;',
        'default'  => '#f5f5f5',
        'autoload' => false
      ),
      array(
        'title'    => __( 'Email Body Background Colour', 'woocommerce' ),
        'desc'     => __( 'The main body background colour. Default <code>#fdfdfd</code>.', 'woocommerce' ),
        'id'       => 'woocommerce_email_body_background_color',
        'type'     => 'color',
        'css'      => 'width:6em;',
        'default'  => '#fdfdfd',
        'autoload' => false
      ),
      array(
        'title'    => __( 'Email Body Text Colour', 'woocommerce' ),
        'desc'     => __( 'The main body text colour. Default <code>#505050</code>.', 'woocommerce' ),
        'id'       => 'woocommerce_email_text_color',
        'type'     => 'color',
        'css'      => 'width:6em;',
        'default'  => '#505050',
        'autoload' => false
      ),
      array( 'type' => 'sectionend', 'id' => 'email_template_options' ),
    ));
    return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings );
  }

  //Send email to administrator of wordpress with errors 
  public function sendEmail($message){
    $infoAdmin = $this->get_settingsAdmin();
    $emailAdmin = $infoAdmin[3]['default'];

    if(!empty($emailAdmin)){
      $to = $emailAdmin;
      $subject = "Errors of PHC FX Woocommerce";
      $txt = $message;
      $headers = "From: ".$emailAdmin;

      mail($to,$subject,$txt,$headers);   
    }
  }

  //Write errors in Log File
  public function writeFileLog($text_function, $messageResponse) {
    $myFile = __DIR__.'/logsErrors/logs.txt';
    $date = date('Y/m/d H:i:s');
    $message = $text_function . " " . $date . ":  " . print_r($messageResponse, true) . "\r\n";

    if (file_exists($myFile)) {
      $fh = fopen($myFile, 'a') or die();
    } else {
      $fh = fopen($myFile, 'w') or die();
    } 
    fwrite($fh, $message);
    fclose($fh);
  }

  //Header to make login
  public function paramsLogin(){
    $settings = get_option(PHCFXWOOCOMMERCE_PLUGIN_NAME);

    global $wpdb;
    $table_name = $wpdb->prefix."postmeta"; 
    $query = "SELECT meta_value FROM %s WHERE meta_key = %s";
    $resultDB = $wpdb->get_row(str_replace("'".$table_name."'", $table_name, $wpdb->prepare($query, $table_name, '_token')));

    // build our web service full URL
    $settings['backend']['url'] = rtrim($settings['backend']['url'], '/');
    $this->url = "{$settings['backend']['url']}".$this->extraurl."/REST/UserLoginWS/userLoginCompany";

    // Create map with request parameters
    $this->params = array ('userCode' => $settings['backend']['username'], 
                     'password' => $settings['backend']['password'], 
                     'applicationType' => $resultDB->meta_value,
                     'company' => $settings['backend']['dbname']
                     );
    // Build Http query using params
    $this->query = http_build_query ($this->params);
  }

  //Header to create Query of webservice called
  public function paramsQuery($webservice, $filterItem = null, $valueItem = null){
    $settings = get_option(PHCFXWOOCOMMERCE_PLUGIN_NAME);
    // build our web service full URL
    $settings['backend']['url'] = rtrim($settings['backend']['url'], '/');
    $this->url = "{$settings['backend']['url']}".$this->extraurl."/REST/{$webservice}/Query";
    // Create map with request parameters
    $this->params =  array ('itemQuery' => '{"groupByItems":[],
                                            "lazyLoaded":false,
                                            "joinEntities":[],
                                            "orderByItems":[],
                                            "SelectItems":[],
                                            "entityName":"",
                                            "filterItems":[{
                                                            "comparison":0,
                                                            "filterItem":"'.$filterItem.'",
                                                            "valueItem":"'.$valueItem.'",
                                                            "groupItem":1,
                                                            "checkNull":false,
                                                            "skipCheckType":false,
                                                            "type":"Number"
                                                          }]}'
                     );
    // Build Http query using params
    $this->query = http_build_query ($this->params);
  }

  //Header to create Query of webservice called
  public function paramsQuery2($webservice){
    $settings = get_option(PHCFXWOOCOMMERCE_PLUGIN_NAME);
    // build our web service full URL
    $settings['backend']['url'] = rtrim($settings['backend']['url'], '/');
    $this->url = "{$settings['backend']['url']}".$this->extraurl."/REST/{$webservice}/Query";
    // Create map with request parameters
    $this->params =  array ('itemQuery' => '{"groupByItems":[],
                                            "lazyLoaded":false,
                                            "joinEntities":[],
                                            "orderByItems":[],
                                            "SelectItems":[],
                                            "entityName":"",
                                            "filterItems":[]
                                            }'
                     );
    // Build Http query using params
    $this->query = http_build_query ($this->params);
  }

  //Header to create Query of webservice called
  public function paramsQuery3($webservice, $filterItems){
    $settings = get_option(PHCFXWOOCOMMERCE_PLUGIN_NAME);
    // build our web service full URL
    $settings['backend']['url'] = rtrim($settings['backend']['url'], '/');
    $this->url = "{$settings['backend']['url']}".$this->extraurl."/REST/{$webservice}/Query";
    // Create map with request parameters
    $this->params =  array ('itemQuery' => '{"groupByItems":[],
                                            "lazyLoaded":false,
                                            "joinEntities":[],
                                            "orderByItems":[],
                                            "SelectItems":[],
                                            "entityName":"",
                                            "filterItems":['.$filterItems.']
                                            }'
                     );
    // Build Http query using params
    $this->query = http_build_query ($this->params);
  }

  //Header to create Query of webservice called
  public function paramsQuery4($webservice, $selectItems, $valueItem){
    $settings = get_option(PHCFXWOOCOMMERCE_PLUGIN_NAME);
    // build our web service full URL
    $settings['backend']['url'] = rtrim($settings['backend']['url'], '/');
    $this->url = "{$settings['backend']['url']}".$this->extraurl."/REST/{$webservice}/Query";
    // Create map with request parameters
    $this->params =  array ('itemQuery' => '{"groupByItems":[],
                                            "lazyLoaded":false,
                                            "joinEntities":[],
                                            "orderByItems":[],
                                            "SelectItems":["'.$selectItems.'"],
                                            "entityName":"",
                                            "filterItems":[{
                                                            "comparison":0,
                                                            "filterItem":"ndos",
                                                            "valueItem":'.$valueItem.',
                                                            "groupItem":1,
                                                            "checkNull":false,
                                                            "skipCheckType":false,
                                                            "type":"Number"
                                                          }]}'
                     );

    // Build Http query using params
    $this->query = http_build_query ($this->params);
  }

  //Header to create Query of webservice called
  public function paramsGetBackEndInfo(){
    $settings = get_option(PHCFXWOOCOMMERCE_PLUGIN_NAME);
    // build our web service full URL
    $settings['backend']['url'] = rtrim($settings['backend']['url'], '/');
    $this->url = "{$settings['backend']['url']}".$this->extraurl."/REST/UserLoginWS/getBackEndInfo";
    // Create map with request parameters
    $this->params =  array ('loginInfoVO' => '{"userCode": "' . $settings['backend']['username'] . '", 
                                             "password": "' . $settings['backend']['password'] . '", 
                                             "company":  "' . $settings['backend']['dbname'] . '", 
                                             "language": "", 
                                             "hash": "" 
                                            }'
                           );

    // Build Http query using params
    $this->query = http_build_query ($this->params);
  }

  //Header to create a new instance of webservice called
  public function paramsNewInstance($webservice, $ndos){
    $settings = get_option(PHCFXWOOCOMMERCE_PLUGIN_NAME);
    // build our web service full URL
    $settings['backend']['url'] = rtrim($settings['backend']['url'], '/');
    $this->url = "{$settings['backend']['url']}".$this->extraurl."/REST/{$webservice}/getNewInstance";
    // Create map with request parameters
    $this->params =  array ('ndos' => $ndos);
    // Build Http query using params
    $this->query = http_build_query ($this->params);
  }

  //Header of actBo 
  public function paramsActBo($stamp_bo, $nr_client){
    $settings = get_option(PHCFXWOOCOMMERCE_PLUGIN_NAME);
    // build our web service full URL
    $settings['backend']['url'] = rtrim($settings['backend']['url'], '/');
    $this->url = "{$settings['backend']['url']}".$this->extraurl."/REST/BoWS/ActBo";
    // Create map with request parameters
    $this->params =  array ('IdBoStamp' => $stamp_bo,
                            'codigo' => 'no',
                            'newValue' => "[".$nr_client.", 0]"
                            );
    // Build Http query using params
    $this->query = http_build_query ($this->params);
  }
  
  //Header to create a new instance by ref of webservice called
  public function paramsNewInstanceByRef($webservice, $webserviceMethod, $fieldWs, $fieldWsEditing, $stamp, $listOfRefs){
    $settings = get_option(PHCFXWOOCOMMERCE_PLUGIN_NAME);
    // build our web service full URL
    $settings['backend']['url'] = rtrim($settings['backend']['url'], '/');
    $this->url = "{$settings['backend']['url']}".$this->extraurl."/REST/{$webservice}/{$webserviceMethod}";
    //Create references list
    if (is_array($listOfRefs)){
      foreach ($listOfRefs as $key => $value){
        if($key == 0){
          $listOfRefs = $value;
        } else {
          $listOfRefs .= "\", \"" . $value;
        }
      }
    }
    // Create map with request parameters
    $this->params =  array ($fieldWs => $stamp,
                            'refsIds' => '["'. $listOfRefs . '"]',
                            $fieldWsEditing => ""
                            );
    // Build Http query using params
    $this->query = http_build_query ($this->params);
  }

  //Header of ActBi
  public function paramsActBi($stamp_bo, $stamp_bi, $fieldChange, $itemChange){
    $settings = get_option(PHCFXWOOCOMMERCE_PLUGIN_NAME);
    // build our web service full URL
    $settings['backend']['url'] = rtrim($settings['backend']['url'], '/');
    $this->url = "{$settings['backend']['url']}".$this->extraurl."/REST/BoWS/ActBi";
    // Create map with request parameters
    $this->params =  array ('IdBoStamp' => $stamp_bo,
                            'codigo' => $fieldChange,
                            'IdBiStamp' => $stamp_bi,
                            'newValue' => $itemChange, //250 para testar
                            'autoCreation' => "false"
                            );
    // Build Http query using params
    $this->query = http_build_query ($this->params);
  }

  //Header to create a new instance from reference of webservice called
  public function paramsNewInstanceFromReference($boStamp){
    $settings = get_option(PHCFXWOOCOMMERCE_PLUGIN_NAME);
    // build our web service full URL
    $settings['backend']['url'] = rtrim($settings['backend']['url'], '/');
    $this->url = "{$settings['backend']['url']}".$this->extraurl."/REST/FtWS/getNewInstanceFromReference";
    // Create map with request parameters
    $this->params =  array ('parameters' => '[{"key":"origin",
                                              "value":"BO"
                                              },
                                              {"key":"originstamp",
                                              "value":"'.$boStamp.'"
                                              },
                                              {"key":"docid",
                                              "value":"'.$settings['backend']['typeOfInvoice'].'"
                                              }
                                            ]'
                            );
    // Build Http query using params
    $this->query = http_build_query ($this->params);
  }

  //Header to save information of webservice called
  public function paramsSave($webservice, $response){
    $settings = get_option(PHCFXWOOCOMMERCE_PLUGIN_NAME);
    // build our web service full URL
    $settings['backend']['url'] = rtrim($settings['backend']['url'], '/');
    $this->url = "{$settings['backend']['url']}".$this->extraurl."/REST/{$webservice}/Save";
    // Create map with request parameters
    $this->params =  array ('itemVO' => json_encode($response['result'][0]),
                      'runWarningRules' => 'false'); 
    // Build Http query using params
    $this->query = http_build_query ($this->params);
  }

  //Header to sign FT
  public function paramsSignDocument($ftstamp){
    $settings = get_option(PHCFXWOOCOMMERCE_PLUGIN_NAME);
    // build our web service full URL
    $settings['backend']['url'] = rtrim($settings['backend']['url'], '/');
    $this->url = "{$settings['backend']['url']}".$this->extraurl."/REST/FtWS/signDocument";
    // Create map with request parameters
    $this->params =  array ('ftstamp' => $ftstamp);
    // Build Http query using params
    $this->query = http_build_query ($this->params);
  }

  //Header to sign FT
  public function paramsGetReportForPrint(){
    $settings = get_option(PHCFXWOOCOMMERCE_PLUGIN_NAME);
    // build our web service full URL
    $settings['backend']['url'] = rtrim($settings['backend']['url'], '/');
    $this->url = "{$settings['backend']['url']}".$this->extraurl."/REST/reportws/getReportsForPrint";
    // Create map with request parameters
    $this->params =  array ('entityname' => 'ft',
                            'numdoc' => $settings['backend']['typeOfInvoice']);
    // Build Http query using params
    $this->query = http_build_query ($this->params);
  }

  //Header to sign FT
  public function paramsSendReportEmail($repstamp, $ftstamp, $emailTo, $emailFrom){
    $settings = get_option(PHCFXWOOCOMMERCE_PLUGIN_NAME);
    // build our web service full URL
    $settings['backend']['url'] = rtrim($settings['backend']['url'], '/');
    $this->url = "{$settings['backend']['url']}".$this->extraurl."/REST/reportws/sendReportEmail";
    // Create map with request parameters
    $this->params = array ('reportEmailVO' => '{"repstamp": "'.$repstamp.'",
                                                "contentType": "pdf",
                                                "signDocument": true,
                                                "items": ["'.$ftstamp.'"],
                                                "serie": "'.$settings['backend']['typeOfOrder'].'",
                                                "oneItemByMail": false,
                                                "sendMultiTo": false,
                                                "sendFrom": "'.$emailFrom.'",
                                                "sendTo": "'.$emailTo.'",
                                                "cc": "",
                                                "bcc": "",
                                                "sendToMyself": false,
                                                "subject": "",
                                                "body": "",
                                                "isBodyHtml": false,
                                                "revisionNumber": 0,
                                                "ChangedFields": {
                                                  "Fields": [],
                                                  "Values": []
                                                },
                                                "Operation": 1,
                                                "logInfo": "",
                                                "isLazyLoaded": false,
                                                "userFields": {
                                                  "fields": []
                                                },
                                                "localeFields": [],
                                                "ousrinis": "",
                                                "ousrdata": "1900-01-01 00:00:00Z",
                                                "ousrhora": "",
                                                "usrinis": "",
                                                "usrdata": "1900-01-01 00:00:00Z",
                                                "usrhora": "",
                                                "syshist": false
                                              }',
                           'forceSendEmail'=> 'true'); 
    // Build Http query using params
    $this->query = http_build_query ($this->params);
  }

  //Header to make logout
  public function paramsLogout(){
    // build our web service full URL
    $settings['backend']['url'] = rtrim($settings['backend']['url'], '/');
    $this->url = "{$settings['backend']['url']}".$this->extraurl."/REST/UserLoginWS/userLogout";
  }

  //Change order status
  public function statusPending() {
    $this->fieldStatus = "Pending";
  }
  //Change order status
  public function statusOnHold() {
    $this->fieldStatus  = "On-Hold";
  }
  //Change order status
  public function statusProcessing() {
    $this->fieldStatus  = "Processing";
  }
  //Change order status
  public function statusRefunded() {
    $this->fieldStatus  = "Refunded";
  }
  //Change order status
  public function statusCompleted() {
    $this->fieldStatus  = "Completed";
  }
  //Change order status
  public function statusCancelled() {
    $this->fieldStatus  = "Cancelled";
  }
  //Change order status
  public function statusFailed() {
    $this->fieldStatus  = "Failed";
  }

  //Communicate with FX backend and obtain type of invoice document and type of order
  public function setCommunicationFx () {
    $settings = get_option(PHCFXWOOCOMMERCE_PLUGIN_NAME);

    //Obtain configuration to make login
    $this->paramsLogin();

    //initial request with login data
    $ch = curl_init();

    //URL to save cookie "ASP.NET_SessionId"
    curl_setopt($ch, CURLOPT_URL, $this->url);
    curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/32.0.1700.107 Chrome/32.0.1700.107 Safari/537.36');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    //Parameters passed to POST

    curl_setopt($ch, CURLOPT_POSTFIELDS, $this->query);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIESESSION, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, '');  //could be empty, but cause problems on some hosts
    curl_setopt($ch, CURLOPT_COOKIEFILE, '');  //could be empty, but cause problems on some hosts
    $response = curl_exec($ch);

    // send response as JSON
    $response = json_decode($response, true); 

    if (curl_error($ch)) {
      $this->writeFileLog('setCommunicationFx', $ch);
      unset($_SESSION['username']);
    } else if(empty($response)){
      $this->writeFileLog('setCommunicationFx', 'EMPTY RESPONSE');
      $this->messagesError("Can't connect to webservice!! There's an empty response");
      unset($_SESSION['username']);
    } else if(isset($response['messages'][0]['messageCodeLocale'])){
      $this->writeFileLog('setCommunicationFx', $response['messages'][0]['messageCodeLocale']);
      $this->messagesError(": Wrong Login! Please check your entered data!");
      unset($_SESSION['username']);
    } else {
      //Save data of login to show in backend.php the other fields (type of invoice and type of order)
      $_SESSION['username'] = $response['result'][0]['username'];

      //Obtain gama of PHC FX
      $this->paramsGetBackEndInfo();

      curl_setopt($ch, CURLOPT_URL, $this->url);
      curl_setopt($ch, CURLOPT_POST, false);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
      $response = curl_exec($ch);
      // send response as JSON
      $response = json_decode($response, true);

      if (curl_error($ch)) {
        $this->writeFileLog('setCommunicationFx2', $ch);
      } else if(empty($response)){
        $this->writeFileLog('setCommunicationFx2', 'EMPTY RESPONSE');
      } else if(isset($response['messages'][0]['messageCodeLocale'])){
        $this->writeFileLog('setCommunicationFx2', $response['messages'][0]['messageCodeLocale']);
      } else {
        $_SESSION['gamaPHCFX'] = $response['result'][0]["gama"];
      }

      //Obtain type invoices
      $this->paramsQuery('TdWS', 'inactivo', 0);

      curl_setopt($ch, CURLOPT_URL, $this->url);
      curl_setopt($ch, CURLOPT_POST, false);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
      $response = curl_exec($ch);
      // send response as JSON
      $response = json_decode($response, true);

      if (curl_error($ch)) {
        $this->writeFileLog('setCommunicationFx3', $ch);
      } else if(empty($response)){
        $this->writeFileLog('setCommunicationFx3', 'EMPTY RESPONSE');
        $this->messagesError("Can't connect to webservice!! There's an empty response");
      } else if(isset($response['messages'][0]['messageCodeLocale'])){
        $this->writeFileLog('setCommunicationFx3', $response['messages'][0]['messageCodeLocale']);
        $this->messagesError(" obtain dropdown with type of invoices! Message from Backend: " . $response['messages'][0]['messageCodeLocale']);
      } else {
        //Create options of dropdownlist invoices
        $i = 0;
        $count = count($response['result']);
        $typeInvoice = array(); 
        $_SESSION['typeOfInvoice'] = '';

        while ($i < $count) {
          $selected_dropdown = '';
          foreach ($response['result'][$i] as $key => $value){
            if($key == "ndoc"){
              $typeInvoice[$i]["ndoc"] = $value;
              if($settings['backend']['typeOfInvoice'] == $value){
                $selected_dropdown = 'selected';
              }
            } else if ($key == "nmdoc"){
              $typeInvoice[$i]["nmdoc"] = $value;     
            }
          }
          $_SESSION['typeOfInvoice'] .= "<option value=" . $typeInvoice[$i]["ndoc"] . " " . $selected_dropdown . ">" . $typeInvoice[$i]["nmdoc"] ."</option><br>";         
          ++$i;
        }

        //Obtain type orders
        $this->paramsQuery('TsWS', 'inactivo', 0);
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
        
        $response = curl_exec($ch);
        // send response as JSON
        $response = json_decode($response, true);

        if (curl_error($ch)) {
          $this->writeFileLog('setCommunicationFx4', $ch);
        } else if(empty($response)){
          $this->writeFileLog('setCommunicationFx4', 'EMPTY RESPONSE');
          $this->messagesError("Can't connect to webservice!! There's an empty response");
        } else if(isset($response['messages'][0]['messageCodeLocale'])){
          $this->writeFileLog('setCommunicationFx4', $response['messages'][0]['messageCodeLocale']);
          $this->messagesError(" obtain dropdown with type of documents! Message from Backend: " . $response['messages'][0]['messageCodeLocale']);
        } else {

          $i = 0;
          $count = count($response['result']);
          $typeInvoice = array(); 
          $_SESSION['typeOfOrder'] = '';
          //Create options of dropdownlist internal documents
          while ($i < $count) {
            $selected_dropdown = '';
            $wrongTypeOrder = false;
            foreach ($response['result'][$i] as $key => $value){
              if($key == "ndos"){
                $typeInvoice[$i]["ndos"] = $value;
                if($settings['backend']['typeOfOrder'] == $value){
                  $selected_dropdown = 'selected';
                }
              } else if ($key == "nmdos"){

                //Obtain type orders
                $this->paramsQuery('BoWS', 'nmdos', $value);

                curl_setopt($ch, CURLOPT_URL, $this->url);
                curl_setopt($ch, CURLOPT_POST, false);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
                
                $response2 = curl_exec($ch);
                // send response as JSON
                $response2 = json_decode($response2, true);  

                //Verify if product contain business
                $x = 0;
                $businessProduct = false;

                if($response['result'][$i]['tsProducts'][$x] != ''){
                  foreach ($response['result'][$i]['tsProducts'][$x] as $key2 => $value2){
                    if($key2 == 'productid' && $value2 == 3){
                      $businessProduct = true;
                    }
                    ++$x;
                  }
                }

                if (curl_error($ch)) {
                  $this->writeFileLog('setCommunicationFx5', $ch);
                } else if(empty($response)){
                  $this->writeFileLog('setCommunicationFx5', 'EMPTY RESPONSE');
                  $this->messagesError("Can't connect to webservice!! There's an empty response");
                } else if(isset($response['messages'][0]['messageCodeLocale'])){
                  $this->writeFileLog('setCommunicationFx5', $response2['messages'][0]['messageCodeLocale']);
                  $this->messagesError(" obtain dropdown with type of documents! Message from Backend: " . $response2['messages'][0]['messageCodeLocale']);
                } else {
                  //Put the same number of order in PHC FX
                  global $wpdb;
                  $table_name = $wpdb->prefix."postmeta";
                  //Obtain next post_id of order in MySQL
                  $query = "SELECT MAX(post_id)+1 as nextPostId FROM %s";
                  $docid = $wpdb->get_row(str_replace("'", "", $wpdb->prepare($query, $table_name)));
           
                  if(($response2['result'][0]['obrano'] == '' || $response2['result'][0]['obrano'] <= $docid->nextPostId) && $response['result'][$i]['bdempresas'] == 'CL' && $businessProduct == true){
                    $wrongTypeOrder = false;
                  } else {
                    $wrongTypeOrder = true;
                  }
                }

                if($wrongTypeOrder == false){
                  $typeInvoice[$i]["nmdos"] = $value;     
                }
              }
            }
            
            if($typeInvoice[$i]["nmdos"] != ''){
              $_SESSION['typeOfOrder'] .= "<option id=" . $typeInvoice[$i]["nmdos"] . " value=" . $typeInvoice[$i]["ndos"] . " " . $selected_dropdown . ">" .  $typeInvoice[$i]["nmdos"] ."</option><br>";   
            }
            ++$i;
          }
        }

        //Obtain warehouses
        $this->paramsQuery2('SaWS');
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
        
        $response = curl_exec($ch);
        // send response as JSON
        $response = json_decode($response, true);

        if (curl_error($ch)) {
          $this->writeFileLog('setCommunicationFx6', $ch);
        } else if(empty($response)){
          $this->writeFileLog('setCommunicationFx6', 'EMPTY RESPONSE');
          $this->messagesError("Can't connect to webservice!! There's an empty response");
        } else if(isset($response['messages'][0]['messageCodeLocale'])){
          $this->writeFileLog('setCommunicationFx6', $response['messages'][0]['messageCodeLocale']);
          $this->messagesError(" obtain dropdown with type of documents! Message from Backend: " . $response['messages'][0]['messageCodeLocale']);
        } else {
          $i = 0;
          $count = count($response['result']);
          $_SESSION['warehouse'] = '';
          //Create options of dropdownlist warehouses
          while ($i < $count) {
            $selected_dropdown = '';
            foreach ($response['result'][$i] as $key => $value){
              if($key == "armazem"){
                $warehousesArray[$i] = $value;
              } 
            }
            ++$i;
          }
          //Make "distinct" like query sql
          $warehousesArray = array_unique($warehousesArray);
          foreach ($warehousesArray as $value) {
            $selected_dropdown = '';
            if($settings['backend']['warehouse'] == $value){
              $selected_dropdown = 'selected';
            }
            $_SESSION['warehouse'] .= "<option id=" . $value . " value=" . $value . " " . $selected_dropdown . ">" .  $value ."</option><br>"; 
          }
        }

        //Obtain warehouses
        $this->paramsQuery2('SaWS');
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
        
        $response = curl_exec($ch);
        // send response as JSON
        $response = json_decode($response, true);

        if (curl_error($ch)) {
          $this->writeFileLog('setCommunicationFx7', $ch);
        } else if(empty($response)){
          $this->writeFileLog('setCommunicationFx7', 'EMPTY RESPONSE');
          $this->messagesError("Can't connect to webservice!! There's an empty response");
        } else if(isset($response['messages'][0]['messageCodeLocale'])){
          $this->writeFileLog('setCommunicationFx7', $response['messages'][0]['messageCodeLocale']);
          $this->messagesError(" obtain dropdown with type of documents! Message from Backend: " . $response['messages'][0]['messageCodeLocale']);
        } else {
          $i = 0;
          $count = count($response['result']);
          $_SESSION['warehouseOrder'] = '';
          //Create options of dropdownlist warehouses
          while ($i < $count) {
            $selected_dropdown = '';
            foreach ($response['result'][$i] as $key => $value){
              if($key == "armazem"){
                $warehousesArray[$i] = $value;
              } 
            }
            ++$i;
          }
          //Make "distinct" like query sql
          $warehousesArray = array_unique($warehousesArray);
          foreach ($warehousesArray as $value) {
            $selected_dropdown = '';
            if($settings['backend']['warehouseOrder'] == $value){
              $selected_dropdown = 'selected';
            }            
            $_SESSION['warehouseOrder'] .= "<option id=" . $value . " value=" . $value . " " . $selected_dropdown . ">" .  $value ."</option><br>"; 
          }
        }

        //Verify if are selected type of internal document and send invoice checkbox
        if(isset($settings['backend']['typeOfInvoice']) && $settings['backend']['createInvoice'] == 'on' && $settings['backend']['typeOfInvoice'] > 0 && isset($settings['backend']['sendInvoice'])){
          //Obtain type invoices
          $this->paramsGetReportForPrint();

          curl_setopt($ch, CURLOPT_URL, $this->url);
          curl_setopt($ch, CURLOPT_POST, false);
          curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
          $response = curl_exec($ch);
          // send response as JSON
          $response = json_decode($response, true);

          if (curl_error($ch)) {
            $this->writeFileLog('setCommunicationFx8', $ch);
          } else if(empty($response)){
            $this->writeFileLog('setCommunicationFx8', 'EMPTY RESPONSE');
            $this->messagesError("Can't connect to webservice!! There's an empty response");
          } else if(isset($response['messages'][0]['messageCodeLocale'])){
            $this->writeFileLog('setCommunicationFx8', $response['messages'][0]['messageCodeLocale']);
            $this->messagesError(" in configuration of send email with FT! Message from Backend: " . $response['messages'][0]['messageCodeLocale']);
          } else {
            //Verify if exists template as default
            $i = 0;
            $count = count($response['result']);
            $sendEmail = false;
            while ($i < $count) {
              foreach ($response['result'][$i] as $key => $value){
                if($key == 'isDefault' && $value == 1){
                  $sendEmail = true;
                  break;
                }
              }
              ++$i;
            }
            //If not exists template as default
            if($sendEmail == false){
              $this->writeFileLog('setCommunicationFx5', 'It is not possible to send email. Please verify your configuration reports of PHC FX');
              $this->messagesError(": It is not possible to send email. Please verify your configuration reports of PHC FX because does not exists any default template");
            }
          }
        }
      }
      //Obtain currency coin of company
      $this->paramsQuery('E1ws', 'estab', 0);
      curl_setopt($ch, CURLOPT_URL, $this->url);
      curl_setopt($ch, CURLOPT_POST, false);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
      $response = curl_exec($ch);
      // send response as JSON
      $response = json_decode($response, true);

      if (curl_error($ch)) {
        $this->writeFileLog('setCommunicationFx9', $ch);
      } else if(empty($response)){
        $this->writeFileLog('setCommunicationFx9', 'EMPTY RESPONSE');
      } else if(isset($response['messages'][0]['messageCodeLocale'])){
        $this->writeFileLog('setCommunicationFx9', $response['messages'][0]['messageCodeLocale']);
      } else {
        if($response['result'][0]['moeda'] != get_option('woocommerce_currency')){
          $this->messagesInformation("Please configure currency in shop according to PHC FX");
          unset($_SESSION['username']);
        }
      }
    }
    //Logout
    $this->paramsLogout();
    curl_setopt($ch, CURLOPT_URL, $this->url);
    curl_setopt($ch, CURLOPT_POST, false);
    $response = curl_exec($ch);
    curl_close ( $ch );
  }

  //Update dropdownlist of type orders
  public function updateTypeOfOrders(){
    $settings = get_option(PHCFXWOOCOMMERCE_PLUGIN_NAME);
    //Obtain configuration to make login
    $this->paramsLogin();
    //initial request with login data
    $ch = curl_init();
    //URL to save cookie "ASP.NET_SessionId"
    curl_setopt($ch, CURLOPT_URL, $this->url);
    curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/32.0.1700.107 Chrome/32.0.1700.107 Safari/537.36');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    //Parameters passed to POST
    curl_setopt($ch, CURLOPT_POSTFIELDS, $this->query);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIESESSION, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, '');  //could be empty, but cause problems on some hosts
    curl_setopt($ch, CURLOPT_COOKIEFILE, '');  //could be empty, but cause problems on some hosts
    $response = curl_exec($ch);
    // send response as JSON
    $response = json_decode($response, true); 

    if (curl_error($ch)) {
      $this->writeFileLog('updateTypeOfOrders', $ch);
    } else if(empty($response)){
      $this->writeFileLog('updateTypeOfOrders', 'EMPTY RESPONSE');
    } else if(isset($response['messages'][0]['messageCodeLocale'])){
      $this->writeFileLog('updateTypeOfOrders', $response['messages'][0]['messageCodeLocale']);
    } else {
      //Obtain type orders
      $this->paramsQuery('TsWS', 'inactivo', 0);
      curl_setopt($ch, CURLOPT_URL, $this->url);
      curl_setopt($ch, CURLOPT_POST, false);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
      
      $response = curl_exec($ch);
      // send response as JSON
      $response = json_decode($response, true);

      if (curl_error($ch)) {
        $this->writeFileLog('updateTypeOfOrders2', $ch);
      } else if(empty($response)){
        $this->writeFileLog('updateTypeOfOrders2', 'EMPTY RESPONSE');
        $this->messagesError("Can't connect to webservice!! There's an empty response");
      } else if(isset($response['messages'][0]['messageCodeLocale'])){
        $this->writeFileLog('updateTypeOfOrders2', $response['messages'][0]['messageCodeLocale']);
        $this->messagesError(" obtain dropdown with type of documents! Message from Backend: " . $response['messages'][0]['messageCodeLocale']);
      } else {
        $i = 0;
        $count = count($response['result']);
        $typeInvoice = array(); 
        //Create options of dropdownlist internal documents
        while ($i < $count) {
          $selected_dropdown = '';
          $wrongTypeOrder = false;
          foreach ($response['result'][$i] as $key => $value){
            if($key == "ndos"){
              $typeInvoice[$i]["ndos"] = $value;
              if($settings['backend']['typeOfOrder'] == $value){
                $selected_dropdown = 'selected';
              }
            } else if ($key == "nmdos"){
              //Obtain type orders
              $this->paramsQuery('BoWS', 'nmdos', $value);
              curl_setopt($ch, CURLOPT_URL, $this->url);
              curl_setopt($ch, CURLOPT_POST, false);
              curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
              
              $response2 = curl_exec($ch);
              // send response as JSON
              $response2 = json_decode($response2, true);  

              //Verify if product contain business
              $x = 0;
              $businessProduct = false;
              foreach ($response['result'][$i]['tsProducts'][$x] as $key2 => $value2){
                if($key2 == 'productid' && $value2 == 3){
                  $businessProduct = true;
                }
                ++$x;
              }

              if (curl_error($ch)) {
                $this->writeFileLog('updateTypeOfOrders3', $ch);
              } else if(empty($response)){
                $this->writeFileLog('updateTypeOfOrders3', 'EMPTY RESPONSE');
                //$this->messagesError("Can't connect to webservice!! There's an empty response");
              } else if(isset($response['messages'][0]['messageCodeLocale'])){
                $this->writeFileLog('updateTypeOfOrders3', $response2['messages'][0]['messageCodeLocale']);
                //$this->messagesError(" obtain dropdown with type of documents! Message from Backend: " . $response2['messages'][0]['messageCodeLocale']);
              } else {
                //Put the same number of order in PHC FX
                global $wpdb;
                $table_name = $wpdb->prefix."postmeta";
                //Obtain next post_id of order in MySQL
                $query = "SELECT MAX(post_id) as nextPostId FROM %s";
                $docid = $wpdb->get_row(str_replace("'", "", $wpdb->prepare($query, $table_name)));
         
                if(($response2['result'][0]['obrano'] == '' || $response2['result'][0]['obrano'] <= $docid->nextPostId) && $response['result'][$i]['bdempresas'] == 'CL' && $businessProduct == true){
                  $wrongTypeOrder = false;
                } else {
                  $wrongTypeOrder = true;
                }
              }

              if($wrongTypeOrder == false){
                $typeInvoice[$i]["nmdos"] = $value;     
              }
            }
          }
          
          if($typeInvoice[$i]["nmdos"] != ''){
            echo "<option id=" . $typeInvoice[$i]["nmdos"] . " value=" . $typeInvoice[$i]["ndos"] . " " . $selected_dropdown . ">" .  $typeInvoice[$i]["nmdos"] ."</option><br>"; 
          }
          ++$i;
        }
      }
    }
    //Logout
    $this->paramsLogout();
    //session_destroy();
    curl_setopt($ch, CURLOPT_URL, $this->url);
    curl_setopt($ch, CURLOPT_POST, false);
    $response = curl_exec($ch);
  }

  //Obtain value of field to PHC FX
  public function statusOfOrder($selectItems, $typeOfOrder){
  	$settings = get_option(PHCFXWOOCOMMERCE_PLUGIN_NAME);
    //Obtain configuration to make login
    $this->paramsLogin();
    //initial request with login data
    $ch = curl_init();
    //URL to save cookie "ASP.NET_SessionId"
    curl_setopt($ch, CURLOPT_URL, $this->url);
    curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/32.0.1700.107 Chrome/32.0.1700.107 Safari/537.36');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    //Parameters passed to POST
    curl_setopt($ch, CURLOPT_POSTFIELDS, $this->query);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIESESSION, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, '');  //could be empty, but cause problems on some hosts
    curl_setopt($ch, CURLOPT_COOKIEFILE, '');  //could be empty, but cause problems on some hosts
    $response = curl_exec($ch);
    // send response as JSON
    $response = json_decode($response, true); 

    if (curl_error($ch)) {
      $this->writeFileLog('statusOfOrder', $ch);
    } else if(empty($response)){
      $this->writeFileLog('statusOfOrder', 'EMPTY RESPONSE');
    } else if(isset($response['messages'][0]['messageCodeLocale'])){
      $this->writeFileLog('statusOfOrder', $response['messages'][0]['messageCodeLocale']);
    } else {
    	//Obtain type invoices
	    $this->paramsQuery4('TsWS', $selectItems, $typeOfOrder);

	    curl_setopt($ch, CURLOPT_URL, $this->url);
	    curl_setopt($ch, CURLOPT_POST, false);
	    curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
	    $response = curl_exec($ch);
	    // send response as JSON
	    $response = json_decode($response, true);

	    if (curl_error($ch)) {
	    	$this->writeFileLog('statusOfOrder2', $ch);
	    } else if(empty($response)){
	        $this->writeFileLog('statusOfOrder2', 'EMPTY RESPONSE');
	    } else if(isset($response['messages'][0]['messageCodeLocale'])){
	        $this->writeFileLog('statusOfOrder2', $response['messages'][0]['messageCodeLocale']);
	    } else {
	    	echo $response['result'][0][$selectItems];
	    }
    }
    //Logout
    $this->paramsLogout();
    //session_destroy();
    curl_setopt($ch, CURLOPT_URL, $this->url);
    curl_setopt($ch, CURLOPT_POST, false);
    $response = curl_exec($ch);
  }

  //Save name of input to show in PHC FX the status order from shop
  public function saveFieldOrderStatus(){
    $settings = get_option(PHCFXWOOCOMMERCE_PLUGIN_NAME);
    //Obtain configuration to make login
    $this->paramsLogin();
    //initial request with login data
    $ch = curl_init();
    //URL to save cookie "ASP.NET_SessionId"
    curl_setopt($ch, CURLOPT_URL, $this->url);
    curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/32.0.1700.107 Chrome/32.0.1700.107 Safari/537.36');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    //Parameters passed to POST
    curl_setopt($ch, CURLOPT_POSTFIELDS, $this->query);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIESESSION, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, '');  //could be empty, but cause problems on some hosts
    curl_setopt($ch, CURLOPT_COOKIEFILE, '');  //could be empty, but cause problems on some hosts
    $response = curl_exec($ch);
    // send response as JSON
    $response = json_decode($response, true); 

    if (curl_error($ch)) {
      $this->writeFileLog('saveFieldOrderStatus', $ch);
    } else if(empty($response)){
      $this->writeFileLog('saveFieldOrderStatus', 'EMPTY RESPONSE');
      $this->messagesError("Can't connect to webservice!! There's an empty response");
    } else if(isset($response['messages'][0]['messageCodeLocale'])){
      $this->writeFileLog('saveFieldOrderStatus', $response['messages'][0]['messageCodeLocale']);
      $this->messagesError(": Wrong Login! Please check your entered data");
    } else {
      //Save data of login to show in backend.php the other fields (type of invoice and type of order)
      $_SESSION['username'] = $response['result'][0]['username'];

      $sanitizeTypeOfOrder = sanitize_text_field( $_POST['phcfx-woocommerce']['backend']['typeOfOrder'] );

      //Obtain type invoices
      $this->paramsQuery('TsWS', 'ndos', $sanitizeTypeOfOrder);

      curl_setopt($ch, CURLOPT_URL, $this->url);
      curl_setopt($ch, CURLOPT_POST, false);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
      $response = curl_exec($ch);
      // send response as JSON
      $response = json_decode($response, true);

      if (curl_error($ch)) {
        $this->writeFileLog('saveFieldOrderStatus2', $ch);
      } else if(empty($response)){
        $this->writeFileLog('saveFieldOrderStatus2', 'EMPTY RESPONSE');
        $this->messagesError("Can't connect to webservice!! There's an empty response");
      } else if(isset($response['messages'][0]['messageCodeLocale'])){
        $this->writeFileLog('saveFieldOrderStatus2', $response['messages'][0]['messageCodeLocale']);
        $this->messagesError(" obtain dropdown with type of invoices! Message from Backend: " . $response['messages'][0]['messageCodeLocale']);
      } else {
        $sanitizeStatusOfOrder = sanitize_text_field( $_POST['phcfx-woocommerce']['backend']['statusOfOrder'] );
        $sanitizeSaveStatusOfOrder = sanitize_text_field( $_POST['phcfx-woocommerce']['backend']['saveStatusOrder'] );

        $response['result'][0][$sanitizeStatusOfOrder] = $sanitizeSaveStatusOfOrder;
        //Update operation
        $response['result'][0]['Operation'] = 2;

        //Save data of client in PHC FX
        $this->paramsSave('TsWS', $response);

        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
        $response = curl_exec($ch);
        // send response as JSON
        $response = json_decode($response, true); 

        if (curl_error($ch)) {
          $this->writeFileLog('saveFieldOrderStatus3', $ch);
        } else if(empty($response)){
          $this->writeFileLog('saveFieldOrderStatus3', 'EMPTY RESPONSE');          
        } else if(isset($response['messages'][0]['messageCodeLocale'])){
          $this->writeFileLog('saveFieldOrderStatus3', $response['messages'][0]['messageCodeLocale']);          
        }
      }
    }
    //Logout
    $this->paramsLogout();
    //session_destroy();
    curl_setopt($ch, CURLOPT_URL, $this->url);
    curl_setopt($ch, CURLOPT_POST, false);
    $response = curl_exec($ch);
  }

  //Create dropdownlist of names that are presented in PHC FX hardcoded
  public function fieldStatusOrder($field){
    switch ($field) {
      case 'nmdesc':
        $field = 'obranome';
        break;
      case 'texto1':
        $field = 'trab1';
        break;
      case 'texto2':
        $field = 'trab2';
        break;
      case 'texto3':
        $field = 'trab3';
        break;
      case 'tmodelo':
        $field = 'maquina';
        break;
      case 'tmarca':
        $field = 'marca';
        break;
      case 'tserie':
        $field = 'serie';
        break;
      default:
        $field = '';
        break;
    }
    return $field;
  }

  //Add new order in PHC FX
  public function addNewOrder(){
    session_start();
    $settings = get_option(PHCFXWOOCOMMERCE_PLUGIN_NAME);
    //Obtain configuration to make login
    $this->paramsLogin();
    //initial request with login data
    $ch = curl_init();
    //URL to save cookie "ASP.NET_SessionId"
    curl_setopt($ch, CURLOPT_URL, $this->url);
    curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/32.0.1700.107 Chrome/32.0.1700.107 Safari/537.36');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    //Parameters passed to POST
    curl_setopt($ch, CURLOPT_POSTFIELDS, $this->query);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIESESSION, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, '');  //could be empty, but cause problems on some hosts
    curl_setopt($ch, CURLOPT_COOKIEFILE, '');  //could be empty, but cause problems on some hosts
    $response = curl_exec($ch);
    // send response as JSON
    $response = json_decode($response, true); 

    if (curl_error($ch)) {
      $this->writeFileLog('addNewOrder', $ch);
    } else if(empty($response)){
      $this->writeFileLog('addNewOrder', 'EMPTY RESPONSE');
    } else if(isset($response['messages'][0]['messageCodeLocale'])){
      $this->writeFileLog('addNewOrder', $response['messages'][0]['messageCodeLocale']);
    } else {
      //Obtain country
      $billing_country = sanitize_text_field( $_REQUEST['billing_country'] );
      $billing_country_ = sanitize_text_field( $_REQUEST['_billing_country'] );

      if($billing_email_ == ''){
        $this->paramsQuery('LocalizationWS', 'nomeabrv', $billing_country);
      } else {
        $this->paramsQuery('LocalizationWS', 'nomeabrv', $billing_country_);
      }

      curl_setopt($ch, CURLOPT_URL, $this->url);
      curl_setopt($ch, CURLOPT_POST, false);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
      $response = curl_exec($ch);
      // send response as JSON
      $response = json_decode($response, true);

      if (curl_error($ch)) {
        $this->writeFileLog('addNewOrder2', $ch);
      } else if(empty($response)){
        $this->writeFileLog('addNewOrder2', 'EMPTY RESPONSE');
      } else if(isset($response['messages'][0]['messageCodeLocale'])){
        $this->writeFileLog('addNewOrder2', $response['messages'][0]['messageCodeLocale']);
      } else {
        $paisesstamp = $response['result'][0]['paisesstamp'];
        $nomePais = $response['result'][0]['nome'];
      }

      //Obtain shipping country 
      $shipping_country = sanitize_text_field( $_REQUEST['shipping_country'] );
      $shipping_country_ = sanitize_text_field( $_REQUEST['_shipping_country'] );

      if($shipping_country_ == ''){
        $this->paramsQuery('LocalizationWS', 'nomeabrv', $shipping_country);
      } else {
        $this->paramsQuery('LocalizationWS', 'nomeabrv', $shipping_country_);
      }

      curl_setopt($ch, CURLOPT_URL, $this->url);
      curl_setopt($ch, CURLOPT_POST, false);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
      $response = curl_exec($ch);
      // send response as JSON
      $response = json_decode($response, true);

      if (curl_error($ch)) {
        $this->writeFileLog('addNewOrder3', $ch);
      } else if(empty($response)){
        $this->writeFileLog('addNewOrder3', 'EMPTY RESPONSE');
      } else if(isset($response['messages'][0]['messageCodeLocale'])){
        $this->writeFileLog('addNewOrder3', $response['messages'][0]['messageCodeLocale']);
      } else {
        $paisesstampShipping = $response['result'][0]['paisesstamp'];
        $nomePaisShipping = $response['result'][0]['nome'];
      }

      //Used to verify coin of PHC FX and woocommerce
      $this->paramsQuery('E1ws', 'estab', 0);
      curl_setopt($ch, CURLOPT_URL, $this->url);
      curl_setopt($ch, CURLOPT_POST, false);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
      $response = curl_exec($ch);
      // send response as JSON
      $response = json_decode($response, true);

      if (curl_error($ch)) {
        $this->writeFileLog('addNewOrder4', $ch);
      } else if(empty($response)){
        $this->writeFileLog('addNewOrder4', 'EMPTY RESPONSE');
      } else if(isset($response['messages'][0]['messageCodeLocale'])){
        $this->writeFileLog('addNewOrder4', $response['messages'][0]['messageCodeLocale']);
      } else {
        //Verify if currency of shop corresponds to PHC FX
        if($response['result'][0]['moeda'] == get_option('woocommerce_currency')){
          //Verify if client already exists in bd
          $billing_email_ = sanitize_text_field( $_REQUEST['_billing_email'] );
          $billing_email = sanitize_text_field( $_REQUEST['billing_email'] );
          $order_received = get_option('woocommerce_checkout_order_received_endpoint');

          unset($_SESSION['listOfSku']);
          unset($_SESSION['listOfQuantity']);
          unset($_SESSION['listOfValueItem']);            

          if (is_array(WC()->cart->cart_contents)){
            $i = 0;
            //Obtain key of wordpress thtat identified the different products in  cart
            foreach (WC()->cart->cart_contents as $key => $value){
              $productsOrder[$i] = $key;       
              ++$i;
            }
            $i = 0;
            $count = count(WC()->cart->cart_contents);
            //Wordpress function to obtain information of cart items
            while ($i < $count) {
              //Obtain info about products in cart
              $productData = WC()->cart->cart_contents[$productsOrder[$i]];
              //obtain reference items in cart
              if($productData['variation_id']!='' && $value['variation_id'] != 0){
                $productReference = wc_get_product( $productData['variation_id'] );
              } else {
                $productReference = wc_get_product( $productData['product_id'] );
              }

              $sku[$i] = $productReference->get_sku();
              //Obtain quantity of the product in cart
              $quantity[$i] = $productData['quantity'];
              //Obtain unit price of the product in cart
              $valueItem[$i] = $productData['line_subtotal'];
              ++$i;
            }
            //Save items of cart
            $_SESSION['listOfSku'] = $sku;
            $_SESSION['listOfQuantity'] = $quantity;
            $_SESSION['listOfValueItem'] = $valueItem;
          }                    

          if(empty($_SESSION['listOfSku'])){
            //Obtain id of order and products
            $order = new WC_Order();
            $order = new WC_Order($order->post->ID);
            $i = 0;
            
            foreach($order->get_items() as $key => $value){
              if($value['variation_id'] != '' && $value['variation_id'] != 0){
                 $product = new WC_Product($value['variation_id']);
              } else {
                 $product = new WC_Product($value['product_id']);
              }
              $sku[$i] = $product->get_sku();
              $quantity[$i] = $value['qty'];
              $valueItem[$i] = $value['line_subtotal'];
              ++$i;
            }
            //Save items of cart
            $_SESSION['listOfSku'] = $sku;
            $_SESSION['listOfQuantity'] = $quantity;
            $_SESSION['listOfValueItem'] = $valueItem;
          }

          //Obtain login of user
          global $current_user;
          $tip_button = false;
          if($current_user->user_email != ''){
            //Verify if client exists
            if($billing_email_ == '' && $billing_email != ''){
              $this->paramsQuery('ClWS', 'email', $billing_email);
            } else if ($billing_email_ != '' && $billing_email == ''){
              $this->paramsQuery('ClWS', 'email', $billing_email_);
            } else {
              $tip_button = true;
            }

            if($tip_button == false){
              curl_setopt($ch, CURLOPT_URL, $this->url);
              curl_setopt($ch, CURLOPT_POST, false);
              curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
              $response = curl_exec($ch);
              // send response as JSON
              $response = json_decode($response, true);        

              if (curl_error($ch)) {
                $this->writeFileLog('addNewOrder5', $ch);
              } else if(empty($response)){
                $this->writeFileLog('addNewOrder5', 'EMPTY RESPONSE');                
              } else if(isset($response['messages'][0]['messageCodeLocale'])){
                $this->writeFileLog('addNewOrder5', $response['messages'][0]['messageCodeLocale']);                
              } else {  
                //Have client
                if(is_array($response['result'][0])){
                  //Save number id of client
                  $_SESSION['nrClient'] = $response['result'][0]['no'];

                  $billing_first_name = sanitize_text_field( $_REQUEST['billing_first_name'] );
                  $billing_last_name = sanitize_text_field( $_REQUEST['billing_last_name'] );
                  $billing_email = sanitize_text_field( $_REQUEST['billing_email'] );
                  $billing_address_1 = sanitize_text_field( $_REQUEST['billing_address_1'] );
                  $billing_city = sanitize_text_field( $_REQUEST['billing_city'] );
                  $billing_phone = sanitize_text_field( $_REQUEST['billing_phone'] );  
                  $billing_postcode = sanitize_text_field( $_REQUEST['billing_postcode'] ); 

                  $billing_first_name_ = sanitize_text_field( $_REQUEST['_billing_first_name'] );
                  $billing_last_name_ = sanitize_text_field( $_REQUEST['_billing_last_name'] );
                  $billing_email_ = sanitize_text_field( $_REQUEST['_billing_email'] );
                  $billing_address_1_ = sanitize_text_field( $_REQUEST['_billing_address_1'] );
                  $billing_city_ = sanitize_text_field( $_REQUEST['_billing_city'] );
                  $billing_phone_ = sanitize_text_field( $_REQUEST['_billing_phone'] );  
                  $billing_postcode_ = sanitize_text_field( $_REQUEST['_billing_postcode'] );                

                  //Used in frontend
                  if($billing_email_ == ''){
                    $response['result'][0]['nome'] = $billing_first_name . " " . $billing_last_name;
                    $response['result'][0]['email'] = $billing_email;
                    $response['result'][0]['morada'] = $billing_address_1;
                    $response['result'][0]['local'] = $billing_city;
					$response['result'][0]['provincia'] = $billing_city;
                    $response['result'][0]['telefone'] = $billing_phone;
                    $response['result'][0]['codpost'] = $billing_postcode;
                  } else { //Used in backend
                    $response['result'][0]['nome'] = $billing_first_name_ . " " . $billing_last_name_;
                    $response['result'][0]['email'] = $billing_email_;
                    $response['result'][0]['morada'] = $billing_address_1_;
                    $response['result'][0]['local'] = $billing_city_;
					$response['result'][0]['provincia'] = $billing_city_;
                    $response['result'][0]['telefone'] = $billing_phone_;
                    $response['result'][0]['codpost'] = $billing_postcode_;
                  }
                  $response['result'][0]['paisesstamp'] = $paisesstamp;
                  $response['result'][0]['pais'] = $nomePais;
                  $response['result'][0]['Operation'] = 2;

                  //Save data of client in PHC FX
                  $this->paramsSave('ClWS', $response);

                  curl_setopt($ch, CURLOPT_URL, $this->url);
                  curl_setopt($ch, CURLOPT_POST, false);
                  curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
                  $response = curl_exec($ch);
                  // send response as JSON
                  $response = json_decode($response, true); 

                  if (curl_error($ch)) {
                    $this->writeFileLog('addNewOrder6', $ch);
                  } else if(empty($response)){
                    $this->writeFileLog('addNewOrder6', 'EMPTY RESPONSE');                    
                  } else if(isset($response['messages'][0]['messageCodeLocale'])){
                    $this->writeFileLog('addNewOrder6', $response['messages'][0]['messageCodeLocale']);
                    if($_SESSION['nrClient'] != ''){
                      //Inserted client in bd
                      $createClientSuccess = 1;
                    }                    
                  } else {
                    //Inserted client in bd
                    $createClientSuccess = 1;
                  }
                //Dont have client - INSERT
                } else {
                  //Obtain new instance of client
                  $this->paramsNewInstance('ClWS', 0);
                  curl_setopt($ch, CURLOPT_URL, $this->url);
                  curl_setopt($ch, CURLOPT_POST, false);
                  curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
                  $response = curl_exec($ch);
                  // send response as JSON
                  $response = json_decode($response, true); 

                  if (curl_error($ch)) {
                    $this->writeFileLog('addNewOrder7', $ch);
                  } else if(empty($response)){
                    $this->writeFileLog('addNewOrder7', 'EMPTY RESPONSE');                    
                  } else if(isset($response['messages'][0]['messageCodeLocale'])){
                    $this->writeFileLog('addNewOrder7', $response['messages'][0]['messageCodeLocale']);                    
                  } else {                  
                    //Save number id of client
                    $_SESSION['nrClient'] = $response['result'][0]['no'];

                    $billing_first_name = sanitize_text_field( $_REQUEST['billing_first_name'] );
                    $billing_last_name = sanitize_text_field( $_REQUEST['billing_last_name'] );
                    $billing_email = sanitize_text_field( $_REQUEST['billing_email'] );
                    $billing_address_1 = sanitize_text_field( $_REQUEST['billing_address_1'] );
                    $billing_city = sanitize_text_field( $_REQUEST['billing_city'] );
                    $billing_phone = sanitize_text_field( $_REQUEST['billing_phone'] );  
                    $billing_postcode = sanitize_text_field( $_REQUEST['billing_postcode'] ); 

                    $billing_first_name_ = sanitize_text_field( $_REQUEST['_billing_first_name'] );
                    $billing_last_name_ = sanitize_text_field( $_REQUEST['_billing_last_name'] );
                    $billing_email_ = sanitize_text_field( $_REQUEST['_billing_email'] );
                    $billing_address_1_ = sanitize_text_field( $_REQUEST['_billing_address_1'] );
                    $billing_city_ = sanitize_text_field( $_REQUEST['_billing_city'] );
                    $billing_phone_ = sanitize_text_field( $_REQUEST['_billing_phone'] );  
                    $billing_postcode_ = sanitize_text_field( $_REQUEST['_billing_postcode'] );                

                    //Used in frontend
                    if($billing_email_ == ''){
                      $response['result'][0]['nome'] = $billing_first_name . " " . $billing_last_name;
                      $response['result'][0]['email'] = $billing_email;
                      $response['result'][0]['morada'] = $billing_address_1;
                      $response['result'][0]['local'] = $billing_city;
					  $response['result'][0]['provincia'] = $billing_city;
                      $response['result'][0]['telefone'] = $billing_phone;
                      $response['result'][0]['codpost'] = $billing_postcode;
                    } else { //Used in backend
                      $response['result'][0]['nome'] = $billing_first_name_ . " " . $billing_last_name_;
                      $response['result'][0]['email'] = $billing_email_;
                      $response['result'][0]['morada'] = $billing_address_1_;
                      $response['result'][0]['local'] = $billing_city_;
					  $response['result'][0]['provincia'] = $billing_city_;
                      $response['result'][0]['telefone'] = $billing_phone_;
                      $response['result'][0]['codpost'] = $billing_postcode_;
                    }
                    $response['result'][0]['paisesstamp'] = $paisesstamp;
                    $response['result'][0]['pais'] = $nomePais;

                    //Save data of client in PHC FX
                    $this->paramsSave('ClWS', $response);

                    curl_setopt($ch, CURLOPT_URL, $this->url);
                    curl_setopt($ch, CURLOPT_POST, false);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
                    $response = curl_exec($ch);
                    // send response as JSON
                    $response = json_decode($response, true); 

                    if (curl_error($ch)) {
                      $this->writeFileLog('addNewOrder8', $ch);
                    } else if(empty($response)){
                      $this->writeFileLog('addNewOrder8', 'EMPTY RESPONSE');                      
                    } else if(isset($response['messages'][0]['messageCodeLocale'])){
                      $this->writeFileLog('addNewOrder8', $response['messages'][0]['messageCodeLocale']);
                       if($_SESSION['nrClient'] != ''){
                        //Inserted client in bd
                        $createClientSuccess = 1;
                      }                      
                    } else {
                      //Inserted client in bd
                      $createClientSuccess = 1;
                    }
                  }
                }
              }
            }
          } else {
            //Verify if existis generic client
            $this->paramsQuery('ClWS', 'clivd', '1');
            curl_setopt($ch, CURLOPT_URL, $this->url);
            curl_setopt($ch, CURLOPT_POST, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
            $response = curl_exec($ch);
            // send response as JSON
            $response = json_decode($response, true);             

            if (curl_error($ch)) {
              $this->writeFileLog('addNewOrder9', $ch);
            } else if(empty($response)){
              $this->writeFileLog('addNewOrder9', 'EMPTY RESPONSE');              
            } else if(isset($response['messages'][0]['messageCodeLocale'])){
              $this->writeFileLog('addNewOrder9', $response['messages'][0]['messageCodeLocale']);              
            } else {  
              //Have generic client
              if(is_array($response['result'][0])){
                //Save number id of client
                $_SESSION['nrClient'] = $response['result'][0]['no'];
                //Obtain client from PHC FX
                $createClientSuccess = 1;
              //Dont have generic client
              } else {
                //Obtain new instance of client
                $this->paramsNewInstance('ClWS', 0);
                curl_setopt($ch, CURLOPT_URL, $this->url);
                curl_setopt($ch, CURLOPT_POST, false);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
                $response = curl_exec($ch);
                // send response as JSON
                $response = json_decode($response, true); 

                if (curl_error($ch)) {
                  $this->writeFileLog('addNewOrder10', $ch);
                } else if(empty($response)){
                  $this->writeFileLog('addNewOrder10', 'EMPTY RESPONSE');
                  //$this->sendEmail(utf8_decode("It is not possible to create client!<br/><br/>Can't connect to webservice!! There's an empty response!<br/><br/><b>Please insert your client and order in PHC FX manually</b>"));
                } else if(isset($response['messages'][0]['messageCodeLocale'])){
                  $this->writeFileLog('addNewOrder10', $response['messages'][0]['messageCodeLocale']);
                  //$this->sendEmail(utf8_decode("It is not possible to create client!<br/><br/>Error from Backend: " . $response['messages'][0]['messageCodeLocale'] . "!<br/><br/><b>Please insert your client and order in PHC FX manually</b>"));
                } else {
                  $response['result'][0]['nome'] = 'Generic Client';
                  $response['result'][0]['clivd'] = true;
                  
                  //Save number id of client
                  $_SESSION['nrClient'] = $response['result'][0]['no'];

                  //Save data of client in PHC FX
                  $this->paramsSave('ClWS', $response);

                  curl_setopt($ch, CURLOPT_URL, $this->url);
                  curl_setopt($ch, CURLOPT_POST, false);
                  curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
                  $response = curl_exec($ch);
                  // send response as JSON
                  $response = json_decode($response, true); 

                  if (curl_error($ch)) {
                    $this->writeFileLog('addNewOrder11', $ch);
                  } else if(empty($response)){
                    $this->writeFileLog('addNewOrder11', 'EMPTY RESPONSE');                    
                  } else if(isset($response['messages'][0]['messageCodeLocale'])){
                    $this->writeFileLog('addNewOrder11', $response['messages'][0]['messageCodeLocale']);
                    if($_SESSION['nrClient'] != ''){
                      //Inserted client in bd
                      $createClientSuccess = 1;
                    }                    
                  } else {
                    //Inserted client in bd
                    $createClientSuccess = 1;
                  }
                }
              }
            }
          }

          //If client is created/obtained with success
          if($createClientSuccess == 1){
            //If in settings of backoffice is checked option "create invoice"
            if($settings['backend']['createInvoice'] == 'on'){
              //See if type of order is configured
              if(!empty($settings['backend']['typeOfOrder']) && $settings['backend']['typeOfOrder'] != 0){
                $post_ID = sanitize_text_field( $_REQUEST['post_ID'] );

                if($post_ID == ''){
                  $post_ID = sanitize_text_field( $_REQUEST['order_id'] );
                }

                //Obtain stamp of internal document based in order
                $docid = get_post_meta($post_ID);
                $filterItem = "bostamp";
                $valueItem = $docid['_docid'][0];
                
                //Verify if exists in bd
                $this->paramsQuery('BoWS', $filterItem, $valueItem);

                curl_setopt($ch, CURLOPT_URL, $this->url);
                curl_setopt($ch, CURLOPT_POST, false);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
                $response = curl_exec($ch);
                // send response as JSON
                $response = json_decode($response, true);    

                if (curl_error($ch)) {
                  $this->writeFileLog('addNewOrder12', $ch);
                } else if(empty($response)){
                  $this->writeFileLog('addNewOrder12', 'EMPTY RESPONSE');                    
                } else if(isset($response['messages'][0]['messageCodeLocale'])){
                  $this->writeFileLog('addNewOrder12', $response['messages'][0]['messageCodeLocale']);                    
                } else {
                  //If internal document doesn't exists
                  if(empty($response['result'][0])){
                    //Obtain new instance of Bo
                    $this->paramsNewInstance('BoWS', $settings['backend']['typeOfOrder']);

                    curl_setopt($ch, CURLOPT_URL, $this->url);
                    curl_setopt($ch, CURLOPT_POST, false);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
                    $response = curl_exec($ch);
                    // send response as JSON
                    $response = json_decode($response, true); 

                    if (curl_error($ch)) {
                      $this->writeFileLog('addNewOrder13', $ch);
                    } else if(empty($response)){
                      $this->writeFileLog('addNewOrder13', 'EMPTY RESPONSE');                        
                    } else if(isset($response['messages'][0]['messageCodeLocale'])){
                      $this->writeFileLog('addNewOrder13', $response['messages'][0]['messageCodeLocale']);                        
                    } else {
                      //Obtain VO with updated Bo
                      $this->paramsActBo($response['result'][0]['bostamp'], $_SESSION['nrClient']);
                      curl_setopt($ch, CURLOPT_URL, $this->url);
                      curl_setopt($ch, CURLOPT_POST, false);
                      curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
                      $response = curl_exec($ch);
                      // send response as JSON
                      $response = json_decode($response, true); 

                      //Obtain VO with updated Bo
                      $this->paramsNewInstanceByRef('BoWS', 'addNewBIsByRef', 'IdBoStamp', 'biStampEditing', $response['result'][0]['bostamp'], $_SESSION['listOfSku']);
                      curl_setopt($ch, CURLOPT_URL, $this->url);
                      curl_setopt($ch, CURLOPT_POST, false);
                      curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
                      $response = curl_exec($ch);
                      // send response as JSON
                      $response = json_decode($response, true); 

                      if (curl_error($ch)) {
                        $this->writeFileLog('addNewOrder14', $ch);
                      } else if(empty($response)){
                        $this->writeFileLog('addNewOrder14', 'EMPTY RESPONSE');                          
                      } else if(isset($response['messages'][0]['messageCodeLocale'])){
                        $this->writeFileLog('addNewOrder14', $response['messages'][0]['messageCodeLocale']);                          
                      } else {
                        //Obtain VO with updated Bi and Bo
                        //Update list of Quantity
                        if (is_array($_SESSION['listOfQuantity'])){
                          foreach ($_SESSION['listOfQuantity'] as $key => $value){
                            if($response['result'][0]['bis'][$key]['qtt'] != $value){
                              $response['result'][0]['bis'][$key]['qtt'] = $value;
                            }           
                          }
                        }
                        //Update list of Value Item
                        if (is_array($_SESSION['listOfValueItem'])){
                          foreach ($_SESSION['listOfValueItem'] as $key => $value){
                            if($response['result'][0]['bis'][$key]['ettdeb'] != $value){
                              $response['result'][0]['bis'][$key]['ettdeb'] = $value;
                            }            
                          }
                        }
                        //Eliminate comercial discount
                        if (is_array($_SESSION['listOfSku'])){
                          foreach ($_SESSION['listOfSku'] as $key => $value){                            
                            //Eliminate discount in field "desconto"
                            $response['result'][0]['bis'][$key]['desconto'] = 0;                               
                            //Eliminate discount in field "desconto"
                            $response['result'][0]['bis'][$key]['desc2'] = 0;
                            //Eliminate discount in field "desc2"
                            $response['result'][0]['bis'][$key]['desc2'] = 0;
                            //Eliminate discount in field "desc3"
                            $response['result'][0]['bis'][$key]['desc3'] = 0;
                            //Eliminate discount in field "desc4"
                            $response['result'][0]['bis'][$key]['desc4'] = 0;
                            //Eliminate discount in field "desc5"
                            $response['result'][0]['bis'][$key]['desc5'] = 0;
                            //Eliminate discount in field "desc6"
                            $response['result'][0]['bis'][$key]['desc6'] = 0;                                                              
                            //Eliminate discount in field "desc6"
                            $response['result'][0]['bis'][$key]['desc6'] = 0;
                          }
                        }
                        //If VO yet exists
                        if(isset($response['result'][0])){
                          //Obtain field in PHC FX to put status order
                          $statusOrderShop = $this->fieldStatusOrder($settings['backend']['statusOfOrder']);
                          if(!empty($statusOrderShop)){
                            $response['result'][0][$statusOrderShop] = $this->fieldStatus;
                          }

                          $response['result'][0]['paisesstampto'] = $paisesstampShipping;
                          $response['result'][0]['paisto'] = $nomePaisShipping;

                          $shipping_address_1 = sanitize_text_field( $_REQUEST['shipping_address_1'] );
                          $shipping_address_1_ = sanitize_text_field( $_REQUEST['_shipping_address_1'] );

                          if($shipping_address_1 != ''){
                            $response['result'][0]['moradato'] = $shipping_address_1;
                          } else if ($shipping_address_1_ != ''){
                            $response['result'][0]['moradato'] = $shipping_address_1_;
                          }    

                          $shipping_city = sanitize_text_field( $_REQUEST['shipping_city'] );
                          $shipping_city_ = sanitize_text_field( $_REQUEST['_shipping_city'] );


                          if($shipping_city != ''){
                            $response['result'][0]['localto'] = $shipping_city;
                          } else if ($shipping_city_ != ''){
                            $response['result'][0]['localto'] = $shipping_city_;
                          }

                          $shipping_postcode = sanitize_text_field( $_REQUEST['shipping_postcode'] );
                          $shipping_postcode_ = sanitize_text_field( $_REQUEST['_shipping_postcode'] );

                          if($shipping_postcode != ''){
                            $response['result'][0]['codpostto'] = $shipping_postcode;
                          } else if ($shipping_postcode_ != ''){
                            $response['result'][0]['codpostto'] = $shipping_postcode_;
                          }

                          $billing_address_1 = sanitize_text_field( $_REQUEST['billing_address_1'] );
                          $billing_city = sanitize_text_field( $_REQUEST['billing_city'] );
                          $billing_phone = sanitize_text_field( $_REQUEST['billing_phone'] );  
                          $billing_postcode = sanitize_text_field( $_REQUEST['billing_postcode'] ); 

                          $billing_address_1_ = sanitize_text_field( $_REQUEST['_billing_address_1'] );
                          $billing_city_ = sanitize_text_field( $_REQUEST['_billing_city'] );
                          $billing_phone_ = sanitize_text_field( $_REQUEST['_billing_phone'] );  
                          $billing_postcode_ = sanitize_text_field( $_REQUEST['_billing_postcode'] );                

                          //Used in frontend
                          if($billing_email_ == ''){
                            $response['result'][0]['morada'] = $billing_address_1;
                            $response['result'][0]['local'] = $billing_city;
							$response['result'][0]['provincia'] = $billing_city;
                            $response['result'][0]['telefone'] = $billing_phone;
                            $response['result'][0]['codpost'] = $billing_postcode;
                          } else { //Used in backend
                            $response['result'][0]['morada'] = $billing_address_1_;
                            $response['result'][0]['local'] = $billing_city_;
							$response['result'][0]['provincia'] = $billing_city_;
                            $response['result'][0]['telefone'] = $billing_phone_;
                            $response['result'][0]['codpost'] = $billing_postcode_;
                          }

                          //Insert
                          $response['result'][0]['Operation'] = 1;

    					  //Put the same number of order in PHC FX
                          global $wpdb;
                          $table_name = $wpdb->prefix."postmeta";
                          //Obtain next post_id of order in MySQL
                          $query = "SELECT MAX(post_id) as nextPostId FROM %s";
                          $docid = $wpdb->get_row(str_replace("'", "", $wpdb->prepare($query, $table_name)));
                          $response['result'][0]['obrano'] = $docid->nextPostId;
						  
                          //Save internal document
                          $this->paramsSave('BoWS', $response);
                          curl_setopt($ch, CURLOPT_URL, $this->url);
                          curl_setopt($ch, CURLOPT_POST, false);
                          curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
                          $response = curl_exec($ch);
                          // send response as JSON
                          $response = json_decode($response, true); 

                          if (curl_error($ch)) {
                            $this->writeFileLog('addNewOrder15', $ch);
                          } else if(empty($response)){
                            $this->writeFileLog('addNewOrder15', 'EMPTY RESPONSE');                              
                          } else if(isset($response['messages'][0]['messageCodeLocale'])){
                            $this->writeFileLog('addNewOrder15', $response['messages'][0]['messageCodeLocale']);
                          } else {
                            $response['result'][0]['paisesstampto'] = $paisesstampShipping;
                            $response['result'][0]['paisto'] = $nomePaisShipping;

                            $shipping_address_1 = sanitize_text_field( $_REQUEST['shipping_address_1'] );
                            $shipping_address_1_ = sanitize_text_field( $_REQUEST['_shipping_address_1'] );

                            if($shipping_address_1 != ''){
                              $response['result'][0]['moradato'] = $shipping_address_1;
                            } else if ($shipping_address_1_ != ''){
                              $response['result'][0]['moradato'] = $shipping_address_1_;
                            }      

                            $shipping_city = sanitize_text_field( $_REQUEST['shipping_city'] );
                            $shipping_city_ = sanitize_text_field( $_REQUEST['_shipping_city'] );

                            if($shipping_city != ''){
                              $response['result'][0]['localto'] = $shipping_city;
                            } else if ($shipping_city_ != ''){
                              $response['result'][0]['localto'] = $shipping_city_;
                            }

                            $shipping_postcode = sanitize_text_field( $_REQUEST['shipping_postcode'] );
                            $shipping_postcode_ = sanitize_text_field( $_REQUEST['_shipping_postcode'] );

                            if($shipping_postcode != ''){
                              $response['result'][0]['codpostto'] = $shipping_postcode;
                            } else if ($shipping_postcode_ != ''){
                              $response['result'][0]['codpostto'] = $shipping_postcode_;
                            }
                            //Update
                            $response['result'][0]['Operation'] = 2;

                            //Save internal document
                            $this->paramsSave('BoWS', $response);
                            curl_setopt($ch, CURLOPT_URL, $this->url);
                            curl_setopt($ch, CURLOPT_POST, false);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
                            $response = curl_exec($ch);
                            // send response as JSON
                            $response = json_decode($response, true); 

                            if (curl_error($ch)) {
                              $this->writeFileLog('addNewOrder16', $ch);
                            } else if(empty($response)){
                              $this->writeFileLog('addNewOrder16', 'EMPTY RESPONSE');                                
                            } else if(isset($response['messages'][0]['messageCodeLocale'])){
                              $this->writeFileLog('addNewOrder16', $response['messages'][0]['messageCodeLocale']);                                
                            } else {
                              $response = $response['result'][0];

                              global $wpdb;
                              $table_name = $wpdb->prefix."postmeta";
                              //Obtain next post_id of order in MySQL
                              $query = "SELECT MAX(post_id) as nextPostId FROM %s";
                              $docid = $wpdb->get_row(str_replace("'", "", $wpdb->prepare($query, $table_name)));

                              //add to table postmeta a key of order and stamp of internal document
                              add_post_meta($docid->nextPostId, '_docid', $response['bostamp']);

                              //Save data of Bo to save FT
                              $_SESSION['responseBo'] = $response;
                            }
                          }
                        }
                      }
                    }
                  //If internal document exists in system
                  } else {
                    $billing_email_ = sanitize_text_field( $_REQUEST['_billing_email'] );
                    $billing_first_name_ = sanitize_text_field( $_REQUEST['_billing_first_name'] );
                    $billing_last_name_ = sanitize_text_field( $_REQUEST['_billing_last_name'] );
                    $billing_address_1_ = sanitize_text_field( $_REQUEST['_billing_address_1'] );
                    $billing_city_ = sanitize_text_field( $_REQUEST['_billing_city'] );
                    $billing_phone_ = sanitize_text_field( $_REQUEST['_billing_phone'] );  
                    $billing_postcode_ = sanitize_text_field( $_REQUEST['_billing_postcode'] );

                    $billing_first_name = sanitize_text_field( $_REQUEST['billing_first_name'] );
                    $billing_last_name = sanitize_text_field( $_REQUEST['billing_last_name'] );
                    $billing_email = sanitize_text_field( $_REQUEST['billing_email'] );
                    $billing_address_1 = sanitize_text_field( $_REQUEST['billing_address_1'] );
                    $billing_city = sanitize_text_field( $_REQUEST['billing_city'] );
                    $billing_phone = sanitize_text_field( $_REQUEST['billing_phone'] );  
                    $billing_postcode = sanitize_text_field( $_REQUEST['billing_postcode'] );

                    //Used in frontend
                    if($billing_email_ == ''){
                      $response['result'][0]['nome'] = $billing_first_name . " " . $billing_last_name;
                      $response['result'][0]['email'] = $billing_email;
                      $response['result'][0]['morada'] = $billing_address_1;
                      $response['result'][0]['local'] = $billing_city;
					  $response['result'][0]['provincia'] = $billing_city;
                      $response['result'][0]['telefone'] = $billing_phone;
                      $response['result'][0]['codpost'] = $billing_postcode;
                    } else { //Used in backend
                      $response['result'][0]['nome'] = $billing_first_name_ . " " . $billing_last_name_;
                      $response['result'][0]['email'] = $billing_email_;
                      $response['result'][0]['morada'] = $billing_address_1_;
                      $response['result'][0]['local'] = $billing_city_;
					  $response['result'][0]['provincia'] = $billing_city_;
                      $response['result'][0]['telefone'] = $billing_phone_;
                      $response['result'][0]['codpost'] = $billing_postcode_;
                    } 
                    $response['result'][0]['pais'] = $nomePais;
                    $response['result'][0]['paisesstamp'] = $paisesstamp;   

                    $result['result'][0]['paisesstampfrom'] = $paisesstampShipping;
                    $result['result'][0]['paisto'] = $nomePaisShipping;

                    $shipping_address_1 = sanitize_text_field( $_REQUEST['shipping_address_1'] );
                    $shipping_address_1_ = sanitize_text_field( $_REQUEST['_shipping_address_1'] );

                    if($shipping_address_1 != ''){
                      $result['result'][0]['moradato'] = $shipping_address_1;
                    } else if ($shipping_address_1_ != ''){
                      $result['result'][0]['moradato'] = $shipping_address_1_;
                    }

                    $shipping_city = sanitize_text_field( $_REQUEST['shipping_city'] );
                    $shipping_city_ = sanitize_text_field( $_REQUEST['_shipping_city'] );

                    if($shipping_city != ''){
                      $result['result'][0]['localto'] = $shipping_city;
                    } else if ($shipping_city_ != ''){
                      $result['result'][0]['localto'] = $shipping_city_;
                    }

                    $shipping_postcode = sanitize_text_field( $_REQUEST['shipping_postcode'] );
                    $shipping_postcode_ = sanitize_text_field( $_REQUEST['_shipping_postcode'] );

                    if($shipping_postcode != ''){
                      $result['result'][0]['codpostto'] = $shipping_postcode;
                    } else if ($shipping_postcode_ != ''){
                      $result['result'][0]['codpostto'] = $shipping_postcode_;
                    }

                    //Obtain VO with updated Bi and Bo
                    if (is_array($_SESSION['listOfQuantity'])){
                      foreach ($_SESSION['listOfQuantity'] as $key => $value){
                        if($response['result'][0]['bis'][$key]['qtt'] != $value){
                          $this->paramsActBi($response['result'][0]['bostamp'], $response['result'][0]['bis'][$key]['bistamp'], 'qtt', $value);
                          curl_setopt($ch, CURLOPT_URL, $this->url);
                          curl_setopt($ch, CURLOPT_POST, false);
                          curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
                          $response = curl_exec($ch); 
                          // send response as JSON
                          $response = json_decode($response, true);         

                          if (curl_error($ch)) {
                            $this->writeFileLog('addNewOrder11', $ch);
                          } else if(empty($response)){
                            $this->writeFileLog('addNewOrder11', 'EMPTY RESPONSE');                              
                            break;
                          } else if(isset($response['messages'][0]['messageCodeLocale'])){
                            $this->writeFileLog('addNewOrder11', $response['messages'][0]['messageCodeLocale']);                              
                            break;
                          } 
                        }           
                      }
                    }
                    if (is_array($_SESSION['listOfValueItem'])){
                      foreach ($_SESSION['listOfValueItem'] as $key => $value){
                        if($response['result'][0]['bis'][$key]['ettdeb'] != $value){
                          $this->paramsActBi($response['result'][0]['bostamp'], $response['result'][0]['bis'][$key]['bistamp'], 'ettdeb', $value);

                          curl_setopt($ch, CURLOPT_URL, $this->url);
                          curl_setopt($ch, CURLOPT_POST, false);
                          curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
                          $response = curl_exec($ch);
                          // send response as JSON
                          $response = json_decode($response, true); 

                          if (curl_error($ch)) {
                            $this->writeFileLog('addNewOrder17', $ch);
                          } else if(empty($response)){
                            $this->writeFileLog('addNewOrder17', 'EMPTY RESPONSE');
                            //$this->sendEmail(utf8_decode("It is not possible to create line in internal document!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/>Please insert your line in internal document in PHC FX manually"));
                            break;
                          } else if(isset($response['messages'][0]['messageCodeLocale'])){
                            $this->writeFileLog('addNewOrder17', $response['messages'][0]['messageCodeLocale']);
                            //$this->sendEmail(utf8_decode("It is not possible to create line in internal document!<br/><br/>Error from Backend: " . $response['messages'][0]['messageCodeLocale'] . "!<br/><br/><b>Please insert your line in internal document in PHC FX manually</b>"));
                            break;
                          }     
                        }            
                      }
                    }
                    //Obtain status
                    $statusOrderShop = $this->fieldStatusOrder($settings['backend']['statusOfOrder']);

                    //write in VO the status
                    if(!empty($statusOrderShop)){
                      $response['result'][0][$statusOrderShop] = $this->fieldStatus;
                    }
                    $response['result'][0]['Operation'] = 2;

                    $this->paramsQuery('ClWS', 'no', $_SESSION['nrClient']);
                    curl_setopt($ch, CURLOPT_URL, $this->url);
                    curl_setopt($ch, CURLOPT_POST, false);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
                    $response2 = curl_exec($ch);
                    // send response as JSON
                    $response2 = json_decode($response2, true); 

                    if (curl_error($ch)) {
                      $this->writeFileLog('addNewOrder18', $ch);
                    } else if(empty($response2)){
                      $this->writeFileLog('addNewOrder18', 'EMPTY RESPONSE');
                      //$this->sendEmail(utf8_decode("It is not possible to create client!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/>Please insert your internal document in PHC FX manually"));
                    } else if(isset($response2['messages'][0]['messageCodeLocale'])){
                      $this->writeFileLog('addNewOrder18', $response2['messages'][0]['messageCodeLocale']);
                      //$this->sendEmail(utf8_decode("It is not possible to save header of internal document!<br/><br/>Error from Backend: " . $response['messages'][0]['messageCodeLocale'] . "!<br/><br/><b>Please insert your internal document in PHC FX manually</b>"));
                    } else {
                      $response['result'][0]['nome'] = $response2['result'][0]['nome'];
                    }
                    
                    if(isset($response)){
                      //Save internal document
                      $this->paramsSave('BoWS', $response);
                      curl_setopt($ch, CURLOPT_URL, $this->url);
                      curl_setopt($ch, CURLOPT_POST, false);
                      curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
                      $response = curl_exec($ch);
                      // send response as JSON
                      $response = json_decode($response, true); 

                      if (curl_error($ch)) {
                        $this->writeFileLog('addNewOrder19', $ch);
                      } else if(empty($response)){
                        $this->writeFileLog('addNewOrder19', 'EMPTY RESPONSE');                          
                      } else if(isset($response['messages'][0]['messageCodeLocale'])){
                        $this->writeFileLog('addNewOrder19', $response['messages'][0]['messageCodeLocale']);                          
                      } else {
                        $response = $response['result'][0];

                        global $wpdb;
                        $table_name = $wpdb->prefix."postmeta";
                        //Obtain next post_id of order in MySQL
                        $query = "SELECT MAX(post_id)+1 as nextPostId FROM %s";
                        $docid = $wpdb->get_row(str_replace("'", "", $wpdb->prepare($query, $table_name)));

                        //add to table postmeta a key of order and stamp of internal document
                        add_post_meta($docid->nextPostId, '_docid', $response['bostamp']);

                        //Save data of Bo to save FT
                        $_SESSION['responseBo'] = $response;
                      }
                    }
                  }
                }
              }
            }
          } else if(($createClientSuccess == '' || $createClientSuccess == 0) && ($tip_button == true || $tip_button == 1)){
            //Verify if exists in bd
            $this->paramsQuery('BoWS', 'obrano', sanitize_text_field( $_REQUEST['order_id'] ));
            curl_setopt($ch, CURLOPT_URL, $this->url);
            curl_setopt($ch, CURLOPT_POST, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
            $response = curl_exec($ch);
            // send response as JSON
            $response = json_decode($response, true);   

            if (curl_error($ch)) {
              $this->writeFileLog('addNewOrder20', $ch);
            } else if(empty($response)){
              $this->writeFileLog('addNewOrder20', 'EMPTY RESPONSE');                
            } else if(isset($response['messages'][0]['messageCodeLocale'])){
              $this->writeFileLog('addNewOrder20', $response['messages'][0]['messageCodeLocale']);                
            } else {
              //Obtain status
              $statusOrderShop = $this->fieldStatusOrder($settings['backend']['statusOfOrder']);

              //write in VO the status
              if(!empty($statusOrderShop)){
                $response['result'][0][$statusOrderShop] = $this->fieldStatus;
              }
              $response['result'][0]['Operation'] = 2;

              //Save internal document
              $this->paramsSave('BoWS', $response);
              curl_setopt($ch, CURLOPT_URL, $this->url);
              curl_setopt($ch, CURLOPT_POST, false);
              curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
              $response = curl_exec($ch);
              // send response as JSON
              $response = json_decode($response, true); 

              if (curl_error($ch)) {
                $this->writeFileLog('addNewOrder21', $ch);
              } else if(empty($response)){
                $this->writeFileLog('addNewOrder21', 'EMPTY RESPONSE');                  
              } else if(isset($response['messages'][0]['messageCodeLocale'])){
                $this->writeFileLog('addNewOrder21', $response['messages'][0]['messageCodeLocale']);                  
              } 
            }
          }
        } else {
          //Show message with error in Wordpress
          $this->messagesInformation("Please configure currency in shop according to PHC FX");
          $this->writeFileLog('coin', 'Please configure currency in shop according to PHC FX');    
          unset($_SESSION['username']);
        }
      }
    }
    //Logout
    $this->paramsLogout();
    curl_setopt($ch, CURLOPT_URL, $this->url);
    curl_setopt($ch, CURLOPT_POST, false);
    $response = curl_exec($ch);
  }

  //If status of order is cancelled or failed
  public function cancelOrder(){
    $settings = get_option(PHCFXWOOCOMMERCE_PLUGIN_NAME);
    //Obtain configuration to make login
    $this->paramsLogin();
    //initial request with login data
    $ch = curl_init();
    //URL to save cookie "ASP.NET_SessionId"
    curl_setopt($ch, CURLOPT_URL, $this->url);
    curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/32.0.1700.107 Chrome/32.0.1700.107 Safari/537.36');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    //Parameters passed to POST
    curl_setopt($ch, CURLOPT_POSTFIELDS, $this->query);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIESESSION, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, '');  //could be empty, but cause problems on some hosts
    curl_setopt($ch, CURLOPT_COOKIEFILE, '');  //could be empty, but cause problems on some hosts
    $response = curl_exec($ch);
    // send response as JSON
    $response = json_decode($response, true); 

    if (curl_error($ch)) {
      $this->writeFileLog('cancelOrder', $ch);
    } else if(empty($response)){
      $this->writeFileLog('cancelOrder', 'EMPTY RESPONSE');      
    } else if(isset($response['messages'][0]['messageCodeLocale'])){
      $this->writeFileLog('cancelOrder', $response['messages'][0]['messageCodeLocale']);
    } else {
      //Obtain type invoices
      $this->paramsQuery('E1ws', 'estab', 0);
      curl_setopt($ch, CURLOPT_URL, $this->url);
      curl_setopt($ch, CURLOPT_POST, false);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
      $response = curl_exec($ch);
      // send response as JSON
      $response = json_decode($response, true);

      if (curl_error($ch)) {
        $this->writeFileLog('cancelOrder2', $ch);
      } else if(empty($response)){
        $this->writeFileLog('cancelOrder2', 'EMPTY RESPONSE');
      } else if(isset($response['messages'][0]['messageCodeLocale'])){
        $this->writeFileLog('cancelOrder2', $response['messages'][0]['messageCodeLocale']);
      } else {
        //Verify if currency of shop corresponds to PHC FX
        if($response['result'][0]['moeda'] == get_option('woocommerce_currency')){
          $post_ID = sanitize_text_field( $_REQUEST['post_ID'] );
          //Obtain stamp of internal document based in order
          $docid = get_post_meta($post_ID);
          $filterItem = "bostamp";
          $valueItem = $docid['_docid'][0];
          
          //Verify if exists in bd
          $this->paramsQuery('BoWS', $filterItem, $valueItem);
          curl_setopt($ch, CURLOPT_URL, $this->url);
          curl_setopt($ch, CURLOPT_POST, false);
          curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
          $response = curl_exec($ch);
          // send response as JSON
          $response = json_decode($response, true);      

          if (curl_error($ch)) {
            $this->writeFileLog('cancelOrder3', $ch);
          } else if(empty($response)){
            $this->writeFileLog('cancelOrder3', 'EMPTY RESPONSE');
          } else if(isset($response['messages'][0]['messageCodeLocale'])){
            $this->writeFileLog('cancelOrder3', $response['messages'][0]['messageCodeLocale']);
          } else {
            if(!empty($response)){          
              //Obtain status
              $statusOrderShop = $this->fieldStatusOrder($settings['backend']['statusOfOrder']);
              //write in VO the status
              if(!empty($statusOrderShop)){
                $response['result'][0][$statusOrderShop] = $this->fieldStatus;
              }
              //Update operation
              $response['result'][0]['Operation'] = 2;

              //Save internal document
              $this->paramsSave('BoWS', $response);
              curl_setopt($ch, CURLOPT_URL, $this->url);
              curl_setopt($ch, CURLOPT_POST, false);
              curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
              $response = curl_exec($ch);
              // send response as JSON
              $response = json_decode($response, true); 

              if (curl_error($ch)) {
                $this->writeFileLog('cancelOrder4', $ch);
              } else if(empty($response)){
                $this->writeFileLog('cancelOrder4', 'EMPTY RESPONSE');
              } else if(isset($response['messages'][0]['messageCodeLocale'])){
                $this->writeFileLog('cancelOrder4', $response['messages'][0]['messageCodeLocale']);
              }
            }
          }
        }
      }
    }
    //Logout
    $this->paramsLogout();
    curl_setopt($ch, CURLOPT_URL, $this->url);
    curl_setopt($ch, CURLOPT_POST, false);
    $response = curl_exec($ch);
  }

  //If status of order is completed (creates FT)
  public function completedOrder(){
    $settings = get_option(PHCFXWOOCOMMERCE_PLUGIN_NAME);
    //Obtain configuration to make login
    $this->paramsLogin();
    //initial request with login data
    $ch = curl_init();
    //URL to save cookie "ASP.NET_SessionId"
    curl_setopt($ch, CURLOPT_URL, $this->url);
    curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/32.0.1700.107 Chrome/32.0.1700.107 Safari/537.36');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    //Parameters passed to POST
    curl_setopt($ch, CURLOPT_POSTFIELDS, $this->query);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIESESSION, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, '');  //could be empty, but cause problems on some hosts
    curl_setopt($ch, CURLOPT_COOKIEFILE, '');  //could be empty, but cause problems on some hosts
    $response = curl_exec($ch);
    // send response as JSON
    $response = json_decode($response, true); 

    if (curl_error($ch)) {
      $this->writeFileLog('completedOrder', $ch);
    } else if(empty($response)){
      $this->writeFileLog('completedOrder', 'EMPTY RESPONSE');      
    } else if(isset($response['messages'][0]['messageCodeLocale'])){
      $this->writeFileLog('completedOrder', $response['messages'][0]['messageCodeLocale']);      
    } else {
      //Obtain information from company
      $this->paramsQuery('E1ws', 'estab', 0);
      curl_setopt($ch, CURLOPT_URL, $this->url);
      curl_setopt($ch, CURLOPT_POST, false);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
      $response = curl_exec($ch);
      // send response as JSON
      $response = json_decode($response, true);

      if (curl_error($ch)) {
        $this->writeFileLog('completedOrder2', $ch);
      } else if(empty($response)){
        $this->writeFileLog('completedOrder2', 'EMPTY RESPONSE');
      } else if(isset($response['messages'][0]['messageCodeLocale'])){
        $this->writeFileLog('completedOrder2', $response['messages'][0]['messageCodeLocale']);
      } else {
        //Verify if currency of shop corresponds to PHC FX
        if($response['result'][0]['moeda'] == get_option('woocommerce_currency')){
          global $post;
          //If in settings of backoffice is checked option "create invoice"
          if($settings['backend']['createInvoice'] == 'on'){
            //Obtain stamp of internal document based in order
            $docid = get_post_meta($post->ID);
            $filterItem = "bostamp";
            $valueItem = $docid['_docid'][0];

            if(empty($valueItem)){
              $order_received = get_option('woocommerce_checkout_order_received_endpoint');
              //Obtain stamp of internal document based in order
              if($order_received == ''){
                 $order_received = sanitize_text_field( $_REQUEST['post_ID'] );
              }
              $docid = get_post_meta($order_received);
              $valueItem = $docid['_docid'][0];
            }
            if(empty($valueItem)){
              $order_received = sanitize_text_field( $_REQUEST['order_id'] ); 
              $docid = get_post_meta($order_received);
              $valueItem = $docid['_docid'][0];
            }

            //Verify if exists in bd
            $this->paramsQuery('BoWS', $filterItem, $valueItem);
            curl_setopt($ch, CURLOPT_URL, $this->url);
            curl_setopt($ch, CURLOPT_POST, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
            $response = curl_exec($ch);
            // send response as JSON
            $response = json_decode($response, true);  

            if (curl_error($ch)) {
              $this->writeFileLog('completedOrder3', $ch);
            } else if(empty($response)){
              $this->writeFileLog('completedOrder3', 'EMPTY RESPONSE');              
            } else if(isset($response['messages'][0]['messageCodeLocale'])){
              $this->writeFileLog('completedOrder3', $response['messages'][0]['messageCodeLocale']);              
            } else {  
              if(!empty($response['result'][0])){          
                //Obtain status
                $statusOrderShop = $this->fieldStatusOrder($settings['backend']['statusOfOrder']);
                //write in VO the status
                if(!empty($statusOrderShop)){
                  $response['result'][0][$statusOrderShop] = $this->fieldStatus;
                }
                //Update operation
                $response['result'][0]['Operation'] = 2;

                //Save internal document
                $this->paramsSave('BoWS', $response);
                curl_setopt($ch, CURLOPT_URL, $this->url);
                curl_setopt($ch, CURLOPT_POST, false);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
                $response = curl_exec($ch);
                // send response as JSON
                $response = json_decode($response, true); 

                if (curl_error($ch)) {
                  $this->writeFileLog('completedOrder4', $ch);
                } else if(empty($response)){
                  $this->writeFileLog('completedOrder4', 'EMPTY RESPONSE');
                } else if(isset($response['messages'][0]['messageCodeLocale'])){
                  $this->writeFileLog('completedOrder4', $response['messages'][0]['messageCodeLocale']);
                }
              }
              
              $response = $response['result'][0];
              if(!empty($response['bostamp'])){
                //Create Invoice only if is configurated type of invoices
                if(isset($settings['backend']['typeOfInvoice']) && $settings['backend']['typeOfInvoice'] > 0){
                  //Add FT from Bo
                  $this->addInternalDocumentInvoice($response, $ch);
                }
              } else {
                //Add Ft without Bo
                $this->addSimpleFT($ch);
              }
            }
          }
        }
      }
    }
    //Logout
    $this->paramsLogout();
    //session_destroy();
    curl_setopt($ch, CURLOPT_URL, $this->url);
    curl_setopt($ch, CURLOPT_POST, false);
    $response = curl_exec($ch);
  }

  //Convert internal document to invoice
  public function addInternalDocumentInvoice($response, $ch){
    $settings = get_option(PHCFXWOOCOMMERCE_PLUGIN_NAME);
    //See if type of order is configured
    if(empty($settings['backend']['typeOfInvoice']) || $settings['backend']['typeOfInvoice'] == 0){
      $this->writeFileLog('addInternalDocumentInvoice', 'Empty type of invoice');
    } else {      
      //Obtain new instance of FT based in Bo(bostamp)
      $this->paramsNewInstanceFromReference($response['bostamp']); 
      curl_setopt($ch, CURLOPT_URL, $this->url);
      curl_setopt($ch, CURLOPT_POST, false);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
      $response = curl_exec($ch);      
      // send response as JSON
      $response = json_decode($response, true); 

      if (curl_error($ch)) {
        $this->writeFileLog('addInternalDocumentInvoice2', $ch);
      } else if(empty($response)){
        $this->writeFileLog('addInternalDocumentInvoice2', 'EMPTY RESPONSE');        
      } else if(isset($response['messages'][0]['messageCodeLocale'])){
        $this->writeFileLog('addInternalDocumentInvoice2', $response['messages'][0]['messageCodeLocale']);        
      } else {
        //Save id of client
        $_SESSION['numberClient'] = $response['result'][0]['no'];
        //Save vo with products
        $_SESSION['voProducts'] = $response['result'][0];
        //Save FT
        $this->paramsSave('FtWS', $response); 
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
        $response = curl_exec($ch);
        // send response as JSON
        $response = json_decode($response, true); 

        if (curl_error($ch)) {
          $this->writeFileLog('addInternalDocumentInvoice3', $ch);
        } else if(empty($response)){
          $this->writeFileLog('addInternalDocumentInvoice3', 'EMPTY RESPONSE');          
        } else if(isset($response['messages'][0]['messageCodeLocale'])){
          $this->writeFileLog('addInternalDocumentInvoice3', $response['messages'][0]['messageCodeLocale']);          
        } else {
          //Enable to sign Document
          if($response['result'][0]['draftRecord'] == 1){
            $_SESSION['ftstamp'] = $response['result'][0]['ftstamp'];
            $this->paramsSignDocument($response['result'][0]['ftstamp']);
            curl_setopt($ch, CURLOPT_URL, $this->url);
            curl_setopt($ch, CURLOPT_POST, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
            $response = curl_exec($ch);
            // send response as JSON
            $response = json_decode($response, true); 

            if (curl_error($ch)) {
              $this->writeFileLog('addInternalDocumentInvoice4', $ch);
            } else if(empty($response)){
              $this->writeFileLog('addInternalDocumentInvoice4', 'EMPTY RESPONSE');              
            } else if(isset($response['messages'][0]['messageCodeLocale'])){
              $this->writeFileLog('addInternalDocumentInvoice4', $response['messages'][0]['messageCodeLocale']);              
            } else {
              //Manage stock
              if($settings['backend']['manageStock'] == 'on'){
                $post_ID = sanitize_text_field( $_REQUEST['post_ID'] );
                $order_received = get_option('woocommerce_checkout_order_received_endpoint');

                if(empty($order_received)){
                  $order = new WC_Order( $post_ID );
                } else {
                  $order = new WC_Order( $order_received );
                }

                $i = 0;
                foreach($order->get_items() as $key => $value){
                  $productReference = wc_get_product( $value['item_meta']['_product_id'][0] );
                  $sku[$i] = $productReference->get_sku();              
                  $productID[$sku[$i]] = $value['item_meta']['_product_id'][0];
                }

                $i = 0;
                $count = count($_SESSION['voProducts']['fis']);
                while ($i < $count) {
                  foreach ($_SESSION['voProducts']['fis'][$i] as $key => $value){
                    //Obtain product from reference
                    $this->paramsQuery('StWS', 'ref', $_SESSION['voProducts']['fis'][$i]['ref']);
                    curl_setopt($ch, CURLOPT_URL, $this->url);
                    curl_setopt($ch, CURLOPT_POST, false);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
                    $response = curl_exec($ch);
                    // send response as JSON
                    $response = json_decode($response, true);

                    if (curl_error($ch)) {
                      $this->writeFileLog('addInternalDocumentInvoice5', $ch);
                    } else if(empty($response)){
                      $this->writeFileLog('addInternalDocumentInvoice5', 'EMPTY RESPONSE');                      
                    } else if(isset($response['messages'][0]['messageCodeLocale'])){
                      $this->writeFileLog('addInternalDocumentInvoice5', $response['messages'][0]['messageCodeLocale']);                      
                    } else {
                      //If find reference, update stock
                      if (is_array($sku)){
                        foreach ($sku as $key => $value) {
                          if($_SESSION['voProducts']['fis'][$i]['ref'] == $value){
                            update_post_meta($productID[$value],'_stock',$response['result'][0]['stock']);
                          }
                        }
                      }
                    }    
                  }
                  ++$i;
                }
              }
              //Verify if are selected type of internal document and send invoice checkbox
              if(isset($settings['backend']['typeOfInvoice']) && $settings['backend']['createInvoice'] == 'on' && $settings['backend']['typeOfInvoice'] > 0 && isset($settings['backend']['sendInvoice'])){
                //Obtain reports for print
                $this->paramsGetReportForPrint();
                curl_setopt($ch, CURLOPT_URL, $this->url);
                curl_setopt($ch, CURLOPT_POST, false);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
                $response = curl_exec($ch);
                // send response as JSON
                $response = json_decode($response, true);

                if (curl_error($ch)) {
                  $this->writeFileLog('addInternalDocumentInvoice6', $ch);
                } else if(empty($response)){
                  $this->writeFileLog('addInternalDocumentInvoice6', 'EMPTY RESPONSE');                  
                } else if(isset($response['messages'][0]['messageCodeLocale'])){
                  $this->writeFileLog('addInternalDocumentInvoice6', $response['messages'][0]['messageCodeLocale']);                  
                } else {
                  //Verify if exists template as default
                  $i = 0;
                  $count = count($response['result']);
                  $sendEmail = false;
                  while ($i < $count) {
                    foreach ($response['result'][$i] as $key => $value){
                      if($key == 'isDefault' && $value == 1){
                        $sendEmail = true;
                        $_SESSION['repstamp'] = $response['result'][$i]['repstamp'];
                        break;
                      }
                    }
                    ++$i;
                  }
                  //If is configured to send email in backend settings of plugin
                  if($sendEmail == true){
                    $this->paramsQuery('ClWS', 'no', $_SESSION['numberClient']);
                    curl_setopt($ch, CURLOPT_URL, $this->url);
                    curl_setopt($ch, CURLOPT_POST, false);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
                    $response = curl_exec($ch);
                    // send response as JSON
                    $response = json_decode($response, true);

                    if (curl_error($ch)) {
                      $this->writeFileLog('addInternalDocumentInvoice7', $ch);
                    } else if(empty($response)){
                      $this->writeFileLog('addInternalDocumentInvoice7', 'EMPTY RESPONSE');                      
                    } else if(isset($response['messages'][0]['messageCodeLocale'])){
                      $this->writeFileLog('addInternalDocumentInvoice7', $response['messages'][0]['messageCodeLocale']);
                      $this->messagesError(utf8_decode(" in configuration of email to send them to client! Message from Backend: " . $response['messages'][0]['messageCodeLocale']));
                    } else {
                      if($response['result'][0]['email'] != ''){
                        //Email To
                        $_SESSION['emailClient'] = $response['result'][0]['email'];
                        //Email From
                        $infoAdmin = $this->get_settingsAdmin();
                        $emailAdmin = $infoAdmin[3]['default'];

                        //Send FT to email selected
                        $this->paramsSendReportEmail($_SESSION['repstamp'], $_SESSION['ftstamp'], $_SESSION['emailClient'], $emailAdmin);
                        curl_setopt($ch, CURLOPT_URL, $this->url);
                        curl_setopt($ch, CURLOPT_POST, false);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
                        $response = curl_exec($ch);
                        // send response as JSON
                        $response = json_decode($response, true); 

                        if (curl_error($ch)) {
                          $this->writeFileLog('addInternalDocumentInvoic8', $ch);
                        } else if(empty($response)){
                          $this->writeFileLog('addInternalDocumentInvoic8', 'EMPTY RESPONSE');                          
                        } else if(isset($response['messages'][0]['messageCodeLocale'])){
                          $this->writeFileLog('addInternalDocumentInvoice8', $response['messages'][0]['messageCodeLocale']);                          
                        }
                      }
                    }
                  }
                }
              }
            }
          } else {
            //Manage stock
              if($settings['backend']['manageStock'] == 'on'){
                $post_ID = sanitize_text_field( $_REQUEST['post_ID'] );
                $order_received = get_option('woocommerce_checkout_order_received_endpoint');

                if(empty($order_received)){
                  $order = new WC_Order( $post_ID );
                } else {
                  $order = new WC_Order( $order_received );
                }
				
                $i = 0;
                foreach($order->get_items() as $key => $value){
                  $productReference = wc_get_product( $value['item_meta']['_product_id'][0] );
                  $sku[$i] = $productReference->get_sku();              
                  $productID[$sku[$i]] = $value['item_meta']['_product_id'][0];
                }

                $i = 0;
                $count = count($_SESSION['voProducts']['fis']);
                while ($i < $count) {
                  foreach ($_SESSION['voProducts']['fis'][$i] as $key => $value){
                    //Obtain product from reference
					$this->paramsQuery('StWS', 'ref', $_SESSION['voProducts']['fis'][$i]['ref']);
                    curl_setopt($ch, CURLOPT_URL, $this->url);
                    curl_setopt($ch, CURLOPT_POST, false);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
                    $response = curl_exec($ch);
                    // send response as JSON
                    $response = json_decode($response, true);

                    if (curl_error($ch)) {
                      $this->writeFileLog('addInternalDocumentInvoice5', $ch);
                    } else if(empty($response)){
                      $this->writeFileLog('addInternalDocumentInvoice5', 'EMPTY RESPONSE');                      
                    } else if(isset($response['messages'][0]['messageCodeLocale'])){
                      $this->writeFileLog('addInternalDocumentInvoice5', $response['messages'][0]['messageCodeLocale']);                      
                    } else {
                      //If find reference, update stock
                      if (is_array($sku)){
                        foreach ($sku as $key => $value) {
                          if($_SESSION['voProducts']['fis'][$i]['ref'] == $value){
                            update_post_meta($productID[$value],'_stock',$response['result'][0]['stock']);
                          }
                        }
                      }
                    }    
                  }
                  ++$i;
                }
            }
          }
        }
      }
    }
  }

  //Insert new invoice without internal document
  public function addSimpleFT($ch){
    $settings = get_option(PHCFXWOOCOMMERCE_PLUGIN_NAME);
    //See if type of invoice is configured
    if(empty($settings['backend']['typeOfInvoice']) || $settings['backend']['typeOfInvoice'] == 0){
      $this->writeFileLog('addSimpleFT', 'Empty type of invoice');
    } else {   
      //Put the same number of order in PHC FX
      global $wpdb;
      $table_name = $wpdb->prefix."postmeta";
      //Obtain next post_id of order in MySQL
      $query = "SELECT meta_key, meta_value FROM %s where post_id = %d";
      $docid = $wpdb->get_results(str_replace("'", "", $wpdb->prepare($query, $table_name, sanitize_text_field($_REQUEST['order_id']))));
      
      foreach ($docid as $key => $value) {
        if($value->meta_key == '_billing_country'){
          $_billing_country = $value->meta_value;
        } else if($value->meta_key == '_billing_first_name') {
          $_billing_first_name = $value->meta_value;
        } else if($value->meta_key == '_billing_last_name') {
          $_billing_last_name = $value->meta_value;
        } else if($value->meta_key == '_billing_address_1') {
          $_billing_address_1 = $value->meta_value;
        } else if($value->meta_key == '_billing_city') {
          $_billing_city = $value->meta_value;
        } else if($value->meta_key == '_billing_postcode') {
          $_billing_postcode = $value->meta_value;
        } else if($value->meta_key == '_billing_email') {
          $_billing_email = $value->meta_value;
        } else if($value->meta_key == '_billing_phone') {
          $_billing_phone = $value->meta_value;
        } else if($value->meta_key == '_shipping_country') {
          $_shipping_country = $value->meta_value;
        } else if($value->meta_key == '_shipping_address_1') {
          $_shipping_address_1 = $value->meta_value;
        } else if($value->meta_key == '_shipping_city') {
          $_shipping_city = $value->meta_value;
        } else if($value->meta_key == '_shipping_postcode') {
          $_shipping_postcode = $value->meta_value;
        }
      }

      $billing_country = sanitize_text_field( $_REQUEST['billing_country'] );
      $billing_country_ = sanitize_text_field( $_REQUEST['_billing_country'] );
      $order_received = get_option('woocommerce_checkout_order_received_endpoint');

      if($_billing_country == ''){
        $_billing_country = sanitize_text_field( $_REQUEST['_billing_country'] );
      }

      //Obtain country
      $this->paramsQuery('LocalizationWS', 'nomeabrv', $_billing_country);
      curl_setopt($ch, CURLOPT_URL, $this->url);
      curl_setopt($ch, CURLOPT_POST, false);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
      $response = curl_exec($ch);
      // send response as JSON
      $response = json_decode($response, true);

      if (curl_error($ch)) {
        $this->writeFileLog('addSimpleFT2', $ch);
      } else if(empty($response)){
        $this->writeFileLog('addSimpleFT2', 'EMPTY RESPONSE');
      } else if(isset($response['messages'][0]['messageCodeLocale'])){
        $this->writeFileLog('addSimpleFT2', $response['messages'][0]['messageCodeLocale']);
      } else {
        $paisesstamp = $response['result'][0]['paisesstamp'];
        $nomePais = $response['result'][0]['nome'];
      }

      if($_shipping_country == ''){
        $_shipping_country = sanitize_text_field( $_REQUEST['_shipping_country'] );
        if($_shipping_country == ''){
          $_shipping_country = sanitize_text_field( $_REQUEST['_billing_country'] );
        }
      }
      //Obtain shipping country
      $this->paramsQuery('LocalizationWS', 'nomeabrv', $_shipping_country);
      curl_setopt($ch, CURLOPT_URL, $this->url);
      curl_setopt($ch, CURLOPT_POST, false);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
      $response = curl_exec($ch);
      // send response as JSON
      $response = json_decode($response, true);

      if (curl_error($ch)) {
        $this->writeFileLog('addSimpleFT3', $ch);
      } else if(empty($response)){
        $this->writeFileLog('addSimpleFT3', 'EMPTY RESPONSE');
      } else if(isset($response['messages'][0]['messageCodeLocale'])){
        $this->writeFileLog('addSimpleFT3', $response['messages'][0]['messageCodeLocale']);
      } else {
        $paisesstampShipping = $response['result'][0]['paisesstamp'];
        $nomePaisShipping = $response['result'][0]['nome'];
      }

      unset($_SESSION['listOfSku']);
      unset($_SESSION['listOfQuantity']);
      unset($_SESSION['listOfValueItem']);            

      if (is_array(WC()->cart->cart_contents)){
        $i = 0;
        //Obtain key of wordpress thtat identified the different products in  cart
        foreach (WC()->cart->cart_contents as $key => $value){
          $productsOrder[$i] = $key;       
          ++$i;
        }
        $i = 0;
        $count = count(WC()->cart->cart_contents);
        //Wordpress function to obtain information of cart items
        while ($i < $count) {
          //Obtain info about products in cart
          $productData = WC()->cart->cart_contents[$productsOrder[$i]];
          //obtain reference items in cart
          if($productData['variation_id']!='' && $value['variation_id'] != 0){
            $productReference = wc_get_product( $productData['variation_id'] );
          } else {
            $productReference = wc_get_product( $productData['product_id'] );
          }

          $sku[$i] = $productReference->get_sku();
          //Obtain quantity of the product in cart
          $quantity[$i] = $productData['quantity'];
          //Obtain unit price of the product in cart
          $valueItem[$i] = $productData['line_subtotal'];
          ++$i;
        }
        //Save items of cart
        $_SESSION['listOfSku'] = $sku;
        $_SESSION['listOfQuantity'] = $quantity;
        $_SESSION['listOfValueItem'] = $valueItem;
      }                    

      if(empty($_SESSION['listOfSku'])){
        //Obtain id of order and products
        $order = new WC_Order();
        $order = new WC_Order($order->post->ID);
        $i = 0;
        
        foreach($order->get_items() as $key => $value){
          if($value['variation_id'] != '' && $value['variation_id'] != 0){
             $product = new WC_Product($value['variation_id']);
          } else {
             $product = new WC_Product($value['product_id']);
          }
          $sku[$i] = $product->get_sku();
          $quantity[$i] = $value['qty'];
          $valueItem[$i] = $value['line_subtotal'];
          ++$i;
        }
        //Save items of cart
        $_SESSION['listOfSku'] = $sku;
        $_SESSION['listOfQuantity'] = $quantity;
        $_SESSION['listOfValueItem'] = $valueItem;
      }

      if(empty($_SESSION['listOfSku'])){
        //Obtain id of order and products
        $order = new WC_Order();
        $order = new WC_Order(sanitize_text_field($_REQUEST['order_id']));
        $i = 0;
        
        foreach($order->get_items() as $key => $value){
          if($value['variation_id'] != '' && $value['variation_id'] != 0){
             $product = new WC_Product($value['variation_id']);
          } else {
             $product = new WC_Product($value['product_id']);
          }
          $sku[$i] = $product->get_sku();
          $quantity[$i] = $value['qty'];
          $valueItem[$i] = $value['line_subtotal'];
          ++$i;
        }
        //Save items of cart
        $_SESSION['listOfSku'] = $sku;
        $_SESSION['listOfQuantity'] = $quantity;
        $_SESSION['listOfValueItem'] = $valueItem;
      }

      if($billing_email_ != '' || $billing_email != ''){
        //Verify if client exists
        if($billing_email_ == '' && $billing_email != ''){
          $this->paramsQuery('ClWS', 'email', $billing_email);
        } else if ($billing_email_ != '' && $billing_email == ''){
          $this->paramsQuery('ClWS', 'email', $billing_email_);
        } 

        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
        $response = curl_exec($ch);
        // send response as JSON
        $response = json_decode($response, true);        

        if (curl_error($ch)) {
          $this->writeFileLog('addSimpleFT4', $ch);
        } else if(empty($response)){
          $this->writeFileLog('addSimpleFT4', 'EMPTY RESPONSE');          
        } else if(isset($response['messages'][0]['messageCodeLocale'])){
          $this->writeFileLog('addSimpleFT4', $response['messages'][0]['messageCodeLocale']);          
        } else {  
          //Have client
          if(is_array($response['result'][0])){
            //Save number id of client
            $_SESSION['nrClient'] = $response['result'][0]['no'];
            //Save vo with products
            $_SESSION['voProducts'] = $response['result'][0];

            $billing_first_name = sanitize_text_field( $_REQUEST['billing_first_name'] );
            $billing_last_name = sanitize_text_field( $_REQUEST['billing_last_name'] );
            $billing_email = sanitize_text_field( $_REQUEST['billing_email'] );
            $billing_address_1 = sanitize_text_field( $_REQUEST['billing_address_1'] );
            $billing_city = sanitize_text_field( $_REQUEST['billing_city'] );
            $billing_phone = sanitize_text_field( $_REQUEST['billing_phone'] );  
            $billing_postcode = sanitize_text_field( $_REQUEST['billing_postcode'] ); 

            $billing_first_name_ = sanitize_text_field( $_REQUEST['_billing_first_name'] );
            $billing_last_name_ = sanitize_text_field( $_REQUEST['_billing_last_name'] );
            $billing_email_ = sanitize_text_field( $_REQUEST['_billing_email'] );
            $billing_address_1_ = sanitize_text_field( $_REQUEST['_billing_address_1'] );
            $billing_city_ = sanitize_text_field( $_REQUEST['_billing_city'] );
            $billing_phone_ = sanitize_text_field( $_REQUEST['_billing_phone'] );  
            $billing_postcode_ = sanitize_text_field( $_REQUEST['_billing_postcode'] );                

            //Used in frontend
            if($billing_email_ == ''){
              $response['result'][0]['nome'] = $billing_first_name . " " . $billing_last_name;
              $response['result'][0]['email'] = $billing_email;
              $response['result'][0]['morada'] = $billing_address_1;
              $response['result'][0]['local'] = $billing_city;
			  $response['result'][0]['provincia'] = $billing_city;
              $response['result'][0]['telefone'] = $billing_phone;
              $response['result'][0]['codpost'] = $billing_postcode;
            } else { //Used in backend
              $response['result'][0]['nome'] = $billing_first_name_ . " " . $billing_last_name_;
              $response['result'][0]['email'] = $billing_email_;
              $response['result'][0]['morada'] = $billing_address_1_;
              $response['result'][0]['local'] = $billing_city_;
			  $response['result'][0]['provincia'] = $billing_city_;
              $response['result'][0]['telefone'] = $billing_phone_;
              $response['result'][0]['codpost'] = $billing_postcode_;
            }
            $response['result'][0]['paisesstamp'] = $paisesstamp;
            $response['result'][0]['pais'] = $nomePais;
            $response['result'][0]['Operation'] = 2;

            //Save data of client in PHC FX
            $this->paramsSave('ClWS', $response);
            curl_setopt($ch, CURLOPT_URL, $this->url);
            curl_setopt($ch, CURLOPT_POST, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
            $response = curl_exec($ch);
            // send response as JSON
            $response = json_decode($response, true); 

            if (curl_error($ch)) {
              $this->writeFileLog('addSimpleFT5', $ch);
            } else if(empty($response)){
              $this->writeFileLog('addSimpleFT5', 'EMPTY RESPONSE');              
            } else if(isset($response['messages'][0]['messageCodeLocale'])){
              $this->writeFileLog('addSimpleFT5', $response['messages'][0]['messageCodeLocale']);
               if($_SESSION['nrClient'] != ''){
                  //Inserted client in bd
                  $createClientSuccess = 1;
                }              
            } else {
              //Inserted client in bd
              $createClientSuccess = 1;
            }
          //Dont have client - INSERT
          } else {
            //Obtain new instance of client
            $this->paramsNewInstance('ClWS', 0);
            curl_setopt($ch, CURLOPT_URL, $this->url);
            curl_setopt($ch, CURLOPT_POST, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
            $response = curl_exec($ch);
            // send response as JSON
            $response = json_decode($response, true); 

            if (curl_error($ch)) {
              $this->writeFileLog('addSimpleFT6', $ch);
            } else if(empty($response)){
              $this->writeFileLog('addSimpleFT6', 'EMPTY RESPONSE');              
            } else if(isset($response['messages'][0]['messageCodeLocale'])){
              $this->writeFileLog('addSimpleFT6', $response['messages'][0]['messageCodeLocale']);              
            } else {                  
              //Save number id of client
              $_SESSION['nrClient'] = $response['result'][0]['no'];
              //Save vo with products
              $_SESSION['voProducts'] = $response['result'][0];

              $billing_first_name = sanitize_text_field( $_REQUEST['billing_first_name'] );
              $billing_last_name = sanitize_text_field( $_REQUEST['billing_last_name'] );
              $billing_email = sanitize_text_field( $_REQUEST['billing_email'] );
              $billing_address_1 = sanitize_text_field( $_REQUEST['billing_address_1'] );
              $billing_city = sanitize_text_field( $_REQUEST['billing_city'] );
              $billing_phone = sanitize_text_field( $_REQUEST['billing_phone'] );  
              $billing_postcode = sanitize_text_field( $_REQUEST['billing_postcode'] ); 

              $billing_first_name_ = sanitize_text_field( $_REQUEST['_billing_first_name'] );
              $billing_last_name_ = sanitize_text_field( $_REQUEST['_billing_last_name'] );
              $billing_email_ = sanitize_text_field( $_REQUEST['_billing_email'] );
              $billing_address_1_ = sanitize_text_field( $_REQUEST['_billing_address_1'] );
              $billing_city_ = sanitize_text_field( $_REQUEST['_billing_city'] );
              $billing_phone_ = sanitize_text_field( $_REQUEST['_billing_phone'] );  
              $billing_postcode_ = sanitize_text_field( $_REQUEST['_billing_postcode'] );                

              //Used in frontend
              if($billing_email_ == ''){
                $response['result'][0]['nome'] = $billing_first_name . " " . $billing_last_name;
                $response['result'][0]['email'] = $billing_email;
                $response['result'][0]['morada'] = $billing_address_1;
                $response['result'][0]['local'] = $billing_city;
				$response['result'][0]['provincia'] = $billing_city;
                $response['result'][0]['telefone'] = $billing_phone;
                $response['result'][0]['codpost'] = $billing_postcode;
              } else { //Used in backend
                $response['result'][0]['nome'] = $billing_first_name_ . " " . $billing_last_name_;
                $response['result'][0]['email'] = $billing_email_;
                $response['result'][0]['morada'] = $billing_address_1_;
                $response['result'][0]['local'] = $billing_city_;
				$response['result'][0]['provincia'] = $billing_city_;
                $response['result'][0]['telefone'] = $billing_phone_;
                $response['result'][0]['codpost'] = $billing_postcode_;
              }
              $response['result'][0]['paisesstamp'] = $paisesstamp;
              $response['result'][0]['pais'] = $nomePais;

              //Save data of client in PHC FX
              $this->paramsSave('ClWS', $response);
              curl_setopt($ch, CURLOPT_URL, $this->url);
              curl_setopt($ch, CURLOPT_POST, false);
              curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
              $response = curl_exec($ch);
              // send response as JSON
              $response = json_decode($response, true); 

              if (curl_error($ch)) {
                $this->writeFileLog('addSimpleFT7', $ch);
              } else if(empty($response)){
                $this->writeFileLog('addSimpleFT7', 'EMPTY RESPONSE');                
              } else if(isset($response['messages'][0]['messageCodeLocale'])){
                $this->writeFileLog('addSimpleFT7', $response['messages'][0]['messageCodeLocale']);
                 if($_SESSION['nrClient'] != ''){
                  //Inserted client in bd
                  $createClientSuccess = 1;
                }                
              } else {
                //Inserted client in bd
                $createClientSuccess = 1;
              }
            }
          }
        }
      } else {
        //Verify if existis generic client
        $this->paramsQuery('ClWS', 'clivd', '1');
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
        $response = curl_exec($ch);
        // send response as JSON
        $response = json_decode($response, true);             

        if (curl_error($ch)) {
          $this->writeFileLog('addSimpleFT8', $ch);
        } else if(empty($response)){
          $this->writeFileLog('addSimpleFT8', 'EMPTY RESPONSE');          
        } else if(isset($response['messages'][0]['messageCodeLocale'])){
          $this->writeFileLog('addSimpleFT8', $response['messages'][0]['messageCodeLocale']);          
        } else {  
          //Have generic client
          if(is_array($response['result'][0])){
            //Save number id of client
            $_SESSION['nrClient'] = $response['result'][0]['no'];
            //Save vo with products
            $_SESSION['voProducts'] = $response['result'][0];
            //Obtain client from PHC FX
            $createClientSuccess = 1;
          //Dont have generic client
          } else {
            //Obtain new instance of client
            $this->paramsNewInstance('ClWS', 0);
            curl_setopt($ch, CURLOPT_URL, $this->url);
            curl_setopt($ch, CURLOPT_POST, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
            $response = curl_exec($ch);
            // send response as JSON
            $response = json_decode($response, true); 

            if (curl_error($ch)) {
              $this->writeFileLog('addSimpleFT9', $ch);
            } else if(empty($response)){
              $this->writeFileLog('addSimpleFT9', 'EMPTY RESPONSE');              
            } else if(isset($response['messages'][0]['messageCodeLocale'])){
              $this->writeFileLog('addSimpleFT9', $response['messages'][0]['messageCodeLocale']);              
            } else {
              $response['result'][0]['nome'] = 'Generic Client';
              $response['result'][0]['clivd'] = true;
              
              //Save number id of client
              $_SESSION['nrClient'] = $response['result'][0]['no'];
              //Save vo with products
              $_SESSION['voProducts'] = $response['result'][0];

              //Save data of client in PHC FX
              $this->paramsSave('ClWS', $response);
              curl_setopt($ch, CURLOPT_URL, $this->url);
              curl_setopt($ch, CURLOPT_POST, false);
              curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
              $response = curl_exec($ch);
              // send response as JSON
              $response = json_decode($response, true); 

              if (curl_error($ch)) {
                $this->writeFileLog('addSimpleFT10', $ch);
              } else if(empty($response)){
                $this->writeFileLog('addSimpleFT10', 'EMPTY RESPONSE');                
              } else if(isset($response['messages'][0]['messageCodeLocale'])){
                $this->writeFileLog('addSimpleFT10', $response['messages'][0]['messageCodeLocale']);
                 if($_SESSION['nrClient'] != ''){
                  //Inserted client in bd
                  $createClientSuccess = 1;
                }                
              } else {
                //Inserted client in bd
                $createClientSuccess = 1;
              }
            }
          }
        }
      }

      //If client is created/obtained with success
      if($createClientSuccess == 1){
        //Obtain new instance of invoice
        $this->paramsNewInstance('FtWS', $settings['backend']['typeOfInvoice']);
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
        $response = curl_exec($ch);      
        // send response as JSON
        $response = json_decode($response, true); 

        if (curl_error($ch)) {
          $this->writeFileLog('addSimpleFT11', $ch);
        } else if(empty($response)){
          $this->writeFileLog('addSimpleFT11', 'EMPTY RESPONSE');          
        } else if(isset($response['messages'][0]['messageCodeLocale'])){
          $this->writeFileLog('addSimpleFT11', $response['messages'][0]['messageCodeLocale']);          
        } else {      
          //Obtain id of order and products
          $order = new WC_Order();
          $order = new WC_Order($order->post->ID);

          $numberOrder = $order->post->ID;
          $i = 0;
          
          foreach($order->get_items() as $key => $value){
            if($value['variation_id'] != '' && $value['variation_id'] != 0){
              $product = new WC_Product($value['variation_id']);
            } else {
              $product = new WC_Product($value['product_id']);
            }
            $sku[$i] = $product->get_sku();
            $quantity[$i] = $value['qty'];
            $valueItem[$i] = $value['line_subtotal'];
            ++$i;
          }
          //Save items of cart
          $_SESSION['listOfSku'] = $sku;
          $_SESSION['listOfQuantity'] = $quantity;
          $_SESSION['listOfValueItem'] = $valueItem;

          //FT from Backend if is clicked "button tip"
          if(empty($_SESSION['listOfSku'][0])){
            //Obtain id of order and products
            $order = new WC_Order();
            $numberOrder = sanitize_text_field($_REQUEST['order_id']);
            $order = new WC_Order($numberOrder);
            
            $i = 0;
            foreach($order->get_items() as $key => $value){
              if($value['variation_id'] != '' && $value['variation_id'] != 0){
                $product = new WC_Product($value['variation_id']);
              } else {
                $product = new WC_Product($value['product_id']);
              }
              $sku[$i] = $product->get_sku();
              $quantity[$i] = $value['qty'];
              $valueItem[$i] = $value['line_subtotal'];
              ++$i;
            }
            //Save items of cart
            $_SESSION['listOfSku'] = $sku;
            $_SESSION['listOfQuantity'] = $quantity;
            $_SESSION['listOfValueItem'] = $valueItem;
          }   

          //Obtain new instance of FT
          // Only If have articles to FT
          if(!empty($_SESSION['listOfSku'])){
            $this->paramsNewInstanceByRef('FtWS', 'addNewFIsByRef', 'IdFtStamp', 'fiStampEditing', $response['result'][0]['ftstamp'], $_SESSION['listOfSku']);
            curl_setopt($ch, CURLOPT_URL, $this->url);
            curl_setopt($ch, CURLOPT_POST, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
            $response = curl_exec($ch);
            // send response as JSON
            $response = json_decode($response, true);   

            if (curl_error($ch)) {
              $this->writeFileLog('addSimpleFT12', $ch);
            } else if(empty($response)){
              $this->writeFileLog('addSimpleFT12', 'EMPTY RESPONSE');          
            } else if(isset($response['messages'][0]['messageCodeLocale'])){
              $this->writeFileLog('addSimpleFT12', $response['messages'][0]['messageCodeLocale']);          
            } else {  
              if($_billing_first_name == '' && $_billing_last_name == ''){
                $_billing_first_name = sanitize_text_field( $_REQUEST['_billing_first_name'] );
                $_billing_last_name = sanitize_text_field( $_REQUEST['_billing_last_name'] );
              }
              if($_billing_city == ''){
                $_billing_last_name = sanitize_text_field( $_REQUEST['_billing_city'] );
              }
              if($_billing_address_1 == ''){
                $_billing_address_1 = sanitize_text_field( $_REQUEST['_billing_address_1'] );
              }
              if($_billing_postcode == ''){
                $_billing_postcode = sanitize_text_field( $_REQUEST['_billing_postcode'] );
              }
              if($_billing_phone == ''){
                $_billing_phone = sanitize_text_field( $_REQUEST['_billing_phone'] );
              } 
              if($_shipping_address_1 == ''){
                $_shipping_address_1 = sanitize_text_field( $_REQUEST['_shipping_address_1'] );
                if($_shipping_address_1 == ''){
                  $_shipping_address_1 = sanitize_text_field( $_REQUEST['_billing_address_1'] );
                }
              }
              if($_shipping_city == ''){
                $_shipping_city = sanitize_text_field( $_REQUEST['_shipping_city'] );
                if($_shipping_city == ''){
                  $_shipping_city = sanitize_text_field( $_REQUEST['_billing_city'] );
                }
              }
              if($_shipping_postcode == ''){
                $_shipping_postcode = sanitize_text_field( $_REQUEST['_shipping_postcode'] );
                if($_shipping_postcode == ''){
                  $_shipping_postcode = sanitize_text_field( $_REQUEST['_billing_postcode'] );
                }
              }           

              $response['result'][0]['nome'] = $_billing_first_name . " " . $_billing_last_name;
              $response['result'][0]['morada'] = $_billing_address_1;
              $response['result'][0]['local'] = $_billing_city;
			  $response['result'][0]['provincia'] = $_billing_city;
              $response['result'][0]['codpost'] = $_billing_postcode;
              $response['result'][0]['telefone'] = $_billing_phone;
              $response['result'][0]['moradato'] = $_shipping_address_1;
              $response['result'][0]['localto'] = $_shipping_city;
              $response['result'][0]['codpostto'] = $_shipping_postcode;
              $response['result'][0]['pais'] = $nomePais;
              $response['result'][0]['paisesstamp'] = $paisesstamp;  
              $response['result'][0]['paisto'] = $nomePaisShipping;
              $response['result'][0]['paisesstampto'] = $paisesstampShipping;   

              //Save number of client in FT
              $response['result'][0]['no'] = $_SESSION['nrClient'];

              foreach ($_SESSION['listOfQuantity'] as $key => $value){
                $response['result'][0]['fis'][$key]['qtt'] = $value;
              }
              foreach ($_SESSION['listOfValueItem'] as $key => $value){
                $response['result'][0]['fis'][$key]['epv'] = $value;                 
              }
              foreach ($_SESSION['listOfSku'] as $key => $value){
                $response['result'][0]['fis'][$key]['desconto'] = 0;  
                $response['result'][0]['fis'][$key]['desc2'] = 0;  
                $response['result'][0]['fis'][$key]['desc3'] = 0;  
                $response['result'][0]['fis'][$key]['desc4'] = 0;  
                $response['result'][0]['fis'][$key]['desc5'] = 0;  
                $response['result'][0]['fis'][$key]['desc6'] = 0;  

                //Eliminate financial discount of client
                $response['result'][0]['efinv'] = 0;  
                $response['result'][0]['fin'] = 0;                 
              }

              // build our web service full URL
              $settings['backend']['url'] = rtrim($settings['backend']['url'], '/');
              $this->url = "{$settings['backend']['url']}".$this->extraurl."/REST/FtWS/actEntity";
              // Create map with request parameters
              $this->params =  array ('entity' => json_encode($response['result'][0]));
              // Build Http query using params
              $this->query = http_build_query ($this->params);
              curl_setopt($ch, CURLOPT_URL, $this->url);
              curl_setopt($ch, CURLOPT_POST, false);
              curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
              $response = curl_exec($ch);
              // send response as JSON
              $response = json_decode($response, true);         

              if (curl_error($ch)) {
                $this->writeFileLog('addSimpleFT13', $ch);
              } else if(empty($response)){
                $this->writeFileLog('addSimpleFT13', 'EMPTY RESPONSE');              
              } else if(isset($response['messages'][0]['messageCodeLocale'])){
                $this->writeFileLog('addSimpleFT13', $response['messages'][0]['messageCodeLocale']);              
              } else {
                //Save FT
                $this->paramsSave('FtWS', $response); 
                curl_setopt($ch, CURLOPT_URL, $this->url);
                curl_setopt($ch, CURLOPT_POST, false);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
                $response = curl_exec($ch);
                // send response as JSON
                $response = json_decode($response, true);  

                if (curl_error($ch)) {
                  $this->writeFileLog('addSimpleFT14', $ch);
                } else if(empty($response)){
                  $this->writeFileLog('addSimpleFT14', 'EMPTY RESPONSE');                
                } else if(isset($response['messages'][0]['messageCodeLocale'])){
                  $this->writeFileLog('addSimpleFT14', $response['messages'][0]['messageCodeLocale']);                
                } else {
                  //Enable to sign Document
                  if($response['result'][0]['draftRecord'] == 1){
                    //Save ftstamp of invoice
                    $_SESSION['ftstamp'] = $response['result'][0]['ftstamp'];

                    //Sign document
                    $this->paramsSignDocument($response['result'][0]['ftstamp']);
                    curl_setopt($ch, CURLOPT_URL, $this->url);
                    curl_setopt($ch, CURLOPT_POST, false);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
                    $response = curl_exec($ch);
                    // send response as JSON
                    $response = json_decode($response, true); 

                    if (curl_error($ch)) {
                      $this->writeFileLog('addSimpleFT15', $ch);
                    } else if(empty($response)){
                      $this->writeFileLog('addSimpleFT15', 'EMPTY RESPONSE');                    
                    } else if(isset($response['messages'][0]['messageCodeLocale'])){
                      $this->writeFileLog('addSimpleFT15', $response['messages'][0]['messageCodeLocale']);                    
                    } else {
                      //Manage stock
                      if($settings['backend']['manageStock'] == 'on'){
                        $post_ID = sanitize_text_field( $_REQUEST['post_ID'] );
                        $order_received = get_option('woocommerce_checkout_order_received_endpoint');

                        if(empty($order_received)){
                          $order = new WC_Order( $post_ID );
                        } else {
                          $order = new WC_Order( $order_received );
                        }

                        $i = 0;
                        foreach($order->get_items() as $key => $value){
                          $productReference = wc_get_product( $value['item_meta']['_product_id'][0] );
                          $sku[$i] = $productReference->get_sku();

                          $productID[$sku[$i]] = $value['item_meta']['_product_id'][0];
                        }

                        $i = 0;
                        $count = count($_SESSION['voProducts']['fis']);
                        while ($i < $count) {
                          foreach ($_SESSION['voProducts']['fis'][$i] as $key => $value){
                            //Obtain product from reference
                            $this->paramsQuery('StWS', 'ref', $_SESSION['voProducts']['fis'][$i]['ref']);
                            curl_setopt($ch, CURLOPT_URL, $this->url);
                            curl_setopt($ch, CURLOPT_POST, false);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
                            $response = curl_exec($ch);
                            // send response as JSON
                            $response = json_decode($response, true);

                            if (curl_error($ch)) {
                              $this->writeFileLog('addSimpleFT16', $ch);
                            } else if(empty($response)){
                              $this->writeFileLog('addSimpleFT16', 'EMPTY RESPONSE');                            
                            } else if(isset($response['messages'][0]['messageCodeLocale'])){
                              $this->writeFileLog('addSimpleFT16', $response['messages'][0]['messageCodeLocale']);                            
                            } else {
                              //If find ref, update stock
                              foreach ($sku as $key => $value) {
                                if($_SESSION['voProducts']['fis'][$i]['ref'] == $value){
                                  update_post_meta($productID[$value],'_stock',$response['result'][0]['stock']);
                                }
                              }
                            }    
                          }
                          ++$i;
                        }
                      }
                      //Verify if are selected type of internal document and send invoice checkbox
                      if(isset($settings['backend']['typeOfInvoice']) && $settings['backend']['createInvoice'] == 'on' && $settings['backend']['typeOfInvoice'] > 0 && isset($settings['backend']['sendInvoice'])){
                        //Obtain reports for print
                        $this->paramsGetReportForPrint();
                        curl_setopt($ch, CURLOPT_URL, $this->url);
                        curl_setopt($ch, CURLOPT_POST, false);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
                        $response = curl_exec($ch);
                        // send response as JSON
                        $response = json_decode($response, true);

                        if (curl_error($ch)) {
                          $this->writeFileLog('addSimpleFT17', $ch);
                        } else if(empty($response)){
                          $this->writeFileLog('addSimpleFT17', 'EMPTY RESPONSE');                        
                        } else if(isset($response['messages'][0]['messageCodeLocale'])){
                          $this->writeFileLog('addSimpleFT17', $response['messages'][0]['messageCodeLocale']);                        
                        } else {
                          //Verify if exists template as default
                          $i = 0;
                          $count = count($response['result']);
                          $sendEmail = false;
                          while ($i < $count) {
                            foreach ($response['result'][$i] as $key => $value){
                              if($key == 'isDefault' && $value == 1){
                                $sendEmail = true;
                                $_SESSION['repstamp'] = $response['result'][$i]['repstamp'];
                                break;
                              }
                            }
                            ++$i;
                          }
                          //If exists template as default
                          if($sendEmail == true){
                            //Obtain client
                            $this->paramsQuery('ClWS', 'no', $_SESSION['numberClient']);
                            curl_setopt($ch, CURLOPT_URL, $this->url);
                            curl_setopt($ch, CURLOPT_POST, false);
                            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
                            $response = curl_exec($ch);
                            // send response as JSON
                            $response = json_decode($response, true);

                            if (curl_error($ch)) {
                              $this->writeFileLog('addSimpleFT18', $ch);
                            } else if(empty($response)){
                              $this->writeFileLog('addSimpleFT18', 'EMPTY RESPONSE');                            
                            } else if(isset($response['messages'][0]['messageCodeLocale'])){
                              $this->writeFileLog('addSimpleFT18', $response['messages'][0]['messageCodeLocale']);
                              $this->messagesError(utf8_decode(" in configuration of email to send them to client! Message from Backend: " . $response['messages'][0]['messageCodeLocale']));
                            } else {
                              if($response['result'][0]['email'] != ''){
                                //Email To
                                $_SESSION['emailClient'] = $response['result'][0]['email'];
                                //Email From
                                $infoAdmin = $this->get_settingsAdmin();
                                $emailAdmin = $infoAdmin[3]['default'];

                                //Send FT to email selected
                                $this->paramsSendReportEmail($_SESSION['repstamp'], $_SESSION['ftstamp'], $_SESSION['emailClient'], $emailAdmin);
                                curl_setopt($ch, CURLOPT_URL, $this->url);
                                curl_setopt($ch, CURLOPT_POST, false);
                                curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
                                $response = curl_exec($ch);
                                // send response as JSON
                                $response = json_decode($response, true); 

                                if (curl_error($ch)) {
                                  $this->writeFileLog('addSimpleFT19', $ch);
                                } else if(empty($response)){
                                  $this->writeFileLog('addSimpleFT19', 'EMPTY RESPONSE');                                
                                } else if(isset($response['messages'][0]['messageCodeLocale'])){
                                  $this->writeFileLog('addSimpleFT19', $response['messages'][0]['messageCodeLocale']);                                
                                }
                              }
                            }
                          }
                        }
                      }
                    }
                  } else {
                    //Manage stock
                    if($settings['backend']['manageStock'] == 'on'){
                      $post_ID = sanitize_text_field( $_REQUEST['post_ID'] );
                      $order_received = get_option('woocommerce_checkout_order_received_endpoint');

                      if(empty($order_received)){
                        $order = new WC_Order( $post_ID );
                      } else {
                        $order = new WC_Order( $order_received );
                      }

                      $i = 0;
                      foreach($order->get_items() as $key => $value){
                        $productReference = wc_get_product( $value['item_meta']['_product_id'][0] );
                        $sku[$i] = $productReference->get_sku();

                        $productID[$sku[$i]] = $value['item_meta']['_product_id'][0];
                      }

                      $i = 0;
                      $count = count($_SESSION['voProducts']['fis']);
                      while ($i < $count) {
                        foreach ($_SESSION['voProducts']['fis'][$i] as $key => $value){
                          //Obtain product from reference
                          $this->paramsQuery('StWS', 'ref', $_SESSION['voProducts']['fis'][$i]['ref']);
                          curl_setopt($ch, CURLOPT_URL, $this->url);
                          curl_setopt($ch, CURLOPT_POST, false);
                          curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
                          $response = curl_exec($ch);
                          // send response as JSON
                          $response = json_decode($response, true);

                          if (curl_error($ch)) {
                            $this->writeFileLog('addSimpleFT20', $ch);
                          } else if(empty($response)){
                            $this->writeFileLog('addSimpleFT20', 'EMPTY RESPONSE');                          
                          } else if(isset($response['messages'][0]['messageCodeLocale'])){
                            $this->writeFileLog('addSimpleFT20', $response['messages'][0]['messageCodeLocale']);                          
                          } else {
                            //If find ref, update stock
                            foreach ($sku as $key => $value) {
                              if($_SESSION['voProducts']['fis'][$i]['ref'] == $value){
                                update_post_meta($productID[$value],'_stock',$response['result'][0]['stock']);
                              }
                            }
                          }    
                        }
                        ++$i;
                      }
                    }
                  }
                }
              }
            }
          }
        }
      }
    }
  }

  //Add products from PHC FX in online shop
  function addProduct($nomeProduct, $contentProduct, $excerptProduct, $slugNameProduct, $stockUnitsProduct, $price, $sku, $manageStock, $visible, $thumb_url){
    $settings = get_option(PHCFXWOOCOMMERCE_PLUGIN_NAME);
    global $wpdb;

    $post = array(
                  'post_title'   => $nomeProduct,
                  'post_content' => $contentProduct,
                  'post_status'  => "publish",
                  'post_excerpt' => $excerptProduct,
                  'post_name'    => $slugNameProduct,
                  'post_type'    => "product"
                 );
     
    //Create product/post:
    $new_post_id = wp_insert_post($post, $wp_error);

    //make product type be variable:
    wp_set_object_terms ($new_post_id,'simple','product_type');

    //set product values:
    if($stockUnitsProduct > 0){
      update_post_meta( $new_post_id, '_stock_status', 'instock');
    } else {
      update_post_meta( $new_post_id, '_stock_status', 'outofstock');
    }

    update_post_meta( $new_post_id, '_sku', $sku);
    update_post_meta( $new_post_id, '_visibility', $visible); 
    update_post_meta( $new_post_id, '_price', $price );
    update_post_meta( $new_post_id, '_regular_price', $price );

    if($settings['backend']['manageStock'] == 'on'){
      update_post_meta( $new_post_id, '_manage_stock', $manageStock);
      update_post_meta( $new_post_id, '_stock', $stockUnitsProduct);
    }  

    //To return product that can't be inserted
    if($nomeProduct == ''){
      echo " Ref.:" . $sku . "   ";
    }

    //Save image
	try {
		$thumb_url = str_replace(" ", "%20", $thumb_url);

		//echo $thumb_url;
		$tmp = tempnam(sys_get_temp_dir(), "UL_IMAGE");
		$img = file_get_contents($thumb_url);
		
		if($img != ''){
			file_put_contents($tmp, $img);
			
			preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG)/', $thumb_url, $matches);
			$file_array['name'] = basename($matches[0]);
			$file_array['tmp_name'] = $tmp;
			
			// If error storing temporarily, unlink
			if ( is_wp_error( $tmp ) ) {
			  @unlink($file_array['tmp_name']);
			  $file_array['tmp_name'] = '';
			}

			//use media_handle_sideload to upload img:
			$thumbid = media_handle_sideload( $file_array, $new_post_id, 'gallery desc' );

			// If error storing permanently, unlink
			if ( is_wp_error($thumbid) ) {
			  @unlink($file_array['tmp_name']);
			}

			set_post_thumbnail($new_post_id, $thumbid);

			update_post_meta( $new_post_id, '_product_image_gallery', $thumbid);
		}
	} catch (Exception $e) {
		$this->writeFileLog('Image Product', ' ');
	}
  }

  //Show list of products
	public function listProducts(){
		$settings = get_option(PHCFXWOOCOMMERCE_PLUGIN_NAME);
    //Obtain configuration to make login
    $this->paramsLogin();
    //initial request with login data
    $ch = curl_init();
    //URL to save cookie "ASP.NET_SessionId"
    curl_setopt($ch, CURLOPT_URL, $this->url);
    curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/32.0.1700.107 Chrome/32.0.1700.107 Safari/537.36');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    //Parameters passed to POST
    curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIESESSION, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, '');
    curl_setopt($ch, CURLOPT_COOKIEFILE, ''); 
    $response = curl_exec($ch);

    if (curl_error($ch)) {
      $this->writeFileLog('listProducts', $ch);
      unset($_SESSION['username']);
    } else if(empty($response)){
      $this->writeFileLog('listProducts', 'EMPTY RESPONSE');
      unset($_SESSION['username']);
    } else if(isset($response['messages'][0]['messageCodeLocale'])){
      $this->writeFileLog('listProducts', $response['messages'][0]['messageCodeLocale']);
      unset($_SESSION['username']);
    } else {       
      	//Configured "All warehouses" in product settings  
  		if($settings['backend']['warehouse'] == -1){
  			$this->paramsQuery2('StWS');
  		} else {
  			$this->paramsQuery('SaWS', "armazem", $settings['backend']['warehouse']);
  		}

	    curl_setopt($ch, CURLOPT_URL, $this->url);
	    curl_setopt($ch, CURLOPT_POST, false);
	    curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
	    $response = curl_exec($ch);
	    // send response as JSON
	    $response = json_decode($response, true);  

	    if (curl_error($ch)) {
	    	$this->writeFileLog('listProducts2', $ch);
	    } else if(empty($response)){
	    	$this->writeFileLog('listProducts2', 'EMPTY RESPONSE');        
	    } else if(isset($response['messages'][0]['messageCodeLocale'])){
	    	$this->writeFileLog('listProducts2', $response['messages'][0]['messageCodeLocale']);        
	    } else {			

		 	$i = 0;
			$count = count($response['result']);
			$refArray = '';
			while ($i < $count) {
			    foreach ($response['result'][$i] as $key => $value){
					if($key == "ref"){
				   		if($i > 0){
				    		$refArray .= ",";
				  		}
				        $arrayRef[$value] = $response['result'][$i]['stock'];
				          
				      	$refArray .= '{
				           		        "comparison":0,
				                   		"filterItem":"ref",
						                "valueItem":"'.$value.'",
						                "groupItem":9,
						                "checkNull":false,
						                "skipCheckType":false,
						                "type":"Number"
						              }';				                                                       
			    	}
	    		}
	    		++$i;
  	    	} 	    		

  			// build our web service full URL
  			$this->paramsQuery3('StWS', $refArray);
  			curl_setopt($ch, CURLOPT_URL, $this->url);
  			curl_setopt($ch, CURLOPT_POST, false);
  			curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
  			$response = curl_exec($ch);
  			// send response as JSON
  			$response = json_decode($response, true);   	 			

  			if (curl_error($ch)) {
  			  $this->writeFileLog('listProducts3', $ch);
  			} else if(empty($response)){
  			  $this->writeFileLog('listProducts3', 'EMPTY RESPONSE');  			  
  			} else if(isset($response['messages'][0]['messageCodeLocale'])){
  			  $this->writeFileLog('listProducts3', $response['messages'][0]['messageCodeLocale']);  			  
  			} else {
		     	//Know which column is used to see iva included based on backend configuration of plugin
	      	switch ($settings['backend']['productPriceColumn']) {
	        	case 'epv1':
		          	$columnIva = 'iva1incl';
		          	break;
		        case 'epv2':
		        	$columnIva = 'iva2incl';
		        	break;
		        case 'epv3':
		        	$columnIva = 'iva3incl';
		        	break;
		        case 'epv4':
		        	$columnIva = 'iva4incl';
		        	break;
		        case 'epv5':
		        	$columnIva = 'iva5incl';
		        	break;
		      }

	      	//Create rows with information of products
	      	$tableProducts = '';
	      	$tableProducts .= "<thead><tr><th style='text-align: left;'><input type='checkbox' onClick='toggle(this)'/> Select all</th><th style='text-align: left;'>Ref</th><th style='text-align: left;'>Product Name</th><th style='text-align: left;'>Family</th><th style='text-align: right;'>Stock</th></tr></thead>";

	      	if(is_array($response['result'])){
	        	if(!empty($settings['backend']['productPriceColumn']) && $settings['backend']['productPriceColumn'] != 'epv0'){
		          	foreach ($response['result'] as $key => $value) {
						$woocommerce_calc_taxes = get_option('woocommerce_calc_taxes');
						$woocommerce_prices_include_tax = get_option('woocommerce_prices_include_tax');

	                	//Configured in tax tab of woocommerce
		            	if(($woocommerce_calc_taxes == "no") || ($woocommerce_calc_taxes == "yes" && $woocommerce_prices_include_tax == "yes")) {
			              	if($response['result'][$key][$columnIva] == 1){
			                	$tableProducts .= "<tr>";
	                			if(wc_get_product_id_by_sku( $response['result'][$key]['ref'] )== ""){
                      				if($arrayRef[$response['result'][$key]['ref']] < 0){
	                    				$tableProducts .= "<td style='text-align: left;'><input type='checkbox' disabled name='checkboxes' value='".$response['result'][$key]['ref']."'></td>";
	                  				} else {
	                    				$tableProducts .= "<td style='text-align: left;'><input type='checkbox' name='checkboxes' value='".$response['result'][$key]['ref']."'></td>";
	                  				}
	                			} else {
	                 	   			$tableProducts .= "<td style='text-align: left;'><div class='updateStockShop' id='".$response['result'][$key]['ref']."'><img src='".plugins_url('/images/right.png', __FILE__)."' alt='' width='16' height='16'></div></td>";
	                			}

	                			$tableProducts .= "<td style='text-align: left;'>". $response['result'][$key]['ref'].   
	                							  "</td><td style='text-align: left;'>". $response['result'][$key]['design'].
	                							  "</td><td style='text-align: left;'>". $response['result'][$key]['familia']."</td>";
		                		if($response['result'][$key]['stock'] < 0){
		                  			$tableProducts .= "<td style='text-align: right; color: red;'>".$arrayRef[$response['result'][$key]['ref']]."</td></tr>";
		                		} else {
		                  			$tableProducts .= "<td style='text-align: right;'>".$arrayRef[$response['result'][$key]['ref']]."</td></tr>";
		                		}
		              		}
		            	} else if($woocommerce_calc_taxes == "yes" && $woocommerce_prices_include_tax == 'no'){
							if($response['result'][$key][$columnIva] == 0){
								$tableProducts .= "<tr>";
								if(wc_get_product_id_by_sku( $response['result'][$key]['ref'] )== ""){
									if($arrayRef[$response['result'][$key]['ref']] < 0){
										$tableProducts .= "<td style='text-align: left;'><input type='checkbox' disabled name='checkboxes' value='".$response['result'][$key]['ref']."'></td>";
									} else {
										$tableProducts .= "<td style='text-align: left;'><input type='checkbox' name='checkboxes' value='".$response['result'][$key]['ref']."'></td>";
									}
								} else {
									$tableProducts .= "<td style='text-align: left;'><div class='updateStockShop' id='".$response['result'][$key]['ref']."'><img src='".plugins_url('/images/right.png', __FILE__)."' alt='' width='16' height='16'></div></td>";
								}

								$tableProducts .= "<td style='text-align: left;'>".$response['result'][$key]['ref'].   "</td><td style='text-align: left;'>".$response['result'][$key]['design']."</td><td style='text-align: left;'>".
													 $response['result'][$key]['familia']."</td>";
								if($response['result'][$key]['stock'] < 0){
								  $tableProducts .= "<td style='text-align: right; color: red;'>".$arrayRef[$response['result'][$key]['ref']]."</td></tr>";
								} else {
								  $tableProducts .= "<td style='text-align: right;'>".$arrayRef[$response['result'][$key]['ref']]."</td></tr>";
								}
							}
			            }
		         	}
					    echo $tableProducts;
	        	} else {
	          		$this->writeFileLog('listProducts4', 'Products: Empty productPriceColumn');
	        	}  
	      	} 
		    }
			}
		}   
		//Logout
    $this->paramsLogout();
    //session_destroy();
    curl_setopt($ch, CURLOPT_URL, $this->url);
    curl_setopt($ch, CURLOPT_POST, false);
    $response = curl_exec($ch);
    curl_close ( $ch );

    return $tableProducts;
	}

  //Save products in online shop
	public function saveProducts($refs){
	  $settings = get_option(PHCFXWOOCOMMERCE_PLUGIN_NAME);
    //Obtain configuration to make login
    $this->paramsLogin();
    //initial request with login data
    $ch = curl_init();
    //URL to save cookie "ASP.NET_SessionId"
    curl_setopt($ch, CURLOPT_URL, $this->url);
    curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/32.0.1700.107 Chrome/32.0.1700.107 Safari/537.36');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    //Parameters passed to POST
    curl_setopt($ch, CURLOPT_POSTFIELDS, $this->query);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIESESSION, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, ''); 
    curl_setopt($ch, CURLOPT_COOKIEFILE, '');  
    $response = curl_exec($ch);
    // send response as JSON
    $response = json_decode($response, true); 

    if (curl_error($ch)) {
      $this->writeFileLog('saveProducts', $ch);
      unset($_SESSION['username']);
    } else if(empty($response)){
      $this->writeFileLog('saveProducts', 'EMPTY RESPONSE');
      unset($_SESSION['username']);
    } else if(isset($response['messages'][0]['messageCodeLocale'])){
      $this->writeFileLog('saveProducts', $response['messages'][0]['messageCodeLocale']);
      unset($_SESSION['username']);
    } else {  
      //Configured "All warehouses" in product settings 
      if($settings['backend']['warehouse'] == -1){
        $this->paramsQuery2('StWS');
      } else {
        $this->paramsQuery('SaWS', "armazem", $settings['backend']['warehouse']);
      }
      curl_setopt($ch, CURLOPT_URL, $this->url);
      curl_setopt($ch, CURLOPT_POST, false);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
      $response = curl_exec($ch);
      // send response as JSON
      $response = json_decode($response, true);  

      if (curl_error($ch)) {
        $this->writeFileLog('saveProducts2', $ch);
      } else if(empty($response)){
        $this->writeFileLog('saveProducts2', 'EMPTY RESPONSE');          
      } else if(isset($response['messages'][0]['messageCodeLocale'])){
        $this->writeFileLog('saveProducts2', $response['messages'][0]['messageCodeLocale']);          
      } else {
        $i = 0;
        $count = count($response['result']);
        $refArray = '';
        //Create query of products to insert
        while ($i < $count) {
          foreach ($response['result'][$i] as $key => $value){
            if(is_array($refs)){
				foreach ($refs as $arrayOfRef){
				  if($key == "ref" && $arrayOfRef == $value){
					if($i > 0){
					  $refArray .= ",";
					}
					$arrayRef[$value] = $response['result'][$i]['stock'];
					
					$refArray .= '{
									"comparison":0,
									"filterItem":"ref",
									"valueItem":"'.$value.'",
									"groupItem":9,
									"checkNull":false,
									"skipCheckType":false,
									"type":"Number"
								  }';                                                              
				  } 
				}
			}
          }
          ++$i;
        }

        //Obtain products from st
        $this->paramsQuery3('StWS', $refArray);
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
        $response = curl_exec($ch);
        // send response as JSON
        $response = json_decode($response, true);    

        if (curl_error($ch)) {
          $this->writeFileLog('saveProducts3', $ch);
        } else if(empty($response)){
          $this->writeFileLog('saveProducts3', 'EMPTY RESPONSE');            
        } else if(isset($response['messages'][0]['messageCodeLocale'])){
          $this->writeFileLog('saveProducts3', $response['messages'][0]['messageCodeLocale']);            
        } else {
          //If exists selected products  
    			if(is_array($refs)){
		        $i = 0;
		        $count = count($response['result']);
		        while ($i < $count) {
	          	foreach ($refs as $key => $value) {
	            	if ($value == $response['result'][$i]['ref']) {
		              //Obtain url of image to save in shop
		              $thumb_url = $settings['backend']['url'] . "/cimagem.aspx?recstamp=".$response['result'][$i]['imagem']['recstamp'].
		                                                         "&oritable=".$response['result'][$i]['imagem']['oriTable'].
		                                                         "&uniqueid=".$response['result'][$i]['imagem']['uniqueid'].
		                                                         "&filename=".$response['result'][$i]['imagem']['imageName'].
		                                                         "&iflstamp=".$response['result'][$i]['imagem']['iflstamp'].
                                                             "&imageExtension=jpg";
                  //Add Product
	              	$this->addProduct($response['result'][$i]['design'], 
	                         $response['result'][$i]['design'], 
	                         $response['result'][$i]['design'], 
	                         $response['result'][$i]['design'], 
	                         $arrayRef[$response['result'][$key]['ref']],  
	                         $response['result'][$i][$settings['backend']['productPriceColumn']],
	                         $response['result'][$i]['ref'],    
	                         'yes',
	                         'visible', 
	                         $thumb_url);
          			}
        			}
        			++$i;
        		}  
      		}     
    	  }	  
  	  }
    }
  	//Logout
    $this->paramsLogout();
    //session_destroy();
    curl_setopt($ch, CURLOPT_URL, $this->url);
    curl_setopt($ch, CURLOPT_POST, false);
    $response = curl_exec($ch);
	}

  //Update only stocks of products
	public function updateStocksProducts($refs){
		$settings = get_option(PHCFXWOOCOMMERCE_PLUGIN_NAME);
    //Obtain configuration to make login
    $this->paramsLogin();
    //initial request with login data
    $ch = curl_init();
    //URL to save cookie "ASP.NET_SessionId"
    curl_setopt($ch, CURLOPT_URL, $this->url);
    curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/32.0.1700.107 Chrome/32.0.1700.107 Safari/537.36');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    //Parameters passed to POST
    curl_setopt($ch, CURLOPT_POSTFIELDS, $this->query);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIESESSION, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, ''); 
    curl_setopt($ch, CURLOPT_COOKIEFILE, '');  
    $response = curl_exec($ch);
    // send response as JSON
    $response = json_decode($response, true); 

    if (curl_error($ch)) {
      $this->writeFileLog('updateStocksProducts', $ch);
      unset($_SESSION['username']);
    } else if(empty($response)){
      $this->writeFileLog('updateStocksProducts', 'EMPTY RESPONSE');
      unset($_SESSION['username']);
    } else if(isset($response['messages'][0]['messageCodeLocale'])){
      $this->writeFileLog('updateStocksProducts', $response['messages'][0]['messageCodeLocale']);
      unset($_SESSION['username']);
    } else {  
      //Configured "All warehouses" in product settings 
      if($settings['backend']['warehouse'] == -1){
          $this->paramsQuery2('StWS');
      } else {
          $this->paramsQuery('SaWS', "armazem", $settings['backend']['warehouse']);
      }
      curl_setopt($ch, CURLOPT_URL, $this->url);
      curl_setopt($ch, CURLOPT_POST, false);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
      $response = curl_exec($ch);
      // send response as JSON
      $response = json_decode($response, true);  

      if (curl_error($ch)) {
        $this->writeFileLog('updateStocksProducts2', $ch);
      } else if(empty($response)){
        $this->writeFileLog('updateStocksProducts2', 'EMPTY RESPONSE');        
      } else if(isset($response['messages'][0]['messageCodeLocale'])){
        $this->writeFileLog('updateStocksProducts2', $response['messages'][0]['messageCodeLocale']);        
      } else {
        $i = 0;
        $count = count($response['result']);
        $refArray = '';
        while ($i < $count) {
          foreach ($response['result'][$i] as $key => $value){
            foreach ($refs as $arrayOfRef){
              if($key == "ref" && $arrayOfRef == $value){
					if($i > 0){
					  $refArray .= ",";
					}
					$arrayRef[$value] = $response['result'][$i]['stock'];
					                
                $refArray .= '{
                                "comparison":0,
                                "filterItem":"ref",
                                "valueItem":"'.$value.'",
                                "groupItem":9,
                                "checkNull":false,
                                "skipCheckType":false,
                                "type":"Number"
                              }';                                                              
              } 
            }
          }
          ++$i;
        }
        //If exists selected products  
        if(is_array($refs)){
          foreach ($refs as $key => $value) {            
            //Obtain post_id of postmeta table
            $productID = wc_get_product_id_by_sku($value);
            //Obtain products
            $this->paramsQuery3('StWS', $refArray);
            curl_setopt($ch, CURLOPT_URL, $this->url);
            curl_setopt($ch, CURLOPT_POST, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
            $response = curl_exec($ch);
            // send response as JSON
            $response = json_decode($response, true);    

            if (curl_error($ch)) {
              $this->writeFileLog('updateStocksProducts3', $ch);
            } else if(empty($response)){
              $this->writeFileLog('updateStocksProducts3', 'EMPTY RESPONSE');
              //$this->sendEmail(utf8_decode("It is not possible to create client!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/>Please insert your internal document in PHC FX manually"));
            } else if(isset($response['messages'][0]['messageCodeLocale'])){
              $this->writeFileLog('updateStocksProducts3', $response['messages'][0]['messageCodeLocale']);
              //$this->sendEmail(utf8_decode("It is not possible to save header of internal document!<br/><br/>Error from Backend: " . $response['messages'][0]['messageCodeLocale'] . "!<br/><br/><b>Please insert your internal document in PHC FX manually</b>"));
            } else {
              //Verify if product is returned to update stock
              if(!empty($response['result'][0]) && !empty($productID)){
                if($settings['backend']['manageStock'] == 'on'){
                  update_post_meta($productID,'_stock',$arrayRef[$response['result'][$key]['ref']]);    
                  
                  if($arrayRef[$response['result'][$key]['ref']] > 0){
                    update_post_meta( $productID, '_stock_status', 'instock');
                  } else {
                    update_post_meta( $productID, '_stock_status', 'outofstock');
                  }
                }   
              }
            }  
          }   
        } 
      }
    }
    //Logout
    $this->paramsLogout();
    //session_destroy();
    curl_setopt($ch, CURLOPT_URL, $this->url);
    curl_setopt($ch, CURLOPT_POST, false);
    $response = curl_exec($ch);
  }

  //Update all field of products
	public function updateAllFieldsProducts($refs){
		$settings = get_option(PHCFXWOOCOMMERCE_PLUGIN_NAME);  
    //If exists selected products  
    if(is_array($refs)){
      foreach ($refs as $key => $value) {            
        //Obtain post_id of postmeta table
        $productID = wc_get_product_id_by_sku($value);

        //Obtain configuration to make login
        $this->paramsLogin();
        //initial request with login data
        $ch = curl_init();
        //URL to save cookie "ASP.NET_SessionId"
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/32.0.1700.107 Chrome/32.0.1700.107 Safari/537.36');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        //Parameters passed to POST
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->query);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIESESSION, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, '');  //could be empty, but cause problems on some hosts
        curl_setopt($ch, CURLOPT_COOKIEFILE, '');  //could be empty, but cause problems on some hosts
        $response = curl_exec($ch);
        // send response as JSON
        $response = json_decode($response, true); 

        if (curl_error($ch)) {
          $this->writeFileLog('updateAllFieldsProducts', $ch);
          unset($_SESSION['username']);
        } else if(empty($response)){
          $this->writeFileLog('updateAllFieldsProducts', 'EMPTY RESPONSE');
          unset($_SESSION['username']);
        } else if(isset($response['messages'][0]['messageCodeLocale'])){
          $this->writeFileLog('updateAllFieldsProducts', $response['messages'][0]['messageCodeLocale']);
          unset($_SESSION['username']);
        } else {  
          //Configured "All warehouses" in product settings 
          if($settings['backend']['warehouse'] == -1){
              $this->paramsQuery2('StWS');
          } else {
              $this->paramsQuery('SaWS', "armazem", $settings['backend']['warehouse']);
          }

          curl_setopt($ch, CURLOPT_URL, $this->url);
          curl_setopt($ch, CURLOPT_POST, false);
          curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
          $response = curl_exec($ch);
          // send response as JSON
          $response = json_decode($response, true);  

          if (curl_error($ch)) {
            $this->writeFileLog('updateAllFieldsProducts2', $ch);
          } else if(empty($response)){
            $this->writeFileLog('updateAllFieldsProducts2', 'EMPTY RESPONSE');            
          } else if(isset($response['messages'][0]['messageCodeLocale'])){
            $this->writeFileLog('updateAllFieldsProducts2', $response['messages'][0]['messageCodeLocale']);            
          } else {
            $i = 0;
            $count = count($response['result']);
            $refArray = '';
            while ($i < $count) {
              foreach ($response['result'][$i] as $keyy => $valuee){
                foreach ($refs as $arrayOfRef){
                  if($keyy == "ref" && $arrayOfRef == $valuee){
					if($i > 0){
					  $refArray .= ",";
					}
					$arrayRef[$valuee] = $response['result'][$i]['stock'];
                    
                    $refArray .= '{
                                    "comparison":0,
                                    "filterItem":"ref",
                                    "valueItem":"'.$valuee.'",
                                    "groupItem":9,
                                    "checkNull":false,
                                    "skipCheckType":false,
                                    "type":"Number"
                                  }';                                                              
                  } 
                }
              }
              ++$i;
            }

            $this->paramsQuery3('StWS', $refArray);
            curl_setopt($ch, CURLOPT_URL, $this->url);
            curl_setopt($ch, CURLOPT_POST, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
            $response = curl_exec($ch);
            // send response as JSON
            $response = json_decode($response, true);    

            if (curl_error($ch)) {
              $this->writeFileLog('updateAllFieldsProducts3', $ch);
            } else if(empty($response)){
              $this->writeFileLog('updateAllFieldsProducts3', 'EMPTY RESPONSE');              
            } else if(isset($response['messages'][0]['messageCodeLocale'])){
              $this->writeFileLog('updateAllFieldsProducts3', $response['messages'][0]['messageCodeLocale']);              
            } else {
              //Verify if product is returned to update stock
              if(!empty($response['result'][0]) && !empty($productID)){
                if($settings['backend']['manageStock'] == 'on'){
                  update_post_meta($productID,'_stock',$arrayRef[$response['result'][$key]['ref']]);                        
                    
                  if($arrayRef[$response['result'][$key]['ref']] > 0){
                    update_post_meta( $productID, '_stock_status', 'instock');
                  } else {
                    update_post_meta( $productID, '_stock_status', 'outofstock');
                  }
                }  
                $my_post = array('ID'           => $productID,
                                 'post_title'   => $response['result'][$key]['design'],
                                 'post_content' => $response['result'][$key]['design'],
                                 'post_excerpt' => $response['result'][$key]['design'],
                                 'post_name'    => $response['result'][$key]['design'],
                                );
                wp_update_post( $my_post );
                update_post_meta( $productID, '_price', $response['result'][$key][$settings['backend']['productPriceColumn']] );
                update_post_meta( $productID, '_regular_price', $response['result'][$key][$settings['backend']['productPriceColumn']]);   

				//Update image
				try {
					//Obtain url of image to save in shop
					$thumb_url = $settings['backend']['url']."/cimagem.aspx?recstamp=".$response['result'][$key]['imagem']['recstamp'].
															 "&oritable=".$response['result'][$key]['imagem']['oriTable'].
															 "&uniqueid=".$response['result'][$key]['imagem']['uniqueid'].
															 "&filename=".$response['result'][$key]['imagem']['imageName'].
															 "&iflstamp=".$response['result'][$key]['imagem']['iflstamp'].
                                                             "&imageExtension=jpg";
															 
					$thumb_url = str_replace(" ", "%20", $thumb_url);

					$tmp = tempnam(sys_get_temp_dir(), "UL_IMAGE");
					
					$img = file_get_contents($thumb_url);
					echo $img;
					if($img != ''){
						file_put_contents($tmp, $img);
						
						preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG)/', $thumb_url, $matches);
						$file_array['name'] = basename($matches[0]);
						$file_array['tmp_name'] = $tmp;
						
						// If error storing temporarily, unlink
						if ( is_wp_error( $tmp ) ) {
						  @unlink($file_array['tmp_name']);
						  $file_array['tmp_name'] = '';
						}

						//use media_handle_sideload to upload img:
						$thumbid = media_handle_sideload( $file_array, $productID, 'gallery desc' );

						// If error storing permanently, unlink
						if ( is_wp_error($thumbid) ) {
						  @unlink($file_array['tmp_name']);
						}

						set_post_thumbnail($productID, $thumbid);

						update_post_meta( $productID, '_product_image_gallery', $thumbid);
					} else {
						$existing = get_post_thumbnail_id( $productID );
						if($existing) {
							wp_delete_attachment($existing, true);
						}
						//delete_post_thumbnail( $productID );
					}
				} catch (Exception $e) {
					$this->writeFileLog('Image Product', ' ');
				}
	
              }
            }
          }  
          //Logout
          $this->paramsLogout();
          //session_destroy();
          curl_setopt($ch, CURLOPT_URL, $this->url);
          curl_setopt($ch, CURLOPT_POST, false);
          $response = curl_exec($ch);
        } 		   
	  	}
    }
	}

	//Add new type of order
	public function newTypeOfOrder($nameTypeOfOrder, $manageStock, $warehouse){
		$settings = get_option(PHCFXWOOCOMMERCE_PLUGIN_NAME);
    //Obtain configuration to make login
    $this->paramsLogin();
    //initial request with login data
    $ch = curl_init();
    //URL to save cookie "ASP.NET_SessionId"
    curl_setopt($ch, CURLOPT_URL, $this->url);
    curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/32.0.1700.107 Chrome/32.0.1700.107 Safari/537.36');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    //Parameters passed to POST
    curl_setopt($ch, CURLOPT_POSTFIELDS, $this->query);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIESESSION, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, '');  
    curl_setopt($ch, CURLOPT_COOKIEFILE, ''); 
    $response = curl_exec($ch);
    // send response as JSON
    $response = json_decode($response, true); 

    if (curl_error($ch)) {
      $this->writeFileLog('newTypeOfOrder', $ch);
      unset($_SESSION['username']);
    } else if(empty($response)){
      $this->writeFileLog('newTypeOfOrder', 'EMPTY RESPONSE');
      unset($_SESSION['username']);
    } else if(isset($response['messages'][0]['messageCodeLocale'])){
      $this->writeFileLog('newTypeOfOrder', $response['messages'][0]['messageCodeLocale']);
      unset($_SESSION['username']);
    } else {  
      $this->paramsNewInstance('TsWS', 0);
      curl_setopt($ch, CURLOPT_URL, $this->url);
      curl_setopt($ch, CURLOPT_POST, false);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
      $response = curl_exec($ch);
      // send response as JSON
      $response = json_decode($response, true);   

      if (curl_error($ch)) {
      	$this->writeFileLog('newTypeOfOrder2', $ch);
      } else if(empty($response)){
      	$this->writeFileLog('newTypeOfOrder2', 'EMPTY RESPONSE');
      } else if(isset($response['messages'][0]['messageCodeLocale'])){
      	$this->writeFileLog('newTypeOfOrder2', $response['messages'][0]['messageCodeLocale']);
      } else {
        //Change VO
      	$response['result'][0]['nmdos'] = $nameTypeOfOrder;
      	$response['result'][0]['bdempresas'] = 'CL';
      	$response['result'][0]['tiposaft'] = 'OT';
      	$response['result'][0]['preco2'] = 'Venda';	
      	$response['result'][0]['qtt1'] = 'QT Real';	
      	$response['result'][0]['qtt2'] = 'QT Facturada';	
      	$response['result'][0]['texto1'] = 'Local de entrega';	
      	$response['result'][0]['texto2'] = 'Observações';	
      	$response['result'][0]['nmdatafim'] = 'Data Final';	
      	$response['result'][0]['nmdtopen'] = 'Data Inicial';	
      	$response['result'][0]['usaiva'] = true;	
      	$response['result'][0]['tabela1'] = '';	
      	$response['result'][0]['evend'] = true;	
      	$response['result'][0]['ndescs'] = 1;	
      	$response['result'][0]['naoencomenda'] = false;	
      	$response['result'][0]['showPackageInfo'] = false;	
      	$response['result'][0]['bonotgenerateft'] = false;	
      	$response['result'][0]['editaobrano'] = true;	
      	$response['result'][0]['rentabilidade'] = true;	

      	//Creation of product in Ts
      	$response['result'][0]['tsProducts'][0]['entityname'] = 'Ts';
      	$response['result'][0]['tsProducts'][0]['productid'] = 3;
      	$response['result'][0]['tsProducts'][0]['oristamp'] = "'" . $response['result'][0]['tsstamp'] . "'";
      	$response['result'][0]['tsProducts'][0]['isLazyLoaded'] = false;
      	$response['result'][0]['tsProducts'][0]['Operation'] = 1;
      	$response['result'][0]['tsProducts'][0]['syshist'] = false;

      	$response['result'][0]['armazem'] = (int)$warehouse;
        if($manageStock == 'true'){
  				$response['result'][0]['cmstocks'] = 7;	
  				$response['result'][0]['cmdesc'] = 'N/Fatura';	
  				$response['result'][0]['stocks'] = true;
  				$response['result'][0]['rescli'] = false;		          		
        } else {
      		$response['result'][0]['cmstocks'] = 0;	
      		$response['result'][0]['cmdesc'] = '';	
      		$response['result'][0]['rescli'] = true;	
      		$response['result'][0]['stocks'] = false;
      	}

        //Save data of Ts in PHC FX
      	$this->paramsSave('TsWS', $response);

      	curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
        $response = curl_exec($ch);
        // send response as JSON
        $response = json_decode($response, true);   

        if (curl_error($ch)) {
        	$this->writeFileLog('newTypeOfOrder3', $ch);
        } else if(empty($response)){
        	$this->writeFileLog('newTypeOfOrder3', 'EMPTY RESPONSE');
        } else if(isset($response['messages'][0]['messageCodeLocale'])){
        	$this->writeFileLog('newTypeOfOrder3', $response['messages'][0]['messageCodeLocale']);
        }
	    }
  	}
  	//Logout
    $this->paramsLogout();
    //session_destroy();
    curl_setopt($ch, CURLOPT_URL, $this->url);
    curl_setopt($ch, CURLOPT_POST, false);
    $response = curl_exec($ch);
	}
}