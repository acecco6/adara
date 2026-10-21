<div
    x-data="{
        visible: false,
        message: '',
        tone: 'success',
        timer: null,
        show(detail) {
            clearTimeout(this.timer);
            this.message = detail.message || '';
            this.tone = ['success', 'info', 'warning', 'error'].includes(detail.tone) ? detail.tone : 'success';
            this.visible = true;
            if (this.tone !== 'error') this.timer = setTimeout(() => this.dismiss(), detail.duration ?? 5000);
        },
        dismiss() {
            clearTimeout(this.timer);
            this.visible = false;
        }
    }"
    x-on:adara:toast.window="show($event.detail)"
    x-on:livewire:navigating.window="dismiss()"
    class="pointer-events-none fixed right-4 bottom-4 z-50 w-[min(25rem,calc(100vw-2rem))]"
>
    <div
        x-cloak
        x-show="visible"
        x-transition.opacity.duration.150ms
        x-bind:role="tone === 'error' ? 'alert' : 'status'"
        x-bind:class="{
            'border-adara-success text-adara-success': tone === 'success',
            'border-adara-primary text-adara-primary': tone === 'info',
            'border-adara-warning text-adara-warning': tone === 'warning',
            'border-adara-danger text-adara-danger': tone === 'error'
        }"
        class="pointer-events-auto flex items-start justify-between gap-3 rounded-adara-control border bg-adara-surface px-4 py-3 shadow-xl"
    >
        <span x-text="message" class="text-sm font-medium leading-6"></span>
        <button type="button" x-on:click="dismiss()" class="shrink-0 rounded-adara-control px-2 py-1 text-xs font-semibold hover:bg-adara-background" aria-label="Cerrar aviso">Cerrar</button>
    </div>
</div>
