// assumes the ids of the sortable child elements
// are properly set
function getSortedElements(parent) {
  const positions = [];
  $(parent).children().each(function() {
    const pos = {};
    pos.id = $(this).attr('id');
    pos.position = $(this).index() + 1;
    positions.push(pos);
  });
  return positions;
}
