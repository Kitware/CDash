$(document).ready(function()
  {
  tooltip();

  // If the button calendar is clicked
  $('#cal').click(function() {
      $( "#calendar" ).toggle();
    });

  if ($("#date_now").length > 0&&$("#date_now").html().length > 0)
    {
    dateNow = $("#date_now").html();
    year = dateNow.substr(0, 4);
    if (dateNow.length == 8)
      {
      month = dateNow.substr(4, 2);
      day = dateNow.substr(6, 2);
      }
    else
      {
      month = dateNow.substr(5, 2);
      day = dateNow.substr(8, 2);
      }
    $('#calendar').datepicker(
      {
        onSelect: calendarSelected,
        defaultDate: new Date(month + '/' + day + '/' + year),
        maxDate: "0D" // restrict to the past
      });
    }
  else
    {
    $('#calendar').datepicker(
      {
      onSelect: calendarSelected,
      maxDate: "0D" // restrict to the past
      });
    }


  // Display the date range from a multi-month inline date picker
  function calendarSelected(dateStr)
    {
    var project = document.getElementById("projectname");
    window.location = "index.php?project=" + project.value + "&date=" + dateStr.substr(6, 4) + dateStr.substr(0, 2) + dateStr.substr(3, 2);
    $('#calendar').hide();
    }

  // Quick links
  /* $('.quicklink').hide();

  $('.table-heading1').mouseover(function()
    {
    $('.quicklink', this).show();
    }).mouseout(function()
    {
    $('.quicklink', this).hide();
    });
  $('.table-heading2').mouseover(function()
    {
    $('.quicklink', this).show();
    }).mouseout(function()
    {
    $('.quicklink', this).hide();
    });

  $('.table-heading3').mouseover(function()
    {
    $('.quicklink', this).show();
    }).mouseout(function()
    {
    $('.quicklink', this).hide();
    });
   */
  });
