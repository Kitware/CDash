const opt = new OptionTransfer('movelabels[]','emaillabels[]');
opt.setAutoSort(true);
opt.setDelimiter(',');

function rightTransfer() {
  opt.transferRight();
  saveChanges();
}

function leftTransfer() {
  opt.transferLeft();
  saveChanges();
}

function SubmitForm() {
  $('#emaillabels option').each(function(i) {
    $(this).attr('selected', 'selected');
  });
}
