<?php

namespace Tests\Feature;

use App\Livewire\JoinRegattaModal;
use App\Mail\SendLoginCredentials;
use App\Models\RegattaEntry;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\Yacht;
use App\Models\Regatta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class GuestJoinRegattaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // UserFactory назначает external_id из статического счётчика (с 1),
        // а модель User берёт значение из таблицы sequences. Чтобы они не
        // конфликтовали в тестах, заранее сдвигаем последовательность вверх.
        foreach (['users_external_id', 'teams_external_id'] as $name) {
            DB::table('sequences')->updateOrInsert(
                ['name' => $name],
                ['current_value' => 1_000_000],
            );
        }
    }

    public function test_guest_registers_and_submits_entry_with_selected_yacht(): void
    {
        Mail::fake();

        $regatta = Regatta::factory()->create();
        $yacht = Yacht::factory()->create(['user_id' => User::factory()]);

        Livewire::test(JoinRegattaModal::class)
            ->call('openModal', $regatta->id)
            ->assertSet('isOpen', true)
            ->set('guestName', 'Иванов Иван Иванович')
            ->set('guestEmail', 'guest@example.com')
            ->set('guestPhone', '+79990000000')
            ->set('teamName', 'Морские волки')
            ->set('yachtMode', 'select')
            ->set('yachtId', $yacht->id)
            ->call('submitGuest')
            ->assertHasNoErrors()
            ->assertSet('submitted', true)
            ->assertSet('guestRegistered', true);

        $user = User::where('email', 'guest@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Auth::check());
        $this->assertSame($user->id, Auth::id());

        $team = Team::where('name', 'Морские волки')->first();
        $this->assertNotNull($team);
        $this->assertSame($user->id, $team->organizer_id);

        // Организатор-участник создан и является капитаном экипажа
        $organizerMember = TeamMember::where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->where('role', 'organizer')
            ->first();
        $this->assertNotNull($organizerMember);

        $entry = RegattaEntry::where('regatta_id', $regatta->id)
            ->where('team_id', $team->id)
            ->first();
        $this->assertNotNull($entry);
        $this->assertSame($yacht->id, $entry->yacht_id);
        $this->assertTrue($entry->crew()->where('role', 'captain')->exists());

        Mail::assertSent(SendLoginCredentials::class);
    }

    public function test_guest_creates_new_yacht(): void
    {
        Mail::fake();

        $regatta = Regatta::factory()->create();

        Livewire::test(JoinRegattaModal::class)
            ->call('openModal', $regatta->id)
            ->set('guestName', 'Петров Пётр')
            ->set('guestEmail', 'petr@example.com')
            ->set('guestPhone', '+79991112233')
            ->set('teamName', 'Бриз')
            ->set('yachtMode', 'create')
            ->set('newYachtName', 'Альбатрос')
            ->set('newYachtVfps', 'VFPS-12345')
            ->call('submitGuest')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $user = User::where('email', 'petr@example.com')->first();
        $yacht = Yacht::where('name', 'Альбатрос')->first();
        $this->assertNotNull($yacht);
        $this->assertSame($user->id, $yacht->user_id);

        $entry = RegattaEntry::where('yacht_id', $yacht->id)->first();
        $this->assertNotNull($entry);
    }

    public function test_guest_submit_validates_required_fields(): void
    {
        $regatta = Regatta::factory()->create();

        Livewire::test(JoinRegattaModal::class)
            ->call('openModal', $regatta->id)
            ->call('submitGuest')
            ->assertHasErrors(['guestName', 'guestEmail', 'guestPhone', 'teamName', 'yachtId']);

        $this->assertSame(0, User::count());
        $this->assertSame(0, RegattaEntry::count());
    }

    public function test_guest_can_add_external_members_to_crew(): void
    {
        Mail::fake();

        $regatta = Regatta::factory()->create();
        $yacht = Yacht::factory()->create(['user_id' => User::factory()]);
        $other = User::factory()->create(['name' => 'Сидоров Сидор', 'email' => 'sidor@example.com']);

        $component = Livewire::test(JoinRegattaModal::class)
            ->call('openModal', $regatta->id)
            ->call('addGuestMember', $other->id);

        $component->assertCount('guestMembers', 1);

        $component->set('guestName', 'Капитан Немо')
            ->set('guestEmail', 'nemo@example.com')
            ->set('guestPhone', '+79995554433')
            ->set('teamName', 'Наутилус')
            ->set('yachtMode', 'select')
            ->set('yachtId', $yacht->id)
            ->call('submitGuest')
            ->assertHasNoErrors();

        $team = Team::where('name', 'Наутилус')->first();
        $this->assertTrue(
            TeamMember::where('team_id', $team->id)->where('user_id', $other->id)->exists()
        );

        $entry = RegattaEntry::where('team_id', $team->id)->first();
        // Капитан (организатор) + 1 добавленный участник
        $this->assertSame(2, $entry->crew()->count());
    }

    public function test_guest_can_add_unregistered_member_who_is_auto_registered(): void
    {
        Mail::fake();

        $regatta = Regatta::factory()->create();
        $yacht = Yacht::factory()->create(['user_id' => User::factory()]);

        $component = Livewire::test(JoinRegattaModal::class)
            ->call('openModal', $regatta->id)
            ->set('newMemberName', 'Новиков Новик Новикович')
            ->set('newMemberBirthDate', '1995-05-20')
            ->set('newMemberSportCategory', 'kms')
            ->call('addUnregisteredGuestMember')
            ->assertHasNoErrors()
            ->assertCount('guestMembers', 1)
            // Поля формы сброшены после добавления
            ->assertSet('newMemberName', '');

        $component->set('guestName', 'Капитан Немо')
            ->set('guestEmail', 'nemo2@example.com')
            ->set('guestPhone', '+79995554400')
            ->set('teamName', 'Арго')
            ->set('yachtMode', 'select')
            ->set('yachtId', $yacht->id)
            ->call('submitGuest')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $member = User::where('name', 'Новиков Новик Новикович')->first();
        $this->assertNotNull($member);
        $this->assertSame('1995-05-20', $member->birth_date->format('Y-m-d'));
        $this->assertSame('kms', $member->sport_category->value);
        // Автоматически сгенерированные email и телефон
        $this->assertStringEndsWith('@noemail.local', $member->email);
        $this->assertNotNull($member->phone);
        // Пароль по умолчанию
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Carter30pro', $member->password));

        $team = Team::where('name', 'Арго')->first();
        $this->assertTrue(
            TeamMember::where('team_id', $team->id)->where('user_id', $member->id)->exists()
        );

        $entry = RegattaEntry::where('team_id', $team->id)->first();
        // Капитан (организатор) + 1 незарегистрированный участник
        $this->assertSame(2, $entry->crew()->count());
    }

    public function test_unregistered_member_requires_name_and_birth_date(): void
    {
        $regatta = Regatta::factory()->create();

        Livewire::test(JoinRegattaModal::class)
            ->call('openModal', $regatta->id)
            ->call('addUnregisteredGuestMember')
            ->assertHasErrors(['newMemberName', 'newMemberBirthDate'])
            ->assertCount('guestMembers', 0);
    }
}
