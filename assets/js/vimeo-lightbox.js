/* eslint-env browser, jquery */

jQuery(function() {
  jQuery(document).on("click", '[data-toggle="lightbox"]', function(event) {
    event.preventDefault();
    jQuery(this).ekkoLightbox({ alwaysShowClose: false });
  });
});
