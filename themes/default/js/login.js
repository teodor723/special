function isEmpty(obj) {
    for(var key in obj) {
        if(obj.hasOwnProperty(key))
            return false;
    }
    return true;
}

var step = 1;
var current_url = '';

function togglePswd(id){
	var check = $('#'+id).attr('type');
	if(check == 'password'){
		$('#'+id).attr('type','text');
	} else {
		$('#'+id).attr('type','password');
	}
}

$('#create-acc').on('click', function() {
    var span = $('#regBtn').find('span');
    var findme = "Error";
    if($(this).attr("type")=="button") {
        $.ajax({
			data:  {email:$("#email").val(),action:"verifyemail"},
			url:   request_source()+'/user.php',
			type:  'post',
			beforeSend: function () {
				$("#create-acc").html(site_lang[275].text);
				$('#error').hide();
			},			
			success:  function (response) {
			    console.log(response);
				if ( response.indexOf(findme) > -1 ) {
					response = response.replace('Error','');
			        swal({
			            title: site_config.name,
			            text: response,
			            type: "error"
			        }, function(t) { 
			        })
					$(span[0]).show();
					$(span[1]).hide();			        
					$("#create-acc").html(site_lang[676].text);
				} else {
					 $("#create-acc").html(site_lang[8].text);
					 $("#register .hide").each(function(){
					     $(this).removeClass("hide");
					 });
					 $("#create-acc").attr("type","submit");
				}
			}
	    });
	    return false;
    } else {
	    $('#register').submit();
    }
	return false;
});
/*
$('#login-now').on('click', function() {
	$('#login').submit();
	return false;
});	
*/
// $('#recover-now').on('click', function() {
// 	$('#recover').submit();
// 	return false;
// });	
$('#register').submit(function(e) {
	var span = $('#regBtn').find('span');
	e.preventDefault();
	var findme = "Error";
	if(isEmpty($('#year').val()) || isEmpty($('#day').val()) || isEmpty($('#month').val())){
	    swal({
	        title: 'uhm...',
	        text: site_lang[182].text,
	        type: "error"
	    }, function(t) { 
	    })	
	return false;	
	}

	$(span[0]).hide();
	$(span[1]).show();
	firebaseEmailRegister();
	/*
	$.ajax({
			data:  $(this).serialize(),
			url:   request_source()+'/user.php',
			type:  'post',
			beforeSend: function () {
				$("#create-acc").html(site_lang[275].text);
				$('#error').hide();
			},			
			success:  function (response) {  	
				if ( response.indexOf(findme) > -1 ) {
					response = response.replace('Error','');
			        swal({
			            title: site_config.name,
			            text: response,
			            type: "error"
			        }, function(t) { 
			        })
					$(span[0]).show();
					$(span[1]).hide();			        
					$("#create-acc").html(site_lang[8].text);
				} else {
					 window.location=site_config['site_url']+'meet';
				}
			}
	});	*/		
});
$('#login').submit(function(e) {
	var span = $('#loginBtn').find('span');	
	e.preventDefault();
	var findme = "Error";
	$(span[0]).hide();
	$(span[1]).show();	
	$.ajax({
			data:  $(this).serialize(),
			url:   request_source()+'/user.php',
			type:  'post',
			beforeSend: function () {
				$("#login-now").html(site_lang[275].text);
				$('#login-error').hide();
			},			
			success:  function (response) {  	
				if ( response.indexOf(findme) > -1 ) {
					response = response.replace('Error','');
			        swal({
			            title: site_config.name,
			            text: response,
			            type: "error"
			        }, function(t) { 
			        })
					$(span[0]).show();
					$(span[1]).hide();			        
					$("#login-now").html(site_lang[13].text);
				} else {
					 window.location=site_config['site_url']+'meet';
				}
			}
	});					
});
/*	
$('#recover').submit(function(e) {
	var span = $('#forgetBtn').find('span');	
	e.preventDefault();
	var findme = "Error";
	$(span[0]).hide();
	$(span[1]).show();	
	$.ajax({
			data:  $(this).serialize(),
			url:   request_source()+'/user.php',
			type:  'post',
			beforeSend: function () {
				$("#recover-now").html(site_lang[275].text);
				$('#recover-error').hide();
			},			
			success:  function (response) {  	
				if ( response.indexOf(findme) > -1 ) {
					response = response.replace('Error','');
			        swal({
			            title: 'uhm...',
			            text: response,
			            type: "error"
			        }, function(t) { 
			        })
					$(span[0]).show();
					$(span[1]).hide();				        
					$("#recover-now").html(site_lang[15].text);
				} else {
			        swal({
			            title: site_lang[28].text,
			            text: site_lang[341].text,
			            type: "success"
			        }, function(t) { 
			        })								
					$("#recover-now").hide();								
				}
			}
	});					
});				
*/
function locInitialize() {
	addressAutocomplete(document.getElementById("loc"), (data) => {
		if(data !== null){
			var lat = data.properties.lat;
			var lng = data.properties.lon;
			if (typeof data.properties.city !== 'undefined') {
				var city = data.properties.city;
			} else {
				var city = data.properties.address_line1;
			}
			var country = data.properties.country;

			$('#locality').val(city);
			$('#lat').val(lat);
			$('#lng').val(lng);
			$('#country').val(country);	
		}		  
	}, {
		width: '300px',
		marginTop: '18px',
		left: '0'
	});					
}

locInitialize();


function addressAutocomplete(containerElement, callback, options) {
  // create input element
  var inputElement = document.createElement("input");
  inputElement.setAttribute("type", "text");
  inputElement.setAttribute("placeholder", site_lang[33].text);
  
  if(current_login_url == 'default'){
  	inputElement.classList.add('input');
  } else { 
  	inputElement.classList.add("bl-input","w-full","p-16","text-14","font-normal","font-inter","placeholder-grey","hover:bg-bl-bg-grey","focus:bg-white");
  }

  containerElement.appendChild(inputElement);

  // add input field clear button
  var clearButton = document.createElement("div");

  
  if(current_login_url == 'default'){
  	clearButton.classList.add("clear-button","clear-button-landing1");
  } else { 
  	clearButton.classList.add("clear-button");
  }
  
  addIcon(clearButton);
  clearButton.addEventListener("click", (e) => {
    e.stopPropagation();
    inputElement.value = '';
    callback(null);
    clearButton.classList.remove("visible");
    closeDropDownList();
  });
  containerElement.appendChild(clearButton);
  document.querySelector('.clear-button').innerHTML='<svg width="24px" height="24px" viewBox="0 0 84 84" version="1.1"><circle classname="border" cx="42" cy="42" r="40" stroke-linecap="round" stroke-width="4" stroke="#be1622" fill="none"></circle><path classname="checkmark" stroke-linecap="round" stroke-linejoin="round" d="M30 55 55 30 M30 30 55 55" stroke-width="4" stroke="#be1622" fill="none"></path></svg>';

  /* Current autocomplete items data (GeoJSON.Feature) */
  var currentItems;

  /* Active request promise reject function. To be able to cancel the promise when a new request comes */
  var currentPromiseReject;

  /* Focused item in the autocomplete list. This variable is used to navigate with buttons */
  var focusedItemIndex;

  /* Execute a function when someone writes in the text field: */
  inputElement.addEventListener("input", function(e) {
    var currentValue = this.value;

    if(currentValue.length < 4){
    	return false;
    }

    /* Close any already open dropdown list */
    closeDropDownList();

    // Cancel previous request promise
    if (currentPromiseReject) {
      currentPromiseReject({
        canceled: true
      });
    }

    if (!currentValue) {
      clearButton.classList.remove("visible");
      return false;
    }

    // Show clearButton when there is a text
    clearButton.classList.add("visible");

    /* Create a new promise and send geocoding request */
    var promise = new Promise((resolve, reject) => {
      currentPromiseReject = reject;

      var apiKey = geolocationKey;
      var url = `https://api.geoapify.com/v1/geocode/autocomplete?text=${encodeURIComponent(currentValue)}&limit=5&apiKey=${apiKey}`;
      
      if (options.type) {
      	url += `&type=${options.type}`;
      }

      fetch(url)
        .then(response => {
          // check if the call was successful
          if (response.ok) {
            response.json().then(data => resolve(data));
          } else {
            response.json().then(data => reject(data));
          }
        });
    });

    promise.then((data) => {
      currentItems = data.features;

      /*create a DIV element that will contain the items (values):*/
      var autocompleteItemsElement = document.createElement("div");
      autocompleteItemsElement.style.width = options.width;
      autocompleteItemsElement.style.marginTop = options.marginTop;
      autocompleteItemsElement.style.left = options.left;
      if(current_login_url == 'auth'){
      	autocompleteItemsElement.style.width = '330px';
      	autocompleteItemsElement.style.marginTop = '8px';
      	autocompleteItemsElement.style.position = 'relative';
      }

      autocompleteItemsElement.setAttribute("class", "autocomplete-items");
      containerElement.appendChild(autocompleteItemsElement);
      var citiesArray = [];
      /* For each item in the results */
      data.features.forEach((feature, index) => {
        /* Create a DIV element for each element: */
        
        /* Set formatted address as item value */

		if (typeof feature.properties.city !== 'undefined') {
			var city = feature.properties.city;
		} else {
			var city = feature.properties.address_line1;
		}

		var checkIfInArray = city+feature.properties.country;

		if (!citiesArray.includes(checkIfInArray)) {
		  citiesArray.push(checkIfInArray);
		} else {
			return;
		}

		var itemElement = document.createElement("DIV");
        itemElement.innerHTML = city+', '+feature.properties.country;

        /* Set the value for the autocomplete text field and notify: */
        itemElement.addEventListener("click", function(e) {

			if (typeof currentItems[index].properties.city !== 'undefined') {
				var selectedCity = currentItems[index].properties.city;
			} else {
				var selectedCity = currentItems[index].properties.address_line1;
			}
			inputElement.value = selectedCity;
          callback(currentItems[index]);

          /* Close the list of autocompleted values: */
          closeDropDownList();
        });

        autocompleteItemsElement.appendChild(itemElement);
      });
    }, (err) => {
      if (!err.canceled) {
        console.log(err);
      }
    });
  });

  /* Add support for keyboard navigation */
  inputElement.addEventListener("keydown", function(e) {
    var autocompleteItemsElement = containerElement.querySelector(".autocomplete-items");
    if (autocompleteItemsElement) {
      var itemElements = autocompleteItemsElement.getElementsByTagName("div");
      if (e.keyCode == 40) {
        e.preventDefault();
        /*If the arrow DOWN key is pressed, increase the focusedItemIndex variable:*/
        focusedItemIndex = focusedItemIndex !== itemElements.length - 1 ? focusedItemIndex + 1 : 0;
        /*and and make the current item more visible:*/
        setActive(itemElements, focusedItemIndex);
      } else if (e.keyCode == 38) {
        e.preventDefault();

        /*If the arrow UP key is pressed, decrease the focusedItemIndex variable:*/
        focusedItemIndex = focusedItemIndex !== 0 ? focusedItemIndex - 1 : focusedItemIndex = (itemElements.length - 1);
        /*and and make the current item more visible:*/
        setActive(itemElements, focusedItemIndex);
      } else if (e.keyCode == 13) {
        /* If the ENTER key is pressed and value as selected, close the list*/
        e.preventDefault();
        if (focusedItemIndex > -1) {
          closeDropDownList();
        }
      }
    } else {
      if (e.keyCode == 40) {
        /* Open dropdown list again */
        var event = document.createEvent('Event');
        event.initEvent('input', true, true);
        inputElement.dispatchEvent(event);
      }
    }
  });

  function setActive(items, index) {
    if (!items || !items.length) return false;

    for (var i = 0; i < items.length; i++) {
      items[i].classList.remove("autocomplete-active");
    }

    /* Add class "autocomplete-active" to the active element*/
    items[index].classList.add("autocomplete-active");

    // Change input value and notify
    inputElement.value = currentItems[index].properties.formatted;
    callback(currentItems[index]);
  }

  function closeDropDownList() {
    var autocompleteItemsElement = containerElement.querySelector(".autocomplete-items");
    if (autocompleteItemsElement) {
      containerElement.removeChild(autocompleteItemsElement);
    }

    focusedItemIndex = -1;
  }

  function addIcon(buttonElement) {
    var svgElement = document.createElementNS("http://www.w3.org/2000/svg", 'svg');
    svgElement.setAttribute('viewBox', "0 0 24 24");
    svgElement.setAttribute('height', "24");

    var iconElement = document.createElementNS("http://www.w3.org/2000/svg", 'path');
    iconElement.setAttribute("d", "M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z");
    iconElement.setAttribute('fill', 'currentColor');
    svgElement.appendChild(iconElement);
    // buttonElement.appendChild(svgElement);
  }
  
    /* Close the autocomplete dropdown when the document is clicked. 
  	Skip, when a user clicks on the input field */
  document.addEventListener("click", function(e) {
    if (e.target !== inputElement) {
      closeDropDownList();
    } else if (!containerElement.querySelector(".autocomplete-items")) {
      // open dropdown list again
      var event = document.createEvent('Event');
      event.initEvent('input', true, true);
      inputElement.dispatchEvent(event);
    }
  });

}