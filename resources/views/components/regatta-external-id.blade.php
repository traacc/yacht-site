{{--
    ID регаты для судейской программы. Виден только админу-разработчику,
    по клику копируется в буфер обмена (с фолбэком для http-контекста,
    где Clipboard API недоступен).
--}}
@props(['value' => null])

@if (auth()->user()?->isDeveloperAdmin())
    @if ($value === null)
        <span {{ $attributes->merge(['class' => 'text-brand-gray-light']) }}>ID: —</span>
    @else
        <button type="button"
                x-data="{
                    copied: false,
                    copy() {
                        const text = '{{ $value }}';
                        const done = () => {
                            this.copied = true;
                            setTimeout(() => this.copied = false, 1500);
                        };
                        if (navigator.clipboard && window.isSecureContext) {
                            navigator.clipboard.writeText(text).then(done);
                            return;
                        }
                        const area = document.createElement('textarea');
                        area.value = text;
                        area.style.position = 'fixed';
                        area.style.opacity = '0';
                        document.body.appendChild(area);
                        area.select();
                        document.execCommand('copy');
                        document.body.removeChild(area);
                        done();
                    },
                }"
                @click.stop.prevent="copy()"
                title="Скопировать ID"
                {{ $attributes->merge(['class' => 'text-brand-gray-light cursor-pointer hover:underline']) }}>
            ID: {{ $value }}
            <span x-show="copied" x-cloak>✓</span>
        </button>
    @endif
@endif
