$(document).ready(function() {
  // Hide the old projects by default
  $(".nonactive").hide();
  $("#hideold").hide();
});   

/** Show the old project */
function showoldproject()
{
  $(".nonactive").show();
  $("#showold").hide();
  $("#hideold").show();
}

/** Hide the old project */
function hideoldproject()
{
  $(".nonactive").hide();
  $("#showold").show();
  $("#hideold").hide();
}
