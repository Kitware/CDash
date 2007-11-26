// Main function
function buildgroup_click(buildid)
{			
		var group = "#buildgroup_"+buildid;
		
		$(group).ajaxSuccess(function(evt, request, settings){
    $(group).fadeIn('slow');
		 });
		
	 //if($(group).html() != "" && $(group).html() != "added to group!")
		if($(group).html() != "" && $(group).is(":visible"))
		  {
				$(group).fadeOut('medium');
				return;
		  }
		
		$(group).hide();
		$(group).load("ajax/addbuildgroup.php?buildid="+buildid);
}

function buildinfo_click(buildid)
{			
		var group = "#buildgroup_"+buildid;
		
		$(group).ajaxSuccess(function(evt, request, settings){
    $(group).fadeIn('slow');
		 });
		
		if($(group).html() != "" && $(group).is(":visible"))
		  {
				$(group).fadeOut('medium');
				return;
		  }
		
		$(group).hide();
		$(group).load("ajax/buildinfogroup.php?buildid="+buildid);
}