$(document).ready(function() {

// Settings
$("#settings").click(function (e) { // binding onclick
  $("#settings ul").toggle();
  e.stopPropagation();
});

$("body").click(function () { // binding onclick to body
  $("#settings ul").hide();
});

/** Show/hide the feed */
var feed_cookie = $.cookie('cdash_hidefeed');
if(feed_cookie)
  {
  $('#feed').hide();
  $('.showfeed').html("Show Feed");
  }

/** Enable the autorefresh */
$('.showfeed').click(function()
  {
  var feed_cookie = $.cookie('cdash_hidefeed');
  if(feed_cookie)
    {
    $.cookie('cdash_hidefeed', null);
    $("#feed").load("ajax/getfeed.php?projectid="+projectid,{},function(){$(this).fadeIn('slow');});
    $('#feed').show();
    $('.showfeed').html("Hide Feed");
    }
  else
    {
    $.cookie('cdash_hidefeed',1);
    $('#feed').hide();
    $('.showfeed').html("Show Feed");
    }
  $("#settings ul").hide();
  return false;
  });

var projectid = document.getElementById("projectid").value;
if(!feed_cookie)
  {
  $("#feed").load("ajax/getfeed.php?projectid="+projectid,{},function(){$(this).fadeIn('slow');});
  }
setInterval(function(){
   var feed_cookie = $.cookie('cdash_hidefeed');
   if(!feed_cookie)
    {
    $("#feed").load("ajax/getfeed.php?projectid="+projectid,{},function(){$(this).fadeIn('slow');})
    }
  }, 30000); // 30s
});
