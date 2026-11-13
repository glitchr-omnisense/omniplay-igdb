# omniplay/igdb

[omnisense/omniplay](https://github.com/glitchr-omnisense/omniplay)'s IGDB, Twitch's games database,
under any PHP host: an application token got from Twitch with the client credentials and renewed a
minute before it expires; search and a game's record through Apicalypse queries (`POST /v4/games`);
a game found by its IGDB number or by its Steam or GOG number (`/v4/external_games`); its studios,
platforms, genres, themes, cover and screenshots (`images.igdb.com`), its score, and its
identifiers on Steam, GOG, Epic and itch.io (external game sources 1, 5, 26 and 30).

```php
$igdb = (new IgdbFactory($httpClient))->create(['client_id' => getenv('TWITCH_CLIENT_ID'), 'client_secret' => getenv('TWITCH_CLIENT_SECRET')]);
$portal = $igdb->game(GameRef::steam(620));
```

Options: `client_id` and `client_secret` (required: a Twitch application's), `api`,
`token_url`, `images`.

## Install

```sh
composer require omniplay/igdb
```

It requires `omnisense/omniplay` `^1.0`, installed with it. A real call needs: **Twitch client
credentials** (IGDB is Twitch's: a Twitch application's client id and secret). With no account, its
tests run on recorded answers (a `MockHttpClient`), and
[omnisense/omniplay](https://github.com/glitchr-omnisense/omniplay#install-and-a-first-call)'s
README has a first call that runs without one.

## IGDB's terms

IGDB is used under [Twitch's developer agreement](https://www.twitch.tv/p/legal/developer-agreement/)
and IGDB's own terms ([api-docs.igdb.com](https://api-docs.igdb.com/#about)): 4 requests a second,
credit to IGDB.com. The documentation refused a re-reading on 2026-10-24 (403): read it before going
live.

## Verified, and not

- Verified (the core's harness, PHP 8.4): the token got once, a search by Apicalypse (quotes
  escaped), a game by its Steam number, unknown; `BareTest`; PHPStan max. The answers are **shaped
  after IGDB's documentation and written by hand**: no Twitch application was used. IGDB moved
  external games from `category` to `external_game_source`: both are read, the lookup asks the
  latter - not seen live.
- Not verified live: no credentials.

## Responsibility

An independent, unofficial client, affiliated with, endorsed by or sponsored by none of the services
it reaches. The MIT licence covers this code only and grants no right to the service or its data:
whoever runs it complies with the terms above and obtains what they require. The authors publish
it free of charge, make no commercial use of it and accept no liability for its use.

## Usage policy

The package declares its usage policy as data, in [policy.json](policy.json) (omnisense/contracts' schema). 4 requests a second, per key, as api-docs.igdb.com says (read on 2026-11-10), and at most 8 open requests at once (`concurrency`: declared and exported, not enforced by the guard, as a PHP process makes one request at a time). Commercial use is under contract: monetized products go through IGDB's partnership, and Twitch's Developer Agreement forbids monetizing its data without permission. Storing and caching are allowed (IGDB's FAQ), redistribution is forbidden, and IGDB.com is credited. Through omniplay's registry, the fifth call within a second is refused before it is made (`UsageLimitReachedException`). A 429 and its `Retry-After` hold every call until the wait is over. A site may lower the limit, never raise it.

## Licence

MIT.
