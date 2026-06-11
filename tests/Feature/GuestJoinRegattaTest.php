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

    public function test_guest_cannot_add_more_than_max_members(): void
    {
        $regatta = Regatta::factory()->create();
        $users = User::factory()->count(JoinRegattaModal::MAX_ADDED_MEMBERS + 1)->create();

        $component = Livewire::test(JoinRegattaModal::class)
            ->call('openModal', $regatta->id);

        // Добавляем ровно лимит
        foreach ($users->take(JoinRegattaModal::MAX_ADDED_MEMBERS) as $u) {
            $component->call('addGuestMember', $u->id);
        }
        $component->assertCount('guestMembers', JoinRegattaModal::MAX_ADDED_MEMBERS);

        // Следующий зарегистрированный участник — отклоняется
        $component->call('addGuestMember', $users->last()->id)
            ->assertCount('guestMembers', JoinRegattaModal::MAX_ADDED_MEMBERS)
            ->assertHasErrors('guestMembers');

        // Незарегистрированный участник сверх лимита — тоже отклоняется
        $component->set('newMemberName', 'Лишний Участник')
            ->set('newMemberBirthDate', '1990-01-01')
            ->call('addUnregisteredGuestMember')
            ->assertCount('guestMembers', JoinRegattaModal::MAX_ADDED_MEMBERS)
            ->assertHasErrors('guestMembers');
    }

    public function test_authenticated_user_submits_entry_via_same_form(): void
    {
        Mail::fake();

        $regatta = Regatta::factory()->create();
        $yacht = Yacht::factory()->create(['user_id' => User::factory()]);
        $user = User::factory()->create();

        $usersBefore = User::count();

        Livewire::actingAs($user)
            ->test(JoinRegattaModal::class)
            ->call('openModal', $regatta->id)
            ->assertSet('isOpen', true)
            ->set('teamName', 'Команда ветерана')
            ->set('yachtMode', 'select')
            ->set('yachtId', $yacht->id)
            ->call('submitGuest')
            ->assertHasNoErrors()
            ->assertSet('submitted', true)
            // Авторизованный не проходит регистрацию
            ->assertSet('guestRegistered', false);

        // Новый пользователь не создан
        $this->assertSame($usersBefore, User::count());

        $team = Team::where('name', 'Команда ветерана')->first();
        $this->assertNotNull($team);
        $this->assertSame($user->id, $team->organizer_id);

        $entry = RegattaEntry::where('regatta_id', $regatta->id)
            ->where('team_id', $team->id)
            ->first();
        $this->assertNotNull($entry);

        // Подающий — капитан экипажа
        $captainCrew = $entry->crew()->where('role', 'captain')->first();
        $this->assertNotNull($captainCrew);
        $this->assertSame($user->id, $captainCrew->teamMember->user_id);

        // Письмо с учётными данными не отправляется
        Mail::assertNothingSent();
    }

    public function test_authenticated_user_does_not_need_personal_fields(): void
    {
        $regatta = Regatta::factory()->create();
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(JoinRegattaModal::class)
            ->call('openModal', $regatta->id)
            ->call('submitGuest')
            ->assertHasErrors(['teamName', 'yachtId'])
            ->assertHasNoErrors(['guestName', 'guestEmail', 'guestPhone']);
    }

    public function test_authenticated_captain_can_select_existing_team(): void
    {
        Mail::fake();

        $regatta = Regatta::factory()->create();
        $yacht = Yacht::factory()->create(['user_id' => User::factory()]);
        $captain = User::factory()->create();
        $mate = User::factory()->create();

        // Существующая команда, где пользователь — капитан (организатор), с одним участником
        $team = Team::create([
            'name'            => 'Старая гвардия',
            'organizer_id'    => $captain->id,
            'approval_status' => 'approved',
        ]);
        TeamMember::create([
            'team_id'   => $team->id,
            'user_id'   => $captain->id,
            'role'      => 'organizer',
            'status'    => 'active',
            'joined_at' => now(),
        ]);
        TeamMember::create([
            'team_id'   => $team->id,
            'user_id'   => $mate->id,
            'role'      => 'member',
            'status'    => 'active',
            'joined_at' => now(),
        ]);

        $teamsBefore = Team::count();

        $component = Livewire::actingAs($captain)
            ->test(JoinRegattaModal::class)
            ->call('openModal', $regatta->id)
            // Есть команда-капитанство — режим выбора по умолчанию
            ->assertSet('teamMode', 'select')
            ->set('teamId', $team->id)
            // Экипаж предзаполнен участниками команды (без самого капитана)
            ->assertCount('guestMembers', 1);

        $component->set('yachtMode', 'select')
            ->set('yachtId', $yacht->id)
            ->call('submitGuest')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        // Новая команда не создана
        $this->assertSame($teamsBefore, Team::count());

        $entry = RegattaEntry::where('regatta_id', $regatta->id)
            ->where('team_id', $team->id)
            ->first();
        $this->assertNotNull($entry);

        // Капитан + участник команды, дубликат TeamMember не создан
        $this->assertSame(2, $entry->crew()->count());
        $this->assertSame(2, TeamMember::where('team_id', $team->id)->count());

        Mail::assertNothingSent();
    }

    public function test_user_cannot_submit_for_team_where_not_captain(): void
    {
        $regatta = Regatta::factory()->create();
        $yacht = Yacht::factory()->create(['user_id' => User::factory()]);
        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $team = Team::create([
            'name'            => 'Чужая команда',
            'organizer_id'    => $owner->id,
            'approval_status' => 'approved',
        ]);
        TeamMember::create([
            'team_id'   => $team->id,
            'user_id'   => $owner->id,
            'role'      => 'organizer',
            'status'    => 'active',
            'joined_at' => now(),
        ]);

        Livewire::actingAs($stranger)
            ->test(JoinRegattaModal::class)
            ->call('openModal', $regatta->id)
            ->set('teamMode', 'select')
            ->set('teamId', $team->id)
            ->set('yachtId', $yacht->id)
            ->call('submitGuest')
            ->assertHasErrors('teamId')
            ->assertSet('submitted', false);

        $this->assertSame(0, RegattaEntry::count());
    }

    public function test_authenticated_user_already_in_crew_sees_in_crew_state(): void
    {
        $regatta = Regatta::factory()->create();
        $yacht = Yacht::factory()->create(['user_id' => User::factory()]);
        $user = User::factory()->create();

        // Подаём заявку от имени пользователя
        Livewire::actingAs($user)
            ->test(JoinRegattaModal::class)
            ->call('openModal', $regatta->id)
            ->set('teamName', 'Экипаж раз')
            ->set('yachtId', $yacht->id)
            ->call('submitGuest')
            ->assertHasNoErrors();

        // Повторное открытие модалки — состояние «в экипаже, капитан»
        $component = Livewire::actingAs($user)
            ->test(JoinRegattaModal::class)
            ->call('openModal', $regatta->id);

        $this->assertSame('in-crew-captain', $component->instance()->state);
    }
}
