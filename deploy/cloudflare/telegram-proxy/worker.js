// Прокси Telegram Bot API для сайта: пересылает запросы в api.telegram.org.
// Сайт обращается сюда вместо api.telegram.org (TELEGRAM_API_URL).

const UPSTREAM = 'https://api.telegram.org';

// Только методы бота и скачивание файлов: /bot<token>/<method>, /file/bot<token>/<path>
const ALLOWED_PATH = /^\/(file\/)?bot\d+:[\w-]+\//;

export default {
  async fetch(request, env) {
    const url = new URL(request.url);

    if (!ALLOWED_PATH.test(url.pathname)) {
      return new Response('Not found', { status: 404 });
    }

    // Общий секрет, чтобы Worker не превратился в открытый прокси.
    if (env.PROXY_SECRET && request.headers.get('X-Proxy-Secret') !== env.PROXY_SECRET) {
      return new Response('Forbidden', { status: 403 });
    }

    const headers = new Headers(request.headers);
    headers.delete('X-Proxy-Secret');
    headers.delete('Host');

    // Тело передаём потоком: multipart sendPhoto уходит без буферизации.
    return fetch(UPSTREAM + url.pathname + url.search, {
      method: request.method,
      headers,
      body: ['GET', 'HEAD'].includes(request.method) ? undefined : request.body,
    });
  },
};
