
jQuery(function($) {
  var text, pass = true, form = $('form[name="modules"]');
  var form_field = $('input[name="configuration[MODULE_PAYMENT_BITCOINTHAI_LIST_CURRENCIES]"]');
  if( form_field.get(0) ) {
    form.on('submit', function(e) {
      e.preventDefault();
      if( form_field.val().length > 0 ) {
        // RegEx by comma, period, space
        ticker_array = form_field.val().split(/[.,]+/);
        for(var i = 0; i < ticker_array.length; i++) {
          let data = ticker_array[i].trim(); // Trim
          if( data.length <= 2  || data.length > 5 || data.match(/^[a-zA-Z0-9]*$/) === null ) {
            pass = false;
            break;
          }else{
            pass = true;
          }
        } // end of loop
      }else{
        pass = true;
      }
      if( pass === true ) {
        $(this).off('submit').submit();
      }else{
        alert( 'Input not valid. Example: BTC, BCH, DAS, DOG, LTC' );
      }
    });
  };
});
