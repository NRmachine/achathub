<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Facture professionnelle {{ $order->number }}</title>
    <style>
        body{font-family:Arial,sans-serif;color:#172033;margin:0;background:#f4f5f7}.invoice{max-width:900px;margin:32px auto;background:#fff;padding:42px}.head,.totals div{display:flex;justify-content:space-between;gap:20px}.head{border-bottom:3px solid #efb400;padding-bottom:24px}.brand{font-size:28px;font-weight:800}.pro{color:#956f00}.grid{display:grid;grid-template-columns:1fr 1fr;gap:28px;margin:30px 0}.box{background:#f5f6f8;padding:18px}table{width:100%;border-collapse:collapse}th,td{padding:13px 10px;border-bottom:1px solid #ddd;text-align:left}.right{text-align:right}.totals{max-width:360px;margin:24px 0 0 auto}.totals div{padding:7px}.grand{font-size:20px;border-top:2px solid #172033;margin-top:7px;padding-top:14px!important}.actions{position:fixed;right:22px;bottom:22px}button{background:#172033;color:#fff;border:0;padding:12px 18px;font-weight:700;cursor:pointer}@media(max-width:700px){.invoice{margin:0;padding:22px}.grid{grid-template-columns:1fr}.actions{position:static;margin:20px}}@media print{body{background:#fff}.invoice{margin:0;max-width:none}.actions{display:none}}
    </style>
</head>
<body>
<main class="invoice">
    <div class="head"><div><div class="brand">AchatHub <span class="pro">PRO</span></div><p>Catalogue grossiste et présentoirs professionnels</p></div><div class="right"><h1>Facture</h1><strong>{{ $order->number }}</strong><br>{{ $order->created_at->format('d/m/Y') }}</div></div>
    <div class="grid">
        <section><h2>Facturé à</h2><p><strong>{{ $order->resellerRequest->business_name }}</strong><br>{{ $order->contact_name }}<br>{{ $order->address }}<br>{{ $order->city }}<br>SIRET {{ $order->resellerRequest->siret }}@if($order->resellerRequest->vat_number)<br>TVA {{ $order->resellerRequest->vat_number }}@endif</p></section>
        <section class="box"><div><span>Commande</span><strong>{{ $order->status }}</strong></div><p>Paiement : <strong>{{ $order->payment_status }}</strong><br>Mode : {{ $order->payment_method }}</p></section>
    </div>
    <table><thead><tr><th>Désignation</th><th>Qté</th><th class="right">Prix HT</th><th class="right">TVA</th><th class="right">Total HT</th></tr></thead><tbody>
    @foreach($order->items as $item)<tr><td>{{ $item->name }}</td><td>{{ $item->quantity }}</td><td class="right">{{ number_format($item->price_ht,2,',',' ') }} €</td><td class="right">{{ number_format($item->vat_rate,0) }} %</td><td class="right">{{ number_format($item->price_ht*$item->quantity,2,',',' ') }} €</td></tr>@endforeach
    </tbody></table>
    <div class="totals"><div><span>Sous-total HT</span><strong>{{ number_format($order->subtotal_ht,2,',',' ') }} €</strong></div><div><span>TVA</span><strong>{{ number_format($order->vat_amount,2,',',' ') }} €</strong></div><div class="grand"><strong>Total TTC</strong><strong>{{ number_format($order->total_ttc,2,',',' ') }} €</strong></div></div>
    <p style="margin-top:50px;color:#667085;font-size:13px">Document généré depuis l’espace sécurisé AchatHub Pro. Conservez cette facture avec votre référence de commande.</p>
</main>
<div class="actions"><button onclick="window.print()">Imprimer ou enregistrer en PDF</button></div>
</body>
</html>
