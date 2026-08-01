<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Преобразование ссылки на видео в URL для <iframe>.
 *
 * Логика жила аксессором в модели VideoLink (видео галерей), но те же ссылки
 * с подписями нужны кейсам ремонта раздела «Carter 30», где своей таблицы
 * видео нет — они хранятся json-колонкой. Чтобы не держать две копии
 * регулярок, разбор вынесен сюда, а VideoLink делегирует.
 */
final class VideoEmbed
{
    /**
     * Ссылки, которые уже являются embed-адресами известных платформ.
     *
     * @var array<string>
     */
    private const EMBED_PATTERNS = [
        '#^https?://(?:www\.)?youtube(?:-nocookie)?\.com/embed/#i',
        '#^https?://player\.vimeo\.com/video/#i',
        '#^https?://rutube\.ru/play/embed/#i',
        '#^https?://(?:vk\.com|vkvideo\.ru)/video_ext\.php#i',
    ];

    /**
     * Поддерживаемые платформы:
     *   - YouTube (watch → embed)
     *   - YouTube Shorts (shorts → embed)
     *   - Vimeo (vimeo.com/... → player.vimeo.com/video/...)
     *   - Rutube (rutube.ru/video/... → rutube.ru/play/embed/...)
     *   - VK Видео (vk.com/video... и vkvideo.ru/video... → vk.com/video_ext.php?oid=…&id=…)
     *
     * Для всех остальных URL возвращается исходное значение.
     */
    public static function url(string $url): string
    {
        // YouTube: https://www.youtube.com/watch?v=VIDEO_ID
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $m)) {
            return 'https://www.youtube.com/embed/'.$m[1];
        }

        // YouTube Shorts: https://www.youtube.com/shorts/VIDEO_ID
        if (preg_match('/youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})/', $url, $m)) {
            return 'https://www.youtube.com/embed/'.$m[1];
        }

        // Vimeo: https://vimeo.com/VIDEO_ID
        if (preg_match('/vimeo\.com\/(\d+)/', $url, $m)) {
            return 'https://player.vimeo.com/video/'.$m[1];
        }

        // Rutube: https://rutube.ru/video/VIDEO_ID/...
        if (preg_match('/rutube\.ru\/video\/([a-zA-Z0-9]+)/', $url, $m)) {
            return 'https://rutube.ru/play/embed/'.$m[1];
        }

        // VK Видео и клипы. Понимаем три написания одной и той же ссылки:
        //   https://vk.com/video-OWNER_ID_VIDEO_ID
        //   https://vkvideo.ru/video-OWNER_ID_VIDEO_ID
        //   https://vk.com/GROUP?z=video-OWNER_ID_VIDEO_ID%2FHASH  ← ссылка со стены сообщества
        // Плееру нужны именованные параметры oid и id: склейка «oid_id» им не понимается.
        // Хвост-хэш (у видео с ограниченным доступом) передаём, если он есть в ссылке.
        $vk = urldecode($url);

        if (preg_match('#(?:(?:vk\.com|vkvideo\.ru)/|[?&]z=)(?:video|clip)(-?\d+)_(\d+)#', $vk, $m)) {
            $embed = 'https://vk.com/video_ext.php?oid='.$m[1].'&id='.$m[2].'&hd=2';

            if (preg_match('#'.preg_quote($m[1].'_'.$m[2], '#').'/([a-f0-9]+)#i', $vk, $hash)) {
                $embed .= '&hash='.$hash[1];
            }

            return $embed;
        }

        // Неизвестный источник — возвращаем как есть
        return $url;
    }

    /**
     * Распознана ли ссылка как видео известной платформы.
     *
     * Нужно там, где нельзя молча отдать iframe с произвольным адресом:
     * блок «Видео» в RichEditor валидирует ввод редактора этим методом.
     */
    public static function supports(string $url): bool
    {
        $url = trim($url);

        if ($url === '') {
            return false;
        }

        if (self::url($url) !== $url) {
            return true;
        }

        foreach (self::EMBED_PATTERNS as $pattern) {
            if (preg_match($pattern, $url) === 1) {
                return true;
            }
        }

        return false;
    }
}
