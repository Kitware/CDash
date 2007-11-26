<!--
 // Set the initial state if no current state or length changed
 if (current == "" || current.length != (db.length-1)) {
 current = ""
 initState = ""
 for (i = 1; i < db.length; i++) { 
 initState += db[i].open
 current += db[i].open
 }
 setCurrState(initState)
 }
 var prevIndentDisplayed = 0
 var showMyDaughter = 0
 // end -->
  
 <!--
       var Outline=""
 // cycle through each entry in the outline array
 for (var i = 1; i < db.length; i++) {
   var currIndent = db[i].indent           // get the indent level
   var expanded = current.substring(i-1,i) // current state
  var top = db[i].top
   if (top == "") { top="content" }
  // display entry only if it meets one of three criteria
   if ((currIndent == 0 || currIndent <= prevIndentDisplayed || (showMyDaughter == 1 && (currIndent - prevIndentDisplayed == 1)))) {
   Outline += pad(currIndent)

  // Insert the appropriate GIF and HREF
   image = "Blank";
   if (db[i].image==1) { image="_bullet"; }
   if (db[i].image==2) { image="_search"; }
   if (db[i].image==3) { image="_cal"; }
   if (db[i].image==4) { image="_upd"; }
   if (db[i].image==5) { image="_admin"; }
   if (!(db[i].mother)) {
    Outline += ""
    } 
   else { 
    if (current.substring(i-1,i) == 1) {
   Outline += "<A HREF=\"javascript:reload()\" onMouseOver=\"window.parent.status=\'Click to collapse\';return true;\" onClick=\"toggle(" + i + ")\">"
   Outline += "<IMG SRC=\"" + Icons + "Minus.gif\" WIDTH=16 HEIGHT=16 BORDER=0><IMG SRC=\"" + Icons + "Open.gif\" WIDTH=16 HEIGHT=16 BORDER=0>"
   Outline += "</A>"
   }
    else {
   Outline += "<A HREF=\"javascript:reload()\" onMouseOver=\"window.parent.status=\'Click to expand\';return true;\" onClick=\"toggle(" + i + ")\">"
   Outline += "<IMG SRC=\"" + Icons + "Plus.gif\" WIDTH=16 HEIGHT=16 BORDER=0><IMG SRC=\"" + Icons + "Closed.gif\" WIDTH=16 HEIGHT=16 BORDER=0>"
   Outline += "</A>"
   }
    }
  Outline += "&nbsp;";
     
  if (db[i].URL == "" || db[i].URL == null) {
    Outline += " " + db[i].display      // no link, just a listed item  
    }
  else {
    Outline += " <A HREF=\"" + db[i].URL + "\">" + db[i].display + "</A>"
    }
                if ( db[i].author != "" && db[i].author != null )
                {
                  if ( db[i].mailto == "" || db[i].mailto == null )
                  {
                    Outline += " by " + db[i].author
                  }
                  else
                  {
                    Outline += " by <a href=\"mailto:" + db[i].mailto + "\">" + db[i].author + "</a>"
                  }
                }
                if ( db[i].comment != null && db[i].comment != "" )
                {
                  Outline += "<br>" + pad(currIndent) + db[i].comment + "<br>"
                }
  // Bold if at level 0
  if (currIndent == 0) { 
    Outline = "<B>" + Outline + "</B>"
    }
//  if (currIndent == 1) {
//      Outline += "&nbsp;<a href=details_project.html><img src=../images/document_select.gif border=0 align=bottom></a>"
//    }
  //if (currIndent == 2) {
  //  Outline += "&nbsp;&nbsp;<a href=overview.html><img src=../images/document_overv.gif border=0></a>&nbsp;<a href=list.html><img src=../images/document_list.gif border=0></a>"
  //  }
//  if (currIndent == 3) {
//    Outline += "&nbsp;<a href=details_part.html><img src=../images/document_select.gif border=0 align=bottom></a>"
//    }
  //if (currIndent == 4) {
  //  Outline += "&nbsp;&nbsp;<a href=overview.html><img src=../images/document_overv.gif border=0></a>&nbsp;<a href=definition.html><img src=../images/document_definition.gif border=0></a>"
  //  }
  Outline += "<BR>"
  prevIndentDisplayed = currIndent
  showMyDaughter = expanded
  // if (i == 1) { Outline = ""}
  if (db.length > 25) {
    document.write(Outline)
    Outline = ""
          }
        }
   }
 document.write(Outline)
 // end -->
