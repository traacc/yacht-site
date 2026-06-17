import mask from '@alpinejs/mask';

document.addEventListener('alpine:init', () => {
    Alpine.plugin(mask);
});

import flatpickr from "flatpickr";
import { Russian } from "flatpickr/dist/l10n/ru.js"; // Импорт русской локали
import "flatpickr/dist/flatpickr.css";

window.flatpickr = flatpickr;
window.flatpickrRussian = Russian; // Делаем её доступной глобально

// Восстановление Livewire после возврата на вкладку (мобильные браузеры).
// При уходе на другую вкладку мобильный браузер замораживает JS и обрывает
// сетевые запросы на полпути. Из-за этого «коммит» компонента Livewire может
// зависнуть, и последующие .live-обновления (поиск команды/рулевого/участников)
// встают в очередь за мёртвым запросом и не отправляются. При возврате вкладки
// в фокус принудительно пере-синхронизируем компоненты, восстанавливая цикл.
let wasHidden = false;
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'hidden') {
        wasHidden = true;
        return;
    }

    if (!wasHidden) return;
    wasHidden = false;

    if (!window.Livewire) return;
    window.Livewire.all().forEach((component) => {
        try {
            component.$wire.$refresh();
        } catch (e) {
            // компонент мог быть уже удалён из DOM — пропускаем
        }
    });
});