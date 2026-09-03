# EVEmu Portal

Веб-портал для приватного сервера EVEmu.

## Функции
- Killboard (данные через API сервер)
- Регистрация и авторизация
- Управление персонажами
- Админ-панель: аккаунты, петиции, таймкоды, выдача предметов, управление ролями

## Зависимости
- PHP 8.x с PDO MySQL
- API сервер EVEmu (порт 26002)
- MariaDB (shared с сервером)

## Установка
1. Скопировать в веб-директорию (nginx/php-fpm)
2. Настроить `config.php` — `API_BASE` на URL API сервера
3. Настроить реврайтинг (nginx: `try_files $uri $uri/ /index.php`)

## nginx конфиг
```nginx
server {
    listen 80;
    server_name kb.yourdomain.com;
    root /path/to/evemu-portal;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```
