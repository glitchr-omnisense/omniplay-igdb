<?php

namespace Omniplay\Igdb;

use Omniplay\Config;
use Omniplay\Http\JsonClient;
use Omniplay\ProviderFactory;
use Omniplay\ProviderInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** "igdb": options client_id and client_secret (required: a Twitch application's), api, token_url, images. */
final class IgdbFactory extends ProviderFactory
{
    public function __construct(private readonly ?HttpClientInterface $http = null)
    {
    }

    protected function populate(Config $c): void
    {
        $c->defaults([
            'omniplay.factory_name' => 'igdb',
            'omniplay.factory_title' => 'IGDB',
            'omniplay.required_options' => ['client_id', 'client_secret'],
            'api' => 'https://api.igdb.com/v4',
            'token_url' => 'https://id.twitch.tv/oauth2/token',
            'images' => 'https://images.igdb.com/igdb/image/upload',
        ]);
    }

    protected function build(Config $c): ProviderInterface
    {
        return new IgdbProvider(
            new JsonClient($this->http ?? HttpClient::create(['timeout' => 20]), 'IGDB', ['Accept' => 'application/json']),
            (string) $c->string('client_id'),
            (string) $c->string('client_secret'),
            rtrim((string) $c->string('api'), '/'),
            (string) $c->string('token_url'),
            rtrim((string) $c->string('images'), '/'),
        );
    }
}
