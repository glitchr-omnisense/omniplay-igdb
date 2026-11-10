<?php

namespace Omniplay\Igdb;

use Omniplay\Capabilities;
use Omniplay\CatalogueInterface;
use Omniplay\Exception\NotSupportedException;
use Omniplay\Exception\ProviderException;
use Omniplay\Http\Data;
use Omniplay\Http\JsonClient;
use Omniplay\Model\Game;
use Omniplay\Model\GameRef;
use Omniplay\Model\Identifiers;
use Omniplay\Model\Image;
use Omniplay\Model\ImageKind;
use Omniplay\Model\Platform;
use Omniplay\Model\Query;
use Omniplay\Model\Rating;
use Omniplay\Model\Scheme;
use Omniplay\ProviderInterface;
use Omniplay\SearchInterface;
use Omnisense\Contracts\Policy\DeclaresUsagePolicyInterface;
use Omnisense\Contracts\Policy\UsagePolicy;

/**
 * IGDB, Twitch's games database: an application token got from Twitch with
 * the client credentials and kept until it expires; search and a game's
 * record by Apicalypse queries; a game found by its IGDB number or by its
 * Steam or GOG number (external_games); its identifiers on the stores.
 */
final class IgdbProvider implements ProviderInterface, SearchInterface, CatalogueInterface, DeclaresUsagePolicyInterface
{
    /** IGDB's usage policy, as its terms read on the day it says (provenance.verifiedAt). */
    public const POLICY = __DIR__.'/policy.json';

    private static ?UsagePolicy $policy = null;

    /** IGDB's external game sources: the store each number belongs to. */
    public const SOURCES = [1 => 'steam', 5 => 'gog', 26 => 'epic', 30 => 'itch'];

    private const FIELDS = 'name,slug,summary,first_release_date,url,status,cover.image_id,screenshots.image_id,genres.name,themes.name,platforms.name,involved_companies.developer,involved_companies.publisher,involved_companies.company.name,external_games.category,external_games.external_game_source,external_games.uid,total_rating,total_rating_count';

    private ?string $token = null;
    private int $expires = 0;

    public function __construct(
        private readonly JsonClient $json,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $api = 'https://api.igdb.com/v4',
        private readonly string $tokenUrl = 'https://id.twitch.tv/oauth2/token',
        private readonly string $images = 'https://images.igdb.com/igdb/image/upload',
    ) {
    }

    public function getName(): string
    {
        return 'igdb';
    }

    public function getTitle(): string
    {
        return 'IGDB';
    }

    public function usagePolicy(): UsagePolicy
    {
        return self::$policy ??= UsagePolicy::fromFile(self::POLICY);
    }

    public function capabilities(): Capabilities
    {
        return new Capabilities(
            search: true,
            catalogue: true,
            lookupBy: [Scheme::Igdb, Scheme::Steam, Scheme::Gog],
            attribution: 'Data from IGDB.com',
            terms: ['https://api-docs.igdb.com/#about', 'https://www.twitch.tv/p/legal/developer-agreement/'],
            policy: $this->usagePolicy(),
        );
    }

    public function search(Query $query): array
    {
        $answer = $this->query('games', 'search "'.addcslashes($query->text, '"\\').'"; fields '.self::FIELDS.'; limit '.$query->limit.';');

        return array_map($this->game0(...), $answer?->items() ?? []);
    }

    public function game(GameRef $ref, ?string $language = null, ?string $country = null): ?Game
    {
        if (!$this->capabilities()->resolves($ref)) {
            throw NotSupportedException::ref($this->getTitle(), $ref);
        }
        $id = Scheme::Igdb === $ref->scheme ? $ref->id : $this->external($ref);
        if (null === $id || !ctype_digit($id)) {
            return null;
        }
        $found = $this->query('games', 'fields '.self::FIELDS.'; where id = '.$id.';')?->items()[0] ?? null;

        return null === $found ? null : $this->game0($found);
    }

    /** IGDB's number of a game known by a store's number. */
    private function external(GameRef $ref): ?string
    {
        $source = array_search($ref->scheme->value, self::SOURCES, true);
        $answer = $this->query('external_games', 'fields game; where uid = "'.addcslashes($ref->id, '"\\').'" & external_game_source = '.(int) $source.'; limit 1;');

        return ($answer?->items()[0] ?? null)?->string('game');
    }

    private function game0(Data $data): Game
    {
        $ids = ['igdb' => $data->string('id')];
        foreach ($data->list('external_games') as $external) {
            $store = self::SOURCES[$external->int('external_game_source') ?? $external->int('category') ?? 0] ?? null;
            if (null !== $store) {
                $ids[$store] ??= $external->string('uid');
            }
        }
        $developers = $publishers = [];
        foreach ($data->list('involved_companies') as $company) {
            $name = $company->string('company.name');
            if (null !== $name && true === $company->bool('developer')) {
                $developers[] = $name;
            }
            if (null !== $name && true === $company->bool('publisher')) {
                $publishers[] = $name;
            }
        }
        $images = [];
        if (null !== $cover = $data->string('cover.image_id')) {
            $images[] = new Image(ImageKind::Cover, $this->images.'/t_cover_big/'.$cover.'.jpg', 264, 374);
        }
        foreach ($data->list('screenshots') as $screenshot) {
            if (null !== $image = $screenshot->string('image_id')) {
                $images[] = new Image(ImageKind::Screenshot, $this->images.'/t_screenshot_big/'.$image.'.jpg', 889, 500);
            }
        }
        $released = $data->int('first_release_date');
        $votes = $data->int('total_rating_count');

        return new Game(
            title: (string) $data->string('name'),
            identifiers: Identifiers::of($ids),
            source: 'igdb',
            url: $data->string('url'),
            summary: $data->string('summary'),
            released: null === $released ? null : (new \DateTimeImmutable('@'.$released))->setTime(0, 0),
            comingSoon: null !== $released && $released > time(),
            developers: array_values(array_unique($developers)),
            publishers: array_values(array_unique($publishers)),
            genres: $data->strings('genres', 'name'),
            tags: $data->strings('themes', 'name'),
            platforms: Platform::fromLabels($data->strings('platforms', 'name')),
            images: $images,
            ratings: null !== $votes && $votes > 0 ? [new Rating('igdb', round((float) $data->float('total_rating'), 1), 100.0, $votes)] : [],
        );
    }

    private function query(string $endpoint, string $body): ?Data
    {
        return $this->json->request('POST', $this->api.'/'.$endpoint, ['body' => $body], ['Client-ID' => $this->clientId, 'Authorization' => 'Bearer '.$this->token(), 'Content-Type' => 'text/plain']);
    }

    /**
     * Twitch's application token, got once and again a minute before it
     * expires: the client credentials grant, form-encoded in the body as
     * Twitch's documentation sends it - the secret never in the URL, where
     * logs and messages keep it.
     */
    private function token(): string
    {
        if (null === $this->token || time() >= $this->expires) {
            $answer = $this->json->request('POST', $this->tokenUrl, ['body' => ['client_id' => $this->clientId, 'client_secret' => $this->clientSecret, 'grant_type' => 'client_credentials']]);
            $this->token = $answer?->string('access_token') ?? throw ProviderException::answered('Twitch', 'POST /oauth2/token', 'no token', (string) $answer?->string('message'));
            $this->expires = time() + max(60, ($answer->int('expires_in') ?? 3600) - 60);
        }

        return $this->token;
    }
}
