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
   });
})
