<?php
declare(strict_types=1);

const ADMIN_USER = 'admin';
const ADMIN_PASS = 'change_me_please';   // сменить!

const STORAGE_FILE = __DIR__ . '/Заявки.txt';
const CART_FILE    = __DIR__ . '/Корзины.json';

const STATUS_LABELS = [
    'new'        => 'Новая',
    'processing' => 'В обработке',
    'done'       => 'Принята',
    'cancelled'  => 'Отклонена',
];

const PRODUCT_IMAGES = [
    'iPhone 18 Pro'     => 'https://fdn2.gsmarena.com/vv/bigpic/apple-iphone-15-pro-max.jpg',
    'iPhone 17 Pro Max' => 'images/iPhone%2017%20Pro%20Max.jpg',
    'iPhone 17 Pro'     => 'https://fdn2.gsmarena.com/vv/bigpic/apple-iphone-15-pro.jpg',
    'iPhone 17'         => 'https://fdn2.gsmarena.com/vv/bigpic/apple-iphone-15.jpg',
    'iPhone 16 Pro Max' => 'https://fdn2.gsmarena.com/vv/bigpic/apple-iphone-15-pro-max.jpg',
    'iPhone 15 Pro Max' => 'https://fdn2.gsmarena.com/vv/bigpic/apple-iphone-15-pro-max.jpg',
    'iPhone 15 Pro'     => 'https://fdn2.gsmarena.com/vv/bigpic/apple-iphone-15-pro.jpg',
    'iPhone 14 Pro Max' => 'images/iPhone%2014%20Pro%20Max.jpg',
    'iPhone 14'         => 'https://fdn2.gsmarena.com/vv/bigpic/apple-iphone-14.jpg',
    'iPhone 12 Pro'     => 'https://fdn2.gsmarena.com/vv/bigpic/apple-iphone-12-pro.jpg',
    'iPhone 13'         => 'https://fdn2.gsmarena.com/vv/bigpic/apple-iphone-13.jpg',
    'iPhone 11 Pro Max' => 'images/iPhone%2011%20Pro%20Max.jpg',
    'iPhone 12'         => 'https://fdn2.gsmarena.com/vv/bigpic/apple-iphone-12.jpg',
    'iPhone 11'         => 'https://fdn2.gsmarena.com/vv/bigpic/apple-iphone-11.jpg',
];

require_once __DIR__ . '/users.php';

// Бутстрап админа при первом запуске
if (!is_file(USERS_FILE)) {
    users_save([
        ADMIN_USER => [
            'username'      => ADMIN_USER,
            'fullname'      => 'Администратор',
            'role'          => 'admin',
            'password_hash' => password_hash(ADMIN_PASS, PASSWORD_DEFAULT),
            'views'         => array_keys(VIEW_LABELS),
            'actions'       => array_keys(ACTION_LABELS),
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ],
    ]);
}