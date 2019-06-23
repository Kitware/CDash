CDash.controller('HeadController', function HeadController($rootScope, $document) {
  // Adapted from:
  // http://www.quirksmode.org/js/cookies.html
  $rootScope.readCookie = function(name) {
    var nameEQ = name + "=";
    var ca = document.cookie.split(';');
    for(var i=0;i < ca.length;i++) {
      var c = ca[i];
      while (c.charAt(0)==' ') c = c.substring(1,c.length);
      if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length);
    }
    return null;
  };

  // Pick which CSS file to use based on user settings.
  var colorblind = $rootScope.readCookie('colorblind');
  if (colorblind == 1) {
    $rootScope.cssfile = "colorblind";
  } else {
    $rootScope.cssfile = "cdash";
  }

  // Load query string parameters into javascript object.
  $rootScope.queryString = {};
  var match,
      pl     = /\+/g,  // Regex for replacing addition symbol with a space
      search = /([^&=]+)=?([^&]*)/g,
      decode = function (s) { return decodeURIComponent(s.replace(pl, " ")); },
      query  = window.location.search.substring(1);
  while (match = search.exec(query)) {
    $rootScope.queryString[decode(match[1])] = decode(match[2]);
  }

  $rootScope.setupCalendar = function(date) {
    if (date) {
      year = date.substr(0, 4);
      if (date.length == 8) {
        month = date.substr(4, 2);
        day = date.substr(6, 2);
      } else {
        month = date.substr(5, 2);
        day = date.substr(8, 2);
      }
      $('#calendar').datepicker({
        onSelect: $rootScope.calendarSelected,
        defaultDate: new Date(month + '/' + day + '/' + year),
        maxDate: "0D" // restrict to the past
      });
    } else {
      $('#calendar').datepicker({
        onSelect: $rootScope.calendarSelected,
        maxDate: "0D" // restrict to the past
      });
    }
  };

  // Navigate to a different date (from the inline date picker) for the current page.
  $rootScope.calendarSelected = function(dateStr) {
    var dateValue = dateStr.substr(6, 4) + "-" + dateStr.substr(0, 2) + "-" + dateStr.substr(3, 2);
    var uri = window.location.href;
    // Insert/replace date value in current URI.
    if (uri.indexOf('date=') == -1) {
      uri += '&date=' + dateValue;
    } else {
      uri = uri.replace(/(date=)[^\&]+/, '$1' + dateValue);
    }
    window.location = uri;
    $('#calendar').hide();
  };

  $rootScope.toggleCalendar = function() {
    if (!$("#calendar").hasClass("hasDatepicker")) {
      // Setup the calendar the first time it is clicked.
      if ("date" in $rootScope.queryString) {
        $rootScope.setupCalendar($rootScope.queryString.date);
      } else {
        $rootScope.setupCalendar();
      }
    }
    $( "#calendar" ).toggle();
  };

});
