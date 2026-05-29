import mask from '@alpinejs/mask';

document.addEventListener('alpine:init', () => {
    Alpine.plugin(mask);
});

import flatpickr from "flatpickr";
import { Russian } from "flatpickr/dist/l10n/ru.js"; // Импорт русской локали
import "flatpickr/dist/flatpickr.css";

window.flatpickr = flatpickr;
window.flatpickrRussian = Russian; // Делаем её доступной глобально