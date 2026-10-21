@php
    $sections = [
        ['label' => 'General', 'items' => [
            ['label' => 'Inicio', 'icon' => 'house', 'route' => 'home'],
            ['label' => 'Productos', 'icon' => 'package', 'route' => 'products.index', 'active' => 'products.*'],
            ['label' => 'Stock y movimientos', 'icon' => 'boxes', 'route' => 'inventory.index'],
        ]],
        ['label' => 'Operaciones', 'items' => [
            ['label' => 'Pedidos a proveedor', 'icon' => 'clipboard-list', 'route' => 'purchases.index', 'active' => 'purchases.show'],
            ['label' => 'Recepción de compras', 'icon' => 'truck', 'route' => 'purchases.receipts'],
            ['label' => 'Ventas', 'icon' => 'shopping-cart', 'route' => 'sales.index'],
            ['label' => 'Nueva venta', 'icon' => 'sparkles', 'route' => 'sales.create'],
        ]],
        ['label' => 'Administración', 'items' => [
            ['label' => 'Clientas', 'icon' => 'users', 'route' => 'customers.index'],
            ['label' => 'Proveedores', 'icon' => 'package-check', 'route' => 'suppliers.index'],
            ['label' => 'Categorías', 'icon' => 'tags', 'route' => 'categories.index'],
            ['label' => 'Reportes', 'icon' => 'chart-no-axes-combined', 'route' => 'reports.index'],
        ]],
        ['label' => 'Sistema', 'items' => [
            ['label' => 'Configuración', 'icon' => 'settings', 'route' => 'settings.index'],
            ['label' => 'Copias de seguridad', 'icon' => 'database-backup', 'route' => 'backups.index'],
            ['label' => 'Ayuda', 'icon' => 'circle-help', 'route' => 'help.index'],
        ]],
    ];

    if (app()->environment(['local', 'testing'])) {
        $sections[] = ['label' => 'Desarrollo', 'items' => [
            ['label' => 'Componentes', 'icon' => 'sparkles', 'route' => 'dev.components'],
        ]];
    }
@endphp

<nav aria-label="Navegación principal" class="adara-sidebar-nav min-h-0 overflow-y-auto pb-4">
    @foreach ($sections as $section)
        <div class="mb-3">
            <p class="px-[15px] pt-3 pb-2 text-[10px] font-bold tracking-[0.15em] text-[#af9dab] uppercase">{{ $section['label'] }}</p>
            <ul class="flex flex-col gap-0.5">
                @foreach ($section['items'] as $item)
                    @php
                        $isActive = request()->routeIs($item['route']) || (isset($item['active']) && request()->routeIs($item['active']));
                    @endphp
                    <li>
                        <a href="{{ route($item['route']) }}" wire:navigate @if (request()->routeIs($item['route'])) aria-current="page" @endif class="flex min-h-10 items-center gap-3 rounded-[11px] px-[14px] text-[13px] font-semibold text-white transition-colors duration-150 hover:bg-white/20 {{ $isActive ? 'bg-white/15' : '' }}">
                            <i data-lucide="{{ $item['icon'] }}" class="h-[18px] w-[18px] shrink-0"></i>
                            <span>{{ $item['label'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</nav>
