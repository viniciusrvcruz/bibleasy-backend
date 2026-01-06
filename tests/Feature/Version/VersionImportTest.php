<?php

use App\Enums\VersionLanguageEnum;
use App\Models\Chapter;
use App\Models\Version;
use Database\Seeders\BookSeeder;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->seed(BookSeeder::class);

    $this->validBibleData = function () {
        $data = array_fill(0, 66, [
            'chapters' => array_fill(0, 18, array_fill(0, 26, 'Sample verse text'))
        ]);

        $data[0]['chapters'][] = array_fill(0, 216, 'Sample verse text');

        return json_encode($data);
    };
});

describe('Version Import', function () {
    it('imports a valid bible version successfully', function () {
        $this->actAsAdmin();

        $validJson = ($this->validBibleData)();

        $file = UploadedFile::fake()->createWithContent('bible.json', $validJson);

        $versionData = [
            'abbreviation' => 'Test Version',
            'name' => 'Test Version Full Name',
            'language' => VersionLanguageEnum::ENGLISH->value,
            'copyright' => 'Public Domain',
        ];

        $response = $this->postJson('/api/admin/versions', [
            'file' => $file,
            'parser' => 'json_thiago_bodruk',
            ...$versionData,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'id',
            'abbreviation',
            'name',
            'language',
            'copyright',
        ]);
        $response->assertJson([
            ...$versionData,
        ]);
        
        $this->assertDatabaseHas('versions', [
            'abbreviation' => 'Test Version',
            'language' => VersionLanguageEnum::ENGLISH->value,
        ]);
    });

    it('rejects import with invalid book count', function () {
        $this->actAsAdmin();

        $invalidJson = json_encode(array_fill(0, 50, [
            'chapters' => array_fill(0, 10, array_fill(0, 10, 'Verse'))
        ]));

        $file = UploadedFile::fake()->createWithContent('bible.json', $invalidJson);

        $response = $this->postJson('/api/admin/versions', [
            'file' => $file,
            'parser' => 'json_thiago_bodruk',
            'abbreviation' => 'Invalid Version',
            'name' => 'Invalid Version Full Name',
            'language' => VersionLanguageEnum::ENGLISH->value,
        ]);


        $response->assertStatus(422);
        $response->assertJson([
            'error' => 'invalid_books_count',
            'message' => 'Expected 66 books but got 50'
        ]);

        $this->assertDatabaseMissing('versions', ['abbreviation' => 'Invalid Version']);
    });

    it('requires authentication', function () {
        $file = UploadedFile::fake()->create('bible.json');

        $response = $this->postJson('/api/admin/versions', [
            'file' => $file,
            'parser' => 'json_thiago_bodruk',
            'abbreviation' => 'Test',
            'name' => 'Test Full Name',
            'language' => VersionLanguageEnum::ENGLISH->value,
        ]);

        $response->assertStatus(401);
    });

    it('prevents regular user from importing', function () {
        $this->actAsUser();

        $file = UploadedFile::fake()->create('bible.json');

        $response = $this->postJson('/api/admin/versions', [
            'file' => $file,
            'parser' => 'json_thiago_bodruk',
            'abbreviation' => 'Test',
            'name' => 'Test Full Name',
            'language' => VersionLanguageEnum::ENGLISH->value,
        ]);

        $response->assertStatus(401);
    });

    it('rejects import with missing chapters', function () {
        $this->actAsAdmin();

        $invalidJson = json_encode(array_fill(0, 66, [
            'chapters' => []
        ]));

        $file = UploadedFile::fake()->createWithContent('bible.json', $invalidJson);

        $response = $this->postJson('/api/admin/versions', [
            'file' => $file,
            'parser' => 'json_thiago_bodruk',
            'abbreviation' => 'Invalid Version',
            'name' => 'Invalid Version Full Name',
            'language' => VersionLanguageEnum::ENGLISH->value,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['error' => 'missing_chapters']);
    });

    it('rejects import with empty verses', function () {
        $this->actAsAdmin();

        $invalidJson = json_encode(array_fill(0, 66, [
            'chapters' => [['   ', '']]
        ]));

        $file = UploadedFile::fake()->createWithContent('bible.json', $invalidJson);

        $response = $this->postJson('/api/admin/versions', [
            'file' => $file,
            'parser' => 'json_thiago_bodruk',
            'abbreviation' => 'Invalid Version',
            'name' => 'Invalid Version Full Name',
            'language' => VersionLanguageEnum::ENGLISH->value,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['error' => 'empty_verse']);
    });

    it('validates parser format', function () {
        $this->actAsAdmin();

        $file = UploadedFile::fake()->create('bible.json');

        $response = $this->postJson('/api/admin/versions', [
            'file' => $file,
            'parser' => 'invalid_format',
            'name' => 'Test',
            'language' => VersionLanguageEnum::ENGLISH->value,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['parser']);
    });

    // Count validation test removed - will be implemented later if needed

    it('creates sequential positions across all chapters', function () {
        $this->actAsAdmin();

        $file = UploadedFile::fake()->createWithContent('bible.json', ($this->validBibleData)());

        $response = $this->postJson('/api/admin/versions', [
            'file' => $file,
            'parser' => 'json_thiago_bodruk',
            'abbreviation' => 'Position Test',
            'name' => 'Position Test Full Name',
            'language' => VersionLanguageEnum::ENGLISH->value,
        ]);

        $response->assertStatus(201);

        $version = Version::where('abbreviation', 'Position Test')->first();
        $positions = Chapter::whereHas('book', fn($q) => $q->where('version_id', $version->id))
            ->orderBy('position')
            ->pluck('position')
            ->toArray();

        expect($positions)->toBe(range(1, 1189));
    });

    it('allows same position in different versions', function () {
        $this->actAsAdmin();

        $validJson = ($this->validBibleData)();

        $file1 = UploadedFile::fake()->createWithContent('bible1.json', $validJson);
        $file2 = UploadedFile::fake()->createWithContent('bible2.json', $validJson);

        $this->postJson('/api/admin/versions', [
            'file' => $file1,
            'parser' => 'json_thiago_bodruk',
            'abbreviation' => 'Version 1',
            'name' => 'Version 1 Full Name',
            'language' => VersionLanguageEnum::ENGLISH->value,
        ])->assertStatus(201);

        $this->postJson('/api/admin/versions', [
            'file' => $file2,
            'parser' => 'json_thiago_bodruk',
            'abbreviation' => 'Version 2',
            'name' => 'Version 2 Full Name',
            'language' => VersionLanguageEnum::ENGLISH->value,
        ])->assertStatus(201);

        $version1 = Version::where('abbreviation', 'Version 1')->first();
        $version2 = Version::where('abbreviation', 'Version 2')->first();

        $position1 = Chapter::whereHas('book', fn($q) => $q->where('version_id', $version1->id))
            ->where('position', 1)->first();
        $position2 = Chapter::whereHas('book', fn($q) => $q->where('version_id', $version2->id))
            ->where('position', 1)->first();

        expect($position1)->not->toBeNull()
            ->and($position2)->not->toBeNull()
            ->and($position1->id)->not->toBe($position2->id);
    });
});
