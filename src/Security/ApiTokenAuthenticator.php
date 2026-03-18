<?php declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Enum\UserStatus;
use App\Repository\UserRepository;
use Override;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class ApiTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private UserRepository $userRepository,
    ) {}

    #[Override]
    public function supports(Request $request): ?bool
    {
        return (
            $request->headers->has('Authorization')
            && str_starts_with((string) $request->headers->get('Authorization'), 'Bearer ')
        );
    }

    #[Override]
    public function authenticate(Request $request): Passport
    {
        $authHeader = (string) $request->headers->get('Authorization');
        $plainToken = substr($authHeader, 7);
        $tokenHash = hash('sha256', $plainToken);

        return new SelfValidatingPassport(new UserBadge($tokenHash, function (string $hash): User {
            $user = $this->userRepository->findByApiTokenHash($hash);

            if ($user === null) {
                throw new AuthenticationException('Invalid API token.');
            }

            if ($user->getStatus() !== UserStatus::Active) {
                throw new AuthenticationException('User account is not active.');
            }

            return $user;
        }));
    }

    #[Override]
    public function onAuthenticationSuccess(
        Request $request,
        #[\SensitiveParameter] TokenInterface $token,
        string $firewallName,
    ): ?Response {
        return null;
    }

    #[Override]
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
    }
}
