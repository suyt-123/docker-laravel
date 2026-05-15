<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <title>{{ $quotation->quotation_no }}</title>
    <style>
        @page {
            size: A4;
            margin: 14mm 12mm 22mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #ffffff;
            color: #111827;
            font-family: "Noto Sans CJK TC", "Noto Sans TC", "DejaVu Sans", sans-serif;
            font-size: 13px;
            line-height: 1.5;
        }

        .page {
            padding: 0;
        }

        .header {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            border-bottom: 2px solid #111827;
            padding-bottom: 18px;
        }

        .brand {
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 0;
        }

        .subtitle {
            margin-top: 4px;
            color: #4b5563;
        }

        .company-meta {
            margin-top: 8px;
            color: #4b5563;
            font-size: 12px;
        }

        .doc-title {
            text-align: right;
        }

        .doc-title h1 {
            margin: 0;
            font-size: 28px;
            line-height: 1.2;
        }

        .doc-no {
            margin-top: 8px;
            color: #4b5563;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin-top: 22px;
        }

        .box {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 14px;
        }

        .box-title {
            margin-bottom: 10px;
            font-weight: 700;
            color: #111827;
        }

        .info-row {
            display: flex;
            gap: 10px;
            margin-top: 6px;
        }

        .label {
            flex: 0 0 72px;
            color: #6b7280;
        }

        .value {
            flex: 1;
            color: #111827;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 22px;
        }

        th {
            background: #f3f4f6;
            border: 1px solid #d1d5db;
            padding: 8px;
            text-align: left;
            font-size: 12px;
        }

        td {
            border: 1px solid #d1d5db;
            padding: 8px;
            vertical-align: top;
        }

        .right {
            text-align: right;
        }

        .summary {
            width: 280px;
            margin-left: auto;
            margin-top: 16px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            border-bottom: 1px solid #e5e7eb;
            padding: 7px 0;
        }

        .summary-row.total {
            border-bottom: 0;
            font-size: 18px;
            font-weight: 700;
        }

        .note {
            margin-top: 22px;
            min-height: 72px;
        }

        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 28px;
            margin-top: 40px;
        }

        .signature-line {
            border-top: 1px solid #6b7280;
            padding-top: 8px;
            color: #4b5563;
        }

    </style>
</head>
<body>
    <main class="page">
        <header class="header">
            <div>
                <div class="brand">{{ data_get($settings ?? [], 'company.name') ?: config('app.name') }}</div>
                <div class="subtitle">鐵皮屋 / 鋼構工程管理系統</div>
                <div class="company-meta">
                    @if (data_get($settings ?? [], 'company.phone'))
                        電話：{{ data_get($settings ?? [], 'company.phone') }}<br>
                    @endif
                    @if (data_get($settings ?? [], 'company.address'))
                        地址：{{ data_get($settings ?? [], 'company.address') }}<br>
                    @endif
                    @if (data_get($settings ?? [], 'company.tax_id'))
                        統一編號：{{ data_get($settings ?? [], 'company.tax_id') }}
                    @endif
                </div>
            </div>
            <div class="doc-title">
                <h1>報價單</h1>
                <div class="doc-no">{{ $quotation->quotation_no }}</div>
            </div>
        </header>

        <section class="grid">
            <div class="box">
                <div class="box-title">客戶資料</div>
                <div class="info-row"><div class="label">客戶名稱</div><div class="value">{{ $quotation->customer?->name ?? '未填' }}</div></div>
                <div class="info-row"><div class="label">電話</div><div class="value">{{ $quotation->customer?->phone ?? '未填' }}</div></div>
                <div class="info-row"><div class="label">LINE ID</div><div class="value">{{ $quotation->customer?->line_id ?? '未填' }}</div></div>
                <div class="info-row"><div class="label">統一編號</div><div class="value">{{ $quotation->customer?->tax_id ?? '未填' }}</div></div>
                <div class="info-row"><div class="label">地址</div><div class="value">{{ $quotation->customer?->address ?? '未填' }}</div></div>
            </div>

            <div class="box">
                <div class="box-title">報價資訊</div>
                <div class="info-row"><div class="label">狀態</div><div class="value">{{ $statuses[$quotation->status] ?? $quotation->status }}</div></div>
                <div class="info-row"><div class="label">有效期限</div><div class="value">{{ $quotation->valid_until?->toDateString() ?? '未填' }}</div></div>
                <div class="info-row"><div class="label">工程案件</div><div class="value">{{ $quotation->project ? $quotation->project->project_no.' · '.$quotation->project->name : '未綁定案件' }}</div></div>
                <div class="info-row"><div class="label">工程地址</div><div class="value">{{ $quotation->project?->address ?? '未填' }}</div></div>
                <div class="info-row"><div class="label">建立人</div><div class="value">{{ $quotation->creator?->name ?? '未填' }}</div></div>
            </div>
        </section>

        <table>
            <thead>
                <tr>
                    <th style="width: 40px;">#</th>
                    <th>項目</th>
                    <th>規格</th>
                    <th class="right" style="width: 72px;">數量</th>
                    <th style="width: 54px;">單位</th>
                    <th class="right" style="width: 90px;">單價</th>
                    <th class="right" style="width: 90px;">小計</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($quotation->items as $item)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>
                            {{ $item->name }}
                            @if ($item->note)
                                <br><span style="color:#6b7280;">{{ $item->note }}</span>
                            @endif
                        </td>
                        <td>{{ $item->spec ?: '未填' }}</td>
                        <td class="right">{{ rtrim(rtrim(number_format((float) $item->quantity, 3, '.', ''), '0'), '.') }}</td>
                        <td>{{ $item->unit }}</td>
                        <td class="right">NT$ {{ number_format($item->unit_price) }}</td>
                        <td class="right">NT$ {{ number_format($item->subtotal) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <section class="summary">
            <div class="summary-row"><span>小計</span><span>NT$ {{ number_format($quotation->subtotal) }}</span></div>
            <div class="summary-row"><span>稅金</span><span>NT$ {{ number_format($quotation->tax) }}</span></div>
            <div class="summary-row"><span>折扣</span><span>NT$ {{ number_format($quotation->discount) }}</span></div>
            <div class="summary-row total"><span>總額</span><span>NT$ {{ number_format($quotation->total) }}</span></div>
        </section>

        <section class="box note">
            <div class="box-title">備註</div>
            <div>{{ $quotation->note ?: '無' }}</div>
        </section>

        @if (filled(data_get($settings ?? [], 'quotation.default_terms')))
            <section class="box note">
                <div class="box-title">報價條款</div>
                <div>{!! nl2br(e(data_get($settings ?? [], 'quotation.default_terms'))) !!}</div>
            </section>
        @endif

        <section class="signatures">
            <div class="signature-line">客戶簽章</div>
            <div class="signature-line">公司簽章</div>
        </section>

    </main>
</body>
</html>
