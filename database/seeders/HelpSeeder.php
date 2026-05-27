<?php

namespace Database\Seeders;

use App\Models\Help;
use App\Models\HelpCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class HelpSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'electric' => [
                'title' => 'Электрика и механика на яхте',
                'items' => [
                    [
                        'title' => 'Проверка электросистем перед регатой',
                        'desc' => 'Диагностика и обслуживание электрических систем яхты.',
                        'includes' => [
                            'Проверка аккумуляторов',
                            'Диагностика бортовой сети',
                            'Проверка навигационного оборудования',
                            'Освещение и электропроводка',
                            'Проверка зарядных систем',
                            'Поиск неисправностей',
                        ],
                        'name' => 'Игорь Скалин',
                        'phone' => '+7 (963) 610-11-13',
                        'email' => 'info@carter-pro.ru',
                        'sphere' => 'Электрик / механик яхт',
                        'city' => 'Москва',
                    ],
                    [
                        'title' => 'Ремонт подвесных моторов',
                        'desc' => 'Обслуживание и ремонт лодочных моторов.',
                        'includes' => [
                            'Диагностика двигателя',
                            'Замена масла и фильтров',
                            'Ремонт системы охлаждения',
                            'Регулировка карбюратора',
                        ],
                        'name' => 'Алексей Петров',
                        'phone' => '+7 (916) 123-45-67',
                        'email' => 'petrov@marine.ru',
                        'sphere' => 'Моторист',
                        'city' => 'Санкт-Петербург',
                    ],
                ],
            ],
            'construct' => [
                'title' => 'Конструктив, отделка, косметика',
                'items' => [
                    [
                        'title' => 'Полировка корпуса',
                        'desc' => 'Восстановление блеска и защита гелькоута.',
                        'includes' => [
                            'Мойка корпуса',
                            'Шлифовка гелькоута',
                            'Полировка',
                            'Нанесение защитного покрытия',
                        ],
                        'name' => 'Марина Иванова',
                        'phone' => '+7 (926) 234-56-78',
                        'email' => 'ivanova@yacht.ru',
                        'sphere' => 'Маляр / полировщик',
                        'city' => 'Москва',
                    ],
                ],
            ],
            'rigging' => [
                'title' => 'Такелажные работы',
                'items' => [
                    [
                        'title' => 'Замена стоячего такелажа',
                        'desc' => 'Профессиональная замена вант и штагов.',
                        'includes' => [
                            'Дефектовка такелажа',
                            'Изготовление вант',
                            'Изготовление штагов',
                            'Установка и настройка',
                        ],
                        'name' => 'Дмитрий Сидоров',
                        'phone' => '+7 (903) 345-67-89',
                        'email' => 'sidorov@rigging.ru',
                        'sphere' => 'Такелажный мастер',
                        'city' => 'Сочи',
                    ],
                ],
            ],
            'sails' => [
                'title' => 'Работа с парусами и парусные мастера',
                'items' => [
                    [
                        'title' => 'Ремонт парусов',
                        'desc' => 'Ремонт разрывов и замена люверсов.',
                        'includes' => [
                            'Осмотр парусов',
                            'Ремонт разрывов',
                            'Замена люверсов',
                            'Усиление швов',
                        ],
                        'name' => 'Елена Кузнецова',
                        'phone' => '+7 (929) 456-78-90',
                        'email' => 'kuznetsova@sails.ru',
                        'sphere' => 'Парусный мастер',
                        'city' => 'Владивосток',
                    ],
                ],
            ],
        ];

        foreach ($categories as $slug => $catData) {
            $category = HelpCategory::create([
                'id' => (string) Str::uuid(),
                'title' => $catData['title'],
                'slug' => $slug,
            ]);

            foreach ($catData['items'] as $item) {
                Help::create([
                    'id' => (string) Str::uuid(),
                    'help_category_id' => $category->id,
                    'title' => $item['title'],
                    'desc' => $item['desc'],
                    'includes' => $item['includes'] ?? [],
                    'contact_type' => 'specialist',
                    'specialist_name' => $item['name'],
                    'specialist_phone' => $item['phone'],
                    'specialist_email' => $item['email'],
                    'specialist_sphere' => $item['sphere'] ?? null,
                    'specialist_city' => $item['city'] ?? null,
                    'status' => 'active',
                ]);
            }
        }
    }
}