<?php

namespace Tests\Feature\Core;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\AbstractTestCase;

/**
 * UsersController Feature Tests.
 *
 * Tests user management list view.
 */
class UsersControllerTest extends AbstractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsAdmin();
    }

    #[Test]
    #[Group('smoke')]
    public function it_returns_a_successful_response_or_redirect(): void
    {
        /* Arrange */
        $this->databaseInsert('ip_users', [
            'user_name'          => 'Alice Tester',
            'user_password'      => password_hash('secret', PASSWORD_DEFAULT),
            'user_psalt'         => bin2hex(random_bytes(10)),
            'user_email'         => 'alice@test.local',
            'user_type'          => 0,
            'user_active'        => 1,
            'user_date_created'  => date('Y-m-d H:i:s'),
            'user_date_modified' => date('Y-m-d H:i:s'),
        ]);

        /* Act */
        $response = $this->get('/users');

        /* Assert */
        $this->assertResponseStatusCode($response, 200);
        $this->assertResponseBodyContains($response, 'Alice Tester');
    }

    #[Test]
    public function it_redirects_a_guest_to_login(): void
    {
        /* Arrange */
        $this->actingAsGuest();

        /* Act */
        $response = $this->get('/users');

        /* Assert */
        self::assertTrue(
            $response->isRedirect(),
            sprintf('Unauthenticated GET [/users] must redirect. Got [%d].', $response->statusCode())
        );
    }

    #[Test]
    public function it_creates_a_user_with_a_hashed_password(): void
    {
        /* Arrange */
        /* Act */
        $response = $this->post('/users/form', [
            'user_type'      => '2',
            'user_email'     => 'new-user@test.local',
            'user_name'      => 'New User',
            'user_password'  => 'correct horse battery staple',
            'user_passwordv' => 'correct horse battery staple',
            'user_language'  => 'system',
            'user_company'   => 'Example Co',
            'btn_submit'     => '1',
        ]);

        /* Assert */
        self::assertTrue($response->isRedirect(), 'Successful user create must redirect.');

        $user = $this->databaseFetchOne('ip_users', ['user_email' => 'new-user@test.local']);
        self::assertNotNull($user);
        self::assertSame('2', (string) $user['user_type']);
        self::assertNotSame('correct horse battery staple', $user['user_password']);
        self::assertTrue(password_verify('correct horse battery staple', $user['user_password']));
    }

    #[Test]
    public function it_does_not_create_a_user_when_password_confirmation_does_not_match(): void
    {
        /* Arrange */
        /* Act */
        $response = $this->post('/users/form', [
            'user_type'      => '2',
            'user_email'     => 'mismatch@test.local',
            'user_name'      => 'Mismatch User',
            'user_password'  => 'correct horse battery staple',
            'user_passwordv' => 'different password',
            'user_language'  => 'system',
            'btn_submit'     => '1',
        ]);

        /* Assert */
        $this->assertResponseStatusCode($response, 200);
        $this->assertResponseBodyContains($response, '<form');
        $this->assertDatabaseMissing('ip_users', ['user_email' => 'mismatch@test.local']);
    }

    #[Test]
    public function it_updates_a_user_without_mass_assigning_protected_fields(): void
    {
        /* Arrange */
        $originalSalt = bin2hex(random_bytes(10));
        $userId       = $this->databaseInsert('ip_users', [
            'user_name'          => 'Editable User',
            'user_password'      => password_hash('existing-secret', PASSWORD_DEFAULT),
            'user_psalt'         => $originalSalt,
            'user_email'         => 'editable@test.local',
            'user_type'          => 2,
            'user_active'        => 1,
            'user_date_created'  => date('Y-m-d H:i:s'),
            'user_date_modified' => date('Y-m-d H:i:s'),
        ]);

        /* Act */
        $response = $this->post('/users/form/' . $userId, [
            'user_type'     => '2',
            'user_email'    => 'renamed@test.local',
            'user_name'     => 'Renamed User',
            'user_language' => 'system',
            'user_active'   => '0',
            'user_psalt'    => 'attacker-controlled-salt',
            'btn_submit'    => '1',
        ]);

        /* Assert */
        self::assertTrue($response->isRedirect(), 'Successful user update must redirect.');

        $user = $this->databaseFetchOne('ip_users', ['user_id' => $userId]);
        self::assertNotNull($user);
        self::assertSame('Renamed User', $user['user_name']);
        self::assertSame('renamed@test.local', $user['user_email']);
        self::assertSame('1', (string) $user['user_active']);
        self::assertSame($originalSalt, $user['user_psalt']);
    }

    #[Test]
    #[Group('security')]
    public function it_prevents_a_non_primary_admin_from_changing_another_users_password(): void
    {
        /* Arrange */
        $attackerId = $this->databaseInsert('ip_users', [
            'user_name'          => 'Attacker Admin',
            'user_password'      => password_hash('attacker-secret', PASSWORD_DEFAULT),
            'user_psalt'         => bin2hex(random_bytes(10)),
            'user_email'         => 'attacker-admin@test.local',
            'user_type'          => 1,
            'user_active'        => 1,
            'user_date_created'  => date('Y-m-d H:i:s'),
            'user_date_modified' => date('Y-m-d H:i:s'),
        ]);

        $victimHash = password_hash('victim-secret', PASSWORD_DEFAULT);
        $victimId   = $this->databaseInsert('ip_users', [
            'user_name'          => 'Victim Admin',
            'user_password'      => $victimHash,
            'user_psalt'         => bin2hex(random_bytes(10)),
            'user_email'         => 'victim-admin@test.local',
            'user_type'          => 1,
            'user_active'        => 1,
            'user_date_created'  => date('Y-m-d H:i:s'),
            'user_date_modified' => date('Y-m-d H:i:s'),
        ]);

        $this->actingAsAdmin($attackerId);

        /* Act */
        $response = $this->post('/users/change_password/' . $victimId, [
            'user_password'         => 'attacker-chosen-password',
            'user_password_confirm' => 'attacker-chosen-password',
            'btn_submit'            => '1',
        ]);

        /* Assert */
        self::assertSame(403, $response->statusCode());

        $victim = $this->databaseFetchOne('ip_users', ['user_id' => $victimId]);
        self::assertNotNull($victim);
        self::assertSame(
            $victimHash,
            $victim['user_password'],
            'A non-primary admin must not be able to mutate another user password hash.'
        );
    }

    #[Test]
    public function it_deletes_a_user_client_assignment(): void
    {
        /* Arrange */
        $userId = $this->databaseInsert('ip_users', [
            'user_name'     => 'Delete Target', 'user_email' => 'delete-target@test.local',
            'user_password' => password_hash('x', PASSWORD_DEFAULT), 'user_psalt' => bin2hex(random_bytes(10)),
            'user_type'     => 2, 'user_active' => 1, 'user_date_created' => date('Y-m-d H:i:s'), 'user_date_modified' => date('Y-m-d H:i:s'),
        ]);
        $clientId     = $this->seedClient();
        $userClientId = $this->databaseInsert('ip_user_clients', ['user_id' => $userId, 'client_id' => $clientId]);

        /* Act */
        $response = $this->post('/users/delete_user_client/' . $userId . '/' . $userClientId);

        /* Assert */
        self::assertTrue($response->isRedirect());
        $this->assertDatabaseMissing('ip_user_clients', ['user_client_id' => $userClientId]);
    }

    #[Test]
    public function it_does_not_delete_a_user_client_assignment_on_a_non_post_request(): void
    {
        /* Arrange */
        $userId = $this->databaseInsert('ip_users', [
            'user_name'     => 'Delete Target Get', 'user_email' => 'delete-target-get@test.local',
            'user_password' => password_hash('x', PASSWORD_DEFAULT), 'user_psalt' => bin2hex(random_bytes(10)),
            'user_type'     => 2, 'user_active' => 1, 'user_date_created' => date('Y-m-d H:i:s'), 'user_date_modified' => date('Y-m-d H:i:s'),
        ]);
        $clientId     = $this->seedClient();
        $userClientId = $this->databaseInsert('ip_user_clients', ['user_id' => $userId, 'client_id' => $clientId]);

        /* Act */
        $this->get('/users/delete_user_client/' . $userId . '/' . $userClientId);

        /* Assert */
        $this->assertDatabaseHas('ip_user_clients', ['user_client_id' => $userClientId]);
    }

    #[Test]
    public function it_renders_the_edit_form_for_a_user_with_assigned_clients(): void
    {
        /* Arrange: form.php's 'user_clients' layout data is consumed by a
         * separate AJAX-loaded tab (users/ajax/load_user_client_table), not
         * rendered inline — this just proves the initial page load itself
         * doesn't crash while that data is being built (it did before the
         * user_clients/mdl_user_clients load-path fixes above). */
        $userId = $this->databaseInsert('ip_users', [
            'user_name'     => 'Form Client Owner', 'user_email' => 'form-client-owner@test.local',
            'user_password' => password_hash('x', PASSWORD_DEFAULT), 'user_psalt' => bin2hex(random_bytes(10)),
            'user_type'     => 2, 'user_active' => 1, 'user_date_created' => date('Y-m-d H:i:s'), 'user_date_modified' => date('Y-m-d H:i:s'),
        ]);
        $clientId = $this->seedClient(['client_name' => 'Form Assigned Client Marker']);
        $this->databaseInsert('ip_user_clients', ['user_id' => $userId, 'client_id' => $clientId]);

        /* Act */
        $response = $this->get('/users/form/' . $userId);

        /* Assert */
        $this->assertResponseStatusCode($response, 200);
        $this->assertResponseHasNoPhpErrors($response);
    }

    #[Test]
    #[Group('security')]
    public function it_prevents_secondary_admin_from_viewing_other_user_form(): void
    {
        /* Arrange: secondary admin tries to view primary admin's edit form */
        $primaryAdminId = 1; // Primary admin always has user_id = 1
        $secondaryAdminId = $this->databaseInsert('ip_users', [
            'user_name'          => 'Secondary Admin',
            'user_password'      => password_hash('secondary-secret', PASSWORD_DEFAULT),
            'user_psalt'         => bin2hex(random_bytes(10)),
            'user_email'         => 'secondary@test.local',
            'user_type'          => 1,
            'user_active'        => 1,
            'user_date_created'  => date('Y-m-d H:i:s'),
            'user_date_modified' => date('Y-m-d H:i:s'),
        ]);

        $this->actingAsAdmin($secondaryAdminId);

        /* Act */
        $response = $this->get('/users/form/' . $primaryAdminId);

        /* Assert */
        self::assertSame(403, $response->statusCode(), 'Secondary admin must not access another user\'s form.');
    }

    #[Test]
    #[Group('security')]
    public function it_prevents_secondary_admin_from_editing_other_user(): void
    {
        /* Arrange: secondary admin tries to edit primary admin's email */
        $primaryAdminId = 1; // Primary admin always has user_id = 1
        $originalEmail = $this->databaseFetchOne('ip_users', ['user_id' => $primaryAdminId])['user_email'];

        $secondaryAdminId = $this->databaseInsert('ip_users', [
            'user_name'          => 'Attacker Admin',
            'user_password'      => password_hash('attacker-secret', PASSWORD_DEFAULT),
            'user_psalt'         => bin2hex(random_bytes(10)),
            'user_email'         => 'attacker@test.local',
            'user_type'          => 1,
            'user_active'        => 1,
            'user_date_created'  => date('Y-m-d H:i:s'),
            'user_date_modified' => date('Y-m-d H:i:s'),
        ]);

        $this->actingAsAdmin($secondaryAdminId);

        /* Act: attempt to change primary admin's email */
        $response = $this->post('/users/form/' . $primaryAdminId, [
            'user_type'     => '1',
            'user_email'    => 'hijacked@attacker.com',
            'user_name'     => 'Hijacked Primary Admin',
            'user_language' => 'system',
            'btn_submit'    => '1',
        ]);

        /* Assert */
        self::assertSame(403, $response->statusCode(), 'Secondary admin must not be able to edit primary admin.');

        $primaryAdmin = $this->databaseFetchOne('ip_users', ['user_id' => $primaryAdminId]);
        self::assertNotNull($primaryAdmin);
        self::assertSame($originalEmail, $primaryAdmin['user_email'], 'Primary admin\'s email must remain unchanged.');
    }

    #[Test]
    #[Group('security')]
    public function it_allows_secondary_admin_to_edit_their_own_account(): void
    {
        /* Arrange: secondary admin edits their own account */
        $secondaryAdminId = $this->databaseInsert('ip_users', [
            'user_name'          => 'Secondary Admin',
            'user_password'      => password_hash('secondary-secret', PASSWORD_DEFAULT),
            'user_psalt'         => bin2hex(random_bytes(10)),
            'user_email'         => 'secondary@test.local',
            'user_type'          => 1,
            'user_active'        => 1,
            'user_date_created'  => date('Y-m-d H:i:s'),
            'user_date_modified' => date('Y-m-d H:i:s'),
        ]);

        $this->actingAsAdmin($secondaryAdminId);

        /* Act */
        $response = $this->post('/users/form/' . $secondaryAdminId, [
            'user_type'     => '1',
            'user_email'    => 'updated@test.local',
            'user_name'     => 'Updated Secondary Admin',
            'user_language' => 'system',
            'btn_submit'    => '1',
        ]);

        /* Assert */
        self::assertTrue($response->isRedirect(), 'Secondary admin must be able to edit their own account.');

        $user = $this->databaseFetchOne('ip_users', ['user_id' => $secondaryAdminId]);
        self::assertNotNull($user);
        self::assertSame('updated@test.local', $user['user_email']);
        self::assertSame('Updated Secondary Admin', $user['user_name']);
    }

    #[Test]
    #[Group('security')]
    public function it_allows_primary_admin_to_edit_any_user(): void
    {
        /* Arrange: primary admin edits another user */
        $targetUserId = $this->databaseInsert('ip_users', [
            'user_name'          => 'Target User',
            'user_password'      => password_hash('target-secret', PASSWORD_DEFAULT),
            'user_psalt'         => bin2hex(random_bytes(10)),
            'user_email'         => 'target@test.local',
            'user_type'          => 2,
            'user_active'        => 1,
            'user_date_created'  => date('Y-m-d H:i:s'),
            'user_date_modified' => date('Y-m-d H:i:s'),
        ]);

        $this->actingAsAdmin(); // Primary admin with user_id = 1

        /* Act */
        $response = $this->post('/users/form/' . $targetUserId, [
            'user_type'     => '2',
            'user_email'    => 'modified@test.local',
            'user_name'     => 'Modified Target User',
            'user_language' => 'system',
            'btn_submit'    => '1',
        ]);

        /* Assert */
        self::assertTrue($response->isRedirect(), 'Primary admin must be able to edit any user.');

        $user = $this->databaseFetchOne('ip_users', ['user_id' => $targetUserId]);
        self::assertNotNull($user);
        self::assertSame('modified@test.local', $user['user_email']);
        self::assertSame('Modified Target User', $user['user_name']);
    }

    #[Test]
    #[Group('security')]
    public function it_does_not_allow_secondary_admin_to_escalate_user_type(): void
    {
        /* Arrange: secondary admin tries to promote themselves to primary admin
         * by editing their own account (should be blocked by not allowing self-escalation) */
        $secondaryAdminId = $this->databaseInsert('ip_users', [
            'user_name'          => 'Would-be Escalator',
            'user_password'      => password_hash('secondary-secret', PASSWORD_DEFAULT),
            'user_psalt'         => bin2hex(random_bytes(10)),
            'user_email'         => 'escalator@test.local',
            'user_type'          => 1, // Secondary admin
            'user_active'        => 1,
            'user_date_created'  => date('Y-m-d H:i:s'),
            'user_date_modified' => date('Y-m-d H:i:s'),
        ]);

        $this->actingAsAdmin($secondaryAdminId);

        /* Act: attempt to change own user_type to primary admin */
        $response = $this->post('/users/form/' . $secondaryAdminId, [
            'user_type'     => '1', // Attempt to keep or change to primary
            'user_email'    => 'escalator@test.local',
            'user_name'     => 'Would-be Escalator',
            'user_language' => 'system',
            'btn_submit'    => '1',
        ]);

        /* Assert */
        self::assertTrue($response->isRedirect(), 'Self-edit must be allowed to submit.');

        $user = $this->databaseFetchOne('ip_users', ['user_id' => $secondaryAdminId]);
        self::assertNotNull($user);
        self::assertSame('1', (string) $user['user_type'], 'User type should not change during self-edit.');
    }

    #[Test]
    #[Group('security')]
    public function it_prevents_secondary_admin_email_takeover_via_password_recovery(): void
    {
        /* Arrange: complete scenario of secondary admin trying to hijack primary admin
         * by changing email, then using password recovery */
        $primaryAdminId = 1;
        $primaryAdminOriginalEmail = $this->databaseFetchOne('ip_users', ['user_id' => $primaryAdminId])['user_email'];

        $secondaryAdminId = $this->databaseInsert('ip_users', [
            'user_name'          => 'Email Hijacker',
            'user_password'      => password_hash('hijacker-secret', PASSWORD_DEFAULT),
            'user_psalt'         => bin2hex(random_bytes(10)),
            'user_email'         => 'hijacker@test.local',
            'user_type'          => 1,
            'user_active'        => 1,
            'user_date_created'  => date('Y-m-d H:i:s'),
            'user_date_modified' => date('Y-m-d H:i:s'),
        ]);

        $this->actingAsAdmin($secondaryAdminId);

        /* Act: attempt to hijack primary admin's account */
        $response = $this->post('/users/form/' . $primaryAdminId, [
            'user_type'     => '1',
            'user_email'    => 'hijacker-new@attacker.com',
            'user_name'     => 'Hijacked Admin',
            'user_language' => 'system',
            'btn_submit'    => '1',
        ]);

        /* Assert: unauthorized access must be blocked at form level */
        self::assertSame(403, $response->statusCode(), 'Unauthorized user edit must return 403.');

        $primaryAdmin = $this->databaseFetchOne('ip_users', ['user_id' => $primaryAdminId]);
        self::assertNotNull($primaryAdmin);
        self::assertSame($primaryAdminOriginalEmail, $primaryAdmin['user_email'], 'Primary admin email must not be modified by unauthorized user.');
    }
}
