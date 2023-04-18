
$(document).ready(() => {
  $('#newuser').hide();
  $('#search').keyup(() => {
    const search = $('#search').val();

    const projectid = document.getElementById('projectid').value;

    if (search.length > 0) {
      // Trigger AJAX request
      $('#newuser').load(`ajax/finduserproject.php?projectid=${projectid}&search=${search}`,{},() => {
        $('#newuser').fadeIn('fast');
      });
    }
    else {
      $('#newuser').fadeOut('medium');
    }
  });
});
