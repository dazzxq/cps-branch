<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($env['APP_NAME'] ?? 'Chillphones Branch') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="max-w-6xl mx-auto p-6">
        <div class="flex items-center justify-between mb-4">
            <h1 class="text-2xl font-bold text-gray-900">POS Catalog (<?= htmlspecialchars($env['APP_BRANCH_CODE'] ?? 'BR') ?>)</h1>
            <a href="/logout" class="text-sm text-gray-700 hover:text-black">Đăng xuất</a>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">SKU</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tên</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Thương hiệu</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Giá</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Tồn</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($products as $p): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-900 font-mono"><?= htmlspecialchars($p['sku']) ?></td>
                            <td class="px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars($p['name']) ?></td>
                            <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($p['brand_name']) ?></td>
                            <td class="px-4 py-3 text-sm text-gray-900 text-right font-semibold"><?= number_format((int)$p['effective_price'],0,',','.') ?> ₫</td>
                            <td class="px-4 py-3 text-sm text-gray-900 text-right"><?= max(0,(int)$p['qty'] - (int)$p['reserved']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
