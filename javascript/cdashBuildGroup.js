// Main function
function buildgroup_click(buildid)
{
		var group = "#buildgroup_"+buildid;
	 if($(group).html() != "" && $(group).html() != "added to group!")
		  {
				$(group).html("");
				$(group).fadeOut('slow');
				return;
		  }
		$(group).fadeIn('slow');
		$(group).load("ajax/addbuildgroup.php?buildid="+buildid);
}
