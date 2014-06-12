// assumes the ids of the sortable child elements
// are properly set
function getSortedElements(parent) {
  var positions = [];
  $(parent).children().each(function() {
    var pos = {};
    pos.buildgroupid = $(this).attr('id');
    pos.position = $(this).index() + 1;
    positions.push(pos);
  });
  return positions;
}
