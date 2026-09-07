<?php

use App\Enums\PortfolioShareStatus;
use App\Enums\PortfolioShareType;
use App\Models\Portfolio;
use App\Models\PortfolioMovement;
use App\Models\PortfolioShare;
use App\Models\User;
use App\Services\Keycloak\JwtValidator;
use App\Services\Keycloak\UserProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function mockUserForRequest(User $user): void
{
    $claims = [
        'sub' => $user->keycloak_id,
        'email' => $user->email,
        'name' => $user->name,
        'iss' => config('keycloak.issuer'),
        'aud' => 'account',
        'azp' => config('keycloak.client_id'),
    ];

    test()->mock(JwtValidator::class, function ($mock) use ($claims) {
        $mock->shouldReceive('validate')
            ->andReturn($claims);
    });

    test()->mock(UserProvisioner::class, function ($mock) use ($claims, $user) {
        $mock->shouldReceive('provision')
            ->with($claims)
            ->andReturn($user);
    });
}

it('allows owner to create viewer invitation', function () {
    $owner = User::factory()->create(['keycloak_id' => 'owner-keycloak-id']);
    mockUserForRequest($owner);

    $portfolio = Portfolio::factory()->for($owner)->create();

    $this->withToken('valid-token')
        ->postJson("/api/portfolios/{$portfolio->id}/shares", [
            'email' => 'invitee@example.com',
            'share_type' => 'viewer',
        ])
        ->assertCreated()
        ->assertJsonPath('share_type', 'viewer')
        ->assertJsonPath('status', 'pending')
        ->assertJsonPath('invited_email', 'invitee@example.com');

    $this->assertDatabaseHas('portfolio_shares', [
        'portfolio_id' => $portfolio->id,
        'invited_email' => 'invitee@example.com',
        'share_type' => PortfolioShareType::Viewer->value,
        'status' => PortfolioShareStatus::Pending->value,
    ]);
});

it('allows owner to create amount and percentage invitations', function () {
    $owner = User::factory()->create(['keycloak_id' => 'owner-keycloak-id']);
    mockUserForRequest($owner);

    $portfolio = Portfolio::factory()->for($owner)->create();

    $this->withToken('valid-token')
        ->postJson("/api/portfolios/{$portfolio->id}/shares", [
            'email' => 'amount@example.com',
            'share_type' => 'amount',
            'share_amount' => '0.5',
            'show_total_balance' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('share_type', 'amount')
        ->assertJsonPath('share_amount', '0.50000000')
        ->assertJsonPath('show_total_balance', true);

    $this->withToken('valid-token')
        ->postJson("/api/portfolios/{$portfolio->id}/shares", [
            'email' => 'percent@example.com',
            'share_type' => 'percentage',
            'share_percentage' => '25',
            'show_total_balance' => false,
        ])
        ->assertCreated()
        ->assertJsonPath('share_type', 'percentage')
        ->assertJsonPath('share_percentage', '25.00');
});

it('rejects self invitation and duplicate active invitations', function () {
    $owner = User::factory()->create([
        'keycloak_id' => 'owner-keycloak-id',
        'email' => 'owner@example.com',
    ]);
    mockUserForRequest($owner);

    $portfolio = Portfolio::factory()->for($owner)->create();

    $this->withToken('valid-token')
        ->postJson("/api/portfolios/{$portfolio->id}/shares", [
            'email' => 'owner@example.com',
            'share_type' => 'viewer',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    $this->withToken('valid-token')
        ->postJson("/api/portfolios/{$portfolio->id}/shares", [
            'email' => 'invitee@example.com',
            'share_type' => 'viewer',
        ])
        ->assertCreated();

    $this->withToken('valid-token')
        ->postJson("/api/portfolios/{$portfolio->id}/shares", [
            'email' => 'invitee@example.com',
            'share_type' => 'viewer',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('lists pending invitations for invitee and allows accept and decline', function () {
    $owner = User::factory()->create(['keycloak_id' => 'owner-keycloak-id', 'name' => 'Owner']);
    $invitee = User::factory()->create([
        'keycloak_id' => 'invitee-keycloak-id',
        'email' => 'invitee@example.com',
    ]);
    $portfolio = Portfolio::factory()->for($owner)->create(['name' => 'Shared BTC']);

    $share = PortfolioShare::factory()
        ->for($portfolio)
        ->create([
            'invited_by_user_id' => $owner->id,
            'invited_email' => 'invitee@example.com',
            'invited_user_id' => $invitee->id,
            'share_type' => PortfolioShareType::Viewer,
            'status' => PortfolioShareStatus::Pending,
        ]);

    mockUserForRequest($invitee);

    $this->withToken('valid-token')
        ->getJson('/api/portfolio-shares/invitations')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.portfolio.name', 'Shared BTC')
        ->assertJsonPath('0.invited_by.name', 'Owner');

    $this->withToken('valid-token')
        ->postJson("/api/portfolio-shares/{$share->id}/accept")
        ->assertOk()
        ->assertJsonPath('status', 'accepted');

    $this->assertDatabaseHas('portfolio_shares', [
        'id' => $share->id,
        'status' => PortfolioShareStatus::Accepted->value,
    ]);

    $declineShare = PortfolioShare::factory()
        ->for($portfolio)
        ->create([
            'invited_by_user_id' => $owner->id,
            'invited_email' => 'invitee@example.com',
            'invited_user_id' => $invitee->id,
            'status' => PortfolioShareStatus::Pending,
        ]);

    $this->withToken('valid-token')
        ->postJson("/api/portfolio-shares/{$declineShare->id}/decline")
        ->assertOk()
        ->assertJsonPath('status', 'declined');
});

it('links pending invitations when user provisions by email', function () {
    $owner = User::factory()->create();
    $portfolio = Portfolio::factory()->for($owner)->create();

    PortfolioShare::factory()
        ->for($portfolio)
        ->create([
            'invited_by_user_id' => $owner->id,
            'invited_email' => 'newuser@example.com',
            'invited_user_id' => null,
            'status' => PortfolioShareStatus::Pending,
        ]);

    $provisioner = app(UserProvisioner::class);
    $user = $provisioner->provision([
        'sub' => 'new-keycloak-id',
        'email' => 'newuser@example.com',
        'name' => 'New User',
    ]);

    $this->assertDatabaseHas('portfolio_shares', [
        'invited_email' => 'newuser@example.com',
        'invited_user_id' => $user->id,
        'status' => PortfolioShareStatus::Pending->value,
    ]);
});

it('allows accepted viewer to read portfolio and movements but not manage it', function () {
    $owner = User::factory()->create(['keycloak_id' => 'owner-keycloak-id']);
    $viewer = User::factory()->create(['keycloak_id' => 'viewer-keycloak-id']);
    $portfolio = Portfolio::factory()->for($owner)->create();

    PortfolioMovement::factory()->for($portfolio)->create([
        'type' => 'deposit',
        'amount' => '1.00000000',
    ]);

    PortfolioShare::factory()
        ->for($portfolio)
        ->accepted($viewer)
        ->viewer()
        ->create([
            'invited_by_user_id' => $owner->id,
            'invited_email' => $viewer->email,
        ]);

    mockUserForRequest($viewer);

    $this->withToken('valid-token')
        ->getJson('/api/portfolios')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.access_role', 'viewer');

    $this->withToken('valid-token')
        ->getJson("/api/portfolios/{$portfolio->id}")
        ->assertOk()
        ->assertJsonPath('access_role', 'viewer')
        ->assertJsonPath('balance', '1.00000000');

    $this->withToken('valid-token')
        ->getJson("/api/portfolios/{$portfolio->id}/movements")
        ->assertOk()
        ->assertJsonCount(1);

    $this->withToken('valid-token')
        ->patchJson("/api/portfolios/{$portfolio->id}", ['name' => 'Hacked'])
        ->assertNotFound();

    $this->withToken('valid-token')
        ->postJson("/api/portfolios/{$portfolio->id}/sync")
        ->assertNotFound();

    $this->withToken('valid-token')
        ->deleteJson("/api/portfolios/{$portfolio->id}")
        ->assertNotFound();
});

it('hides total balance for amount share when show_total_balance is false', function () {
    $owner = User::factory()->create(['keycloak_id' => 'owner-keycloak-id']);
    $viewer = User::factory()->create(['keycloak_id' => 'viewer-keycloak-id']);
    $portfolio = Portfolio::factory()->for($owner)->create();

    PortfolioMovement::factory()->for($portfolio)->create([
        'type' => 'deposit',
        'amount' => '2.00000000',
        'unit_price_usd' => '50000.00000000',
    ]);

    PortfolioShare::factory()
        ->for($portfolio)
        ->accepted($viewer)
        ->amount('0.50000000')
        ->create([
            'invited_by_user_id' => $owner->id,
            'invited_email' => $viewer->email,
            'show_total_balance' => false,
        ]);

    mockUserForRequest($viewer);

    $this->withToken('valid-token')
        ->getJson("/api/portfolios/{$portfolio->id}")
        ->assertOk()
        ->assertJsonPath('balance', null)
        ->assertJsonPath('cost_basis_usd', null)
        ->assertJsonPath('share.my_share_amount', '0.50000000')
        ->assertJsonPath('share.my_share_cost_basis_usd', '25000.00000000');

    $this->withToken('valid-token')
        ->getJson("/api/portfolios/{$portfolio->id}/movements")
        ->assertOk()
        ->assertJsonCount(0);
});

it('shows total balance for amount share when show_total_balance is true', function () {
    $owner = User::factory()->create(['keycloak_id' => 'owner-keycloak-id']);
    $viewer = User::factory()->create(['keycloak_id' => 'viewer-keycloak-id']);
    $portfolio = Portfolio::factory()->for($owner)->create();

    PortfolioMovement::factory()->for($portfolio)->create([
        'type' => 'deposit',
        'amount' => '2.00000000',
        'unit_price_usd' => '50000.00000000',
    ]);

    PortfolioShare::factory()
        ->for($portfolio)
        ->accepted($viewer)
        ->amount('0.50000000')
        ->create([
            'invited_by_user_id' => $owner->id,
            'invited_email' => $viewer->email,
            'show_total_balance' => true,
        ]);

    mockUserForRequest($viewer);

    $this->withToken('valid-token')
        ->getJson("/api/portfolios/{$portfolio->id}")
        ->assertOk()
        ->assertJsonPath('balance', '2.00000000')
        ->assertJsonPath('share.my_share_amount', '0.50000000');
});

it('computes percentage share amount from portfolio balance', function () {
    $owner = User::factory()->create(['keycloak_id' => 'owner-keycloak-id']);
    $viewer = User::factory()->create(['keycloak_id' => 'viewer-keycloak-id']);
    $portfolio = Portfolio::factory()->for($owner)->create();

    PortfolioMovement::factory()->for($portfolio)->create([
        'type' => 'deposit',
        'amount' => '4.00000000',
    ]);

    PortfolioShare::factory()
        ->for($portfolio)
        ->accepted($viewer)
        ->percentage('25.00')
        ->create([
            'invited_by_user_id' => $owner->id,
            'invited_email' => $viewer->email,
            'show_total_balance' => false,
        ]);

    mockUserForRequest($viewer);

    $this->withToken('valid-token')
        ->getJson("/api/portfolios/{$portfolio->id}")
        ->assertOk()
        ->assertJsonPath('share.my_share_amount', '1.00000000');
});

it('allows owner to list and revoke shares', function () {
    $owner = User::factory()->create(['keycloak_id' => 'owner-keycloak-id']);
    mockUserForRequest($owner);

    $portfolio = Portfolio::factory()->for($owner)->create();

    $share = PortfolioShare::factory()
        ->for($portfolio)
        ->create([
            'invited_by_user_id' => $owner->id,
            'status' => PortfolioShareStatus::Pending,
        ]);

    $this->withToken('valid-token')
        ->getJson("/api/portfolios/{$portfolio->id}/shares")
        ->assertOk()
        ->assertJsonCount(1);

    $this->withToken('valid-token')
        ->deleteJson("/api/portfolios/{$portfolio->id}/shares/{$share->id}")
        ->assertNoContent();

    $this->assertDatabaseHas('portfolio_shares', [
        'id' => $share->id,
        'status' => PortfolioShareStatus::Revoked->value,
    ]);
});

it('returns not found when non owner accesses share management', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create(['keycloak_id' => 'other-keycloak-id']);
    $portfolio = Portfolio::factory()->for($owner)->create();

    mockUserForRequest($other);

    $this->withToken('valid-token')
        ->getJson("/api/portfolios/{$portfolio->id}/shares")
        ->assertNotFound();

    $this->withToken('valid-token')
        ->postJson("/api/portfolios/{$portfolio->id}/shares", [
            'email' => 'someone@example.com',
            'share_type' => 'viewer',
        ])
        ->assertNotFound();
});

it('returns not found when unrelated user tries to accept invitation', function () {
    $owner = User::factory()->create();
    $invitee = User::factory()->create(['keycloak_id' => 'invitee-keycloak-id']);
    $stranger = User::factory()->create(['keycloak_id' => 'stranger-keycloak-id']);
    $portfolio = Portfolio::factory()->for($owner)->create();

    $share = PortfolioShare::factory()
        ->for($portfolio)
        ->create([
            'invited_by_user_id' => $owner->id,
            'invited_email' => $invitee->email,
            'invited_user_id' => $invitee->id,
            'status' => PortfolioShareStatus::Pending,
        ]);

    mockUserForRequest($stranger);

    $this->withToken('valid-token')
        ->postJson("/api/portfolio-shares/{$share->id}/accept")
        ->assertNotFound();
});
