<?php
$offer = $offer ?? [];
$rows = $rows ?? [];
$totalCost = $totalCost ?? 0.0;
$totalSale = $totalSale ?? 0.0;
$company = $company ?? [];
$fmtMoney = $fmtMoney ?? fn($v) => number_format((float)$v, 2, '.', '');
$fmtQty = $fmtQty ?? fn($v) => rtrim(rtrim(number_format((float)$v, 3, '.', ''), '0'), '.');

$logo = trim((string)($company['logo_thumb'] ?? $company['logo_url'] ?? ''));
$companyName = trim((string)($company['name'] ?? ''));
?>
<!doctype html>
<html lang="ro">
<head>
  <meta charset="utf-8">
  <title>Bon ofertă <?= htmlspecialchars((string)($offer['code'] ?? '')) ?></title>
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
    .muted { color: #666; }
  </style>
</head>
<body>
  <div class="doc-wrap">
    <div class="doc-header">
      <div>
        <h1 class="doc-title">Bon ofertă</h1>
        <div class="meta">
          <div>Oferta: <strong><?= htmlspecialchars((string)($offer['code'] ?? '')) ?></strong></div>
          <div>Denumire: <?= htmlspecialchars((string)($offer['name'] ?? '')) ?></div>
          <div>Data: <?= htmlspecialchars((string)($offer['created_at'] ?? '')) ?></div>
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
          <th class="text-end">Preț vânzare</th>
          <th class="text-end">Total vânzare</th>
          <th class="text-end">Cost total</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td>
              <strong><?= htmlspecialchars((string)($r['product_name'] ?? '')) ?></strong>
              <?php if (!empty($r['product_desc'])): ?>
                <div class="muted"><?= nl2br(htmlspecialchars((string)$r['product_desc'])) ?></div>
              <?php endif; ?>
            </td>
            <td class="text-end"><?= $fmtQty($r['qty'] ?? 0) ?> <?= htmlspecialchars((string)($r['unit'] ?? 'buc')) ?></td>
            <td class="text-end"><?= $fmtMoney($r['sale_price'] ?? 0) ?></td>
            <td class="text-end"><?= $fmtMoney($r['sale_total'] ?? 0) ?></td>
            <td class="text-end"><?= $fmtMoney($r['cost_total'] ?? 0) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="5" class="muted">Nu există produse.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

    <div class="totals">
      <div class="box">
        <div class="row">
          <div><strong>Cost total</strong></div>
          <div><strong><?= $fmtMoney($totalCost) ?> lei</strong></div>
        </div>
        <div class="row">
          <div>Preț ofertă</div>
          <div><?= $fmtMoney($totalSale) ?> lei</div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>

