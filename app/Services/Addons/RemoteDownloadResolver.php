<?php

namespace Pterodactyl\Services\Addons;

use Illuminate\Support\Facades\Http;
use Pterodactyl\Exceptions\DisplayException;

/**
 * Turns a third party download link into something the daemon can actually
 * fetch, and refuses to point the daemon at anything private.
 *
 * Two separate problems are solved here:
 *
 *  1. Wings' remote downloader does not follow HTTP redirects. Any 301/302/307
 *     makes it fail with "got bad response status from endpoint", so the final
 *     URL has to be resolved on the panel side first.
 *  2. Without a host check the panel is a confused deputy: it would happily
 *     fetch http://127.0.0.1/, a LAN address, or a cloud metadata endpoint such
 *     as http://169.254.169.254/ on behalf of whoever supplied the link.
 */
class RemoteDownloadResolver
{
    private const USER_AGENT = 'Mozilla/5.0 (compatible; Veltox-Panel/1.0)';

    /**
     * Validates the URL, walks the redirect chain, validates every hop, and
     * returns the final direct URL.
     */
    public function resolve(string $url): string
    {
        $this->assertPublic($url);

        $finalUrl = $url;

        try {
            Http::withOptions([
                // Never buffer the whole jar into the panel's memory.
                'stream' => true,
                'allow_redirects' => [
                    'max' => 10,
                    'strict' => true,
                    'referer' => false,
                    'protocols' => ['http', 'https'],
                    'track_redirects' => true,
                    'on_redirect' => function ($request, $response, $uri) {
                        $this->assertPublic((string) $uri);
                    },
                ],
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) use (&$finalUrl) {
                    $finalUrl = (string) $stats->getEffectiveUri();
                },
            ])
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->timeout(25)
                ->get($url);
        } catch (DisplayException $exception) {
            // A blocked redirect target must never be swallowed.
            throw $exception;
        } catch (\Throwable $exception) {
            // Network hiccup while resolving: fall back to the original URL,
            // which was already validated, so behaviour never gets worse.
            return $url;
        }

        $this->assertPublic($finalUrl);

        return $finalUrl ?: $url;
    }

    /**
     * Rejects anything that is not plain http(s) pointing at a public address.
     */
    public function assertPublic(string $url): void
    {
        $parts = parse_url($url);

        if ($parts === false || empty($parts['host'])) {
            throw new DisplayException('The download URL is not a valid URL.');
        }

        if (!in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            throw new DisplayException('Downloads must use http:// or https://.');
        }

        $host = $parts['host'];
        $addresses = [];

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $addresses[] = $host;
        } else {
            foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
                if (!empty($record['ip'])) {
                    $addresses[] = $record['ip'];
                }
                if (!empty($record['ipv6'])) {
                    $addresses[] = $record['ipv6'];
                }
            }
        }

        if (empty($addresses)) {
            throw new DisplayException(sprintf('The download host "%s" could not be resolved.', $host));
        }

        foreach ($addresses as $address) {
            // NO_PRIV_RANGE covers RFC1918 and fc00::/7, NO_RES_RANGE covers
            // loopback, link-local (including 169.254.169.254) and 0.0.0.0/8.
            if (!filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new DisplayException(sprintf(
                    'The download URL resolves to the non-public address %s and was blocked.',
                    $address
                ));
            }
        }
    }
}
