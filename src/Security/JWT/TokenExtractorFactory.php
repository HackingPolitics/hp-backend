<?php

declare(strict_types=1);

namespace App\Security\JWT;

use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\AuthorizationHeaderTokenExtractor;
use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\ChainTokenExtractor;

/**
 * @todo this prevents enabling other extractors via config. How can we simply
 *       add another extractor to the configured chain?
 */
class TokenExtractorFactory
{
    public function __invoke(
        AuthorizationHeaderTokenExtractor $authorizationHeaderTokenExtractor,
        PostParameterTokenExtractor $postParameterTokenExtractor
    ): ChainTokenExtractor {
        $chainTokenExtractor = new ChainTokenExtractor([
            $authorizationHeaderTokenExtractor,
            $postParameterTokenExtractor,
        ]);

        return $chainTokenExtractor;
    }
}
