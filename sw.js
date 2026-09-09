// EVEmu portal — web-push service worker.
// Receives payloadless pushes from tools/push_worker.php, then asks the portal
// (/mail/poll) for the current unread state and shows a notification.
self.addEventListener('install', function (e) { self.skipWaiting(); });
self.addEventListener('activate', function (e) { e.waitUntil(self.clients.claim()); });

function notif(body, url) {
  return self.registration.showNotification('EVEmu — почта', {
    body: body || 'У вас новые события',
    tag: 'evemu-mail',
    renotify: true,
    data: { url: url || '/mail' }
  });
}

self.addEventListener('push', function (event) {
  event.waitUntil(
    fetch('/mail/poll', { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.ok) return;
        var parts = [];
        if (d.unread > 0) {
          if (d.top && d.top.sendername) {
            parts.push('Новое письмо от ' + d.top.sendername + (d.top.title ? ': ' + d.top.title : ''));
          } else {
            parts.push('Новых писем: ' + d.unread);
          }
        }
        if (d.notifications > 0) {
          parts.push('Уведомлений: ' + d.notifications);
        }
        if (parts.length) return notif(parts.join('\n'));
      })
      .catch(function () {})
  );
});

self.addEventListener('notificationclick', function (event) {
  event.notification.close();
  var url = (event.notification.data && event.notification.data.url) || '/mail';
  event.waitUntil(
    self.clients.matchAll({ type: 'window' }).then(function (list) {
      for (var i = 0; i < list.length; i++) {
        if (new URL(list[i].url).pathname.indexOf(url) === 0) {
          return list[i].focus();
        }
      }
      return self.clients.openWindow(url);
    })
  );
});
