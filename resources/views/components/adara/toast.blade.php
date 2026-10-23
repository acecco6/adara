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
            this.timer = setTimeout(() => this.dismiss(), detail.duration ?? (this.tone === 'error' ? 3500 : 2500));
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
        <button type="button" x-on:click="dismiss()" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-adara-control transition-colors hover:bg-adara-background focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-adara-primary" aria-label="Cerrar notificación">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M5 5l14 14M19 5L5 19"/></svg>
        </button>
    </div>
</div>
