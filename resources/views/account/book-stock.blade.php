<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Book Stock</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 0; background: #f3f6fb; color: #1f2937; }
        .wrap { max-width: 600px; margin: 12px auto; background: #fff; border: 1px solid #d7dfeb; border-radius: 10px; padding: 14px; }
        h1 { margin: 0 0 10px; font-size: 24px; color: #1f3f66; }
        .status { margin: 8px 0; padding: 8px 10px; border-radius: 7px; font-size: 12px; }
        .status.ok { border: 1px solid #9fd5af; background: #e6f6ec; color: #12522d; }
        .status.err { border: 1px solid #f2b1b1; background: #fdeaea; color: #8e1f1f; }
        .sec { border: 1px solid #d8e2ef; border-radius: 8px; padding: 10px; margin-bottom: 10px; background: #f8fbff; }
        .sec h3 { margin: 0 0 8px; font-size: 14px; color: #2d4f74; }
        .grid { display: grid; grid-template-columns: 130px 1fr; gap: 10px 12px; align-items: center; }
        label { font-size: 12px; font-weight: 700; color: #375b84; }
        input { height: 32px; border: 1px solid #bfd0e6; border-radius: 6px; padding: 0 8px; font-size: 12px; width: 100%; box-sizing: border-box; }
        .toolbar { display: flex; gap: 8px; flex-wrap: wrap; padding-top: 6px; }
        button { height: 34px; border: 1px solid #2a6398; border-radius: 7px; background: #e8f2ff; color: #17456e; padding: 0 12px; font-size: 12px; font-weight: 700; cursor: pointer; }
        button.primary { border-color: #2a7a42; background: #e6f8ec; color: #1b5b31; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Book Stock</h1>

    @if(!empty($message))
        <div class="status {{ $messageType === 'error' ? 'err' : 'ok' }}">{{ $message }}</div>
    @endif

    <form method="POST" action="{{ url()->current() }}">
        @csrf

        <div class="sec">
            <h3>Stock &amp; Cash Values</h3>
            <div class="grid">
                <label for="stock_value">Stock Value</label>
                <input id="stock_value" name="stock_value" type="number" step="0.01" value="{{ old('stock_value', $stockValue ?? 0) }}">

                <label for="cash_value">Cash Value</label>
                <input id="cash_value" name="cash_value" type="number" step="0.01" value="{{ old('cash_value', $cashValue ?? 0) }}">
            </div>
        </div>

        <div class="toolbar">
            <button type="submit" class="primary">Save (F9)</button>
            <button type="button" id="btnExit">Exit (Esc)</button>
        </div>
    </form>
</div>

<script>
(() => {
    document.getElementById('btnExit').addEventListener('click', () => {
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: 'goldapp:close-module-frame' }, '*');
        } else { window.close(); }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'F9') {
            e.preventDefault();
            document.querySelector('form').submit();
        } else if (e.key === 'Escape') {
            e.preventDefault();
            document.getElementById('btnExit').click();
        }
    });
})();
</script>
</body>
</html>
