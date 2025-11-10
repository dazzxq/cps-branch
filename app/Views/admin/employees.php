<?php $env = $env ?? []; ?>
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-900">Nhân viên (replica)</h2>
        <button class="px-4 py-2 rounded-lg bg-gray-900 text-white hover:bg-black text-sm" onclick="UI.Modal.alert('Thêm nhân viên: quản lý tại Central')">Thêm</button>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tên</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vai trò</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Kích hoạt</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cập nhật</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($emps as $e): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm text-gray-900">#<?= (int)$e['id'] ?></td>
                        <td class="px-6 py-4 text-sm text-gray-900"><?= htmlspecialchars($e['name']) ?></td>
                        <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($e['email']) ?></td>
                        <td class="px-6 py-4 text-sm text-gray-700"><?= htmlspecialchars($e['role']) ?></td>
                        <td class="px-6 py-4 text-sm text-gray-700"><?= ((int)$e['enabled'])? 'Enabled':'Disabled' ?></td>
                        <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($e['updated_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
