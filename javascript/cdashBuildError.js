function showArguments(errorid)
{
  $("#showarguments_"+errorid).hide();
  $("#argumentlist_"+errorid).show();
  return false;
}

function hideArguments(errorid)
{
  $("#showarguments_"+errorid).show();  
  $("#argumentlist_"+errorid).hide();
  return false;
}
