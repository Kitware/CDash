$(document).ready(function() {
  $('.subproject_core').click(function(e) {
    var project = $(this).data('projectid');
    var subproject = $(this).data('subprojectid');
    var core = $(this).data('core');
    var link = $(this);

    $(this).text("...");
    $.get("manageSubproject.php", { "projectid": project, "subprojectid": subproject, "core": core }, function( data ) {
      if (core == 1) {
        link.text("[Mark as non-core]");
        link.data("core", 0);
      }
      else {
        link.text("[Mark as core]");
        link.data("core", 1);
      }
    });
  });
});
