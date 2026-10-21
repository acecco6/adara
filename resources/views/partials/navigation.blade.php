@php
    $sections = [
        ['label' => 'General', 'items' => [
            ['label' => 'Inicio', 'icon' => 'house', 'route' => 'home'],
            ['label' => 'Productos', 'icon' => 'package'],
            ['label' => 'Stock y movimientos', 'icon' => 'boxes'],
        ]],
        ['label' => 'Operaciones', 'items' => [
            ['label' => 'Pedidos a proveedor', 'icon' => 'clipboard-list'],
            ['label' => 'Recepción de compras', 'icon' => 'truck'],
            ['label' => 'Ventas', 'icon' => 'shopping-cart'],
        ]],
        ['label' => 'Administración', 'items' => [
            ['label' => 'Clientas', 'icon' => 'users'],
            ['label' => 'Proveedores', 'icon' => 'package-check'],
            ['label' => 'Categorías', 'icon' => 'tags'],
            ['label' => 'Reportes', 'icon' => 'chart-no-axes-combined'],
        ]],
        ['label' => 'Sistema', 'items' => [
            ['label' => 'Configuración', 'icon' => 'settings'],
            ['label' => 'Copias de seguridad', 'icon' => 'database-backup'],
            ['label' => 'Ayuda', 'icon' => 'circle-help'],
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
                    <li>
                        @if (isset($item['route']))
                            <a href="{{ route($item['route']) }}" wire:navigate @if (request()->routeIs($item['route'])) aria-current="page" @endif class="flex min-h-10 items-center gap-3 rounded-[11px] px-[14px] text-[13px] font-semibold text-white transition-colors duration-150 hover:bg-white/20 {{ request()->routeIs($item['route']) ? 'bg-white/15' : '' }}">
                                <i data-lucide="{{ $item['icon'] }}" class="h-[18px] w-[18px] shrink-0"></i>
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @else
                            <span aria-disabled="true" class="flex min-h-10 items-center gap-3 rounded-[11px] px-[14px] text-[13px] text-white/55">
                                <i data-lucide="{{ $item['icon'] }}" class="h-[18px] w-[18px] shrink-0"></i>
                                <span>{{ $item['label'] }}</span>
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
</nav>
