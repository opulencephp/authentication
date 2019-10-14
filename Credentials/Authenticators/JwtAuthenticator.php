<?php

/**
 * Opulence
 *
 * @link      https://www.opulencephp.com
 * @copyright Copyright (C) 2019 David Young
 * @license   https://github.com/opulencephp/Opulence/blob/master/LICENSE.md
 */

declare(strict_types=1);

namespace Opulence\Authentication\Credentials\Authenticators;

use Opulence\Authentication\Credentials\ICredential;
use Opulence\Authentication\ISubject;
use Opulence\Authentication\Principal;
use Opulence\Authentication\PrincipalTypes;
use Opulence\Authentication\Subject;
use Opulence\Authentication\Tokens\JsonWebTokens\SignedJwt;
use Opulence\Authentication\Tokens\JsonWebTokens\Verification\IContextVerifier;
use Opulence\Authentication\Tokens\JsonWebTokens\Verification\JwtErrorTypes;
use Opulence\Authentication\Tokens\JsonWebTokens\Verification\JwtVerifier;
use Opulence\Authentication\Tokens\JsonWebTokens\Verification\VerificationContext;

/**
 * Defines the JWT authenticator
 */
class JwtAuthenticator implements IAuthenticator
{
    /** @var IContextVerifier The JWT verifier */
    protected IContextVerifier $jwtVerifier;
    /** @var VerificationContext The verification context to use */
    protected VerificationContext $verificationContext;
    /** @var SignedJwt|null The signed JWT generated from authentication */
    protected ?SignedJwt $signedJwt = null;

    /**
     * @param IContextVerifier $jwtVerifier The JWT verifier
     * @param VerificationContext $verificationContext The verification context to use
     */
    public function __construct(IContextVerifier $jwtVerifier, VerificationContext $verificationContext)
    {
        $this->jwtVerifier = $jwtVerifier;
        $this->verificationContext = $verificationContext;
    }

    /**
     * @inheritdoc
     */
    public function authenticate(ICredential $credential, ISubject &$subject = null, string &$error = null): bool
    {
        // Reset the JWT
        $this->signedJwt = null;
        $tokenString = $credential->getValue('token');

        if ($tokenString === null) {
            $error = AuthenticatorErrorTypes::CREDENTIAL_MISSING;

            return false;
        }

        $this->signedJwt = SignedJwt::createFromString((string)$tokenString);
        $jwtErrors = [];

        if (!$this->jwtVerifier->verify($this->signedJwt, $this->verificationContext, $jwtErrors)) {
            if (in_array(JwtErrorTypes::EXPIRED, $jwtErrors, true)) {
                $error = AuthenticatorErrorTypes::CREDENTIAL_EXPIRED;
            } else {
                $error = AuthenticatorErrorTypes::CREDENTIAL_INCORRECT;
            }

            return false;
        }

        $subject = $this->getSubjectFromJwt($this->signedJwt, $credential);

        return true;
    }

    /**
     * Gets a subject from a JWT
     *
     * @param SignedJwt $jwt The signed JWT
     * @param ICredential $credential The credential
     * @return ISubject The subject
     */
    protected function getSubjectFromJwt(SignedJwt $jwt, ICredential $credential): ISubject
    {
        $roles = $jwt->getPayload()->get('roles') ?: [];

        return new Subject(
            [new Principal(PrincipalTypes::PRIMARY, $jwt->getPayload()->getSubject(), $roles)],
            [$credential]
        );
    }
}
