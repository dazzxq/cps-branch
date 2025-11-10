<?php $env = $env ?? []; ?>
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-900">Đơn hàng</h2>
        <button class="px-4 py-2 rounded-lg bg-gray-900 text-white hover:bg-black text-sm" onclick="UI.Modal.alert('Tạo đơn hàng: TODO UI')">Tạo đơn</button>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Mã đơn</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Khách hàng</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Tổng</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Trạng thái</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Thời gian</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($orders as $o): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm text-gray-900">#<?= (int)$o['id'] ?></td>
                        <td class="px-6 py-4 text-sm text-gray-900"><?= htmlspecialchars($o['order_code'] ?? '') ?></td>
                        <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($o['customer_id'] ?? '') ?></td>
                        <td class="px-6 py-4 text-sm text-gray-900 text-right font-semibold"><?= number_format((int)$o['total'],0,',','.') ?> ₫</td>
                        <td class="px-6 py-4 text-sm text-gray-700"><?= htmlspecialchars($o['status'] ?? '') ?></td>
                        <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($o['created_at'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
