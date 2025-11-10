<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($env['APP_NAME'] ?? 'Chillphones Branch') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <header class="fixed top-0 left-0 right-0 bg-white border-b border-gray-200 z-40">
        <div class="flex items-center justify-between px-6 py-4">
            <div class="flex items-center gap-4">
                <h1 class="text-xl font-bold text-gray-900"><span class="text-[#d0011c]">●</span> <?= htmlspecialchars($env['APP_NAME'] ?? 'Branch') ?></h1>
                <div class="text-sm text-gray-600">
                    <span class="px-2 py-1 bg-gray-100 rounded font-semibold"><?= htmlspecialchars($env['APP_BRANCH_CODE'] ?? '') ?></span>
                </div>
            </div>
            
            <!-- User Profile Dropdown -->
            <div class="relative">
                <button id="user-menu-button" class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-gray-100 transition-colors">
                    <div class="w-8 h-8 rounded-full bg-[#d0011c] flex items-center justify-center text-white font-semibold">
                        <?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?>
                    </div>
                    <span class="text-sm font-medium text-gray-700"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></span>
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
                
                <!-- Dropdown Menu -->
                <div id="user-menu" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-lg border border-gray-200 py-1">
                    <div class="px-4 py-2 border-b border-gray-100">
                        <div class="text-xs text-gray-500">Đăng nhập với</div>
                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></div>
                        <div class="text-xs text-gray-500 truncate"><?= htmlspecialchars($_SESSION['user_email'] ?? '') ?></div>
                        <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($_SESSION['role'] ?? 'STAFF') ?></div>
                    </div>
                    <a href="/logout" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                            </svg>
                            Đăng xuất
                        </span>
                    </a>
                </div>
            </div>
        </div>
    </header>
    <div class="flex">
        <aside class="fixed top-16 bottom-0 w-64 bg-white border-r border-gray-200">
            <nav class="p-4 space-y-2">
                <a href="/products" class="block px-4 py-2 rounded-lg text-gray-800 hover:bg-gray-100">Sản phẩm</a>
                <a href="/orders" class="block px-4 py-2 rounded-lg text-gray-800 hover:bg-gray-100">Đơn hàng</a>
                <a href="/employees" class="block px-4 py-2 rounded-lg text-gray-800 hover:bg-gray-100">Nhân viên</a>
                <div class="pt-4 mt-4 border-t border-gray-200">
                    <a href="/acid-demo" class="block px-4 py-2 rounded-lg bg-gradient-to-r from-red-600 to-purple-600 text-white hover:from-red-700 hover:to-purple-700 text-sm font-semibold text-center mb-3">
                        🎓 ACID Demo
                    </a>
                    <button id="btn-sync-outbox" class="w-full px-4 py-2 rounded-lg bg-gradient-to-r from-blue-600 to-indigo-600 text-white hover:from-blue-700 hover:to-indigo-700 text-sm mb-2 font-semibold">
                        📤 Sync Outbox <span id="outbox-badge" class="ml-1 px-2 py-0.5 bg-white/20 rounded-full text-xs">0</span>
                    </button>
                    <button id="btn-sync-products" class="w-full px-4 py-2 rounded-lg bg-gray-900 text-white hover:bg-black text-sm mb-2">Sync Sản phẩm</button>
                    <button id="btn-sync-employees" class="w-full px-4 py-2 rounded-lg bg-gray-900 text-white hover:bg-black text-sm">Sync Nhân viên</button>
                </div>
            </nav>
        </aside>
        <main class="flex-1 p-8 ml-64 mt-16">
            <?php include $viewFile; ?>
        </main>
    </div>
    <script src="/assets/js/components.js"></script>
    <script src="/assets/js/app.js"></script>
    <script>
    // User menu dropdown toggle
    const userMenuButton = document.getElementById('user-menu-button');
    const userMenu = document.getElementById('user-menu');
    
    userMenuButton?.addEventListener('click', (e) => {
        e.stopPropagation();
        userMenu.classList.toggle('hidden');
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', () => {
        userMenu?.classList.add('hidden');
    });
    
    // Update outbox badge
    async function updateOutboxBadge() {
        try {
            const res = await fetch('/outbox/status');
            const data = await res.json();
            if (data.success && data.stats) {
                const badge = document.getElementById('outbox-badge');
                if (badge) {
                    badge.textContent = data.stats.pending || 0;
                    if (data.stats.pending > 0) {
                        badge.classList.add('animate-pulse');
                    } else {
                        badge.classList.remove('animate-pulse');
                    }
                }
            }
        } catch (e) {
            console.error('Failed to update outbox badge:', e);
        }
    }
    
    // Sync outbox button
    document.getElementById('btn-sync-outbox')?.addEventListener('click', async () => {
        const btn = document.getElementById('btn-sync-outbox');
        btn.disabled = true;
        btn.innerHTML = '⏳ Syncing...';
        
        try {
            const res = await fetch('/outbox/sync', { method: 'POST' });
            const data = await res.json();
            
            if (data.success) {
                alert(`✅ ${data.message}\n\nSynced: ${data.synced}\nFailed: ${data.failed}`);
                updateOutboxBadge();
            } else {
                alert(`❌ Error: ${data.message}`);
            }
        } catch (e) {
            alert(`❌ Error: ${e.message}`);
        } finally {
            btn.disabled = false;
            btn.innerHTML = '📤 Sync Outbox <span id="outbox-badge" class="ml-1 px-2 py-0.5 bg-white/20 rounded-full text-xs">0</span>';
            updateOutboxBadge();
        }
    });
    
    // Sync buttons
    document.getElementById('btn-sync-products')?.addEventListener('click', async () => {
        UI.Modal.alert('Đang sync sản phẩm...');
        try { const res = await App.api('/sync/products', { method: 'POST' }); UI.Modal.alert(res.data?.message || 'Sync sản phẩm xong!', 'Thành công'); } catch(e){ UI.Modal.alert(e.message || 'Lỗi sync sản phẩm', 'Lỗi'); }
    });
    document.getElementById('btn-sync-employees')?.addEventListener('click', async () => {
        UI.Modal.alert('Đang sync nhân viên...');
        try { const res = await App.api('/sync/employees', { method: 'POST' }); UI.Modal.alert(res.data?.message || 'Sync nhân viên xong!', 'Thành công'); } catch(e){ UI.Modal.alert(e.message || 'Lỗi sync nhân viên', 'Lỗi'); }
    });
    
    // Update badge on page load and every 30s
    updateOutboxBadge();
    setInterval(updateOutboxBadge, 30000);
    </script>
</body>
</html>


