<!--
var total=1;
var db = new Array();

// -- Enter Values Here --
// Format: dbAdd(parent[true|false] , description, URL [blank for nohref], level , TARGET [blank for "content"], image [1=yes])

// Get current cookie setting
var current=getCurrState()
function getCurrState() {
  var label = "currState="
  var labelLen = label.length
  var cLen = document.cookie.length
  var i = 0
  while (i < cLen) {
    var j = i + labelLen
    if (document.cookie.substring(i,j) == label) {
      var cEnd = document.cookie.indexOf(";",j)
      if (cEnd == -1) { cEnd = document.cookie.length }
      return unescape(document.cookie.substring(j,cEnd))
    }
    i++
  }
  return ""
}

// Record current settings in cookie
function setCurrState(setting) {
  var expire = new Date();
  expire.setTime(expire.getTime() + ( 60*60*1000 ) ); // expire in 1 hour
  document.cookie = "currState=" + escape(setting) + "; expires=" + expire.toGMTString();
  }

function XMLStrFormat(str)
{
  str = str.replace(/&amp;apos;/g,"\'");
  str = str.replace(/&amp;quot;/g,"\"");
  str = str.replace(/&amp;amp;/g,"\&");
  str = str.replace(/&amp;lt;/g,"\&lt;");
  str = str.replace(/&amp;gt;/g,"\&gt;");
  return str;
}


// Add an entry to the database
function dbAdd(mother,display,URL,indent,top,open,author,mailto,comment,bugURL,bugid,bugpos) {
  db[total] = new Object;
  db[total].mother = mother
  db[total].display = display
  db[total].URL = URL
  db[total].indent = indent
  db[total].top = top
  db[total].open = open
  db[total].image = ""
  db[total].author = author
  db[total].mailto = mailto
  db[total].comment = XMLStrFormat(comment)
  db[total].bugURL = bugURL
  db[total].bugid = bugid
  db[total].bugpos = bugpos
  total++
  }

// toggles an outline mother entry, storing new value in the cookie
function toggle(n) {
  if (n != 0) {
    var newString = ""
    var expanded = current.substring(n-1,n) // of clicked item
    newString += current.substring(0,n-1)
    newString += expanded ^ 1 // Bitwise XOR clicked item
    newString += current.substring(n,current.length)
    setCurrState(newString) // write new state back to cookie
  }
}

// Reload page
function reload() {
  //   if (navigator.userAgent.toLowerCase().indexOf('opera') == -1) {
  //        history.go(0);
  //     } else {
      if (document.images) {
         location.replace(location.href);
      } else {
         location.href(location.href);
      }
  //     }
}

// returns padded spaces (in mulTIPles of 2) for indenting
function pad(n) {
  var result = ""
  for (var i = 1; i <= n; i++) { result += "&nbsp;&nbsp;&nbsp;&nbsp;" }
  return result
}

// Expand everything
function explode() {
  current = "";
  initState="";
  for (var i = 1; i < db.length; i++) {
    initState += "1"
    current += "1"
    }
  setCurrState(initState);
  reload();
  }

// Collapse everything
function contract() {
  current = "";
  initState="";
  for (var i = 1; i < db.length; i++) {
    initState += "0"
    current += "0"
    }
  setCurrState(initState);
  reload();
  }

function tree_close() {
  window.parent.location = window.parent.content.location;
  }

//end -->
