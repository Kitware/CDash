CDash.factory('filters', () => {
  const filters = {};

  filters.toggle = function(show) {
    const str = new String(window.location);
    const idx = str.indexOf('&showfilters=', 0);
    if (idx > 0) {
      if (show) {
        window.location.href = str.replace('&showfilters=0', '&showfilters=1');
      }
      else {
        window.location.href = str.replace('&showfilters=1', '&showfilters=0');
      }
    }
  };

  filters.getString = function() {
    const str = new String(window.location);
    const idx = str.indexOf('&filtercount=', 0);
    if (idx > 0) {
      return str.substr(idx);
    }
    else {
      return '';
    }
  };

  filters.getLabelString = function(filterdata) {
    // eslint-disable-next-line eqeqeq
    if ( filterdata == undefined ) {
      return '';
    }

    let s = '&showfilters=1';
    let n = 0;
    for (let i = 1; i <= filterdata.filters.length; i++) {
      const field = filterdata.filters[i-1].field;
      if (field === 'label') {
        n = n + 1;
        s = `${s}&field${i}=${escape(field)}`;
        s = `${s}&compare${i}=${escape(filterdata.filters[i-1].compare)}`;
        s = `${s}&value${i}=${escape(filterdata.filters[i-1].value)}`;
      }
    }

    if (n > 1) {
      s = `&filtercombine=${filterdata.filtercombine}${s}`;
    }

    if (n > 0) {
      return `&filtercount=${n}${s}`;
    }
    else {
      return '';
    }
  };

  return filters;
});
