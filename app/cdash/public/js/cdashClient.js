$(document).ready(function() {
  checkSystem();
});

function clearOS()
  {
  $("#system_select").each(function(){
      $("#system_select option").removeAttr("selected");});
  checkSystem();
  return false;
  }

function clearCompiler()
  {
  $("#compiler_select").each(function(){
      $("#compiler_select option").removeAttr("selected");});
  checkSystem();
  return false;
  }

function clearCMake()
  {
  $("#cmake_select").each(function(){
      $("#cmake_select option").removeAttr("selected");});
  checkSystem();
  return false;
  }

function clearSite()
  {
  $("#site_select").each(function(){
      $("#site_select option").removeAttr("selected");});
  checkSystem();
  return false;
  }

function clearLibrary()
  {
  $("#library_select").each(function(){
      $("#library_select option").removeAttr("selected");});
  checkSystem();
  return false;
  }

/** Check how many machines are currently available */
function checkSystem()
  {
  var os='';
  $('#system_select :selected').each(function(i, selected){
  if(os != '')
      {
    os += ',';
    }
  os += $(selected).val();
  });

  var compiler='';
  $('#compiler_select :selected').each(function(i, selected){
  if(compiler != '')
      {
    compiler += ',';
    }
  compiler += $(selected).val();
  });

  var cmake='';
  $('#cmake_select :selected').each(function(i, selected){
  if(cmake != '')
      {
    cmake += ',';
    }
  cmake += $(selected).val();
  });

  var site='';
  $('#site_select :selected').each(function(i, selected){
  if(site != '')
      {
    site += ',';
    }
  site += $(selected).val();
  });

  var library='';
  $('#library_select :selected').each(function(i, selected){
  if(library != '')
      {
    library += ',';
    }
  library += $(selected).val();
  });

  $.ajax({
      type: "POST",
      url: "ajax/clientchecksystem.php",
      dataType: 'html',
      timeout: 100000000,
      data: "os="+os+"&compiler="+compiler+"&cmake="+cmake+"&site="+site+"&library="+library,
      success: function(html){
    $("#check").html(html);
        }
       });
  }
