<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contract Flight Print</title>
</head>
<body>
    <script>
        (() => {
            const token = new URLSearchParams(window.location.search).get('print_token') || '';
            const key = `cf-print-preview:${token}`;
            const sheet = token ? localStorage.getItem(key) : null;
            if (sheet) localStorage.removeItem(key);
            if (!sheet) {
                document.body.innerHTML = '<div style="max-width:680px;margin:80px auto;padding:30px;border-radius:8px;background:#fff;text-align:center;box-shadow:0 4px 20px #0000000f;color:#475569;font:14px Arial,sans-serif"><strong style="display:block;margin-bottom:8px;color:#1e293b;font-size:18px">Print statement expired</strong>Return to Contract Flight and click Print again.</div>';
                document.body.style.cssText = 'margin:0;padding:40px;background:#f0f2f5';
                return;
            }
            document.open();
            document.write(sheet);
            document.close();
        })();
    </script>
</body>
</html>
