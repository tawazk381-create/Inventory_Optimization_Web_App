<?php
// File: resources/views/partials/flash.php
// Shows flash messages stored in $_SESSION['flash'] = ['type'=>'success|danger|info','message'=>'...']
if (!empty($_SESSION['flash']) && is_array($_SESSION['flash'])):
  $f = $_SESSION['flash'];
  $type = in_array($f['type'] ?? '', ['success','danger','warning','info']) ? $f['type'] : 'info';
  $msg = $f['message'] ?? '';
  // clear flash
  unset($_SESSION['flash']);
?>
  <div class="alert alert-<?= e($type) ?> alert-dismissible fade show" role="alert">
    <?= nl2br(e($msg)) ?>
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
      <span aria-hidden="true">&times;</span>
    </button>
  </div>
<?php endif; ?>
