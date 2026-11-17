<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Doctor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_redirect_returns_redirect_response(): void
    {
        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('redirect')->once()->andReturn(redirect('https://accounts.google.com/o/oauth2/v2/auth'));

        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

        $response = $this->get('/auth/google/redirect');

        $response->assertRedirect('https://accounts.google.com/o/oauth2/v2/auth');
    }

    public function test_google_login_resolves_existing_doctor_by_google_id(): void
    {
        $doctor = Doctor::factory()->create([
            'google_id' => 'google-user-id-999',
            'email' => 'google.doctor@hospital.local',
            'last_login_at' => null,
        ]);

        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('google-user-id-999');
        $googleUser->shouldReceive('getEmail')->andReturn('google.doctor@hospital.local');
        $googleUser->user = ['email_verified' => true];

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($doctor, 'doctor');

        $doctor->refresh();
        $this->assertNotNull($doctor->last_login_at);
    }

    public function test_google_login_links_to_existing_doctor_by_verified_email(): void
    {
        $doctor = Doctor::factory()->create([
            'google_id' => null,
            'email' => 'hospital.staff@hospital.local',
        ]);

        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('new-google-id-555');
        $googleUser->shouldReceive('getEmail')->andReturn('hospital.staff@hospital.local');
        $googleUser->user = ['email_verified' => true];

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($doctor, 'doctor');

        $doctor->refresh();
        $this->assertSame('new-google-id-555', $doctor->google_id);
    }

    public function test_google_login_refuses_unverified_email_and_does_not_link_doctor(): void
    {
        $doctor = Doctor::factory()->create([
            'google_id' => null,
            'email' => 'unverified.doc@hospital.local',
        ]);

        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('unverified-google-id-777');
        $googleUser->shouldReceive('getEmail')->andReturn('unverified.doc@hospital.local');
        $googleUser->user = ['email_verified' => false];

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/login');
        $response->assertSessionHas('error');
        $this->assertGuest('doctor');

        $doctor->refresh();
        $this->assertNull($doctor->google_id);
    }

    public function test_doctor_with_different_google_id_already_linked_refuses_overwrite(): void
    {
        $doctor = Doctor::factory()->create([
            'google_id' => 'original-google-id-111',
            'email' => 'linked.doctor@hospital.local',
        ]);

        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('intruder-google-id-222');
        $googleUser->shouldReceive('getEmail')->andReturn('linked.doctor@hospital.local');
        $googleUser->user = ['email_verified' => true];

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/login');
        $response->assertSessionHas('error');
        $this->assertGuest('doctor');

        $doctor->refresh();
        $this->assertSame('original-google-id-111', $doctor->google_id);
    }

    public function test_soft_deleted_doctor_matched_by_google_id_is_refused(): void
    {
        $doctor = Doctor::factory()->create([
            'google_id' => 'deleted-google-id',
            'email' => 'deleted.google@hospital.local',
        ]);
        $doctor->delete();
        $this->assertSoftDeleted('doctors', ['doctor_id' => $doctor->doctor_id]);

        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('deleted-google-id');
        $googleUser->shouldReceive('getEmail')->andReturn('deleted.google@hospital.local');
        $googleUser->user = ['email_verified' => true];

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/login');
        $response->assertSessionHas('error');
        $this->assertGuest('doctor');
    }

    public function test_soft_deleted_doctor_matched_by_email_is_refused_and_google_id_stays_null(): void
    {
        $doctor = Doctor::factory()->create([
            'google_id' => null,
            'email' => 'softdeleted.email@hospital.local',
        ]);
        $doctor->delete();
        $this->assertSoftDeleted('doctors', ['doctor_id' => $doctor->doctor_id]);

        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('incoming-google-id-888');
        $googleUser->shouldReceive('getEmail')->andReturn('softdeleted.email@hospital.local');
        $googleUser->user = ['email_verified' => true];

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/login');
        $response->assertSessionHas('error');
        $this->assertGuest('doctor');

        $doctor->refresh();
        $this->assertNull($doctor->google_id);
    }

    public function test_google_user_with_no_email_is_refused(): void
    {
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('valid-google-id-no-email');
        $googleUser->shouldReceive('getEmail')->andReturn(null);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/login');
        $response->assertSessionHas('error');
        $this->assertGuest('doctor');
    }

    public function test_google_login_redirects_to_register_if_doctor_does_not_exist(): void
    {
        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('unknown-google-id');
        $googleUser->shouldReceive('getEmail')->andReturn('unknown@hospital.local');
        $googleUser->user = ['email_verified' => true];

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->once()->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect('/register');
        $response->assertSessionHas('error');
        $this->assertGuest('doctor');
        $this->assertDatabaseMissing('doctors', ['email' => 'unknown@hospital.local']);
    }
}
