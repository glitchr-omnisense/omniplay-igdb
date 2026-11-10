<?php

namespace Omniplay\Igdb\Tests;

use Omniplay\Exception\OmniplayException;
use Omniplay\Exception\UsageLimitReachedException;
use Omniplay\Igdb\IgdbFactory;
use Omniplay\Igdb\IgdbProvider;
use Omniplay\Model\GameRef;
use Omniplay\Registry;
use Omniplay\Testing\RecordedHttpClient;
use Omnisense\Contracts\Policy\InvalidPolicyException;
use Omnisense\Contracts\Policy\Permission;
use Omnisense\Contracts\Policy\RateLimit;
use Omnisense\Contracts\Policy\UsagePolicy;
use PHPUnit\Framework\TestCase;

/** IGDB's usage policy, as policy.json declares it, held through omniplay's registry. */
final class PolicyTest extends TestCase
{
    public function testThePolicyIsTheOneDeclared(): void
    {
        $policy = self::registry(self::answering())->policy('igdb');

        self::assertSame('igdb', $policy->provider);
        self::assertSame(['4/second/key/*'], null === $policy->rateLimits ? null : array_map(static fn (RateLimit $limit) => $limit->id(), $policy->rateLimits));
        self::assertSame(8, $policy->concurrency, 'at most 8 open requests');
        self::assertSame(Permission::Contract, $policy->rights->commercial);
        self::assertSame(true, $policy->rights->attribution->required);
        self::assertSame(true, $policy->requirements->key);
        self::assertSame('2026-11-10', $policy->provenance->verifiedAt);
        self::assertSame(UsagePolicy::fromFile(IgdbProvider::POLICY)->toArray(), $policy->toArray(), 'the registry runs the policy the package declares');
        self::assertSame($policy->toArray(), self::registry(self::answering())->get('igdb')->capabilities()->policy()->toArray(), 'its Capabilities carry it too');
    }

    public function testASitesLimitIsCountedAndTheCallOverItIsNeverMade(): void
    {
        $http = self::answering();
        $registry = self::registry($http, ['rateLimits' => [['max' => 1, 'window' => 'minute', 'operations' => ['game']]]]);

        $registry->catalogue('igdb')->game(GameRef::igdb(1));
        $made = \count($http->requests);
        try {
            $registry->catalogue('igdb')->game(GameRef::igdb(1));
            self::fail('The call over the site\'s limit was made.');
        } catch (UsageLimitReachedException $e) {
            self::assertInstanceOf(OmniplayException::class, $e, 'the family\'s catch holds it');
            self::assertSame('1/minute/application/game', $e->limit?->id());
        }
        self::assertCount($made, $http->requests, 'the refused call reached nothing');
    }

    public function testA429AndItsRetryAfterHoldEveryCallUntilTheWaitIsOver(): void
    {
        $http = RecordedHttpClient::routes(['~oauth2/token~' => [200, '{"access_token": "not-a-real-app-token", "expires_in": 5000000, "token_type": "bearer"}'], '~~' => [429, '', ['retry-after' => ['30']]]]);
        $registry = self::registry($http);

        try {
            $registry->catalogue('igdb')->game(GameRef::igdb(1));
            self::fail('A 429 was read as an answer.');
        } catch (UsageLimitReachedException $e) {
            self::assertSame(30, $e->retryAfter);
        }
        $made = \count($http->requests);
        try {
            $registry->catalogue('igdb')->game(GameRef::igdb(1));
            self::fail('A call went out while IGDB asked to wait.');
        } catch (UsageLimitReachedException $e) {
            self::assertGreaterThanOrEqual(29, $e->retryAfter);
        }
        self::assertCount($made, $http->requests, 'the wait held the next request back');
    }

    public function testASiteMayLowerTheConcurrencyNeverRaiseIt(): void
    {
        self::assertSame(2, self::registry(self::answering(), ['concurrency' => 2])->policy('igdb')->concurrency);

        $this->expectException(InvalidPolicyException::class);
        self::registry(self::answering(), ['concurrency' => 16])->catalogue('igdb');
    }

    public function testASiteCannotRaiseTheLimit(): void
    {
        $this->expectException(InvalidPolicyException::class);
        self::registry(self::answering(), ['rateLimits' => [['max' => 10, 'window' => 'second', 'scope' => 'key']]])->catalogue('igdb');
    }

    /** @param array<string, mixed> $policy */
    private static function registry(RecordedHttpClient $http, array $policy = []): Registry
    {
        return new Registry([new IgdbFactory($http)], ['igdb' => ['factory' => 'igdb', 'options' => ['client_id' => 'not-a-real-client-id', 'client_secret' => 'not-a-real-client-secret']] + ([] === $policy ? [] : ['policy' => $policy])]);
    }

    private static function answering(): RecordedHttpClient
    {
        return RecordedHttpClient::routes(['~oauth2/token~' => [200, '{"access_token": "not-a-real-app-token", "expires_in": 5000000, "token_type": "bearer"}'], '~~' => [200, '[]']]);
    }
}
