$(document).ready(() => {
  $('#newuser').hide();
  $('#search').keyup(() => {
    const search = $('#search').val();

    if (search.length > 0) {
      // Trigger AJAX request
      $('#newuser').load(`ajax/findusers.php?search=${search}`,{},() => {
        $('#newuser').fadeIn('fast');
      });
    }
    else {
      $('#newuser').fadeOut('medium');
    }
  });
});
