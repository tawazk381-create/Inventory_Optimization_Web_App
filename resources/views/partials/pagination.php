<?php
// File: resources/views/partials/pagination.php
// Reusable pagination partial.
// Expects local variables: $page, $perPage, $total, optional $baseUrl

$page     = $page     ?? 1;
$perPage  = $perPage  ?? 10;   // ✅ Fix: ensure $perPage is always defined
$total    = $total    ?? 0;
$baseUrl  = $baseUrl  ?? strtok($_SERVER["REQUEST_URI"], '?'); // path only
$query    = $_GET;

$totalPages = (int) ceil($total / max(1, (int)$perPage));
if ($totalPages <= 1) {
    // nothing to render
    return;
}

$createUrl = function($p) use ($baseUrl, $query) {
    $q = $query;
    $q['page'] = $p;
    return $baseUrl . '?' . http_build_query($q);
};

$start = max(1, $page - 3);
$end   = min($totalPages, $page + 3);
?>
<nav aria-label="Pagination">
  <ul class="pagination">
    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
      <a class="page-link" href="<?= $createUrl(1) ?>">« First</a>
    </li>
    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
      <a class="page-link" href="<?= $createUrl(max(1, $page - 1)) ?>">‹ Prev</a>
    </li>

    <?php for ($p = $start; $p <= $end; $p++): ?>
      <li class="page-item <?= ($p == $page) ? 'active' : '' ?>">
        <a class="page-link" href="<?= $createUrl($p) ?>"><?= $p ?></a>
      </li>
    <?php endfor; ?>

    <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
      <a class="page-link" href="<?= $createUrl(min($totalPages, $page + 1)) ?>">Next ›</a>
    </li>
    <li class="page-item <?= ($page >= $totalPages) ? 'disabled' : '' ?>">
      <a class="page-link" href="<?= $createUrl($totalPages) ?>">Last »</a>
    </li>
  </ul>
</nav>
