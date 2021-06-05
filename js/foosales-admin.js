function updateFooSalesCategoryMultiselect() {
  if (
    jQuery("input[name='globalFooSalesProductsToDisplay']:checked").val() ===
    "cat"
  ) {
    jQuery("select#globalFooSalesProductCategories").removeAttr("disabled");
  } else {
    jQuery("select#globalFooSalesProductCategories").attr(
      "disabled",
      "disabled"
    );
  }
}

jQuery(document).ready(function () {
  jQuery(".foosales-tooltip").tooltip({
    tooltipClass: "foosales-tooltip-box",
  });

  if (jQuery("input[name='globalFooSalesProductsToDisplay']").length > 0) {
    updateFooSalesCategoryMultiselect();

    jQuery("input[name='globalFooSalesProductsToDisplay']").change(function () {
      updateFooSalesCategoryMultiselect();
    });
  }

  if (jQuery("input#globalFooSalesStoreLogoURL").length > 0) {
    jQuery(".wrap").on("click", ".upload_image_button_foosales", function (e) {
      e.preventDefault();

      var button = jQuery(this);
      var uploadInput = jQuery("input#globalFooSalesStoreLogoURL");

      wp.media.editor.send.attachment = function (props, attachment) {
        jQuery(uploadInput).val(attachment.url);
      };

      wp.media.editor.open(button);

      return false;
    });

    jQuery(".upload_reset_foosales").click(function () {
      jQuery("input#globalFooSalesStoreLogoURL").val("");
      return false;
    });

    window.original_send_to_editor = function () {};
  }
});
