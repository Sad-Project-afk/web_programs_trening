<?php
// Пример конфигурации базы данных.
// Скопируйте этот файл в config.php, впишите свои доступы и загрузите config.php
// на хостинг (config.php в git не попадает).

return [
    // 'mysql'  — база данных на хостинге
    // 'sqlite' — локальный файл (для разработки/теста без MySQL)
    'driver' => 'mysql',

    'host'     => 'localhost',   // хост MySQL (обычно localhost или IP из панели)
    'port'     => 3306,
    'database' => 'fitlog',      // имя созданной БД в панели хостинга
    'username' => 'fitlog_user', // пользователь MySQL
    'password' => 'secret',      // пароль MySQL

    // Только для driver = 'sqlite' (локальная разработка)
    'sqlite_path' => __DIR__ . '/data/fitlog.sqlite',
];