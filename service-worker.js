const CACHE_NAME = 'barbearia-vintage-cache-v2'; // Mudei para v2 para forçar a atualização
const urlsToCache = [
  '/',
  '/index.php',
  '/manifest.json',
  '/icon-192x192.png',
  '/icon-512x512.png',
  'https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Montserrat:wght@400;500;600;700&display=swap'
];

// Instala o Service Worker e adiciona os arquivos ao cache
self.addEventListener('install', event => {
  self.skipWaiting(); // Força a ativação do novo service worker
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Cache aberto e arquivos sendo adicionados');
        return cache.addAll(urlsToCache);
      })
  );
});

// Ativa o Service Worker e limpa caches antigos
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME) {
            console.log('Deletando cache antigo:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  return self.clients.claim();
});

// Intercepta as requisições de rede
self.addEventListener('fetch', event => {
  // Ignora requisições que não são GET (ex: POST para agendar)
  if (event.request.method !== 'GET') {
    return;
  }
  
  event.respondWith(
    caches.open(CACHE_NAME).then(cache => {
      return fetch(event.request)
        .then(response => {
          // Se a requisição foi bem sucedida, clona e guarda no cache
          if (response.status === 200) {
            cache.put(event.request.url, response.clone());
          }
          return response;
        })
        .catch(() => {
          // Se a rede falhar, tenta pegar do cache
          return cache.match(event.request);
        });
    })
  );
});
