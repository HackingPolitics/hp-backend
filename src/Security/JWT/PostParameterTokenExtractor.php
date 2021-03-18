<?php

namespace App\Security\JWT;

use Lexik\Bundle\JWTAuthenticationBundle\TokenExtractor\TokenExtractorInterface;
use Symfony\Component\HttpFoundation\Request;

class PostParameterTokenExtractor implements TokenExtractorInterface
{
    protected string $parameterName;

    public function __construct(string $parameterName)
    {
        $this->parameterName = $parameterName;
    }

    /**
     * {@inheritdoc}
     */
    public function extract(Request $request)
    {
        return $request->request->get($this->parameterName, false);
    }
}
