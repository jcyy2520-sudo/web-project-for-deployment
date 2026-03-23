<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Receipt - Legal Ease</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #1f2937; margin: 0; background-color: #f9fafb; }
        .container { max-width: 500px; margin: 0 auto; padding: 20px; }
        .header { background: white; padding: 24px 20px; text-align: center; border-bottom: 2px solid #e5e7eb; }
        .header h1 { margin: 0; font-size: 24px; font-weight: 700; color: #111827; }
        .header p { margin: 4px 0 0; font-size: 13px; color: #6b7280; }
        .content { background: white; padding: 32px 20px; margin-top: 0; }
        .text { font-size: 14px; color: #4b5563; margin: 12px 0; line-height: 1.6; }
        .receipt-box { background: #f9fafb; border: 1px solid #d1d5db; padding: 16px; margin: 20px 0; border-radius: 4px; }
        .receipt-title { font-weight: 700; font-size: 15px; color: #111827; margin-bottom: 12px; text-align: center; text-transform: uppercase; letter-spacing: 0.5px; }
        .detail-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 13px; border-bottom: 1px solid #e5e7eb; }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { font-weight: 600; color: #4b5563; }
        .detail-value { color: #1f2937; text-align: right; }
        .total-row { display: flex; justify-content: space-between; padding: 10px 0 6px; font-size: 15px; font-weight: 700; border-top: 2px solid #111827; margin-top: 8px; }
        .total-label { color: #111827; }
        .total-value { color: #111827; }
        .balance-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 13px; color: #b45309; font-weight: 600; }
        .hash-box { background: #f3f4f6; border: 1px solid #e5e7eb; padding: 10px; margin: 16px 0; border-radius: 4px; text-align: center; }
        .hash-label { font-size: 10px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.5px; }
        .hash-value { font-family: 'Courier New', monospace; font-size: 11px; color: #6b7280; word-break: break-all; margin-top: 2px; }
        .footer { font-size: 12px; color: #6b7280; text-align: center; margin-top: 24px; padding-top: 20px; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Legal Ease</h1>
            <p>Official Receipt</p>
        </div>

        <div class="content">
            <p class="text">Hello {{ $receipt['client_name'] }},</p>
            <p class="text">Thank you for your payment. Below is your official receipt.</p>

            <div class="receipt-box">
                <div class="receipt-title">Receipt {{ $receipt['receipt_id'] }}</div>

                <div class="detail-row">
                    <span class="detail-label">Date:</span>
                    <span class="detail-value">{{ \Carbon\Carbon::parse($receipt['date'])->format('F d, Y g:i A') }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Client:</span>
                    <span class="detail-value">{{ $receipt['client_name'] }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Service:</span>
                    <span class="detail-value">{{ $receipt['service'] }}</span>
                </div>
                @if(!empty($receipt['appointment_date']))
                <div class="detail-row">
                    <span class="detail-label">Appointment:</span>
                    <span class="detail-value">{{ \Carbon\Carbon::parse($receipt['appointment_date'])->format('F d, Y') }}</span>
                </div>
                @endif
                <div class="detail-row">
                    <span class="detail-label">Service Price:</span>
                    <span class="detail-value">₱{{ number_format($receipt['service_price'], 2) }}</span>
                </div>
                @if($receipt['discount'] > 0)
                <div class="detail-row">
                    <span class="detail-label">Discount ({{ $receipt['discount_type'] }}):</span>
                    <span class="detail-value" style="color: #16a34a;">-₱{{ number_format($receipt['discount'], 2) }}</span>
                </div>
                @endif
                <div class="detail-row">
                    <span class="detail-label">Payment Type:</span>
                    <span class="detail-value" style="text-transform: capitalize;">{{ $receipt['payment_type'] }}</span>
                </div>
                @if(!empty($receipt['in_kind_description']))
                <div class="detail-row">
                    <span class="detail-label">In-kind Items:</span>
                    <span class="detail-value">{{ $receipt['in_kind_description'] }}</span>
                </div>
                @endif
                @if(!empty($receipt['in_kind_estimated_value']))
                <div class="detail-row">
                    <span class="detail-label">Estimated Value:</span>
                    <span class="detail-value">₱{{ number_format($receipt['in_kind_estimated_value'], 2) }}</span>
                </div>
                @endif
                <div class="total-row">
                    <span class="total-label">Total Paid:</span>
                    <span class="total-value">₱{{ number_format($receipt['total_paid'], 2) }}</span>
                </div>
                @if($receipt['balance_remaining'] > 0)
                <div class="balance-row">
                    <span>Balance Remaining:</span>
                    <span>₱{{ number_format($receipt['balance_remaining'], 2) }}</span>
                </div>
                @endif
            </div>

            <div class="hash-box">
                <div class="hash-label">Verification Code</div>
                <div class="hash-value">{{ $receipt['integrity_hash'] }}</div>
            </div>

            <p class="text">If you have any questions about this receipt, please don't hesitate to contact us.</p>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} Legal Ease. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
