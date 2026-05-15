<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <title>{{ $project->project_no }} 請款單</title>
    <style>
        @page { size: A4; margin: 14mm 12mm 22mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #fff;
            color: #111827;
            font-family: "Noto Sans CJK TC", "Noto Sans TC", "DejaVu Sans", sans-serif;
            font-size: 13px;
            line-height: 1.5;
        }
        .page { padding: 0; }
        .header {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            border-bottom: 2px solid #111827;
            padding-bottom: 18px;
        }
        .brand { font-size: 24px; font-weight: 700; letter-spacing: 0; }
        .subtitle, .company-meta, .muted { color: #4b5563; }
        .company-meta { margin-top: 8px; font-size: 12px; }
        .doc-title { text-align: right; }
        .doc-title h1 { margin: 0; font-size: 28px; line-height: 1.2; }
        .doc-no { margin-top: 8px; color: #4b5563; }
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
        .box-title { margin-bottom: 10px; font-weight: 700; color: #111827; }
        .info-row { display: flex; gap: 10px; margin-top: 6px; }
        .label { flex: 0 0 72px; color: #6b7280; }
        .value { flex: 1; color: #111827; }
        table { width: 100%; border-collapse: collapse; margin-top: 22px; }
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
        .right { text-align: right; }
        .summary {
            width: 300px;
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
        .note { margin-top: 22px; }
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
                <h1>請款單</h1>
                <div class="doc-no">{{ $project->project_no }}</div>
                <div class="doc-no">開立日期：{{ $issuedAt->toDateString() }}</div>
            </div>
        </header>

        <section class="grid">
            <div class="box">
                <div class="box-title">客戶資料</div>
                <div class="info-row"><div class="label">客戶名稱</div><div class="value">{{ $project->customer?->name ?? '未填' }}</div></div>
                <div class="info-row"><div class="label">電話</div><div class="value">{{ $project->customer?->phone ?? '未填' }}</div></div>
                <div class="info-row"><div class="label">LINE ID</div><div class="value">{{ $project->customer?->line_id ?? '未填' }}</div></div>
                <div class="info-row"><div class="label">統一編號</div><div class="value">{{ $project->customer?->tax_id ?? '未填' }}</div></div>
                <div class="info-row"><div class="label">地址</div><div class="value">{{ $project->customer?->address ?? '未填' }}</div></div>
            </div>
            <div class="box">
                <div class="box-title">工程案件</div>
                <div class="info-row"><div class="label">案號</div><div class="value">{{ $project->project_no }}</div></div>
                <div class="info-row"><div class="label">案件名稱</div><div class="value">{{ $project->name }}</div></div>
                <div class="info-row"><div class="label">工程地址</div><div class="value">{{ $project->address ?? '未填' }}</div></div>
            </div>
        </section>

        <table>
            <thead>
                <tr>
                    <th style="width: 42px;">#</th>
                    <th style="width: 80px;">類型</th>
                    <th>請款項目</th>
                    <th style="width: 86px;">到期日</th>
                    <th style="width: 72px;">狀態</th>
                    <th class="right" style="width: 100px;">金額</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($records as $record)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $types[$record->type] ?? $record->type }}</td>
                        <td>
                            {{ $record->title }}
                            @if ($record->note)
                                <br><span class="muted">{{ $record->note }}</span>
                            @endif
                        </td>
                        <td>{{ $record->due_date?->toDateString() ?? '未填' }}</td>
                        <td>{{ $statuses[$record->status] ?? $record->status }}</td>
                        <td class="right">NT$ {{ number_format($record->amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <section class="summary">
            <div class="summary-row total"><span>請款合計</span><span>NT$ {{ number_format($total) }}</span></div>
        </section>

        <section class="grid">
            <div class="box note">
                <div class="box-title">匯款資訊</div>
                <div class="info-row"><div class="label">銀行</div><div class="value">{{ data_get($settings ?? [], 'payment.bank_name') ?: '未填' }}</div></div>
                <div class="info-row"><div class="label">銀行代碼</div><div class="value">{{ data_get($settings ?? [], 'payment.bank_code') ?: '未填' }}</div></div>
                <div class="info-row"><div class="label">帳號</div><div class="value">{{ data_get($settings ?? [], 'payment.account_number') ?: '未填' }}</div></div>
                <div class="info-row"><div class="label">戶名</div><div class="value">{{ data_get($settings ?? [], 'payment.account_name') ?: '未填' }}</div></div>
            </div>

            <div class="box note">
                <div class="box-title">請款條款</div>
                <div>{!! nl2br(e(data_get($settings ?? [], 'invoice.default_terms') ?: '請依約定付款條件完成付款。')) !!}</div>
            </div>
        </section>

    </main>
</body>
</html>
