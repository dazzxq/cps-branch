<?php
$stats = isset($this) ? $this->getStats() : ['inventory' => [], 'recent_orders' => [], 'outbox_pending' => 0];
?>

<div class="max-w-7xl mx-auto">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">
            <span class="text-[#d0011c]">🎓</span> ACID + Transaction + Stored Procedures Demo
        </h1>
        <p class="text-gray-600">Interactive demo cho giảng viên - Hệ thống Chillphones Branch <?= htmlspecialchars($env['APP_BRANCH_CODE'] ?? 'HN') ?></p>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-medium text-gray-500">Inventory Items</h3>
                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                </svg>
            </div>
            <p class="text-3xl font-bold text-gray-900"><?= count($stats['inventory']) ?></p>
            <p class="text-xs text-gray-500 mt-1">products in stock</p>
        </div>
        
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-medium text-gray-500">Demo Orders</h3>
                <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
            </div>
            <p class="text-3xl font-bold text-gray-900"><?= count($stats['recent_orders']) ?></p>
            <p class="text-xs text-gray-500 mt-1">recent test orders</p>
        </div>
        
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-sm font-medium text-gray-500">Outbox Pending</h3>
                <svg class="w-5 h-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <p class="text-3xl font-bold text-gray-900"><?= $stats['outbox_pending'] ?></p>
            <p class="text-xs text-gray-500 mt-1">events waiting sync</p>
        </div>
    </div>

    <!-- Demo Sections -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- DEMO 1: ATOMICITY -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="border-b border-gray-200 bg-gradient-to-r from-red-50 to-white px-6 py-4">
                <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                    <span class="text-2xl">A</span>
                    <span>Atomicity Test</span>
                </h2>
                <p class="text-sm text-gray-600 mt-1">"Tất cả hoặc không có gì" - Transaction rollback khi lỗi</p>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Product</label>
                        <select id="atomicity-product" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#d0011c]">
                            <?php if (!empty($products)): ?>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?= $product['id'] ?>">
                                        <?= htmlspecialchars($product['name']) ?> 
                                        (ID: <?= $product['id'] ?>, Stock: <?= $product['stock'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="">No products available</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Quantity (đặt số lớn để test rollback)</label>
                        <input type="number" id="atomicity-qty" value="999" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#d0011c]">
                    </div>
                    <button onclick="testAtomicity()" class="w-full px-4 py-3 bg-[#d0011c] text-white rounded-lg hover:bg-red-700 font-semibold">
                        🧪 Run Atomicity Test
                    </button>
                </div>
                <div id="atomicity-result" class="mt-4 hidden">
                    <!-- Result sẽ hiển thị ở đây -->
                </div>
            </div>
        </div>

        <!-- DEMO 2: ISOLATION -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="border-b border-gray-200 bg-gradient-to-r from-blue-50 to-white px-6 py-4">
                <h2 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                    <span class="text-2xl">I</span>
                    <span>Isolation Demo</span>
                </h2>
                <p class="text-sm text-gray-600 mt-1">"FOR UPDATE" ngăn race condition/oversell</p>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Product</label>
                        <select id="isolation-product" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#d0011c]">
                            <?php if (!empty($products)): ?>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?= $product['id'] ?>">
                                        <?= htmlspecialchars($product['name']) ?> 
                                        (ID: <?= $product['id'] ?>, Stock: <?= $product['stock'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="">No products available</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <button onclick="testIsolation()" class="w-full px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold">
                        🔒 View Isolation SQL
                    </button>
                </div>
                <div id="isolation-result" class="mt-4 hidden">
                    <!-- Result sẽ hiển thị ở đây -->
                </div>
            </div>
        </div>
    </div>

    <!-- Current Inventory -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8">
        <div class="border-b border-gray-200 px-6 py-4 flex items-center justify-between">
            <h2 class="text-lg font-bold text-gray-900">📦 Current Inventory</h2>
            <button onclick="refreshStats()" class="text-sm px-3 py-1 bg-gray-100 hover:bg-gray-200 rounded-lg">
                <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                Refresh
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stock</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Updated</th>
                    </tr>
                </thead>
                <tbody id="inventory-table" class="divide-y divide-gray-200">
                    <?php foreach ($stats['inventory'] as $item): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm text-gray-900 font-mono">#<?= $item['product_id'] ?></td>
                        <td class="px-6 py-4 text-sm text-gray-900"><?= htmlspecialchars($item['product_name']) ?></td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $item['qty'] > 5 ? 'bg-green-100 text-green-800' : ($item['qty'] > 0 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                                <?= $item['qty'] ?> units
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500"><?= $item['updated_at'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Demo Orders -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="border-b border-gray-200 px-6 py-4 flex items-center justify-between">
            <h2 class="text-lg font-bold text-gray-900">📋 Recent Demo Orders</h2>
            <button onclick="resetData()" class="text-sm px-3 py-1 bg-red-100 hover:bg-red-200 text-red-700 rounded-lg font-medium">
                🗑️ Reset Data
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order Code</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                    </tr>
                </thead>
                <tbody id="orders-table" class="divide-y divide-gray-200">
                    <?php if (empty($stats['recent_orders'])): ?>
                    <tr>
                        <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500">
                            Chưa có demo order. Chạy test để tạo order mẫu.
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($stats['recent_orders'] as $order): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm font-mono text-gray-900"><?= htmlspecialchars($order['order_code']) ?></td>
                            <td class="px-6 py-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $order['status'] === 'CONFIRMED' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                    <?= htmlspecialchars($order['status']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900"><?= number_format($order['total']) ?> ₫</td>
                            <td class="px-6 py-4 text-sm text-gray-500"><?= $order['created_at'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Result Modal -->
<div id="result-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden flex flex-col">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-gray-50">
            <h3 id="modal-title" class="text-lg font-bold text-gray-900">Test Result</h3>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <div id="modal-content" class="px-6 py-4 overflow-y-auto flex-1">
            <!-- Content will be inserted here -->
        </div>
        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex justify-end gap-3">
            <button onclick="closeModal()" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-lg font-medium">Close</button>
        </div>
    </div>
</div>

<script>
// API helper
async function apiCall(endpoint, data = {}) {
    const response = await fetch(endpoint, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    });
    return await response.json();
}

// Test Atomicity
async function testAtomicity() {
    const productId = document.getElementById('atomicity-product').value;
    const qty = document.getElementById('atomicity-qty').value;
    
    showLoading('Testing Atomicity...');
    
    try {
        const result = await apiCall('/acid-demo/test-atomicity', {
            product_id: productId,
            qty: qty
        });
        
        showResult('Atomicity Test Result', renderAtomicityResult(result));
        refreshStats();
    } catch (error) {
        showResult('Error', `<div class="text-red-600">${error.message}</div>`);
    }
}

function renderAtomicityResult(result) {
    const statusColor = result.success ? 'green' : 'red';
    const statusIcon = result.success ? '✅' : '❌';
    
    return `
        <div class="space-y-6">
            <!-- Status -->
            <div class="p-4 rounded-lg bg-${statusColor}-50 border border-${statusColor}-200">
                <div class="flex items-center gap-2 text-${statusColor}-800 font-bold text-lg mb-2">
                    <span>${statusIcon}</span>
                    <span>${result.status}</span>
                </div>
                <p class="text-${statusColor}-700">${result.message}</p>
            </div>
            
            <!-- Stock Comparison -->
            <div class="grid grid-cols-2 gap-4">
                <div class="p-4 bg-blue-50 rounded-lg">
                    <div class="text-sm text-blue-600 mb-1">Stock BEFORE</div>
                    <div class="text-3xl font-bold text-blue-900">${result.stock_before}</div>
                </div>
                <div class="p-4 bg-${result.stock_before === result.stock_after ? 'yellow' : 'green'}-50 rounded-lg">
                    <div class="text-sm text-${result.stock_before === result.stock_after ? 'yellow' : 'green'}-600 mb-1">Stock AFTER</div>
                    <div class="text-3xl font-bold text-${result.stock_before === result.stock_after ? 'yellow' : 'green'}-900">${result.stock_after}</div>
                </div>
            </div>
            
            <!-- Explanation -->
            <div class="p-4 bg-purple-50 border border-purple-200 rounded-lg">
                <div class="font-bold text-purple-900 mb-2">💡 Giải thích:</div>
                <p class="text-purple-800">${result.explanation}</p>
            </div>
            
            <!-- SQL Executed -->
            <div class="p-4 bg-gray-900 rounded-lg text-gray-100">
                <div class="font-bold text-green-400 mb-3 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    SQL được thực thi:
                </div>
                <pre class="text-sm overflow-x-auto">${result.sql_executed.join('\n')}</pre>
            </div>
            
            ${result.order_code ? `
            <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
                <div class="text-sm text-green-600 mb-1">Order Code:</div>
                <div class="font-mono font-bold text-green-900">${result.order_code}</div>
            </div>
            ` : ''}
        </div>
    `;
}

// Test Isolation
async function testIsolation() {
    const productId = document.getElementById('isolation-product').value;
    
    showLoading('Loading Isolation Demo...');
    
    try {
        const result = await apiCall('/acid-demo/test-isolation', {
            product_id: productId
        });
        
        showResult('Isolation Demo - FOR UPDATE', renderIsolationResult(result));
    } catch (error) {
        showResult('Error', `<div class="text-red-600">${error.message}</div>`);
    }
}

function renderIsolationResult(result) {
    return `
        <div class="space-y-6">
            <!-- Current Stock -->
            <div class="p-4 bg-blue-50 rounded-lg">
                <div class="text-sm text-blue-600 mb-1">Current Stock cho Product #${result.product_id}:</div>
                <div class="text-3xl font-bold text-blue-900">${result.current_stock} units</div>
            </div>
            
            <!-- Explanation -->
            <div class="p-4 bg-purple-50 border border-purple-200 rounded-lg">
                <div class="font-bold text-purple-900 mb-2">💡 Giải thích:</div>
                <p class="text-purple-800">${result.explanation}</p>
            </div>
            
            <!-- SQL Demo -->
            <div class="p-4 bg-gray-900 rounded-lg text-gray-100">
                <div class="font-bold text-green-400 mb-3 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    Mô phỏng 2 Transaction chạy song song:
                </div>
                <pre class="text-sm overflow-x-auto">${result.sql_executed.join('\n')}</pre>
            </div>
            
            <!-- Test Instructions -->
            <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                <div class="font-bold text-yellow-900 mb-2">🧪 Cách test thực tế:</div>
                <ol class="list-decimal list-inside space-y-2 text-yellow-800 text-sm">
                    <li>Mở 2 terminal MySQL</li>
                    <li>Terminal 1: Chạy transaction với FOR UPDATE + SLEEP(10)</li>
                    <li>Terminal 2: Chạy ngay sau, sẽ bị BLOCK</li>
                    <li>Sau 10s, T1 commit → T2 mới chạy tiếp</li>
                    <li>Nếu stock = 1, chỉ T1 thành công, T2 fail</li>
                </ol>
            </div>
        </div>
    `;
}

// Reset Data
async function resetData() {
    if (!confirm('Reset all demo data? This will clear demo orders and reset inventory.')) return;
    
    showLoading('Resetting data...');
    
    try {
        const result = await apiCall('/acid-demo/reset-data');
        showResult('Reset Complete', `
            <div class="space-y-4">
                <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
                    <div class="text-green-800 font-bold">✅ ${result.message}</div>
                </div>
                <div class="p-4 bg-gray-50 rounded-lg">
                    <div class="font-bold text-gray-900 mb-2">Inventory Reset:</div>
                    <pre class="text-sm">${JSON.stringify(result.inventory_reset, null, 2)}</pre>
                </div>
            </div>
        `);
        refreshStats();
    } catch (error) {
        showResult('Error', `<div class="text-red-600">${error.message}</div>`);
    }
}

// Refresh Stats
async function refreshStats() {
    try {
        const response = await fetch('/acid-demo/stats');
        const stats = await response.json();
        
        // Update inventory table
        const inventoryTable = document.getElementById('inventory-table');
        inventoryTable.innerHTML = stats.inventory.map(item => {
            const stockClass = item.qty > 5 ? 'bg-green-100 text-green-800' : (item.qty > 0 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800');
            return `
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm text-gray-900 font-mono">#${item.product_id}</td>
                    <td class="px-6 py-4 text-sm text-gray-900">${item.product_name}</td>
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${stockClass}">
                            ${item.qty} units
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">${item.updated_at}</td>
                </tr>
            `;
        }).join('');
        
        // Update orders table
        const ordersTable = document.getElementById('orders-table');
        if (stats.recent_orders.length === 0) {
            ordersTable.innerHTML = '<tr><td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500">Chưa có demo order.</td></tr>';
        } else {
            ordersTable.innerHTML = stats.recent_orders.map(order => {
                const statusClass = order.status === 'CONFIRMED' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800';
                return `
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm font-mono text-gray-900">${order.order_code}</td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusClass}">
                                ${order.status}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">${parseInt(order.total).toLocaleString('vi-VN')} ₫</td>
                        <td class="px-6 py-4 text-sm text-gray-500">${order.created_at}</td>
                    </tr>
                `;
            }).join('');
        }
    } catch (error) {
        console.error('Failed to refresh stats:', error);
    }
}

// Modal helpers
function showLoading(message) {
    showResult('Loading...', `<div class="text-center py-8"><div class="animate-spin inline-block w-8 h-8 border-4 border-gray-300 border-t-[#d0011c] rounded-full"></div><p class="mt-4 text-gray-600">${message}</p></div>`);
}

function showResult(title, content) {
    document.getElementById('modal-title').textContent = title;
    document.getElementById('modal-content').innerHTML = content;
    document.getElementById('result-modal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('result-modal').classList.add('hidden');
}

// Auto refresh stats every 10s
setInterval(refreshStats, 10000);
</script>

