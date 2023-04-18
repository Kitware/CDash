function showcoveragegraph_click(buildid,zoomout) {
  if (zoomout) {
    $('#graph').load(`ajax/showcoveragegraph.php?buildid=${buildid}`);
    return;
  }

  // eslint-disable-next-line eqeqeq
  if ($('#graph').html() != '' && $('#grapholder').is(':visible')) {
    $('#grapholder').hide();
    $('#graphoptions').html('');
    return;
  }

  $('#graph').fadeIn('slow');
  $('#graph').html('fetching...<img src=img/loading.gif></img>');
  $('#grapholder').attr('style','width:800px;height:400px;');
  $('#graphoptions').html(`<a href=javascript:showcoveragegraph_click(${buildid},true)>Zoom out</a>`);

  $('#graph').load(`ajax/showcoveragegraph.php?buildid=${buildid}`,{},() => {
    $('#grapholder').fadeIn('slow');
    $('#graphoptions').show();
  });
}
