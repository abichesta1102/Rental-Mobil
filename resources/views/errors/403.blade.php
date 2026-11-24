<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 - Akses Ditolak | {{ config('app.name', 'Rental Mobil') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Instrument Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            max-width: 540px;
            width: 100%;
            padding: 2.5rem;
            text-align: center;
        }
        .badge {
            display: inline-block;
            background-color: #fee2e2;
            color: #dc2626;
            font-weight: 600;
            font-size: 0.875rem;
            padding: 0.35rem 0.85rem;
            border-radius: 9999px;
            margin-bottom: 1.25rem;
        }
        h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.75rem;
        }
        p {
            color: #64748b;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 1.75rem;
        }
        .message-box {
            background-color: #f1f5f9;
            border-left: 4px solid #ef4444;
            padding: 0.85rem 1rem;
            border-radius: 0.375rem;
            text-align: left;
            font-size: 0.875rem;
            color: #334155;
            margin-bottom: 1.75rem;
            word-break: break-word;
        }
        .actions {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        @media (min-width: 480px) {
            .actions {
                flex-direction: row;
                justify-content: center;
            }
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.625rem 1.25rem;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: 0.5rem;
            text-decoration: none;
            transition: all 0.15s ease-in-out;
            cursor: pointer;
            border: 1px solid transparent;
        }
        .btn-primary {
            background-color: #0f172a;
            color: #ffffff;
        }
        .btn-primary:hover {
            background-color: #1e293b;
        }
        .btn-outline {
            background-color: #ffffff;
            color: #334155;
            border-color: #cbd5e1;
        }
        .btn-outline:hover {
            background-color: #f8fafc;
        }
        .btn-danger {
            background-color: #ef4444;
            color: #ffffff;
        }
        .btn-danger:hover {
            background-color: #dc2626;
        }
    </style>
</head>
<body>
    <div class="card">
        <span class="badge">403 Forbidden</span>
        <h1>Akses Ditolak</h1>
        <p>Anda tidak memiliki hak akses yang diperlukan untuk membuka halaman ini.</p>

        @if($exception && $exception->getMessage())
            <div class="message-box">
                <strong>Keterangan:</strong> {{ $exception->getMessage() }}
            </div>
        @else
            <div class="message-box">
                Halaman ini hanya dapat diakses oleh pengguna dengan peran tertentu (misalnya Administrator).
            </div>
        @endif

        <div class="actions">
            <a href="/dashboard" class="btn btn-primary">Ke Dashboard</a>
            <a href="/vehicles" class="btn btn-outline">Katalog Mobil</a>
            <form method="POST" action="/logout" style="display: inline;">
                @csrf
                <button type="submit" class="btn btn-danger">Keluar / Ganti Akun</button>
            </form>
        </div>
    </div>
</body>
</html>
