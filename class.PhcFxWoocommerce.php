<?php
class PhcFxWoocommerce {
  public $url;
  public $params;
  public $query;
  public $fieldStatus;
  public $extraurl = "";
  //public $extraurl = "/PHCWS";

  private $validSettings = false;

  // backend settings
  private $options = array(
    'backend' => array(
      'username'          => array('label' => 'Username',                                   'type' => 'text',          'required' => true,  'descr' => 'This username allows the backend to accept data sent from this plugin.', 'notice' => 'This can not be empty! Please enter your username...'),
      'password'          => array('label' => 'Password',                                   'type' => 'password',      'required' => true,  'descr' => 'This password allows the backend to accept data sent from this plugin.', 'notice' => 'This can not be empty! Please enter your password...'),
      'appid'             => array('label' => 'Application ID',                             'type' => 'text',          'required' => true,  'descr' => 'Here you should enter PHC FX Engine\'s Application ID key.<br>This allows your application to communicate with PHC FX Engine.', 'notice' => 'PHC-FX Engine is disabled! Please enter your Application ID...'),
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
      'manageStock'       => array('label' => 'Manage Stock',                               'type' => 'checkbox',      'required' => false, 'checkboxDescription' => 'Possibility to manage stock at your online shop', 'notice' => ''),
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

  /**
   * Initializes WordPress hooks
   */
  
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

    add_action( 'woocommerce_order_status_cancelled',  array($this, 'cancelOrder'));
    add_action( 'woocommerce_order_status_failed',  array($this, 'cancelOrder'));
    add_action( 'woocommerce_order_status_refunded',  array($this, 'cancelOrder'));

    $settings = get_option(PHCFXWOOCOMMERCE_PLUGIN_NAME);
    if(!empty($settings['backend']['typeOfOrder']) && !empty($settings['backend']['statusOfOrder'])){
      add_action('update_option',  array($this, 'saveFieldOrderStatus'));
    }  

    // handler for form submission
    add_action('admin_post_woocommerce_fx', array($this, 'woocommerce_fx'));
  }

  //Obtain info of plugins and save information
  public function init_plugin() {
    $plugins = get_plugins();

    define('PLUGIN_NAME_WOOCOMMERCE',    $plugins[PHCFXWOOCOMMERCE_PLUGIN]['Name']);
    define('PLUGIN_VERSION_WOOCOMMERCE', $plugins[PHCFXWOOCOMMERCE_PLUGIN]['Version']);
  }

  public function register_settings() {
    // === BACKEND SETTINGS SECTION ===========================================
    add_settings_section('backend-section', null, null, 'backend-options');
    add_settings_field(null, null, null, 'backend-options', 'backend-section');
    register_setting('backend-options', PHCFXWOOCOMMERCE_PLUGIN_NAME);

    // === IMPORT SETTINGS SECTION ============================================
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
  			return $this->listProducts();
  			break;
  		case 'saveProducts':
  			return $this->saveProducts($refs);
  			break;
  		case 'updateStocksProducts':
  			return $this->updateStocksProducts($refs);
  			break;
  		case 'updateAllFieldsProducts':
  			return $this->updateAllFieldsProducts($refs);
  			break;	
  		case 'statusOfOrder':
  			return $this->statusOfOrder($selectItems);
  			break;
  		case 'newTypeOfOrder':
  			$this->newTypeOfOrder($nameTypeOfOrder, $manageStock, $warehouse);
  			break;
      case 'updateTypeOfOrder':
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
    // build our web service full URL
    $settings['backend']['url'] = rtrim($settings['backend']['url'], '/');
    $this->url = "{$settings['backend']['url']}".$this->extraurl."/REST/UserLoginWS/userLoginCompany";

    // Create map with request parameters
    $this->params = array ('userCode' => $settings['backend']['username'], 
                     'password' => $settings['backend']['password'], 
                     'applicationType' => $settings['backend']['appid'],
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
                                                            "valueItem":'.$settings['backend']['typeOfOrder'].',
                                                            "groupItem":1,
                                                            "checkNull":false,
                                                            "skipCheckType":false,
                                                            "type":"Number"
                                                          }]}'
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

      //Obtain type invoices
      $this->paramsQuery('TdWS', 'inactivo', 0);

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
        $this->messagesError("Can't connect to webservice!! There's an empty response");
      } else if(isset($response['messages'][0]['messageCodeLocale'])){
        $this->writeFileLog('setCommunicationFx2', $response['messages'][0]['messageCodeLocale']);
        $this->messagesError(" obtain dropdown with type of invoices! Message from Backend: " . $response['messages'][0]['messageCodeLocale']);
      } else {

        //Create options of dropdownlist invoices
        $i = 0;
        $count = count($response['result']);
        $typeInvoice = array(); 

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
          $this->writeFileLog('setCommunicationFx3', $ch);
        } else if(empty($response)){
          $this->writeFileLog('setCommunicationFx3', 'EMPTY RESPONSE');
          $this->messagesError("Can't connect to webservice!! There's an empty response");
        } else if(isset($response['messages'][0]['messageCodeLocale'])){
          $this->writeFileLog('setCommunicationFx3', $response['messages'][0]['messageCodeLocale']);
          $this->messagesError(" obtain dropdown with type of documents! Message from Backend: " . $response['messages'][0]['messageCodeLocale']);
        } else {

          $i = 0;
          $count = count($response['result']);
          $typeInvoice = array(); 
          //Create options of dropdownlist internal documents
          while ($i < $count) {
            $selected_dropdown = '';
            foreach ($response['result'][$i] as $key => $value){
              if($key == "ndos"){
                $typeInvoice[$i]["ndos"] = $value;
                if($settings['backend']['typeOfOrder'] == $value){
                  $selected_dropdown = 'selected';
                }
              } else if ($key == "nmdos"){
                $typeInvoice[$i]["nmdos"] = $value;     
              }
            }
            $_SESSION['typeOfOrder'] .= "<option id=" . $typeInvoice[$i]["nmdos"] . " value=" . $typeInvoice[$i]["ndos"] . " " . $selected_dropdown . ">" .  $typeInvoice[$i]["nmdos"] ."</option><br>"; 
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
          $this->writeFileLog('setCommunicationFx3', $ch);
        } else if(empty($response)){
          $this->writeFileLog('setCommunicationFx3', 'EMPTY RESPONSE');
          $this->messagesError("Can't connect to webservice!! There's an empty response");
        } else if(isset($response['messages'][0]['messageCodeLocale'])){
          $this->writeFileLog('setCommunicationFx3', $response['messages'][0]['messageCodeLocale']);
          $this->messagesError(" obtain dropdown with type of documents! Message from Backend: " . $response['messages'][0]['messageCodeLocale']);
        } else {

          $i = 0;
          $count = count($response['result']);
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
          $this->writeFileLog('setCommunicationFx3', $ch);
        } else if(empty($response)){
          $this->writeFileLog('setCommunicationFx3', 'EMPTY RESPONSE');
          $this->messagesError("Can't connect to webservice!! There's an empty response");
        } else if(isset($response['messages'][0]['messageCodeLocale'])){
          $this->writeFileLog('setCommunicationFx3', $response['messages'][0]['messageCodeLocale']);
          $this->messagesError(" obtain dropdown with type of documents! Message from Backend: " . $response['messages'][0]['messageCodeLocale']);
        } else {

          $i = 0;
          $count = count($response['result']);
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
            $this->writeFileLog('setCommunicationFx4', $ch);
          } else if(empty($response)){
            $this->writeFileLog('setCommunicationFx4', 'EMPTY RESPONSE');
            $this->messagesError("Can't connect to webservice!! There's an empty response");
          } else if(isset($response['messages'][0]['messageCodeLocale'])){
            $this->writeFileLog('setCommunicationFx4', $response['messages'][0]['messageCodeLocale']);
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
      //Obtain type invoices
      $this->paramsQuery('E1ws', 'estab', 0);

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
      } else if(isset($response['messages'][0]['messageCodeLocale'])){
        $this->writeFileLog('saveFieldOrderStatus2', $response['messages'][0]['messageCodeLocale']);
      } else {
        if($response['result'][0]['moeda'] != get_option('woocommerce_currency')){
          $this->messagesError(": Please configure currency in shop according to PHC FX");
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
      $this->writeFileLog('saveFieldOrderStatus', $ch);
    } else if(empty($response)){
      $this->writeFileLog('saveFieldOrderStatus', 'EMPTY RESPONSE');
    } else if(isset($response['messages'][0]['messageCodeLocale'])){
      $this->writeFileLog('saveFieldOrderStatus', $response['messages'][0]['messageCodeLocale']);
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
        $this->writeFileLog('setCommunicationFx3', $ch);
      } else if(empty($response)){
        $this->writeFileLog('setCommunicationFx3', 'EMPTY RESPONSE');
        $this->messagesError("Can't connect to webservice!! There's an empty response");
      } else if(isset($response['messages'][0]['messageCodeLocale'])){
        $this->writeFileLog('setCommunicationFx3', $response['messages'][0]['messageCodeLocale']);
        $this->messagesError(" obtain dropdown with type of documents! Message from Backend: " . $response['messages'][0]['messageCodeLocale']);
      } else {

        $i = 0;
        $count = count($response['result']);
        $typeInvoice = array(); 
        //Create options of dropdownlist internal documents
        while ($i < $count) {
          $selected_dropdown = '';
          foreach ($response['result'][$i] as $key => $value){
            if($key == "ndos"){
              $typeInvoice[$i]["ndos"] = $value;
              if($settings['backend']['typeOfOrder'] == $value){
                $selected_dropdown = 'selected';
              }
            } else if ($key == "nmdos"){
              $typeInvoice[$i]["nmdos"] = $value;     
            }
          }
          echo "<option id=" . $typeInvoice[$i]["nmdos"] . " value=" . $typeInvoice[$i]["ndos"] . " " . $selected_dropdown . ">" .  $typeInvoice[$i]["nmdos"] ."</option><br>"; 
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
  public function statusOfOrder($selectItems){
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
    } else if(isset($response['messages'][0]['messageCodeLocale'])){
      $this->writeFileLog('saveFieldOrderStatus', $response['messages'][0]['messageCodeLocale']);
    } else {
    	//Obtain type invoices
	    $this->paramsQuery4('TsWS', $selectItems, $settings['backend']['typeOfOrder']);

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
	    } else if(isset($response['messages'][0]['messageCodeLocale'])){
	        $this->writeFileLog('saveFieldOrderStatus2', $response['messages'][0]['messageCodeLocale']);
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

      //Obtain type invoices
      $this->paramsQuery('TsWS', 'ndos', $settings['backend']['typeOfOrder']);

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
          //$this->sendEmail(utf8_decode("It is not possible to create field order!!<br/><br/>Can't connect to webservice!!<br/><br/>There's an empty response!!<br/><br/>Please insert your client and order in PHC FX manually"));
        } else if(isset($response['messages'][0]['messageCodeLocale'])){
          $this->writeFileLog('saveFieldOrderStatus3', $response['messages'][0]['messageCodeLocale']);
          //$this->sendEmail(utf8_decode("It is not possible to save field order!<br/><br/>Error from Backend: " . $response['messages'][0]['messageCodeLocale'] . "!!"));
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
      //$this->sendEmail(utf8_decode("It is not possible to make login!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/><b>Please insert your client and order in PHC FX manually</b>"));
    } else if(isset($response['messages'][0]['messageCodeLocale'])){
      $this->writeFileLog('addNewOrder', $response['messages'][0]['messageCodeLocale']);
      //$this->sendEmail(utf8_decode("Wrong Login!<br/><br/><b>Please check your settings and insert your client and order manually</b>"));
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
        $this->writeFileLog('saveFieldOrderStatus2', $ch);
      } else if(empty($response)){
        $this->writeFileLog('saveFieldOrderStatus2', 'EMPTY RESPONSE');
      } else if(isset($response['messages'][0]['messageCodeLocale'])){
        $this->writeFileLog('saveFieldOrderStatus2', $response['messages'][0]['messageCodeLocale']);
      } else {
        //Verify if currency of shop corresponds to PHC FX
        if($response['result'][0]['moeda'] == get_option('woocommerce_currency')){
          //Verify if client already exists in bd
          $billing_email_ = sanitize_text_field( $_REQUEST['_billing_email'] );
          $billing_email = sanitize_text_field( $_REQUEST['billing_email'] );
          $order_received = get_option('woocommerce_checkout_order_received_endpoint');

          if($billing_email_ == '' && $billing_email != ''){
            $this->paramsQuery('ClWS', 'email', $billing_email);
          } else if($billing_email_ != '' && $billing_email == ''){
            $this->paramsQuery('ClWS', 'email', $billing_email_);
          } else {
            $order = new WC_Order( $order_received );
            $billing_email = $order->billing_email;
            if($billing_email == ''){
              $billing_email = 'generic_client@email.com';
            }
            $this->paramsQuery('ClWS', 'email', $billing_email);
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
            //$this->sendEmail(utf8_decode("It is not possible to see if client already exists!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/><b>Please insert your information in PHC FX manually</b>"));
          } else if(isset($response['messages'][0]['messageCodeLocale'])){
            $this->writeFileLog('addNewOrder2', $response['messages'][0]['messageCodeLocale']);
            //$this->sendEmail(utf8_decode("It is not possible to see if client already exists!<br/><br/>Error from Backend: " . $response['messages'][0]['messageCodeLocale'] . "!<br/><br/><b>Please insert your information in PHC FX manually</b>"));
          } else {  
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
                $productReference = wc_get_product( $productData['product_id'] );
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
                $product = new WC_Product($value['product_id']);
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

            //User doesn't exists
            if(empty($response['result'])){
             
               //Obtain new instance of client
              $this->paramsNewInstance('ClWS', 0);

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
                //$this->sendEmail(utf8_decode("It is not possible to create client!<br/><br/>Can't connect to webservice!! There's an empty response!<br/><br/><b>Please insert your client and order in PHC FX manually</b>"));
              } else if(isset($response['messages'][0]['messageCodeLocale'])){
                $this->writeFileLog('addNewOrder3', $response['messages'][0]['messageCodeLocale']);
                //$this->sendEmail(utf8_decode("It is not possible to create client!<br/><br/>Error from Backend: " . $response['messages'][0]['messageCodeLocale'] . "!<br/><br/><b>Please insert your client and order in PHC FX manually</b>"));
              } else {
                
                //Save number id of client
                $_SESSION['nrClient'] = $response['result'][0]['no'];

                $billing_email_ = sanitize_text_field( $_REQUEST['_billing_email'] );
                $billing_first_name = sanitize_text_field( $_REQUEST['billing_first_name'] );
                $billing_last_name = sanitize_text_field( $_REQUEST['billing_last_name'] );
                $billing_email = sanitize_text_field( $_REQUEST['billing_email'] );
                $billing_address_1 = sanitize_text_field( $_REQUEST['billing_address_1'] );
                $billing_city = sanitize_text_field( $_REQUEST['billing_city'] );
                $billing_phone = sanitize_text_field( $_REQUEST['billing_phone'] );  
                $billing_postcode = sanitize_text_field( $_REQUEST['billing_postcode'] );                

                //Used in frontedn
                if($billing_email_ == ''){
                  $response['result'][0]['nome'] = $billing_first_name . " " . $billing_last_name;
                  //Passing client for order introduced like as "visitor"
                  if(trim($response['result'][0]['nome'])==""){
                    $response['result'][0]['nome'] = 'Generic Client Woocommerce';
                    $response['result'][0]['email'] = 'generic_client@email.com';
                  } else {
                    $response['result'][0]['email'] = $billing_email;
                  }
                  $response['result'][0]['morada'] = $billing_address_1;
                  $response['result'][0]['local'] = $billing_city;
                  $response['result'][0]['telefone'] = $billing_phone;
                  $response['result'][0]['codpost'] = $billing_postcode;
                } else { //Used in backend
                  $response['result'][0]['nome'] = $billing_first_name . " " . $billing_last_name;
                  //Generic Client Woocommerce for order introduced like as "visitor"
                  if(trim($response['result'][0]['nome'])==""){
                    $response['result'][0]['nome'] = 'Generic Client Woocommerce';
                    $response['result'][0]['email'] = 'generic_client@email.com';
                  } else {
                    $response['result'][0]['email'] = $billing_email;
                  }
                  $response['result'][0]['morada'] = $billing_address_1;
                  $response['result'][0]['local'] = $billing_city;
                  $response['result'][0]['telefone'] = $billing_phone;
                  $response['result'][0]['codpost'] = $billing_postcode;
                }

                //Save data of client in PHC FX
                $this->paramsSave('ClWS', $response);

                //Verify if required name field that's not an empty field
                /*if(empty($response['result'][0]['nome'])){
                  $this->sendEmail("It is not possible to save client!<br/><br/>Error from Backend: This error origined by an empty name of client!<br/><br/><b>Please insert your client and order in PHC FX manually</b>");
                } */

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
                  //$this->sendEmail(utf8_decode("It is not possible to create client!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/>Please insert your client and order in PHC FX manually"));
                } else if(isset($response['messages'][0]['messageCodeLocale'])){
                  $this->writeFileLog('addNewOrder4', $response['messages'][0]['messageCodeLocale']);
                  //$this->sendEmail(utf8_decode("It is not possible to save client!<br/><br/>Error from Backend: " . $response['messages'][0]['messageCodeLocale'] . "!<br/><br/><b>Please insert internal document and their items in PHC FX manually</b>"));
                } else {
                  //Inserted client in bd
                  $createClientSuccess = 1;
                }
              }
              //User exists
            } else {             
              //Save number id of client
              $_SESSION['nrClient'] = $response['result'][0]['no'];

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

              //Used in frontedn
              if($billing_email_ == '' && $billing_email != ""){
                if(($billing_first_name != "") || ($billing_last_name != "")){
                  $response['result'][0]['nome'] = $billing_first_name . " " . $billing_last_name;
                }
                if($billing_email != ""){
                  $response['result'][0]['email'] = $billing_email;
                }
                if($billing_address_1 != ""){
                  $response['result'][0]['morada'] = $billing_address_1;
                }
                if($billing_city != ""){
                  $response['result'][0]['local'] = $billing_city;
                }
                if($billing_phone != ""){
                  $response['result'][0]['telefone'] = $billing_phone;
                }
                if($billing_postcode != ""){
                  $response['result'][0]['codpost'] = $billing_postcode;
                }                
              } else { //Used in backend
                if(($billing_first_name_ != "") || ($billing_last_name_ != "")){
                  $response['result'][0]['nome'] = $billing_first_name_ . " " . $billing_last_name_;
                }
                if($billing_email_ != ""){
                  $response['result'][0]['email'] = $billing_email_;
                }
                if($billing_address_1_ != ""){
                  $response['result'][0]['morada'] = $billing_address_1_;
                }
                if($billing_city_ != ""){
                  $response['result'][0]['local'] = $billing_city_;
                }
                if($billing_phone_ != ""){
                  $response['result'][0]['telefone'] = $billing_phone_;
                }
                if($billing_postcode_ != ""){
                  $response['result'][0]['codpost'] = $billing_postcode_;
                }  
              }

              //Update operation
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
                $this->writeFileLog('addNewOrder4', $ch);
              } else if(empty($response)){
                $this->writeFileLog('addNewOrder4', 'EMPTY RESPONSE');
                //$this->sendEmail(utf8_decode("It is not possible to update client!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/>Please update your client and order in PHC FX manually"));
              } else if(isset($response['messages'][0]['messageCodeLocale'])){
                $this->writeFileLog('addNewOrder4', $response['messages'][0]['messageCodeLocale']);
                //$this->sendEmail(utf8_decode("It is not possible to update client!<br/><br/>Error from Backend: " . $response['messages'][0]['messageCodeLocale'] . "!<br/><br/><b>Please update internal document and their items in PHC FX manually</b>"));
              } 

              //Inserted client in bd
              $createClientSuccess = 1;
            }

            //If client is created/obtained with success
            if($createClientSuccess == 1){
                //If in settings of backoffice is checked option "create invoice"
                if($settings['backend']['createInvoice'] == 'on'){
                  //See if type of order is configured
                  if(!empty($settings['backend']['typeOfOrder']) && $settings['backend']['typeOfOrder'] != 0){
                    
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
                      $this->writeFileLog('addNewOrder5', $ch);
                    } else if(empty($response)){
                      $this->writeFileLog('addNewOrder5', 'EMPTY RESPONSE');
                      //$this->sendEmail(utf8_decode("It is not possible to create client!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/>Please insert your internal document in PHC FX manually"));
                    } else if(isset($response['messages'][0]['messageCodeLocale'])){
                      $this->writeFileLog('addNewOrder5', $response['messages'][0]['messageCodeLocale']);
                      //$this->sendEmail(utf8_decode("It is not possible to save header of internal document!<br/><br/>Error from Backend: " . $response['messages'][0]['messageCodeLocale'] . "!<br/><br/><b>Please insert your internal document in PHC FX manually</b>"));
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
                          $this->writeFileLog('addNewOrder6', $ch);
                        } else if(empty($response)){
                          $this->writeFileLog('addNewOrder6', 'EMPTY RESPONSE');
                          //$this->sendEmail(utf8_decode("It is not possible to create client!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/>Please insert your client and order in PHC FX manually"));
                        } else if(isset($response['messages'][0]['messageCodeLocale'])){
                          $this->writeFileLog('addNewOrder6', $response['messages'][0]['messageCodeLocale']);
                          //$this->sendEmail(utf8_decode("Error obtaining header of internal document!<br/><br/>Error from Backend: " . $response['messages'][0]['messageCodeLocale'] . "!<br/><br/><b>Please insert your internal document in PHC FX manually</b>"));
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
                            $this->writeFileLog('addNewOrder7', $ch);
                          } else if(empty($response)){
                            $this->writeFileLog('addNewOrder7', 'EMPTY RESPONSE');
                            //$this->sendEmail(utf8_decode("It is not possible to create lines of Bo!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/>Please insert your lines of internal document in PHC FX manually"));
                          } else if(isset($response['messages'][0]['messageCodeLocale'])){
                            $this->writeFileLog('addNewOrder7', $response['messages'][0]['messageCodeLocale']);
                            //$this->sendEmail(utf8_decode("It is not possible to create lines of Bo!<br/><br/>Error from Backend: " . $response['messages'][0]['messageCodeLocale'] . "!<br/><br/><b>Please insert your lines of internal document in PHC FX manually</b>"));
                          } else {
                             
                            //Obtain VO with updated Bi and Bo
                            if (is_array($_SESSION['listOfQuantity'])){
                              foreach ($_SESSION['listOfQuantity'] as $key => $value){
                                if($response['result'][0]['bis'][$key]['qtt'] != $value){
                                  $response['result'][0]['bis'][$key]['qtt'] = $value;
                                }           
                              }
                            }

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

                            if(isset($response['result'][0])){
                              $statusOrderShop = $this->fieldStatusOrder($settings['backend']['statusOfOrder']);

                              if(!empty($statusOrderShop)){
                                $response['result'][0][$statusOrderShop] = $this->fieldStatus;
                              }

                              //Update
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
                                $this->writeFileLog('addNewOrder10', $ch);
                              } else if(empty($response)){
                                $this->writeFileLog('addNewOrder10', 'EMPTY RESPONSE');
                                //$this->sendEmail(utf8_decode("It is not possible to create client!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/>Please insert your internal document in PHC FX manually"));
                              } else if(isset($response['messages'][0]['messageCodeLocale'])){
                                $this->writeFileLog('addNewOrder10', $response['messages'][0]['messageCodeLocale']);
                                //$this->sendEmail(utf8_decode("It is not possible to save header of internal document!<br/><br/>Error from Backend: " . $response['messages'][0]['messageCodeLocale'] . "!<br/><br/><b>Please insert your internal document in PHC FX manually</b>"));
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
                          //Generic Client Woocommerce for order introduced like as "visitor"
                          if(trim($response['result'][0]['nome'])==""){
                            $response['result'][0]['nome'] = 'Generic Client Woocommerce';
                            $response['result'][0]['email'] = 'generic_client@email.com';
                          } else {
                            $response['result'][0]['email'] = $billing_email;
                          }
                          $response['result'][0]['morada'] = $billing_address_1;
                          $response['result'][0]['local'] = $billing_city;
                          $response['result'][0]['telefone'] = $billing_phone;
                          $response['result'][0]['codpost'] = $billing_postcode;
                        } else { //Used in backend
                          $response['result'][0]['nome'] = $billing_first_name_ . " " . $billing_last_name_;
                          //Generic Client Woocommerce for order introduced like as "visitor"
                          if(trim($response['result'][0]['nome'])==""){
                            $response['result'][0]['nome'] = 'Generic Client Woocommerce';
                            $response['result'][0]['email'] = 'generic_client@email.com';
                          } else {
                            $response['result'][0]['email'] = $billing_email_;
                          }
                          $response['result'][0]['morada'] = $billing_address_1_;
                          $response['result'][0]['local'] = $billing_city_;
                          $response['result'][0]['telefone'] = $billing_phone_;
                          $response['result'][0]['codpost'] = $billing_postcode;
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
                                //$this->sendEmail(utf8_decode("It is not possible to create line in internal document!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/>Please insert your line in internal document in PHC FX manually"));
                                break;
                              } else if(isset($response['messages'][0]['messageCodeLocale'])){
                                $this->writeFileLog('addNewOrder11', $response['messages'][0]['messageCodeLocale']);
                                //$this->sendEmail(utf8_decode("It is not possible to create line in internal document!<br/><br/>Error from Backend: " . $response['messages'][0]['messageCodeLocale'] . "!<br/><br/><b>Please insert your line in internal document in PHC FX manually</b>"));
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
                                $this->writeFileLog('addNewOrder12', $ch);
                              } else if(empty($response)){
                                $this->writeFileLog('addNewOrder12', 'EMPTY RESPONSE');
                                //$this->sendEmail(utf8_decode("It is not possible to create line in internal document!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/>Please insert your line in internal document in PHC FX manually"));
                                break;
                              } else if(isset($response['messages'][0]['messageCodeLocale'])){
                                $this->writeFileLog('addNewOrder12', $response['messages'][0]['messageCodeLocale']);
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
                            $this->writeFileLog('addNewOrder13', $ch);
                          } else if(empty($response)){
                            $this->writeFileLog('addNewOrder13', 'EMPTY RESPONSE');
                            //$this->sendEmail(utf8_decode("It is not possible to create client!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/>Please insert your internal document in PHC FX manually"));
                          } else if(isset($response['messages'][0]['messageCodeLocale'])){
                            $this->writeFileLog('addNewOrder13', $response['messages'][0]['messageCodeLocale']);
                            //$this->sendEmail(utf8_decode("It is not possible to save header of internal document!<br/><br/>Error from Backend: " . $response['messages'][0]['messageCodeLocale'] . "!<br/><br/><b>Please insert your internal document in PHC FX manually</b>"));
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
              }
            }
          } else {
            $this->messagesError(": Please configure currency in shop according to PHC FX");
            unset($_SESSION['username']);
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
      //$this->sendEmail(utf8_decode("It is not possible to make login!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/><b>Please insert your client and order in PHC FX manually</b>"));
    } else if(isset($response['messages'][0]['messageCodeLocale'])){
      $this->writeFileLog('cancelOrder', $response['messages'][0]['messageCodeLocale']);
      //$this->sendEmail("Wrong Login!<br/><br/><b>Please check your settings and insert your client and order manually</b>");
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
        $this->writeFileLog('saveFieldOrderStatus2', $ch);
      } else if(empty($response)){
        $this->writeFileLog('saveFieldOrderStatus2', 'EMPTY RESPONSE');
      } else if(isset($response['messages'][0]['messageCodeLocale'])){
        $this->writeFileLog('saveFieldOrderStatus2', $response['messages'][0]['messageCodeLocale']);
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
            $this->writeFileLog('cancelOrder2', $ch);
          } else if(empty($response)){
            $this->writeFileLog('cancelOrder2', 'EMPTY RESPONSE');
            //$this->sendEmail(utf8_decode("It is not possible to create client!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/>Please insert your internal document in PHC FX manually"));
          } else if(isset($response['messages'][0]['messageCodeLocale'])){
            $this->writeFileLog('cancelOrder2', $response['messages'][0]['messageCodeLocale']);
            //$this->sendEmail(utf8_decode("It is not possible to save header of internal document!<br/><br/>Error from Backend: " . $response['messages'][0]['messageCodeLocale'] . "!<br/><br/><b>Please insert your internal document in PHC FX manually</b>"));
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
              } else if(empty($response)){
                  //$this->sendEmail(utf8_decode("It is not possible to create client!! Can't connect to webservice!! There's an empty response!! Please insert your internal document in PHC FX manually"));
              } else if(isset($response['messages'][0]['messageCodeLocale'])){
                  //$this->sendEmail(utf8_decode("It is not possible to save header of internal document!! Error from Backend: " . $response['messages'][0]['messageCodeLocale'] . "!! <b>Please insert your internal document in PHC FX manually</b>"));
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
      //$this->sendEmail(utf8_decode("It is not possible to make login!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/><b>Please insert your client and order in PHC FX manually</b>"));
    } else if(isset($response['messages'][0]['messageCodeLocale'])){
      $this->writeFileLog('completedOrder', $response['messages'][0]['messageCodeLocale']);
      //$this->sendEmail("Wrong Login!<br/><br/><b>Please check your settings and insert your client and order manually</b>");
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
        $this->writeFileLog('saveFieldOrderStatus2', $ch);
      } else if(empty($response)){
        $this->writeFileLog('saveFieldOrderStatus2', 'EMPTY RESPONSE');
      } else if(isset($response['messages'][0]['messageCodeLocale'])){
        $this->writeFileLog('saveFieldOrderStatus2', $response['messages'][0]['messageCodeLocale']);
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

            //Verify if exists in bd
            $this->paramsQuery('BoWS', $filterItem, $valueItem);

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
              //$this->sendEmail(utf8_decode("It is not possible to create client!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/>Please insert your internal document in PHC FX manually"));
            } else if(isset($response['messages'][0]['messageCodeLocale'])){
              $this->writeFileLog('completedOrder2', $response['messages'][0]['messageCodeLocale']);
              //$this->sendEmail(utf8_decode("It is not possible to save header of internal document!<br/><br/>Error from Backend: " . $response['messages'][0]['messageCodeLocale'] . "!<br/><br/><b>Please insert your internal document in PHC FX manually</b>"));
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
                } else if(empty($response)){
                    //$this->sendEmail(utf8_decode("It is not possible to create client!! Can't connect to webservice!! There's an empty response!! Please insert your internal document in PHC FX manually"));
                } else if(isset($response['messages'][0]['messageCodeLocale'])){
                    //$this->sendEmail(utf8_decode("It is not possible to save header of internal document!! Error from Backend: " . $response['messages'][0]['messageCodeLocale'] . "!! <b>Please insert your internal document in PHC FX manually</b>"));
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

  //Add internal document
  public function addInternalDocumentInvoice($response, $ch){
    $settings = get_option(PHCFXWOOCOMMERCE_PLUGIN_NAME);

    //See if type of order is configured
    if(empty($settings['backend']['typeOfInvoice']) || $settings['backend']['typeOfInvoice'] == 0){
      //$this->sendEmail("It is not selected any type of invoice!<br/><br/><b>Please insert FT document and their items in PHC FX manually</b>");
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
        $this->writeFileLog('addInternalDocumentInvoice', $ch);
      } else if(empty($response)){
        $this->writeFileLog('addInternalDocumentInvoice', 'EMPTY RESPONSE');
        //$this->sendEmail(utf8_decode("It is not possible to create FT!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/>Please insert your FT in PHC FX manually"));
      } else if(isset($response['messages'][0]['messageCodeLocale'])){
        $this->writeFileLog('addInternalDocumentInvoice', $response['messages'][0]['messageCodeLocale']);
        //$this->sendEmail(utf8_decode("Error obtaining FT!<br/><br/>Error from Backend: " . $response['messages'][0]['messageCodeLocale'] . "!<br/><br/><b>Please insert your FT document in PHC FX manually</b>"));
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
          $this->writeFileLog('addInternalDocumentInvoice2', $ch);
        } else if(empty($response)){
          $this->writeFileLog('addInternalDocumentInvoice2', 'EMPTY RESPONSE');
          //$this->sendEmail(utf8_decode("It is not possible to create FT!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/>Please insert your FT in PHC FX manually"));
        } else if(isset($response['messages'][0]['messageCodeLocale'])){
          $this->writeFileLog('addInternalDocumentInvoice2', $response['messages'][0]['messageCodeLocale']);
          //$this->sendEmail(utf8_decode("It is not possible to save FT!<br/><br/>Error from Backend: " . $response['messages'][0]['messageCodeLocale'] . "!<br/><br/><b>Please insert FT in PHC FX manually</b>"));
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
              $this->writeFileLog('addInternalDocumentInvoice3', $ch);
            } else if(empty($response)){
              $this->writeFileLog('addInternalDocumentInvoice3', 'EMPTY RESPONSE');
              //$this->sendEmail(utf8_decode("It is not possible to sign FT!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/>Please sign your FT in PHC FX manually"));
            } else if(isset($response['messages'][0]['messageCodeLocale'])){
              $this->writeFileLog('addInternalDocumentInvoice3', $response['messages'][0]['messageCodeLocale']);
              //$this->sendEmail(utf8_decode("It is not possible to sign FT!<br/><br/>Error from Backend: " . $response['messages'][0]['messageCodeLocale'] . "!<br/><br/><b>Please sign FT in PHC FX manually</b>"));
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
                    
                    //Obtain type invoices
                    $this->paramsQuery('StWS', 'ref', $_SESSION['voProducts']['fis'][$i]['ref']);

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
                      //$this->sendEmail(utf8_decode("It is not possible to send FT to email!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/>Please send your FT manually"));
                    } else if(isset($response['messages'][0]['messageCodeLocale'])){
                      $this->writeFileLog('addInternalDocumentInvoice4', $response['messages'][0]['messageCodeLocale']);
                      //$this->sendEmail(utf8_decode(" in configuration of send email with FT! Message from Backend: " . $response['messages'][0]['messageCodeLocale']));
                    } else {
                      //If find ref, update stock
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
                //Obtain type invoices
                $this->paramsGetReportForPrint();

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
                  //$this->sendEmail(utf8_decode("It is not possible to send FT to email!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/>Please send your FT manually"));
                } else if(isset($response['messages'][0]['messageCodeLocale'])){
                  $this->writeFileLog('addInternalDocumentInvoice4', $response['messages'][0]['messageCodeLocale']);
                  //$this->sendEmail(utf8_decode(" in configuration of send email with FT! Message from Backend: " . $response['messages'][0]['messageCodeLocale']));
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

                    //Obtain type invoices
                    $this->paramsQuery('ClWS', 'no', $_SESSION['numberClient']);

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
                      //$this->sendEmail(utf8_decode("It is not possible to send FT to email!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/>Please send your FT manually"));
                    } else if(isset($response['messages'][0]['messageCodeLocale'])){
                      $this->writeFileLog('addInternalDocumentInvoice5', $response['messages'][0]['messageCodeLocale']);
                      $this->messagesError(utf8_decode(" in configuration of email to send them to client! Message from Backend: " . $response['messages'][0]['messageCodeLocale']));
                    } else {
                      
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
                        $this->writeFileLog('addInternalDocumentInvoic6', $ch);
                      } else if(empty($response)){
                        $this->writeFileLog('addInternalDocumentInvoic6', 'EMPTY RESPONSE');
                        //$this->sendEmail(utf8_decode("It is not possible to send FT to email!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/>Please send your FT manually"));
                      } else if(isset($response['messages'][0]['messageCodeLocale'])){
                        $this->writeFileLog('addInternalDocumentInvoice6', $response['messages'][0]['messageCodeLocale']);
                        //$this->sendEmail(utf8_decode("It is not possible to send FT!<br/><br/>Error from Backend: " . $response['messages'][0]['messageCodeLocale'] . "!<br/><br/><b>Please send FT in PHC FX manually</b>"));
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
                  
                  //Obtain type invoices
                  $this->paramsQuery('StWS', 'ref', $_SESSION['voProducts']['fis'][$i]['ref']);

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
                    //$this->sendEmail(utf8_decode("It is not possible to send FT to email!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/>Please send your FT manually"));
                  } else if(isset($response['messages'][0]['messageCodeLocale'])){
                    $this->writeFileLog('addInternalDocumentInvoice4', $response['messages'][0]['messageCodeLocale']);
                    //$this->sendEmail(utf8_decode(" in configuration of send email with FT! Message from Backend: " . $response['messages'][0]['messageCodeLocale']));
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

  //Add FT
  public function addSimpleFT($ch){
    $settings = get_option(PHCFXWOOCOMMERCE_PLUGIN_NAME);

    //See if type of order is configured
    if(empty($settings['backend']['typeOfInvoice']) || $settings['backend']['typeOfInvoice'] == 0){
      //$this->sendEmail("It is not selected any type of invoice!! <b>Please insert FT document and their items in PHC FX manually</b>");
    } else {   
      $billing_email_ = sanitize_text_field( $_REQUEST['_billing_email'] );
      $billing_email = sanitize_text_field( $_REQUEST['billing_email'] );
      $order_received = get_option('woocommerce_checkout_order_received_endpoint');

      if($billing_email_ == '' && $billing_email != ''){
        $this->paramsQuery('ClWS', 'email', $billing_email);
      } else if($billing_email_ != '' && $billing_email == ''){
        $this->paramsQuery('ClWS', 'email', $billing_email_);
      } else {
        $order = new WC_Order( $order_received );
        $billing_email = $order->billing_email;
        //From backend - status completed with client "visitor"
        if(empty($billing_email)){
          $billing_email = 'generic_client@email.com';
        }
        $this->paramsQuery('ClWS', 'email', $billing_email);
      }

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
        //$this->sendEmail(utf8_decode("It is not possible to send FT to email!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/>Please send your FT manually"));
      } else if(isset($response['messages'][0]['messageCodeLocale'])){
        $this->writeFileLog('addSimpleFT2', $response['messages'][0]['messageCodeLocale']);
        $this->messagesError(utf8_decode(" in configuration of email to send them to client! Message from Backend: " . $response['messages'][0]['messageCodeLocale']));
      } else {

        //Save vo with products
        $_SESSION['voProducts'] = $response['result'][0];

        //Save id of client
        $_SESSION['numberClient'] = $response['result'][0]['no'];

        //Obtain new instance of Bo
        $this->paramsNewInstance('FtWS', $settings['backend']['typeOfInvoice']);

        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->params);
        $response = curl_exec($ch);      
        // send response as JSON
        $response = json_decode($response, true); 

        if (curl_error($ch)) {
          $this->writeFileLog('addSimpleFT', $ch);
        } else if(empty($response)){
          $this->writeFileLog('addSimpleFT', 'EMPTY RESPONSE');
          //$this->sendEmail(utf8_decode("It is not possible to create FT!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/>Please insert your FT in PHC FX manually"));
        } else if(isset($response['messages'][0]['messageCodeLocale'])){
          $this->writeFileLog('addSimpleFT', $response['messages'][0]['messageCodeLocale']);
          //$this->sendEmail(utf8_decode("Error obtaining header of FT!<br/><br/>Error from Backend: " . $response['messages'][0]['messageCodeLocale'] . "!<br/><br/><b>Please insert your FT document in PHC FX manually</b>"));
        } else {
          $order_received = get_option('woocommerce_checkout_order_received_endpoint');
          //FT from Frontend
          if (!empty($order_received)){
            //Obtain id of order and products
            $order = new WC_Order();
            $order = new WC_Order($order_received);
            $i = 0;
            
            foreach($order->get_items() as $key => $value){
              $product = new WC_Product($value['product_id']);
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

          //FT from Backend
          if(empty($_SESSION['listOfSku'])){
            //Obtain id of order and products
            $order = new WC_Order();
            $order = new WC_Order($order->post->ID);
            $i = 0;
            
            foreach($order->get_items() as $key => $value){
              $product = new WC_Product($value['product_id']);
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
              //Generic Client Woocommerce for order introduced like as "visitor"
              if(trim($response['result'][0]['nome'])==""){
                $response['result'][0]['nome'] = 'Generic Client Woocommerce';
                $response['result'][0]['email'] = 'generic_client@email.com';
              } else {
                $response['result'][0]['email'] = $billing_email;
              }
              $response['result'][0]['morada'] = $billing_address_1;
              $response['result'][0]['local'] = $billing_city;
              $response['result'][0]['telefone'] = $billing_phone;
              $response['result'][0]['codpost'] = $billing_postcode;
            } else { //Used in backend
              $response['result'][0]['nome'] = $billing_first_name_ . " " . $billing_last_name_;
              //Generic Client Woocommerce for order introduced like as "visitor"
              if(trim($response['result'][0]['nome'])==""){
                $response['result'][0]['nome'] = 'Generic Client Woocommerce';
                $response['result'][0]['email'] = 'generic_client@email.com';
              } else {
                $response['result'][0]['email'] = $billing_email_;
              }
              $response['result'][0]['morada'] = $billing_address_1_;
              $response['result'][0]['local'] = $billing_city_;
              $response['result'][0]['telefone'] = $billing_phone_;
              $response['result'][0]['codpost'] = $billing_postcode_;
            } 

            //Save number of client in FT
            $response['result'][0]['no'] = $_SESSION['numberClient'];

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
              $this->writeFileLog('addSimpleFT2', $ch);
            } else if(empty($response)){
              $this->writeFileLog('addSimpleFT2', 'EMPTY RESPONSE');
              //$this->sendEmail(utf8_decode("It is not possible to create FT!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/>Please insert your FT in PHC FX manually"));
            } else if(isset($response['messages'][0]['messageCodeLocale'])){
              $this->writeFileLog('addSimpleFT2', $response['messages'][0]['messageCodeLocale']);
              //$this->sendEmail(utf8_decode("It is not possible to save FT!<br/><br/>Error from Backend: " . $response['messages'][0]['messageCodeLocale'] . "!<br/><br/><b>Please insert FT in PHC FX manually</b>"));
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
                $this->writeFileLog('addSimpleFT3', $ch);
              } else if(empty($response)){
                $this->writeFileLog('addSimpleFT3', 'EMPTY RESPONSE');
                //$this->sendEmail(utf8_decode("It is not possible to create FT!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/>Please insert your FT in PHC FX manually"));
              } else if(isset($response['messages'][0]['messageCodeLocale'])){
                $this->writeFileLog('addSimpleFT3', $response['messages'][0]['messageCodeLocale']);
                //$this->sendEmail(utf8_decode("It is not possible to save FT!<br/><br/>Error from Backend: " . $response['messages'][0]['messageCodeLocale'] . "!<br/><br/><b>Please insert FT in PHC FX manually</b>"));
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
                    $this->writeFileLog('addSimpleFT4', $ch);
                  } else if(empty($response)){
                    $this->writeFileLog('addSimpleFT4', 'EMPTY RESPONSE');
                    //$this->sendEmail(utf8_decode("It is not possible to sign FT!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/>Please sign your FT in PHC FX manually"));
                  } else if(isset($response['messages'][0]['messageCodeLocale'])){
                    $this->writeFileLog('addSimpleFT4', $response['messages'][0]['messageCodeLocale']);
                    //$this->sendEmail(utf8_decode("It is not possible to sign FT!<br/><br/>Error from Backend: " . $response['messages'][0]['messageCodeLocale'] . "!<br/><br/><b>Please sign FT in PHC FX manually</b>"));
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
                          
                          //Obtain type invoices
                          $this->paramsQuery('StWS', 'ref', $_SESSION['voProducts']['fis'][$i]['ref']);

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
                            //$this->sendEmail(utf8_decode("It is not possible to send FT to email!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/>Please send your FT manually"));
                          } else if(isset($response['messages'][0]['messageCodeLocale'])){
                            $this->writeFileLog('addInternalDocumentInvoice4', $response['messages'][0]['messageCodeLocale']);
                            //$this->sendEmail(utf8_decode(" in configuration of send email with FT! Message from Backend: " . $response['messages'][0]['messageCodeLocale']));
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
                      //Obtain type invoices
                      $this->paramsGetReportForPrint();

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
                        //$this->sendEmail(utf8_decode("It is not possible to send FT to email!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/>Please send your FT manually"));
                      } else if(isset($response['messages'][0]['messageCodeLocale'])){
                        $this->writeFileLog('addSimpleFT5', $response['messages'][0]['messageCodeLocale']);
                        //$this->sendEmail(utf8_decode(" in configuration of send email with FT! Message from Backend: " . $response['messages'][0]['messageCodeLocale']));
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
                          //Obtain type invoices
                          $this->paramsQuery('ClWS', 'no', $_SESSION['numberClient']);

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
                            //$this->sendEmail(utf8_decode("It is not possible to send FT to email!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/>Please send your FT manually"));
                          } else if(isset($response['messages'][0]['messageCodeLocale'])){
                            $this->writeFileLog('addSimpleFT6', $response['messages'][0]['messageCodeLocale']);
                            $this->messagesError(utf8_decode(" in configuration of email to send them to client! Message from Backend: " . $response['messages'][0]['messageCodeLocale']));
                          } else {
                            
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
                              $this->writeFileLog('addInternalDocumentInvoic6', $ch);
                            } else if(empty($response)){
                              $this->writeFileLog('addInternalDocumentInvoic6', 'EMPTY RESPONSE');
                              //$this->sendEmail(utf8_decode("It is not possible to send FT to email!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/>Please send your FT manually"));
                            } else if(isset($response['messages'][0]['messageCodeLocale'])){
                              $this->writeFileLog('addInternalDocumentInvoice6', $response['messages'][0]['messageCodeLocale']);
                              //$this->sendEmail(utf8_decode("It is not possible to send FT!<br/><br/>Error from Backend: " . $response['messages'][0]['messageCodeLocale'] . "!<br/><br/><b>Please send FT in PHC FX manually</b>"));
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
                        
                        //Obtain type invoices
                        $this->paramsQuery('StWS', 'ref', $_SESSION['voProducts']['fis'][$i]['ref']);

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
                          //$this->sendEmail(utf8_decode("It is not possible to send FT to email!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/>Please send your FT manually"));
                        } else if(isset($response['messages'][0]['messageCodeLocale'])){
                          $this->writeFileLog('addInternalDocumentInvoice4', $response['messages'][0]['messageCodeLocale']);
                          //$this->sendEmail(utf8_decode(" in configuration of send email with FT! Message from Backend: " . $response['messages'][0]['messageCodeLocale']));
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

  /********************************
   Function to add products to shop
  **********************************/
  function addProduct($nomeProduct, $contentProduct, $excerptProduct, $slugNameProduct, $stockUnitsProduct, $price, $sku, $manageStock, $visible, $thumb_url){
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

    $thumb_url = str_replace(" ", "%20", $thumb_url);
 
    $tmp = tempnam(sys_get_temp_dir(), "UL_IMAGE");
    $img = file_get_contents($thumb_url);
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

    //unlink($file_loc);
  }

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
	    curl_setopt($ch, CURLOPT_COOKIEJAR, '');  //could be empty, but cause problems on some hosts
	    curl_setopt($ch, CURLOPT_COOKIEFILE, '');  //could be empty, but cause problems on some hosts
	    $response = curl_exec($ch);

	    if (curl_error($ch)) {
	      $this->writeFileLog('setCommunicationFx', $ch);
	      unset($_SESSION['username']);
	    } else if(empty($response)){
	      $this->writeFileLog('setCommunicationFx', 'EMPTY RESPONSE');
	      unset($_SESSION['username']);
	    } else if(isset($response['messages'][0]['messageCodeLocale'])){
	      $this->writeFileLog('setCommunicationFx', $response['messages'][0]['messageCodeLocale']);
	      unset($_SESSION['username']);
	    } else {          
            // Create map with request parameters
			if($settings['backend']['warehouse'] == -1){
				$this->paramsQuery2('SaWS');
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
              $this->writeFileLog('addNewOrder5', $ch);
            } else if(empty($response)){
              $this->writeFileLog('addNewOrder5', 'EMPTY RESPONSE');
              //$this->sendEmail(utf8_decode("It is not possible to create client!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/>Please insert your internal document in PHC FX manually"));
            } else if(isset($response['messages'][0]['messageCodeLocale'])){
              $this->writeFileLog('addNewOrder5', $response['messages'][0]['messageCodeLocale']);
              //$this->sendEmail(utf8_decode("It is not possible to save header of internal document!<br/><br/>Error from Backend: " . $response['messages'][0]['messageCodeLocale'] . "!<br/><br/><b>Please insert your internal document in PHC FX manually</b>"));
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
				  $this->writeFileLog('addNewOrder5', $ch);
				} else if(empty($response)){
				  $this->writeFileLog('addNewOrder5', 'EMPTY RESPONSE');
				  //$this->sendEmail(utf8_decode("It is not possible to create client!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/>Please insert your internal document in PHC FX manually"));
				} else if(isset($response['messages'][0]['messageCodeLocale'])){
				  $this->writeFileLog('addNewOrder5', $response['messages'][0]['messageCodeLocale']);
				  //$this->sendEmail(utf8_decode("It is not possible to save header of internal document!<br/><br/>Error from Backend: " . $response['messages'][0]['messageCodeLocale'] . "!<br/><br/><b>Please insert your internal document in PHC FX manually</b>"));
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

				            	if(($woocommerce_calc_taxes == "no") || ($woocommerce_calc_taxes == "yes" && $woocommerce_prices_include_tax == "yes")) {
					              	if($response['result'][$key][$columnIva] == 1){
					                	$tableProducts .= "<tr>";
					                	if(wc_get_product_id_by_sku( $response['result'][$key]['ref'] )== ""){
					                  		if($response['result'][$key]['stock'] < 0){
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
						                  $tableProducts .= "<td style='text-align: right; color: red;'>".$response['result'][$key]['stock']."</td></tr>";
						                } else {
						                  $tableProducts .= "<td style='text-align: right;'>".$response['result'][$key]['stock']."</td></tr>";
						                }
					              	}
				            	} else if($woocommerce_calc_taxes == "yes" && $woocommerce_prices_include_tax == 'no'){

				              		if($response['result'][$key][$columnIva] == 0){
				                		$tableProducts .= "<tr>";
				                		if(wc_get_product_id_by_sku( $response['result'][$key]['ref'] )== ""){
				                  			if($response['result'][$key]['stock'] < 0){
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
						                  $tableProducts .= "<td style='text-align: right; color: red;'>".$response['result'][$key]['stock']."</td></tr>";
						                } else {
						                  $tableProducts .= "<td style='text-align: right;'>".$response['result'][$key]['stock']."</td></tr>";
						                }
				              		}
				            	}
				         	}
							echo $tableProducts;

			        	} else {
			          		$this->writeFileLog('Products: Empty productPriceColumn', '');
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
	      unset($_SESSION['username']);
	    } else if(isset($response['messages'][0]['messageCodeLocale'])){
	      $this->writeFileLog('setCommunicationFx', $response['messages'][0]['messageCodeLocale']);
	      unset($_SESSION['username']);
	    } else {  
      
            $this->paramsQuery2('StWS');

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
              //$this->sendEmail(utf8_decode("It is not possible to create client!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/>Please insert your internal document in PHC FX manually"));
            } else if(isset($response['messages'][0]['messageCodeLocale'])){
              $this->writeFileLog('addNewOrder5', $response['messages'][0]['messageCodeLocale']);
              //$this->sendEmail(utf8_decode("It is not possible to save header of internal document!<br/><br/>Error from Backend: " . $response['messages'][0]['messageCodeLocale'] . "!<br/><br/><b>Please insert your internal document in PHC FX manually</b>"));
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
                                                                        "&imageExtension=.jpg";
				              	//Add Product
				              	$this->addProduct($response['result'][$i]['design'], 
				                         $response['result'][$i]['design'], 
				                         $response['result'][$i]['design'], 
				                         $response['result'][$i]['design'], 
				                         $response['result'][$i]['stock'],  
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
	  	//Logout
	    $this->paramsLogout();
	    //session_destroy();
	    curl_setopt($ch, CURLOPT_URL, $this->url);
	    curl_setopt($ch, CURLOPT_POST, false);
	    $response = curl_exec($ch);
	}

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
	      unset($_SESSION['username']);
	    } else if(isset($response['messages'][0]['messageCodeLocale'])){
	      $this->writeFileLog('setCommunicationFx', $response['messages'][0]['messageCodeLocale']);
	      unset($_SESSION['username']);
	    } else {  
        	//If exists selected products  
		    if(is_array($refs)){
		    	foreach ($refs as $key => $value) {            
			        //Obtain post_id of postmeta table
			        $productID = wc_get_product_id_by_sku($value);

			        $this->paramsQuery('StWS', 'ref', $value);

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
		              //$this->sendEmail(utf8_decode("It is not possible to create client!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/>Please insert your internal document in PHC FX manually"));
		            } else if(isset($response['messages'][0]['messageCodeLocale'])){
		              $this->writeFileLog('addNewOrder5', $response['messages'][0]['messageCodeLocale']);
		              //$this->sendEmail(utf8_decode("It is not possible to save header of internal document!<br/><br/>Error from Backend: " . $response['messages'][0]['messageCodeLocale'] . "!<br/><br/><b>Please insert your internal document in PHC FX manually</b>"));
		            } else {
			          	//Verify if product is returned to update stock
				        if(!empty($response['result'][0]) && !empty($productID)){

				            if($settings['backend']['manageStock'] == 'on'){
				        	    update_post_meta($productID,'_stock',$response['result'][0]['stock']);    
				              
					            if($response['result'][0]['stock'] > 0){
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
	  	//Logout
	    $this->paramsLogout();
	    //session_destroy();
	    curl_setopt($ch, CURLOPT_URL, $this->url);
	    curl_setopt($ch, CURLOPT_POST, false);
	    $response = curl_exec($ch);
	}

	public function updateAllFieldsProducts($refs){
 		
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
	      unset($_SESSION['username']);
	    } else if(isset($response['messages'][0]['messageCodeLocale'])){
	      $this->writeFileLog('setCommunicationFx', $response['messages'][0]['messageCodeLocale']);
	      unset($_SESSION['username']);
	    } else {  
      
            //If exists selected products  
		    if(is_array($refs)){
		    	foreach ($refs as $key => $value) {            
			        //Obtain post_id of postmeta table
			        $productID = wc_get_product_id_by_sku($value);

			        $this->paramsQuery('StWS', 'ref', $value);

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
		              //$this->sendEmail(utf8_decode("It is not possible to create client!<br/><br/>Can't connect to webservice!<br/><br/>There's an empty response!<br/><br/>Please insert your internal document in PHC FX manually"));
		            } else if(isset($response['messages'][0]['messageCodeLocale'])){
		              $this->writeFileLog('addNewOrder5', $response['messages'][0]['messageCodeLocale']);
		              //$this->sendEmail(utf8_decode("It is not possible to save header of internal document!<br/><br/>Error from Backend: " . $response['messages'][0]['messageCodeLocale'] . "!<br/><br/><b>Please insert your internal document in PHC FX manually</b>"));
		            } else {
			          	//Verify if product is returned to update stock
				        if(!empty($response['result'][0]) && !empty($productID)){
				            if($settings['backend']['manageStock'] == 'on'){
				        	    update_post_meta($productID,'_stock',$response['result'][0]['stock']);    
				              
					            if($response['result'][0]['stock'] > 0){
					            	update_post_meta( $productID, '_stock_status', 'instock');
					            } else {
					            	update_post_meta( $productID, '_stock_status', 'outofstock');
					            }
					        }             
					    }
					    $my_post = array(
						                	'ID'           => $productID,
						                	'post_title'   => $response['result'][0]['design'],
						                	'post_content' => $response['result'][0]['design'],
						                	'post_excerpt' => $response['result'][0]['design'],
						                	'post_name' => $response['result'][0]['design'],
						            	);
						wp_update_post( $my_post );
			            update_post_meta( $productID, '_price', $response['result'][0][$settings['backend']['productPriceColumn']] );
			            update_post_meta( $productID, '_regular_price', $response['result'][0][$settings['backend']['productPriceColumn']]); 
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
	      unset($_SESSION['username']);
	    } else if(isset($response['messages'][0]['messageCodeLocale'])){
	      $this->writeFileLog('setCommunicationFx', $response['messages'][0]['messageCodeLocale']);
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
            	$this->writeFileLog('addNewOrder5', $ch);
            } else if(empty($response)){
            	$this->writeFileLog('addNewOrder5', 'EMPTY RESPONSE');
            } else if(isset($response['messages'][0]['messageCodeLocale'])){
            	$this->writeFileLog('addNewOrder5', $response['messages'][0]['messageCodeLocale']);
            } else {
	          	
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
	          	$response['result'][0]['tabela1'] = 'Status';	
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

	          	//Save data of client in PHC FX
        		$this->paramsSave('TsWS', $response);

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