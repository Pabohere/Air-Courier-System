<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_manifest_is_public_and_has_installable_icons_with_real_dimensions(): void
    {
        $this->get(route('pwa.manifest'))->assertOk()->assertHeader('Content-Type', 'application/manifest+json');
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('Standard Courier', $manifest['name']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('./', $manifest['start_url']);
        $this->assertSame('./', $manifest['scope']);
        foreach ($manifest['icons'] as $icon) {
            $size = getimagesize(public_path(ltrim($icon['src'], './')));
            $this->assertSame($icon['sizes'], $size[0].'x'.$size[1]);
            $this->assertSame('image/png', $size['mime']);
        }
        $this->assertSame([180, 180], array_slice(getimagesize(public_path('images/apple-touch-icon.png')), 0, 2));
    }

    public function test_login_and_workspace_offer_installation_without_exposing_private_pages(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->get(route('login'))->assertOk()->assertSee('rel="manifest"', false)
            ->assertSee('apple-touch-icon')->assertSee('data-pwa-install', false)->assertSee('data-pwa-help', false);
        $this->get(route('dashboard'))->assertRedirectToRoute('login');
        $this->actingAs(User::factory()->admin()->create())->get(route('dashboard'))->assertOk()
            ->assertSee('data-worker-url=', false)->assertSee('data-pwa-install', false)->assertSee('data-pwa-offline', false);
    }

    public function test_offline_document_is_self_contained_and_can_return_to_home(): void
    {
        $html = file_get_contents(public_path('offline.html'));
        $this->assertStringContainsString('You’re offline', $html);
        $this->assertStringContainsString('href="/"', $html);
        $this->assertStringContainsString('require a live connection', $html);
        $this->assertStringNotContainsString('https://', $html);
        $this->assertStringNotContainsString('http://', $html);
    }
}
