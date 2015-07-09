<?php
    // get stored settings
    $settings = get_option(PHCFXWOOCOMMERCE_PLUGIN_NAME);

    global $wpdb;
    $table_name = $wpdb->prefix."postmeta"; 
    $query = "SELECT meta_value FROM %s WHERE meta_key = %s";
    $resultDB = $wpdb->get_row(str_replace("'".$table_name."'", $table_name, $wpdb->prepare($query, $table_name, '_token')));
?>

<h3>PHC FX Backend Configurations</h3>

<?php
  // check required ones
  $required = array();
  foreach ($this->options['backend'] as $id => $opts)
    if ($opts['required']) $required[] = "<code>{$opts['label']}</code>";

  ?><p>Provide the info bellow so that the plugin can talk to your PHC FX backend installation.<br>
  <?php echo implode(', ', $required) ?> are required to be setup!</p>

  <div id="box"> 
    <div id="cab">
      Initialize Plugin Settings
    </div>
    <table class="form-table">
      <tbody> 
        <tr>
          <th scope="row">
            <label for="url">Backend URL</label>
          </th>
          <td>
            <input class="regular-text" id="url" type="text" name="phcfx-woocommerce[backend][url]" value="<?php echo $settings['backend']['url'] ?>">
            <div id="backend_url_initialize" class="button button-primary" title="Autenticate plugin">
              <img id="plus" src="<?php echo plugins_url('/../images/plus.png', __FILE__) ?>" title="Autenticate plugin"> 
              <?php if($resultDB->meta_value != ''){ ?>
                <span id="autenticatePlugin">Re-Autenticate</span>
              <?php } else { ?>
                <span id="autenticatePlugin">Autenticate</span>
              <?php } ?>
            </div>
            <p class="description">The URL of your PHC FX application.<br>Something like, e.g. https://myurl.com/myphc</p>
          </td>
        </tr>
      </tbody>
    </table>
  </div><?php 

  foreach ($this->options['backend'] as $id => $opts):
    $name  = sprintf(PHCFXWOOCOMMERCE_PLUGIN_NAME.'[backend][%s]', $id);
    $value = isset($settings['backend'][$id]) ? $settings['backend'][$id] : null;
    $checkedBox = '';

    if (is_array($value)){
      $value = '';
    }

    if($resultDB->meta_value != '' && $resultDB->meta_value != 'error'){
      $showErrorsLogin = true;
      switch ($id) {
        case 'username':
          ?><div id="box"> 
              <div id="cab">
                Backend Settings
              </div>
              <table class="form-table">
                <tbody> 
                  <tr>
                    <th scope="row">
                      <label for="<?php echo $id ?>"><?php echo $opts['label'] ?></label>
                    </th>
                    <td>
                      <input class="regular-text" id="<?php echo $id ?>" type="<?php echo $opts['type'] ?>" name="<?php echo $name ?>" value="<?php echo $value ?>">
                      <span style='color:red;'>*</span>
                      <p class="description"><?php echo $opts['descr'] ?></p>
                    </td>
                  </tr>
              <?php break;
        case 'password':
          ?><tr>
            <th scope="row">
              <label for="<?php echo $id ?>"><?php echo $opts['label'] ?></label>
            </th>
            <td>
              <input class="regular-text" id="<?php echo $id ?>" type="<?php echo $opts['type'] ?>" name="<?php echo $name ?>" value="<?php echo $value ?>">
              <span style='color:red;'>*</span>
              <p class="description"><?php echo $opts['descr'] ?></p>
              </td>
            </tr>   
            <?php break;
        case 'dbname':
          ?><tr>
            <th scope="row">
              <label for="<?php echo $id ?>"><?php echo $opts['label'] ?></label>
            </th>
            <td>
              <input class="regular-text" id="<?php echo $id ?>" type="<?php echo $opts['type'] ?>" name="<?php echo $name ?>" value="<?php echo $value ?>">
              <p class="description"><?php echo $opts['descr'] ?></p>
            </td>
          </tr>   
          <?php break;
        case 'createInvoice':
          ?></tbody>
          </table>
          </div> <?php 
          if($_SESSION['username'] != ''){ ?>
            <div id="box">
                <div id="cab">
                  Invoices Settings
                </div>
                <table class="form-table">
                  <tbody> 
                    <tr>
                      <th scope="row">
                        <label for="<?php echo $id ?>"><?php echo $opts['label'] ?></label>
                      </th>
                      <td>
                        <?php if($value == 'on'){
                          $checkedBox = 'checked';
                        } else {
                          $checkbox = '';
                        } ?>
                        <input style="width: 0;" class="regular-text" <?php echo $checkedBox ?> id="<?php echo $id ?>" type="<?php echo $opts['type'] ?>" name="<?php echo $name ?>">
                        <span><?php echo $opts['checkboxDescription'] ?></span>
                        <p class="description"><?php echo $opts['descr'] ?></p>
                      </td>
                    </tr>
          <?php } break;
        case 'sendInvoice':
          if($_SESSION['username'] != '' && $settings['backend']['createInvoice'] != ''){ ?> 
          <tr>
            <th scope="row">
              <label for="<?php echo $id ?>"><?php echo $opts['label'] ?></label>
            </th>
            <td>
              <?php if($value == 'on'){
                $checkedBox = 'checked';
              } else {
                $checkbox = '';
              } ?>
              <input style="width: 0;" class="regular-text" <?php echo $checkedBox ?> id="<?php echo $id ?>" type="<?php echo $opts['type'] ?>" name="<?php echo $name ?>">
              <span><?php echo $opts['checkboxDescription'] ?></span>
              <p class="description"><?php echo $opts['descr'] ?></p>
            </td>
          </tr>
          <?php } break;
        case 'typeOfInvoice':
          if($_SESSION['username'] != '' && $settings['backend']['createInvoice'] != ''){ ?> 
          <tr>
            <th scope="row">
              <label for="<?php echo $id ?>"><?php echo $opts['label'] ?></label>
            </th>
            <td>
              <select id="<?php echo $id ?>" name="<?php echo $name ?>"> 
              <?php if(!empty($_SESSION[$id])){ ?>
                <option value="0">Select one...</option>
              <?php } 
              echo $_SESSION[$id]; ?>
            </td>
          </tr>
          <?php } break;
        case 'warehouseOrder':
          if($_SESSION['username'] != ''){ ?> 
              </tbody>
              </table>
              </div>
              <div id="box"> 
                  <div id="cab">
                    Order Settings
                  </div>
                  <table class="form-table">
                    <tbody> 
                      <tr>
                        <th scope="row">
                          <label for="<?php echo $id ?>"><?php echo $opts['label'] ?></label>
                        </th>
                        <td>
                          <select id="<?php echo $id ?>" name="<?php echo $name ?>"> 
                          <?php if(!empty($_SESSION[$id])){ ?>
                            <option value="1">Default Warehouse</option>
                          <?php } 
                          echo $_SESSION[$id]; ?>
                        </td>
                      </tr>
                      <?php } break;
        case 'nameOfNewOrder':
          if($_SESSION['username'] != '' && $settings['backend']['warehouseOrder'] != '' && $_SESSION['gamaPHCFX'] > 0){ ?> 
                    <tr>
                      <th scope="row">
                        <label for="<?php echo $id ?>"><?php echo $opts['label'] ?></label>
                      </th>
                      <td>
                        <input class="regular-text" id="<?php echo $id ?>" type="<?php echo $opts['type'] ?>" name="<?php echo $name ?>" value="<?php echo $value ?>">
                        <div id="addNewTypeOfOrder" class="button button-primary" title="Add New Type Of Order">
                          <img id="plus" src="<?php echo plugins_url('/../images/plus.png', __FILE__) ?>" title="Add New Type Of Order"> Add New Type Of Order
                        </div>
                        <div id="addNewTypeOrderMessage" style="color: #4F8A10;"></div>
                        <p class="description"><?php echo $opts['descr'] ?></p>
                      </td>
                    </tr>
                    <?php } break;
        case 'typeOfOrder':
          if($_SESSION['username'] != '' && $settings['backend']['createInvoice'] != '' && $settings['backend']['createInvoice'] != ''){ ?> 
            <tr>
              <th scope="row">
                <label for="<?php echo $id ?>"><?php echo $opts['label'] ?></label>
              </th>
              <td>
                <select id="<?php echo $id ?>" name="<?php echo $name ?>"> 
                <?php if(!empty($_SESSION[$id])){ ?>
                  <option value="0">Select one...</option>
                <?php } 
                echo $_SESSION[$id]; ?>
              </td>
            </tr>
            <?php } break;
        case 'statusOfOrder':
          //if($_SESSION['username'] != '' && $settings['backend']['createInvoice'] != '' && ($settings['backend']['typeOfOrder'] != '' && $settings['backend']['typeOfOrder'] > 0)){ 
          if($_SESSION['username'] != '' && $settings['backend']['createInvoice'] != ''){ ?> 
          <tr>
            <th scope="row">
              <label for="<?php echo $id ?>"><?php echo $opts['label'] ?></label>
            </th>
            <td>
              <select id="<?php echo $id ?>" name="<?php echo $name ?>"> 
              <?php $fieldsStatus = array('notSelected' => 'Select one...',
                                          'nmdesc' => 'Main Description',
                                          'texto1' => 'Name of Final Text',
                                          'texto2' => 'Name of Final Text 2',
                                          'texto3' => 'Name of Final Text 3',
                                          'tmodelo' => 'Alphanumeric Field 1',
                                          'tmarca' => 'Alphanumeric Field 2',
                                          'tserie' => 'Alphanumeric Field 3'
                                          );
                    foreach ($fieldsStatus as $key => $value){
                      $selected_dropdown = '';
                      if($settings['backend']['statusOfOrder'] == $key){
                        $selected_dropdown = 'selected';
                      } ?>
                        <option id="<?php echo $key ?>" <?php echo $selected_dropdown ?> value="<?php echo $key ?>"><?php echo $value ?></option>
                      <?php                     
                    } ?>
            </td>
          </tr>
          <?php } break;
        case 'saveStatusOrder':
          //if($_SESSION['username'] != '' && $settings['backend']['createInvoice'] != '' && ($settings['backend']['typeOfOrder'] != '' && $settings['backend']['typeOfOrder'] > 0)){ 
          if($_SESSION['username'] != '' && $settings['backend']['createInvoice'] != ''){ ?> 
            <tr>
              <th scope="row">
                <label for="<?php echo $id ?>"><?php echo $opts['label'] ?></label>
              </th>
              <td>
                <input class="regular-text" id="<?php echo $id ?>" type="<?php echo $opts['type'] ?>" name="<?php echo $name ?>" value="<?php echo $value ?>">
                <p class="description"><?php echo $opts['descr'] ?></p>
              </td>
            </tr>   
            <?php } break;
        case 'manageStock':
          if($_SESSION['username'] != ''){ ?> 
            </tbody>
            </table>
            </div>
            <div id="box">
              <div id="cab">
                Product Settings
              </div>
              <table class="form-table">
                <tbody> 
                  <tr>
                    <th scope="row">
                      <label for="<?php echo $id ?>"><?php echo $opts['label'] ?></label>
                    </th>
                    <td>
                      <?php if($value == 'on'){
                        $checkedBox = 'checked';
                      } else {
                        $checkbox = '';
                      } ?>
                      <input style="width: 0;" class="regular-text" <?php echo $checkedBox ?> id="<?php echo $id ?>" type="<?php echo $opts['type'] ?>" name="<?php echo $name ?>">
                      <span><?php echo $opts['checkboxDescription'] ?></span>
                      <p class="description"><?php echo $opts['descr'] ?></p>
                    </td>
                  </tr>
                  <?php } break;
        case 'productPriceColumn':
          if($_SESSION['username'] != ''){ ?> 
            <tr>
              <th scope="row">
                <label for="<?php echo $id ?>"><?php echo $opts['label'] ?></label>
              </th>
              <td>
                <select id="<?php echo $id ?>" name="<?php echo $name ?>"> 
                <?php $fieldsStatus = array('epv0' => 'Select one...',
                                             'epv1' => 'Retail price 1 (epv1)',
                                             'epv2' => 'Retail price 2 (epv2)',
                                             'epv3' => 'Retail price 3 (epv3)',
                                             'epv4' => 'Retail price 4 (epv4)',
                                             'epv5' => 'Retail price 5 (epv5)'
                                            );
                      foreach ($fieldsStatus as $key => $value){
                        $selected_dropdown = '';
                        if($settings['backend']['productPriceColumn'] == $key){
                          $selected_dropdown = 'selected';
                        } ?>
                          <option id="<?php echo $key ?>" <?php echo $selected_dropdown ?> value="<?php echo $key ?>"><?php echo $value ?></option>
                        <?php                     
                      } ?>
              </td>
            </tr>
            <?php } break;
        case 'warehouse':
          if($_SESSION['username'] != ''){ ?> 
          <tr>
            <th scope="row">
              <label for="<?php echo $id ?>"><?php echo $opts['label'] ?></label>
            </th>
            <td>
              <select id="<?php echo $id ?>" name="<?php echo $name ?>"> 
              <?php if(!empty($_SESSION[$id])){ ?>
                <option value="-1">All Warehouses</option>
              <?php } 
              echo $_SESSION[$id]; ?>
            </td>
          </tr>
          </tbody>
          </table>
          </div>
          <?php } else {
            ?> </tbody>
          </table>
          </div>
          <?php } break;
        default:
          break;
      }
    }
        
  endforeach; 
  
  if($showErrorsLogin == true && (empty($settings['backend']['username']) || empty($settings['backend']['password']) || empty($settings['backend']['url']))){
    $this->messagesInformation("Incomplete configurations.<br>Please fill Username, Password and Backend URL");
  } 
?>
