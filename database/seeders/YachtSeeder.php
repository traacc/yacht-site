<?php

namespace Database\Seeders;

use App\Models\Yacht;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class YachtSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // 5 обычных одобренных яхт (привязаны к пользователям)
        Yacht::factory(2)
            ->approved()
            ->create();

        // 2 яхты на рассмотрении
        Yacht::factory(2)
            ->pending()
            ->create();

        /*
        // 1 отклонённая яхта
        Yacht::factory()
            ->rejected()
            ->create();
        
        // 2 яхты, доступные для аренды
        Yacht::factory(2)
            ->approved()
            ->rental()
            ->create();
        */
        /*
        // 1 яхта без владельца (user_id = null), только контактные данные
        Yacht::factory()
            ->approved()
            ->notForRent()
            ->create([
                'user_id'      => null,
                'owner_name'  => 'Дмитрий Соколов',
                'owner_email' => 'dmitry@example.com',
                'owner_phone' => '+7 (916) 123-45-67',
            ]);
        */
        /*
        // 1 мягко удалённая яхта
        Yacht::factory()
            ->approved()
            ->trashed()
            ->create();

        */
    }
}