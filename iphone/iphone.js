
(function(){var animateX=-20;var animateInterval=10;var currentPage=null;var currentDialog=null;var currentWidth=0;var currentHash=location.hash;var hashPrefix="#_";var pageHistory=[];addEventListener("load",function(event)
{var body=document.getElementsByTagName("body")[0];for(var child=body.firstChild;child;child=child.nextSibling)
{if(child.nodeType==1&&child.getAttribute("selection")=="true")
{showPage(child);break;}}
setInterval(checkOrientAndLocation,300);setTimeout(scrollTo,0,0,1);},false);$j(document).ready(function(){$j('.showPage').click(function(){var page=document.getElementById($j(this).attr('href').substring(1));showPage(page);return false;});});function checkOrientAndLocation()
{if(window.outerWidth!=currentWidth)
{currentWidth=window.outerWidth;var orient=currentWidth==320?"profile":"landscape";document.body.setAttribute("orient",orient);}
if(location.hash!=currentHash)
{currentHash=location.hash;var pageId=currentHash.substr(hashPrefix.length);var page=document.getElementById(pageId);if(page)
{var index=pageHistory.indexOf(pageId);var backwards=index!=-1;if(backwards)
pageHistory.splice(index,pageHistory.length);showPage(page,backwards);}}}
function showPage(page,backwards)
{if(currentDialog)
{currentDialog.removeAttribute("selection");currentDialog=null;}
if(page.className.indexOf("dialog")!=-1)
showDialog(page);else
{location.href=currentHash=hashPrefix+page.id;pageHistory.push(page.id);var fromPage=currentPage;currentPage=page;var pageTitle=document.getElementById("pageTitle");pageTitle.innerHTML=page.title||"";var homeButton=document.getElementById("homeButton");if(homeButton)
homeButton.style.display=("#"+page.id)==homeButton.hash?"none":"inline";if(fromPage)
setTimeout(swipePage,0,fromPage,page,backwards);}}
function swipePage(fromPage,toPage,backwards)
{toPage.style.left="100%";toPage.setAttribute("selection","true");scrollTo(0,1);var percent=100;var timer=setInterval(function()
{percent+=animateX;if(percent<=0)
{percent=0;fromPage.removeAttribute("selection");clearInterval(timer);}
fromPage.style.left=(backwards?(100-percent):(percent-100))+"%";toPage.style.left=(backwards?-percent:percent)+"%";},animateInterval);}
function showDialog(form)
  {
	 currentDialog=form;
  form.setAttribute("selection","true");
		form.onclick=function(event)
     {
					if(event.target==form)
     form.removeAttribute("selection");
					}
			}
	})();
$j('.submitButton').click(function(){$j(this).parent().parent().submit();});
$j(document).ready(function(){$j('#username, #password').focus(function(){$j('#'+$j(this).attr('id')+'-label').hide();});
if($j.user().isLoggedIn==true)
  {
}});