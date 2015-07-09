var debug_mode = false;

jQuery(document).ready(function() {
				
	//Initialize plugin
	jQuery('#backend_url_initialize').click(function (){
	  //Open popup
	  window.open("https://trial.phcfx.com/OauthCallback/oauth2callback_phcfx.aspx?backendUrl="+jQuery('#url').val()+"&appName=PHC FX Woocommerce&redirectUri="+window.location.href, "yyyyy", "width=580,height=550,resizable=no,toolbar=no,menubar=no,location=no,status=no");	  
	  return false;
	});

	if(jQuery('#nameOfNewOrder').val() == ''){
		jQuery('#addNewTypeOrderMessage').html('');
  	} else {
		jQuery('#addNewTypeOrderMessage').html('New type of Order was added in PHC FX');
  	}
	
	if(jQuery('#typeOfOrder').val() == 0){
		jQuery('#statusOfOrder').parent('td').parent('tr').hide();
		jQuery('#saveStatusOrder').parent('td').parent('tr').hide();
	}
	
	jQuery('#updateStocks').hide();
	jQuery('#saveProductInShop').hide();
	jQuery('#updateStocks').hide();
	jQuery('#updateAllFields').hide();

	switch(jQuery('#statusOfOrder').val()){
		case 'nmdesc':
	    	jQuery("#saveStatusOrder").attr('maxlength','20');
	    	break;
		case 'texto1':
	    	jQuery("#saveStatusOrder").attr('maxlength','67');
	    	break;
	    case 'texto2':
	    	jQuery("#saveStatusOrder").attr('maxlength','67');
	    	break;
		case 'texto3':
	    	jQuery("#saveStatusOrder").attr('maxlength','67');
	    	break;
	    case 'tmodelo':
	    	jQuery("#saveStatusOrder").attr('maxlength','12');
	    	break;
		case 'tmarca':
	    	jQuery("#saveStatusOrder").attr('maxlength','12');
	    	break;
	    case 'tserie':
	    	jQuery("#saveStatusOrder").attr('maxlength','50');
	    	break;
		default:
			break;
	} 

	jQuery.post(url, {
  		action: 'woocommerce_fx',
  		method: 'updateTypeOfOrder'
  	})
  	.done(function (data) {
  		jQuery('#typeOfOrder').html("<option value='0'>Select one...</option>");
		jQuery('#typeOfOrder').append(data);
	})

  	//When is changed dropdownlist, input is changed
  	jQuery('#statusOfOrder').on('change', function (event) {
  		// stop normal form submission handler
    	event.preventDefault();

	  	jQuery.post(url, {
	  		action: 'woocommerce_fx',
	  		method: 'statusOfOrder',
	  		selectItems: jQuery('#statusOfOrder').val(), 
	  		typeOfOrder: jQuery('#typeOfOrder').val() 
	  	})
	  	.done(function (data) {
	    	jQuery('#saveStatusOrder').val(data.replace(/"/g, ""));
	    })

	  	//Define max size of inputs
	    switch(jQuery('#statusOfOrder').val()) {
			case 'nmdesc':
		    	jQuery("#saveStatusOrder").prop('maxlength',20);
		    	break;
			case 'texto1':
		    	jQuery("#saveStatusOrder").prop('maxlength',67);
		    	break;
		    case 'texto2':
		    	jQuery("#saveStatusOrder").prop('maxlength',67);
		    	break;
			case 'texto3':
		    	jQuery("#saveStatusOrder").prop('maxlength',67);
		    	break;
		    case 'tmodelo':
		    	jQuery("#saveStatusOrder").prop('maxlength',12);
		    	break;
			case 'tmarca':
		    	jQuery("#saveStatusOrder").prop('maxlength',12);
		    	break;
		    case 'tserie':
		    	jQuery("#saveStatusOrder").prop('maxlength',50);
		    	break;
			default:
				break;
		} 
  	});

	//When is changed dropdownlist, input is changed
  	jQuery('#typeOfOrder').on('change', function (event) {
  		// stop normal form submission handler
    	event.preventDefault();

	  	jQuery.post(url, {
	  		action: 'woocommerce_fx',
	  		method: 'statusOfOrder',
	  		selectItems: jQuery('#statusOfOrder').val(), 
	  		typeOfOrder: jQuery('#typeOfOrder').val()  
	  	})
	  	.done(function (data) {
	  		console.log(jQuery('#statusOfOrder').val());
	  		console.log(data);
	  		//jQuery('#saveStatusOrder').val('');
	    	jQuery('#saveStatusOrder').val(data.replace(/"/g, ""));
	    })

	  	//Define max size of inputs
	    switch(jQuery('#statusOfOrder').val()) {
			case 'nmdesc':
		    	jQuery("#saveStatusOrder").prop('maxlength',20);
		    	break;
			case 'texto1':
		    	jQuery("#saveStatusOrder").prop('maxlength',67);
		    	break;
		    case 'texto2':
		    	jQuery("#saveStatusOrder").prop('maxlength',67);
		    	break;
			case 'texto3':
		    	jQuery("#saveStatusOrder").prop('maxlength',67);
		    	break;
		    case 'tmodelo':
		    	jQuery("#saveStatusOrder").prop('maxlength',12);
		    	break;
			case 'tmarca':
		    	jQuery("#saveStatusOrder").prop('maxlength',12);
		    	break;
		    case 'tserie':
		    	jQuery("#saveStatusOrder").prop('maxlength',50);
		    	break;
			default:
				break;
		}
		if(jQuery('#typeOfOrder').val() != 0){
			jQuery('#statusOfOrder').parent('td').parent('tr').show();
			jQuery('#saveStatusOrder').parent('td').parent('tr').show();
		} else {
			jQuery('#statusOfOrder').parent('td').parent('tr').hide();
			jQuery('#saveStatusOrder').parent('td').parent('tr').hide();
		}
  	});
	
	//Run to show list of products
  	jQuery('#importToShop').click(function (event){
	  	jQuery('#loader').append('<img style="margin-bottom: 10px; margin-left: 20px;" src="'+pathPlugin+'images/ajax-loader.gif" title="Loading..">');
	    jQuery('#importToShop').hide();
	    // stop normal form submission handler
	    event.preventDefault();

	    jQuery.post(url, {
	  		action: 'woocommerce_fx',
	  		method: 'listOfProducts'
	  	})
	  	.done(function (data) {
	  		jQuery("#tableOfProducts").html("");
		    jQuery('#tableOfProducts').append(data);
		    try{
		    	jQuery('#tableOfProducts').DataTable({
		        	   "iDisplayLength": 10,
		               "bDestroy":true
		        });
		        jQuery('#updateStocks').show();
		        jQuery('#updateAllFields').show();
		        jQuery('#saveProductInShop').show();
		        jQuery('#importToShop').show();
		        jQuery('#loader').html('');
		    } catch(err) {
		        jQuery('#descriptionPlugin').html('<div class="error" style="width: 96%;"><b><p>Incomplete configurations.<br/>Please verify if you fill "Create Invoice" and "Field to obtain product price in PHC FX".</p></b></div>');
		        jQuery('#importToShop').show();
		        jQuery('#loader').html('');
		        jQuery('#updateStocks').hide();
		        jQuery('#updateAllFields').hide();
		        jQuery('#saveProductInShop').hide();
		    }
	    	jQuery("#tableOfProducts_previous").after("&nbsp;&nbsp;&nbsp;&nbsp;");
	  	})
  	});

  	//Save selected products in online shop
  	jQuery('#saveProductInShop').click(function (event) {
	    //Obtain checkboxes selected
	    var arr = new Array();
	    jQuery("input:checkbox[name=checkboxes]:checked").each(function() {
	      arr.push(jQuery(this).val());
	    });

	    jQuery('#loader2').html('<img style="margin-top: 10px; margin-left: 20px;" src="'+pathPlugin+'images/ajax-loader.gif" title="Loading..">');
	    jQuery('#saveProductInShop').hide();
	    jQuery('#updateStocks').hide();
	    jQuery('#updateAllFields').hide();

	    // stop normal form submission handler
	    event.preventDefault();

	    //save products in MySQL
	    jQuery.post(url, {
	  		action: 'woocommerce_fx',
	  		method: 'saveProducts',
	  		refs: arr
	  	})
	  	.done(function (data) {
	        jQuery('.alignleft #messageSuccess').html('');
			
			if(data.length != 0){
				jQuery('.alignleft h2').before('<div id="messageSuccess"><div class="error"><p><strong>Please fill description of products to import them successfully: '+data+'</strong></p></div></div>');
			}
			
	        jQuery('.alignleft h2').before('<div id="messageSuccess"><div id="setting-error-settings_updated" class="updated settings-error"><p><strong>Import successfull</strong></p></div></div>');
	        
	        jQuery('#saveProductInShop').show();
	        jQuery('#updateStocks').show();
	        jQuery('#updateAllFields').show();
	        jQuery('#loader2').html('');

	        //Refresh of list of products
	        jQuery.post(url, {
		  		action: 'woocommerce_fx',
		  		method: 'listOfProducts'
		  	})
		  	.done(function (data) {
		  		jQuery("#tableOfProducts").html("");
			    jQuery('#tableOfProducts').append(data);
			    try{
			    	jQuery('#tableOfProducts').DataTable({
			        	   "iDisplayLength": 10,
			               "bDestroy":true
			        });
			        jQuery('#updateStocks').show();
			        jQuery('#updateAllFields').show();
			        jQuery('#saveProductInShop').show();
			        jQuery('#importToShop').show();
			        jQuery('#loader').html('');
			    } catch(err) {
			        jQuery('#descriptionPlugin').html('<div class="error" style="width: 96%;"><b><p>Incomplete configurations.<br/>Please verify if you fill "Create Invoice" and "Field to obtain product price in PHC FX".</p></b></div>');
			        jQuery('#importToShop').show();
			        jQuery('#loader').html('');
			        jQuery('#updateStocks').hide();
			        jQuery('#updateAllFields').hide();
			        jQuery('#saveProductInShop').hide();
			    }
		    	jQuery("#tableOfProducts_previous").after("&nbsp;&nbsp;&nbsp;&nbsp;");
		  	})
		})
	});	

  	//Update stocks of products presented in PHC FX
  	jQuery('#updateStocks').click(function (event) {
	    //Update products
	    var arr = new Array();
	    jQuery(".updateStockShop").each(function() {
	      arr.push(jQuery(this).attr('id'));
	    });

	    jQuery('#loader2').html('<img style="margin-top: 10px; margin-left: 20px;" src="'+pathPlugin+'images/ajax-loader.gif" title="Loading..">');
	    jQuery('#saveProductInShop').hide();
	    jQuery('#updateStocks').hide();
	    jQuery('#updateAllFields').hide();

	    // stop normal form submission handler
	    event.preventDefault();

	    //save products in MySQL
	    jQuery.post(url, {
	  		action: 'woocommerce_fx',
	  		method: 'updateStocksProducts',
	  		refs: arr
	  	})
	    .done(function (data) {
	        jQuery('.alignleft #messageSuccess').html('');
	        jQuery('.alignleft h2').before('<div id="messageSuccess"><div id="setting-error-settings_updated" class="updated settings-error"><p><strong>Update of selected products successfull</strong></p></div></div>');
	        
	        jQuery('#saveProductInShop').show();
	        jQuery('#updateStocks').show();
	        jQuery('#updateAllFields').show();
	        jQuery('#loader2').html('');

	        //Refresh of list of products
	        jQuery.post(url, {
		  		action: 'woocommerce_fx',
		  		method: 'listOfProducts'
		  	})
		  	.done(function (data) {
		  		jQuery("#tableOfProducts").html("");
			    jQuery('#tableOfProducts').append(data);
			    try{
			    	jQuery('#tableOfProducts').DataTable({
			        	   "iDisplayLength": 10,
			               "bDestroy":true
			        });
			        jQuery('#updateStocks').show();
			        jQuery('#updateAllFields').show();
			        jQuery('#saveProductInShop').show();
			        jQuery('#importToShop').show();
			        jQuery('#loader').html('');
			    } catch(err) {
			        jQuery('#descriptionPlugin').html('<div class="error" style="width: 96%;"><b><p>Incomplete configurations.<br/>Please verify if you fill "Create Invoice" and "Field to obtain product price in PHC FX".</p></b></div>');
			        jQuery('#importToShop').show();
			        jQuery('#loader').html('');
			        jQuery('#updateStocks').hide();
			        jQuery('#updateAllFields').hide();
			        jQuery('#saveProductInShop').hide();
			    }
		    	jQuery("#tableOfProducts_previous").after("&nbsp;&nbsp;&nbsp;&nbsp;");
		  	})
	    })
 	});

    //Update all fields od products presented in PHC FX
  	jQuery('#updateAllFields').click(function (event) {
	    //Update products
	    var arr = new Array();
	    jQuery(".updateStockShop").each(function() {
	      arr.push(jQuery(this).attr('id'));
	    });

	    jQuery('#loader2').html('<img style="margin-top: 10px; margin-left: 20px;" src="'+pathPlugin+'images/ajax-loader.gif" title="Loading..">');
	    jQuery('#saveProductInShop').hide();
	    jQuery('#updateStocks').hide();
	    jQuery('#updateAllFields').hide();
	    // stop normal form submission handler
	    event.preventDefault();
	    //save products in MySQL
	    jQuery.post(url, {
	  		action: 'woocommerce_fx',
	  		method: 'updateAllFieldsProducts',
	  		refs: arr
	  	})
	    .done(function (data) {
	        jQuery('.alignleft #messageSuccess').html('');
	        jQuery('.alignleft h2').before('<div id="messageSuccess"><div id="setting-error-settings_updated" class="updated settings-error"><p><strong>Update of selected products successfull</strong></p></div></div>');
	        
	        jQuery('#saveProductInShop').show();
	        jQuery('#updateStocks').show();
	        jQuery('#updateAllFields').show();
	        jQuery('#loader2').html('');

	        //Refresh of list of products
	        jQuery.post(url, {
		  		action: 'woocommerce_fx',
		  		method: 'listOfProducts'
		  	})
		  	.done(function (data) {
		  		jQuery("#tableOfProducts").html("");
			    jQuery('#tableOfProducts').append(data);
			    try{
			    	jQuery('#tableOfProducts').DataTable({
			        	   "iDisplayLength": 10,
			               "bDestroy":true
			        });
			        jQuery('#updateStocks').show();
			        jQuery('#updateAllFields').show();
			        jQuery('#saveProductInShop').show();
			        jQuery('#importToShop').show();
			        jQuery('#loader').html('');
			    } catch(err) {
			        jQuery('#descriptionPlugin').html('<div class="error" style="width: 96%;"><b><p>Incomplete configurations.<br/>Please verify if you fill "Create Invoice" and "Field to obtain product price in PHC FX".</p></b></div>');
			        jQuery('#importToShop').show();
			        jQuery('#loader').html('');
			        jQuery('#updateStocks').hide();
			        jQuery('#updateAllFields').hide();
			        jQuery('#saveProductInShop').hide();
			    }
		    	jQuery("#tableOfProducts_previous").after("&nbsp;&nbsp;&nbsp;&nbsp;");
		  	})
    	})
 	});

	//Save new type of order in PHC FX
  	jQuery('#addNewTypeOfOrder').click(function (event) {
	    // stop normal form submission handler
	    event.preventDefault();
	    //save products in MySQL
	    jQuery.post(url, {
	  		action: 'woocommerce_fx',
	  		method: 'newTypeOfOrder',
	  		nameTypeOfOrder: jQuery('#nameOfNewOrder').val(),
	  		manageStock: jQuery('#manageStock').prop('checked'),
	  		warehouse: jQuery('#warehouseOrder').val()
	  	})
	    .done(function () {
	    	if(jQuery('#nameOfNewOrder').val() == ''){
				jQuery('#addNewTypeOrderMessage').html('');
			} else {
				jQuery('#addNewTypeOrderMessage').html('A new type of Order was added in PHC FX');
			}
			jQuery('#typeOfOrder').html('');
			jQuery.post(url, {
		  		action: 'woocommerce_fx',
		  		method: 'updateTypeOfOrder'
		  	})
		  	.done(function (data) {
		  		jQuery('#typeOfOrder').html("<option value='0'>Select one...</option>");
				jQuery('#typeOfOrder').append(data);
	    	})
	    })
 	});

});

//Select All or Remove All checks
function toggle(source) {
	checkboxes = document.getElementsByName('checkboxes');
  	for(var i=0, n=checkboxes.length;i<n;i++) {
    	checkboxes[i].checked = source.checked;
  	}
}