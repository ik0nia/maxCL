<?php
$offer = $offer ?? [];
$rows = $rows ?? [];
$totalCost = $totalCost ?? 0.0;
$totalSale = $totalSale ?? 0.0;
$company = $company ?? [];
$client = $client ?? null;
$fmtMoney = $fmtMoney ?? fn($v) => number_format((float)$v, 2, '.', '');
$fmtQty = $fmtQty ?? fn($v) => rtrim(rtrim(number_format((float)$v, 3, '.', ''), '0'), '.');
$validityDays = (int)($offer['validity_days'] ?? 0);
if ($validityDays <= 0) $validityDays = 14;

$logo = trim((string)($company['logo_thumb'] ?? $company['logo_url'] ?? ''));
$companyName = trim((string)($company['name'] ?? ''));
if ($companyName === '') $companyName = 'HPL Manager';
$createdAt = trim((string)($offer['created_at'] ?? ''));
$dateOnly = $createdAt;
if (preg_match('/^\d{4}-\d{2}-\d{2}/', $createdAt, $m)) {
  $dateOnly = $m[0];
}
?>
<!doctype html>
<html lang="ro">
<head>
  <meta charset="utf-8">
  <title>Ofertă <?= htmlspecialchars((string)($offer['code'] ?? '')) ?></title>
  <style>
    body { font-family: Inter, Arial, sans-serif; font-size: 13px; color: #111; }
    .doc-wrap { max-width: 980px; margin: 20px auto; }
    .doc-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
    .doc-title { font-size: 20px; font-weight: 700; margin: 0; }
    .meta { margin-top: 8px; color: #444; }
    .meta div { margin-bottom: 2px; }
    .company { font-size: 12px; color: #333; }
    .company strong { display: block; font-size: 14px; }
    table { width: 100%; border-collapse: collapse; margin-top: 16px; }
    th, td { border: 1px solid #ddd; padding: 8px; vertical-align: top; }
    th { background: #f7f7f7; text-align: left; }
    td.text-end, th.text-end { text-align: right; }
    .totals { margin-top: 16px; display: flex; justify-content: flex-end; }
    .totals .box { min-width: 320px; border: 1px solid #ddd; padding: 12px; }
    .totals .row { display: flex; justify-content: space-between; margin-bottom: 6px; }
    .strike { text-decoration: line-through; color: #666; }
    .muted { color: #666; }
  </style>
</head>
<body>
  <div class="doc-wrap">
    <div class="doc-header">
      <div>
        <h1 class="doc-title">Ofertă #<?= htmlspecialchars((string)($offer['code'] ?? '')) ?></h1>
        <div class="meta">
          <div>Denumire: <?= htmlspecialchars((string)($offer['name'] ?? '')) ?></div>
          <div>Data ofertă: <?= htmlspecialchars($dateOnly) ?></div>
          <?php if ($client && !empty($client['name'])): ?>
            <div style="margin-top:6px"><strong>Date facturare client</strong></div>
            <div><?= htmlspecialchars((string)($client['name'] ?? '')) ?></div>
            <?php if (!empty($client['cui'])): ?><div>CUI: <?= htmlspecialchars((string)$client['cui']) ?></div><?php endif; ?>
            <?php if (!empty($client['contact_person'])): ?><div>Contact: <?= htmlspecialchars((string)$client['contact_person']) ?></div><?php endif; ?>
            <?php if (!empty($client['phone'])): ?><div>Telefon: <?= htmlspecialchars((string)$client['phone']) ?></div><?php endif; ?>
            <?php if (!empty($client['email'])): ?><div>Email: <?= htmlspecialchars((string)$client['email']) ?></div><?php endif; ?>
            <?php if (!empty($client['address'])): ?><div>Adresă: <?= nl2br(htmlspecialchars((string)$client['address'])) ?></div><?php endif; ?>
          <?php else: ?>
            <div style="margin-top:6px"><strong>Date facturare client</strong></div>
            <div class="muted">—</div>
          <?php endif; ?>
        </div>
      </div>
      <div class="company">
        <?php if ($logo !== ''): ?>
          <div><img src="<?= htmlspecialchars($logo) ?>" alt="<?= htmlspecialchars($companyName) ?>" style="height:40px;width:auto"></div>
        <?php endif; ?>
        <strong><?= htmlspecialchars($companyName) ?></strong>
        <?php if (!empty($company['cui'])): ?><div>CUI: <?= htmlspecialchars((string)$company['cui']) ?></div><?php endif; ?>
        <?php if (!empty($company['reg'])): ?><div>Reg. Com.: <?= htmlspecialchars((string)$company['reg']) ?></div><?php endif; ?>
        <?php if (!empty($company['address'])): ?><div><?= htmlspecialchars((string)$company['address']) ?></div><?php endif; ?>
        <?php if (!empty($company['phone'])): ?><div>Tel: <?= htmlspecialchars((string)$company['phone']) ?></div><?php endif; ?>
        <?php if (!empty($company['email'])): ?><div>Email: <?= htmlspecialchars((string)$company['email']) ?></div><?php endif; ?>
      </div>
    </div>

    <table>
      <thead>
        <tr>
          <th>Produs</th>
          <th class="text-end">Cantitate</th>
          <th class="text-end">Preț ofertă</th>
          <th class="text-end">Total ofertă</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <?php
            $qty = (float)($r['qty'] ?? 0);
            $listTotal = (float)($r['cost_total'] ?? 0);
            $offerTotal = (float)($r['sale_total'] ?? 0);
            $listUnit = $qty > 0 ? ($listTotal / $qty) : 0.0;
            $offerUnit = (float)($r['sale_price'] ?? 0);
            $discTotal = $listTotal - $offerTotal;
            $discPct = ($listTotal > 0) ? (($discTotal / $listTotal) * 100.0) : 0.0;
          ?>
          <tr>
            <td>
              <strong><?= htmlspecialchars((string)($r['product_name'] ?? '')) ?></strong>
              <?php if (!empty($r['product_desc'])): ?>
                <div class="muted"><?= nl2br(htmlspecialchars((string)$r['product_desc'])) ?></div>
              <?php endif; ?>
            </td>
            <td class="text-end"><?= $fmtQty($r['qty'] ?? 0) ?> <?= htmlspecialchars((string)($r['unit'] ?? 'buc')) ?></td>
            <td class="text-end">
              <?php if ($listUnit > 0): ?>
                <div class="strike"><?= $fmtMoney($listUnit) ?></div>
              <?php endif; ?>
              <div><?= $fmtMoney($offerUnit) ?></div>
            </td>
            <td class="text-end">
              <?php if ($listTotal > 0): ?>
                <div class="strike"><?= $fmtMoney($listTotal) ?></div>
              <?php endif; ?>
              <div><?= $fmtMoney($offerTotal) ?></div>
              <?php if ($discTotal > 0.01): ?>
                <div class="muted small">Discount: -<?= $fmtMoney($discTotal) ?> (<?= number_format($discPct, 1, '.', '') ?>%)</div>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="4" class="muted">Nu există produse.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

    <div class="totals">
      <div class="box">
        <?php
          $discTotalAll = (float)$totalCost - (float)$totalSale;
          $discPctAll = ($totalCost > 0) ? (($discTotalAll / (float)$totalCost) * 100.0) : 0.0;
        ?>
        <div class="row">
          <div><strong>Preț de listă</strong></div>
          <div><strong><span class="strike"><?= $fmtMoney($totalCost) ?> lei</span></strong></div>
        </div>
        <div class="row">
          <div>Preț ofertă</div>
          <div><?= $fmtMoney($totalSale) ?> lei</div>
        </div>
        <?php if ($discTotalAll > 0.01): ?>
          <div class="row">
            <div>Discount</div>
            <div>-<?= $fmtMoney($discTotalAll) ?> lei (<?= number_format($discPctAll, 1, '.', '') ?>%)</div>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <div class="muted" style="margin-top:12px">
      Aceasta ofertă este valabilă <?= (int)$validityDays ?> zile de la data emiterii.
    </div>
  </div>
</body>
</html>

