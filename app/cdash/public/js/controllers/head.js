CDash.controller('HeadController', ($rootScope, $document) => {
  // Adapted from:
  // http://www.quirksmode.org/js/cookies.html
  $rootScope.readCookie = function(name) {
    const nameEQ = `${name}=`;
    const ca = document.cookie.split(';');
    for (let i=0;i < ca.length;i++) {
      let c = ca[i];
      // eslint-disable-next-line eqeqeq
      while (c.charAt(0)==' ') {
        c = c.substring(1,c.length);
      }
      // eslint-disable-next-line eqeqeq
      if (c.indexOf(nameEQ) == 0) {
        return c.substring(nameEQ.length,c.length);
      }
    }
    return null;
  };

  // Pick which CSS file to use based on user settings.
  const colorblind = $rootScope.readCookie('colorblind');
  // eslint-disable-next-line eqeqeq
  if (colorblind == 1) {
    $rootScope.cssfile = 'colorblind';
  }
  else {
    $rootScope.cssfile = 'cdash';
  }

  // Load query string parameters into javascript object.
  $rootScope.queryString = {};
  let match;
  const pl = /\+/g; // Regex for replacing addition symbol with a space
  const search = /([^&=]+)=?([^&]*)/g;
  const decode = function (s) {
    return decodeURIComponent(s.replace(pl, ' '));
  };
  const query  = window.location.search.substring(1);
  while (match = search.exec(query)) {
    $rootScope.queryString[decode(match[1])] = decode(match[2]);
  }

  $rootScope.setupCalendar = function(date) {
    if (date) {
      year = date.substr(0, 4);
      // eslint-disable-next-line eqeqeq
      if (date.length == 8) {
        month = date.substr(4, 2);
        day = date.substr(6, 2);
      }
      else {
        month = date.substr(5, 2);
        day = date.substr(8, 2);
      }
      $('#calendar').datepicker({
        onSelect: $rootScope.calendarSelected,
        defaultDate: new Date(`${month}/${day}/${year}`),
        maxDate: '0D', // restrict to the past
      });
    }
    else {
      $('#calendar').datepicker({
        onSelect: $rootScope.calendarSelected,
        maxDate: '0D', // restrict to the past
      });
    }
  };

  // Navigate to a different date (from the inline date picker) for the current page.
  $rootScope.calendarSelected = function(dateStr) {
    const dateValue = `${dateStr.substr(6, 4)}-${dateStr.substr(0, 2)}-${dateStr.substr(3, 2)}`;
    let uri = window.location.href;
    // eslint-disable-next-line no-var
    var dateStr = `&date=${dateValue}`;

    // Strip out any previous date/begin/end parameters.
    uri = uri.replace(/(&begin=)[^\&]+/, '');
    uri = uri.replace(/(&end=)[^\&]+/, '');
    uri = uri.replace(/(&date=)[^\&]+/, '');

    const filterIdx = uri.indexOf('&filter');
    if (filterIdx > -1) {
      // Insert the date clause before any filter stuff.
      uri = uri.slice(0, filterIdx) + dateStr + uri.slice(filterIdx);
    }
    else {
      // No filters, stick the date on the end.
      uri += dateStr;
    }

    window.location = uri;
    $('#calendar').hide();
  };

  $rootScope.toggleCalendar = function() {
    if (!$('#calendar').hasClass('hasDatepicker')) {
      // Setup the calendar the first time it is clicked.
      if ('date' in $rootScope.queryString) {
        $rootScope.setupCalendar($rootScope.queryString.date);
      }
      else {
        $rootScope.setupCalendar();
      }
    }
    $('#calendar').toggle();
  };

});
