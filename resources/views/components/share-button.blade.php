@props([
    'title',
    'text',
    'url',
    'label' => 'Share',
])

<div
    x-cloak
    x-show="'share' in navigator || !!navigator.clipboard"
    x-data="{
        copied: false,
        async share() {
            if ('share' in navigator) {
                try { await navigator.share({ title: @js($title), text: @js($text), url: @js($url) }); } catch {}
                return;
            }
            if (navigator.clipboard) {
                await navigator.clipboard.writeText(@js($url));
                this.copied = true;
                setTimeout(() => this.copied = false, 2000);
            }
        }
    }"
    class="relative inline-flex"
    data-testid="share-button"
>
    <flux:button size="sm" icon="share" @click="share()">
        <span x-show="!copied">{{ $label }}</span>
        <span x-show="copied" x-cloak>Copied!</span>
    </flux:button>
</div>
