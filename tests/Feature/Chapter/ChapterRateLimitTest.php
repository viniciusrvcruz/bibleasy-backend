<?php

use App\Enums\BookAbbreviationEnum;
use App\Models\Book;
use App\Models\Chapter;
use App\Models\Verse;
use App\Models\Version;
use App\Support\ChapterRateLimit;
use Illuminate\Support\Facades\Config;

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

    it('blocks all chapters for same version after rate limit exceeded on one chapter', function () {
        // Create chapter 2
        $chapter2 = Chapter::factory()->create([
            'number' => 2,
            'book_id' => $this->book->id,
        ]);
        foreach ([1, 2, 3] as $number) {
            Verse::factory()->create([
                'chapter_id' => $chapter2->id,
                'number' => $number,
            ]);
        }

        $url1 = "/api/versions/{$this->version->id}/books/gen/chapters/1";
        $url2 = "/api/versions/{$this->version->id}/books/gen/chapters/2";

        // Exceed limit on chapter 1
        for ($i = 0; $i < 61; $i++) {
            $this->getJson($url1);
        }

        // Chapter 1 should be blocked
        $response = $this->getJson($url1);
        $response->assertStatus(429);

        // Chapter 2 should also be blocked (same IP + version)
        $response = $this->getJson($url2);
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

describe('Chapter Rate Limit with X-App-Key bypass', function () {
    beforeEach(function () {
        Config::set('app.api_key', 'test-bypass-key');
    });

    it('bypasses throttle when X-App-Key header matches config key', function () {
        $url = "/api/versions/{$this->version->id}/books/gen/chapters/1";
        $headers = [ChapterRateLimit::API_KEY_HEADER => 'test-bypass-key'];

        for ($i = 0; $i < 65; $i++) {
            $response = $this->withHeaders($headers)->getJson($url);
            $response->assertStatus(200);
        }
    });

    it('bypasses block when X-App-Key header matches config key even after exceeding limit', function () {
        $url = "/api/versions/{$this->version->id}/books/gen/chapters/1";

        // Exceed limit without X-App-Key to trigger block
        for ($i = 0; $i < 61; $i++) {
            $this->getJson($url);
        }

        $response = $this->getJson($url);
        $response->assertStatus(429);

        // Same IP with valid X-App-Key should bypass block and get 200
        $response = $this->withHeaders([ChapterRateLimit::API_KEY_HEADER => 'test-bypass-key'])->getJson($url);
        $response->assertStatus(200);
    });

    it('applies rate limit when X-App-Key header does not match config key', function () {
        $url = "/api/versions/{$this->version->id}/books/gen/chapters/1";

        for ($i = 0; $i < 60; $i++) {
            $this->withHeaders([ChapterRateLimit::API_KEY_HEADER => 'wrong-key'])->getJson($url);
        }

        $response = $this->withHeaders([ChapterRateLimit::API_KEY_HEADER => 'wrong-key'])->getJson($url);
        $response->assertStatus(429);
    });

    it('applies rate limit when X-App-Key header is empty', function () {
        $url = "/api/versions/{$this->version->id}/books/gen/chapters/1";

        for ($i = 0; $i < 60; $i++) {
            $this->withHeaders([ChapterRateLimit::API_KEY_HEADER => ''])->getJson($url);
        }

        $response = $this->withHeaders([ChapterRateLimit::API_KEY_HEADER => ''])->getJson($url);
        $response->assertStatus(429);
    });
});
