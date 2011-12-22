$(document).ready(function() {
  changeViewerType()
});

function previousTab(i)
  {
  $('.tab_help').html('');
  $('#wizard').triggerTab(parseInt(i)-1);
  }

function nextTab(i)
  {
  if(i==1)
    {
    if ($("#name").attr("value") == '')
       {
       alert('Please specify a name for the project.');
       return false;
       }
     }
   $('.tab_help').html('');
   $('#wizard').enableTab(parseInt(i)+1);
   $('#wizard').triggerTab(parseInt(i)+1);
   if(i==5)
     {
     $("input").removeAttr("disabled");
     }
   }

function saveChanges()
  {
  $("#changesmade").show();
  }

function confirmDelete()
  {
  if (window.confirm("Are you sure you want to delete this project?"))
    {
    return true;
    }
  return false;
  }

function changeViewerType()
{
  var baseurl = $('#cvsURL').attr("value");
  if(baseurl == '')
    {
    baseurl = 'repositoryurl';
    }


  //$('#repositoryurlexample').html(text);
  $.getJSON('api/?method=repository&task=exampleurl&url='+$('#cvsURL').attr("value")+'&type='+$('#cvsviewertype').attr("value"), function(data) {
     $('#repositoryurlexample').html(data);

  });

}
