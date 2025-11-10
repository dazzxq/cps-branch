<?php $env = $env ?? []; ?>
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-gray-900">Sản phẩm</h2>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">SKU</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tên</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Thương hiệu</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Giá</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Tồn</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Thao tác</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($list as $p): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm text-gray-900 font-mono"><?= htmlspecialchars($p['sku']) ?></td>
                        <td class="px-6 py-4 text-sm text-gray-900"><?= htmlspecialchars($p['name']) ?></td>
                        <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($p['brand_name']) ?></td>
                        <td class="px-6 py-4 text-sm text-right">
                            <?php if (!empty($p['promo_price']) && $p['promo_price'] < $p['price']): ?>
                                <!-- Has promo price: show promo in red + original price strikethrough -->
                                <div class="text-[#d0011c] font-bold"><?= number_format((int)$p['promo_price'],0,',','.') ?> ₫</div>
                                <div class="text-xs text-gray-400 line-through"><?= number_format((int)$p['price'],0,',','.') ?> ₫</div>
                            <?php else: ?>
                                <!-- No promo: show regular price -->
                                <div class="text-gray-900 font-semibold"><?= number_format((int)$p['price'],0,',','.') ?> ₫</div>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900 text-right"><?= max(0,(int)$p['qty'] - (int)$p['reserved']) ?></td>
                        <td class="px-6 py-4 text-sm text-right whitespace-nowrap">
                            <button class="text-gray-800 hover:opacity-80 mr-4" onclick="openPrice(<?= (int)$p['product_id'] ?>, '<?= htmlspecialchars($p['sku']) ?>')">Giá bán</button>
                            <button class="text-gray-800 hover:opacity-80" onclick="openStock(<?= (int)$p['product_id'] ?>, '<?= htmlspecialchars($p['sku']) ?>', <?= (int)$p['qty'] ?>, <?= (int)$p['reserved'] ?>)">Tồn kho</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
function openPrice(id, sku){
    UI.Modal.open({
        html: `
            <h3 class="text-lg font-semibold mb-2">Cập nhật giá bán - ${sku}</h3>
            <div class="space-y-3">
                <div>
                    <label class="block text-sm text-gray-700 mb-1">Giá</label>
                    <input id="price" type="number" class="w-full px-3 py-2 border rounded-lg" placeholder="VD: 19990000">
                </div>
                <div>
                    <label class="block text-sm text-gray-700 mb-1">Giá khuyến mãi</label>
                    <input id="promo_price" type="number" class="w-full px-3 py-2 border rounded-lg" placeholder="VD: 18990000">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm text-gray-700 mb-1">Bắt đầu</label>
                        <input id="starts_at" type="datetime-local" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-700 mb-1">Kết thúc</label>
                        <input id="ends_at" type="datetime-local" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                </div>
            </div>
        `,
        actions: [
            { text: 'Hủy', variant: 'secondary' },
            { text: 'Lưu', variant: 'primary', onClick: async () => {
                const body = {
                    price: document.getElementById('price').value,
                    promo_price: document.getElementById('promo_price').value,
                    starts_at: document.getElementById('starts_at').value,
                    ends_at: document.getElementById('ends_at').value
                };
                try { await App.api(`/products/${id}/override`, { method:'POST', body: JSON.stringify(body) }); UI.Modal.alert('Cập nhật giá bán thành công', 'Thành công'); } catch(e){ UI.Modal.alert(e.message||'Lỗi cập nhật giá'); }
            }}
        ]
    });
}
function openStock(id, sku, qty, reserved){
    UI.Modal.open({
        html: `
            <h3 class="text-lg font-semibold mb-2">Cập nhật tồn kho - ${sku}</h3>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm text-gray-700 mb-1">Số lượng</label>
                    <input id="qty" type="number" class="w-full px-3 py-2 border rounded-lg" value="${qty}">
                </div>
                <div>
                    <label class="block text-sm text-gray-700 mb-1">Giữ chỗ</label>
                    <input id="reserved" type="number" class="w-full px-3 py-2 border rounded-lg" value="${reserved}">
                </div>
            </div>
        `,
        actions: [
            { text: 'Hủy', variant: 'secondary' },
            { text: 'Lưu', variant: 'primary', onClick: async () => {
                const body = { qty: document.getElementById('qty').value, reserved: document.getElementById('reserved').value };
                try { await App.api(`/products/${id}/stock`, { method:'POST', body: JSON.stringify(body) }); UI.Modal.alert('Cập nhật tồn kho thành công', 'Thành công'); } catch(e){ UI.Modal.alert(e.message||'Lỗi cập nhật tồn kho'); }
            }}
        ]
    });
}
</script>
