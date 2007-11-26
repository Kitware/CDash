var obj = null;
var obj2 = null;
var calhover = null;

function checkHover() {
 if (obj) {
  obj.find('ul').fadeOut('fast');
  calhover = null;
  //obj2 = null;
 } //if
} //checkHover


function checkHoverCal() {
 if (obj2 && !calhover) {
  //$('#calendar').hide();
 } //if
} //checkHoverCal

function findPosX(obj)
  {
    var curleft = 0;
    if(obj.offsetParent)
        while(1) 
        {
         curleft += Math.abs(obj.offsetLeft);
          if(!obj.offsetParent)
            break;
          obj = obj.offsetParent;
        }
    else if(obj.x)
        curleft += obj.x;
    return curleft;
  }

function findPosY(obj)
  {
    var curtop = 0;
    if(obj.offsetParent)
        while(1)
        {
          curtop += obj.offsetTop;
          if(!obj.offsetParent)
            break;
          obj = obj.offsetParent;
        }
    else if(obj.y)
        curtop += obj.y;
    return curtop;
  }


function findHeight(obj)
  {
    var curtop = 0;
    return obj.offsetHeight;
  }

function findCalendarXPos(obj)
  {
  return document.getElementById("Nav").offsetLeft+document.getElementById("Dartboard").offsetWidth;
  }

// Main function
$(document).ready(function() {  

  $('#cal').click(function() {
  if (obj2) {
   $('#calendar').fadeOut('fast');
   obj2 = null;
   return;
  } //if
  
  //var posX = findPosX(document.getElementById("cal"));
  var posX = findCalendarXPos(document.getElementById("cal"));
  var posY = findPosY(document.getElementById("cal"));
  
  
  var height = findHeight(document.getElementById("cal"));
  $('#calendar').css({ top:posY+height });
  $('#calendar').css({ left:posX-50 });
  $('#calendar').fadeIn('fast');
  obj2 = $(this);
  //calhover = 1;
 }, function() {
  //obj2 = $(this);
  /*setTimeout(
   "checkHoverCal()",
   400);*/
 });
  
 // If we are hover the calendar
 /*$('#calendar').hover(function() {
   calhover = 1;
 });*/
 
 $('#Nav > li').hover(function() {  
  if (obj2) {
   $('#calendar').fadeOut('fast');
   calhover = null;
   obj2 = null;
  } //if
  
  if (obj) {
   obj.find('ul').fadeOut('fast');
   obj = null;
  } //if
  
  $(this).find('ul').fadeIn('fast');
  var height = findHeight(document.getElementById("cal"));
  $(this).find('ul').css({ top:height });
  if ( jQuery.browser.msie )
    {
    $(this).find('ul').find('a').css({ width:100 });
    }
 }
 
 , function() {
  obj = $(this);
  setTimeout(
   "checkHover()",
   400);
 });
 
});
