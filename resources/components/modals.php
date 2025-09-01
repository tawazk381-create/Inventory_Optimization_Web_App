<?php // File: resources/components/modals.php
?>
<!-- Generic confirmation modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-sm" role="document">
    <div class="modal-content">
      <div class="modal-body" id="confirmModalBody">Are you sure?</div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        <button id="confirmModalOk" class="btn btn-danger">Yes</button>
      </div>
    </div>
  </div>
</div>
<script>
  function confirmAction(message, cb) {
    document.getElementById('confirmModalBody').textContent = message || 'Are you sure?';
    $('#confirmModal').modal('show');
    document.getElementById('confirmModalOk').onclick = function(){
      $('#confirmModal').modal('hide');
      cb && cb();
    };
  }
</script>
