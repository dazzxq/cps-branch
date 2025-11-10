<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - Chillphones Branch</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-6">
    <div class="bg-white w-full max-w-md rounded-2xl shadow p-8">
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Đăng nhập chi nhánh</h1>
        <?php if (session_status()===PHP_SESSION_NONE) session_start(); if (!empty($_SESSION['flash_error'])): ?>
            <div class="mb-4 text-sm text-white bg-red-500 rounded px-4 py-2"><?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
        <?php endif; ?>
        <form method="POST" action="/login" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                <input name="username" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gray-900">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Mật khẩu</label>
                <input type="password" name="password" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-gray-900">
            </div>
            <button type="submit" class="w-full bg-gray-900 text-white rounded-lg py-2.5 hover:bg-black">Đăng nhập</button>
        </form>
    </div>
</body>
</html>




