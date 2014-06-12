// function to alternate the color of the rows in our group tbody
function restripe(tbody_selector) {
  $( tbody_selector + " tr" ).each(function () {
    $(this).removeClass("odd");
    $(this).removeClass("even");
  });
  $( tbody_selector + " tr:visible:odd" ).each(function () {
    $(this).addClass("odd");
  });
  $( tbody_selector + " tr:visible:even" ).each(function () {
    $(this).addClass("even");
  });
}

// in order for restriping to work, the tbody must have an id set.
$(document).ready(function(){

  function getChildren($row) {
    var children = [];
    while($row.next().hasClass('child_row')) {
         children.push($row.next());
         $row = $row.next();
    }
    return children;
  }

  $('.parent_row').click(function() {
    var children = getChildren($(this));
    $.each(children, function() {
        $(this).toggle();
    })

    var folderIcon = $(this).find(".glyphicon");
    if (folderIcon.hasClass("glyphicon-folder-open")) {
      folderIcon.removeClass("glyphicon-folder-open");
      folderIcon.addClass("glyphicon-folder-close");
    }
    else {
      folderIcon.removeClass("glyphicon-folder-close");
      folderIcon.addClass("glyphicon-folder-open");
    }

  restripe("#" + $(this).closest("tbody").attr('id'));
  });
})
