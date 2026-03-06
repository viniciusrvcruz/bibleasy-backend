<?php

use App\Enums\BookAbbreviationEnum;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Verse;
use App\Models\Version;
use App\Support\ChapterRateLimit;

beforeEach(function () {
    $this->version = Version::factory()->create();
    $this->book = Book::factory()->create([
        'version_id' => $this->version->id,
        'abbreviation' => BookAbbreviationEnum::GEN,
        'name' => BookAbbreviationEnum::GEN->value,
    ]);
    $chapter = Chapter::factory()->create([
        'number' => 1,
        'book_id' => $this->book->id,
    ]);
    foreach ([1, 2, 3] as $number) {
        Verse::factory()->create([
            'chapter_id' => $chapter->id,
            'number' => $number,
        ]);
    }
});

describe('Chapter Rate Limit', function () {
    it('allows up to 60 requests per minute for same IP and version', function () {
        $url = "/api/versions/{$this->version->id}/books/gen/chapters/1";

        for ($i = 0; $i < 60; $i++) {
            $response = $this->getJson($url);
            $response->assertStatus(200);
        }
    });

    it('returns 429 on the 61st request', function () {
        $url = "/api/versions/{$this->version->id}/books/gen/chapters/1";

        for ($i = 0; $i < 60; $i++) {
            $this->getJson($url);
        }

        $response = $this->getJson($url);

        $response->assertStatus(429);
        $response->assertJson(['message' => 'Too many requests. Please try again later.']);
    });

    it('blocks further requests for 1 hour after limit exceeded', function () {
        $url = "/api/versions/{$this->version->id}/books/gen/chapters/1";

        for ($i = 0; $i < 61; $i++) {
            $this->getJson($url);
        }

        $response = $this->getJson($url);
        $response->assertStatus(429);

        $response = $this->getJson($url);
        $response->assertStatus(429);
    });

    it('does not share limit between different IPs', function () {
        $url = "/api/versions/{$this->version->id}/books/gen/chapters/1";

        for ($i = 0; $i < 60; $i++) {
            $this->withHeaders(['X-Forwarded-For' => '192.168.1.1'])->getJson($url);
        }

        $response = $this->withHeaders(['X-Forwarded-For' => '192.168.1.2'])->getJson($url);
        $response->assertStatus(200);
    });

    it('does not share limit between different versions', function () {
        $version2 = Version::factory()->create();
        $book2 = Book::factory()->create([
            'version_id' => $version2->id,
            'abbreviation' => BookAbbreviationEnum::GEN,
            'name' => BookAbbreviationEnum::GEN->value,
        ]);
        $chapter2 = Chapter::factory()->create([
            'number' => 1,
            'book_id' => $book2->id,
        ]);
        foreach ([1, 2, 3] as $number) {
            Verse::factory()->create([
                'chapter_id' => $chapter2->id,
                'number' => $number,
            ]);
        }

        $url1 = "/api/versions/{$this->version->id}/books/gen/chapters/1";
        $url2 = "/api/versions/{$version2->id}/books/gen/chapters/1";

        for ($i = 0; $i < 60; $i++) {
            $this->getJson($url1);
        }

        $response = $this->getJson($url2);
        $response->assertStatus(200);
    });

    it('returns 429 with correct message', function () {
        $url = "/api/versions/{$this->version->id}/books/gen/chapters/1";

        for ($i = 0; $i < 60; $i++) {
            $this->getJson($url);
        }

        $response = $this->getJson($url);

        $response->assertStatus(429);
        $response->assertJson(['message' => 'Too many requests. Please try again later.']);
    });

    it('uses X-Forwarded-For for IP when provided', function () {
        $url = "/api/versions/{$this->version->id}/books/gen/chapters/1";

        for ($i = 0; $i < 61; $i++) {
            $this->withHeaders(['X-Forwarded-For' => '10.0.0.100'])->getJson($url);
        }

        $response = $this->withHeaders(['X-Forwarded-For' => '10.0.0.100'])->getJson($url);
        $response->assertStatus(429);

        $response = $this->withHeaders(['X-Forwarded-For' => '10.0.0.200'])->getJson($url);
        $response->assertStatus(200);
    });

    it('releases route after 1 hour block expires', function () {
        $ip = '192.168.50.100';
        $url = "/api/versions/{$this->version->id}/books/gen/chapters/1";

        for ($i = 0; $i < 61; $i++) {
            $this->withHeaders(['X-Forwarded-For' => $ip])->getJson($url);
        }

        $response = $this->withHeaders(['X-Forwarded-For' => $ip])->getJson($url);
        $response->assertStatus(429);

        // Simulate 1 hour + 1 second: block and rate limit expire (cache uses Carbon for TTL)
        $this->travel(ChapterRateLimit::BLOCK_DURATION_SECONDS + 1)->seconds();

        $response = $this->withHeaders(['X-Forwarded-For' => $ip])->getJson($url);
        $response->assertStatus(200);
    });
});
