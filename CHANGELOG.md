# Changelog

## Unreleased (1.x)

### Changed
- `omnigame/igdb` is now `omniplay/igdb` (2026-11-09: the omnigame family merged into omniplay, this
  repository's history kept, from omnigame-igdb at b1ebd3e): the namespace `Omniplay\Igdb`,
  requiring `omnisense/omniplay`; the default User-Agent `omniplay/1.0
  (+https://github.com/glitchr-omnisense/omniplay)`.

### Fixed
- Twitch's token is asked with the client id and secret in a form-encoded body, as Twitch's
  documentation sends them (client credentials grant), no longer in the query string, where
  the HTTP client's messages and logs kept the secret; an unreachable Twitch's
  `UnreachableException` names `POST /oauth2/token` only.
