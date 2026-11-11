<?php

namespace App\Tests\Support;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * Mock de l'API Have I Been Pwned (utilisé uniquement en environnement de test).
 * Les tests ne dépendent ainsi ni d'Internet ni du pare-feu de l'établissement.
 */
class FakePwnedPasswordClient implements HttpClientInterface
{
    /** Mots de passe que l'API simulée déclare « compromis ». */
    public const COMPROMISED = ['Password123!Password'];

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $prefix = substr($url, -5);
        $body = str_repeat('0', 35) . ":0\r\n";

        foreach (self::COMPROMISED as $password) {
            $hash = strtoupper(sha1($password));
            if (str_starts_with($hash, $prefix)) {
                $body .= substr($hash, 5) . ":42\r\n";
            }
        }

        return (new MockHttpClient(new MockResponse($body)))->request($method, $url, $options);
    }

    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        throw new \LogicException('Non utilisé dans les tests.');
    }

    public function withOptions(array $options): static
    {
        return clone $this;
    }
}