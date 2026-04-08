<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'مدیریت باشگاه فوتبال' ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Tahoma, Arial, sans-serif; background: #f5f5f5; color: #333; line-height: 1.6; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { background: #2c3e50; color: white; padding: 15px 0; margin-bottom: 20px; }
        .header .container { display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 24px; }
        .nav { display: flex; gap: 20px; }
        .nav a { color: white; text-decoration: none; padding: 8px 15px; border-radius: 4px; transition: background 0.3s; }
        .nav a:hover { background: #34495e; }
        .card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .btn { display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; transition: background 0.3s; }
        .btn:hover { background: #2980b9; }
        .btn-success { background: #27ae60; }
        .btn-success:hover { background: #229954; }
        .btn-danger { background: #e74c3c; }
        .btn-danger:hover { background: #c0392b; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .table th, .table td { padding: 12px; text-align: right; border-bottom: 1px solid #ddd; }
        .table th { background: #f8f9fa; font-weight: bold; }
        .table tr:hover { background: #f8f9fa; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .stat-box { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; text-align: center; }
        .stat-box h3 { font-size: 32px; margin-bottom: 5px; }
        .stat-box p { opacity: 0.9; }
    </style>
</head>
<body>
    <?php if (Auth::check()): ?>
    <div class="header">
        <div class="container">
            <h1>⚽ مدیریت باشگاه</h1>
            <nav class="nav">
                <a href="/dashboard">داشبورد</a>
                <a href="/squad">ترکیب</a>
                <a href="/matches">بازی‌ها</a>
                <a href="/transfers">نقل و انتقالات</a>
                <a href="/competition/1/standings">جدول</a>
                <?php if (in_array(Auth::gameRole(), ['COACH', 'OWNER'], true)): ?>
                    <a href="/ownership/request">مالکیت باشگاه</a>
                <?php endif; ?>
                <?php if (Auth::isAdmin()): ?>
                    <a href="/admin">مدیریت سایت</a>
                <?php endif; ?>
                <a href="/logout">خروج</a>
            </nav>
        </div>
    </div>
    <?php endif; ?>
    <div class="container">
