CDash.controller('HeadController', function HeadController($scope) {

  // Adapted from:
  // http://www.quirksmode.org/js/cookies.html
  $scope.readCookie = function(name) {
    var nameEQ = name + "=";
    var ca = document.cookie.split(';');
    for(var i=0;i < ca.length;i++) {
      var c = ca[i];
      while (c.charAt(0)==' ') c = c.substring(1,c.length);
      if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
    }
    return null;
  }

  var colorblind = $scope.readCookie('colorblind');
  if (colorblind == 1) {
    $scope.cssfile = "colorblind.css";
  } else {
    $scope.cssfile = "cdash.css";
  }


});
