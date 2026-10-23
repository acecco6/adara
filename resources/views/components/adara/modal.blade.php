@props(['id', 'title', 'description' => null, 'dismissible' => true, 'wide' => false, 'bare' => false, 'onCloseEvent' => null])

<div
    x-data="{
        opener: null,
        isClosing: false,
        animation: null,
        open() {
            this.opener = document.activeElement;
            this.isClosing = false;
            this.$refs.dialog.getAnimations().forEach(animation => animation.cancel());
            if (!this.$refs.dialog.open) {
                this.$refs.dialog.showModal();
            }
            this.$refs.dialog.focus();
            this.$refs.dialog.animate([
                { opacity: 0, transform: 'translateY(12px) scale(.985)' },
                { opacity: 1, transform: 'translateY(0) scale(1)' },
            ], { duration: 220, easing: 'cubic-bezier(.2,.75,.25,1)' });
            this.$refs.dialog.animate([
                { backgroundColor: 'rgb(49 38 49 / 0%)' },
                { backgroundColor: 'rgb(49 38 49 / 60%)' },
            ], { duration: 220, pseudoElement: '::backdrop' });
        },
        close() {
            if (!this.$refs.dialog.open || this.isClosing) return;
            this.isClosing = true;
            this.$refs.dialog.getAnimations().forEach(animation => animation.cancel());
            window.dispatchEvent(new CustomEvent('adara:modal-closing', { detail: { id: '{{ $id }}' } }));
            const animation = this.$refs.dialog.animate([
                { opacity: 1, transform: 'translateY(0) scale(1)' },
                { opacity: 0, transform: 'translateY(8px) scale(.99)' },
            ], { duration: 160, easing: 'ease-in', fill: 'forwards' });
            this.$refs.dialog.animate([
                { backgroundColor: 'rgb(49 38 49 / 60%)' },
                { backgroundColor: 'rgb(49 38 49 / 0%)' },
            ], { duration: 160, pseudoElement: '::backdrop' });
            animation.finished.then(() => {
                if (!this.isClosing) return;
                if (this.$refs.dialog.open) this.$refs.dialog.close();
                this.isClosing = false;
            }).catch(() => {});
        },
        trapFocus(event) {
            const controls = [...this.$refs.dialog.querySelectorAll('a[href], button, input, select, textarea, [tabindex]')].filter(control => !control.disabled && control.tabIndex >= 0 && control.getClientRects().length > 0);
            if (controls.length === 0) return;
            const first = controls[0];
            const last = controls[controls.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        },
        returnFocus() {
            if (this.opener?.isConnected) this.opener.focus();
            this.opener = null;
        }
    }"
    x-on:adara:open.window="if ($event.detail.id === '{{ $id }}') open()"
    x-on:adara:close.window="if ($event.detail.id === '{{ $id }}') close()"
    x-on:adara:modal-closing.window="if ($event.detail.id === '{{ $id }}' && @js($onCloseEvent)) Livewire.dispatch(@js($onCloseEvent))"
    x-on:livewire:navigating.window="close()"
>
    <dialog
        id="{{ $id }}"
        x-ref="dialog"
        tabindex="-1"
        x-on:close="returnFocus(); window.dispatchEvent(new CustomEvent('adara:modal-closed', { detail: { id: '{{ $id }}' } }))"
        x-on:keydown.tab="trapFocus($event)"
        x-on:click.self="@js($dismissible) && close()"
        aria-modal="true"
        aria-labelledby="{{ $id }}-title"
        @if ($description) aria-describedby="{{ $id }}-description" @endif
        {{ $attributes->class(['m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] overflow-y-auto rounded-adara-dialog border border-adara-border bg-adara-surface p-0 text-adara-ink shadow-xl backdrop:bg-adara-sidebar/60', 'max-w-[44rem]' => $wide, 'max-w-[34rem]' => ! $wide]) }}
    >
        @unless ($bare)
            <div class="flex items-start justify-between gap-4 border-b border-adara-border px-6 py-5">
                <div>
                    <h2 id="{{ $id }}-title" class="font-display text-xl font-semibold">{{ $title }}</h2>
                    @if ($description)
                        <p id="{{ $id }}-description" class="mt-1 text-sm leading-6 text-adara-readable-muted">{{ $description }}</p>
                    @endif
                </div>
                <button x-ref="initial" type="button" x-on:click="close()" aria-label="Cerrar {{ $title }}" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-adara-control text-adara-readable-muted hover:bg-adara-primary-light hover:text-adara-primary">
                    <span aria-hidden="true" class="text-xl leading-none">×</span>
                </button>
            </div>
        @endunless

        <div @class(['px-6 py-5' => ! $bare])>{{ $slot }}</div>

        @isset($footer)
            <div class="flex flex-wrap justify-end gap-3 border-t border-adara-border px-6 py-4">{{ $footer }}</div>
        @endisset
    </dialog>
</div>
