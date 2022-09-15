<?php
  // Add page title
  $params = array();
  $params['title'] = "ACCESS Organizations";

  print $this->element("pageTitleAndButtons", $params);
?> 
<script>
  $(function() {
    $("#organization-choose").autocomplete({
      source: "<?php print $this->Html->url(array('plugin' => 'access_organization', 'controller' => 'access_organizations', 'action' => 'find', 'co' => $cur_co['Co']['id'])); ?>",
      minLength: 3,
      select: function (event, ui) {
        $("#organization-choose").hide();
        $("#organization-choose-name").text(ui.item.label).show();
        $("#organization-choose-button").prop('disabled', false).focus();
        $("#organization-choose-clear-button").show();
        return false;
      },
      search: function (event, ui) {
        $("#organization-choose-search-container .co-loading-mini").show();
      },
      focus: function (event, ui) {
        $("#organization-choose-search-container .co-loading-mini").hide();
      },
      close: function (event, ui) {
        $("#organization-choose-search-container .co-loading-mini").hide();
      }
    });

    $("#organization-choose-button").click(function() {
      var range = document.createRange();
      e = document.getElementById("organization-choose-name");
      range.selectNode(e);
      window.getSelection().removeAllRanges();
      window.getSelection().addRange(range);
      document.execCommand("copy");
      window.getSelection().removeAllRanges();
    });

    $("#organization-choose-clear-button").click(function() {
      $("#organization-choose-name").hide();
      $("#organization-choose-button").prop('disabled', true).focus();
      $("#organization-choose-clear-button").hide();
      $("#organization-choose").val("").show().focus();
      return false;
    });

    $('[data-toggle="tooltip"]').tooltip();

  });

</script>

<p>
Type in the box below to find an ACCESS Organization and click COPY to copy to your clipboard.
</p>

<div id="organization-choose-search-container">
  <label for="organization-choose" class="col-form-label-sm">ACCESS Organization: </label>
  <span class="co-loading-mini-input-container">
    <input id="organization-choose" type="text" class="form-control-sm" placeholder="enter organization name" />
    <span class="co-loading-mini"><span></span><span></span><span></span></span>
  </span>
  <span id="organization-choose-name" style="display: none;"></span>
  <button id="organization-choose-button" class="btn btn-primary btn-sm" disabled="disabled"><?php print _txt('Copy'); ?></button>
  <button id="organization-choose-clear-button" class="btn btn-sm" style="display: none;"><?php print _txt('op.clear'); ?></button>
</div>
