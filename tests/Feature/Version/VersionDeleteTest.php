<?php

use App\Models\Version;

describe('Version Delete', function () {
    it('deletes version successfully', function () {
        $this->actAsAdmin();

        $version = Version::factory()->create();

        $response = $this->deleteJson("/api/admin/versions/{$version->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('versions', ['id' => $version->id]);
    });

    it('requires authentication', function () {
        $version = Version::factory()->create();

        $response = $this->deleteJson("/api/admin/versions/{$version->id}");

        $response->assertStatus(401);
    });

    it('prevents regular user from deleting', function () {
        $this->actAsUser();

        $version = Version::factory()->create();

        $response = $this->deleteJson("/api/admin/versions/{$version->id}");

        $response->assertStatus(401);
    });
});
